<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ParserModule;
use ContentEgg\application\components\feed\FeedImportPendingException;

/**
 * SearchAllProductsAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Search several active product modules for one keyword in a single call and
 * return the results grouped by module. A resilient fan-out: each module is
 * searched in isolation, so one broken module (bad API key, quota) reports a
 * per-module error instead of failing the whole request. The caller picks the
 * modules explicitly (no implicit "search everything"), and the count is capped
 * to keep latency and affiliate-API cost predictable.
 */
final class SearchAllProductsAbility extends AbilityBase
{
    const MAX_MODULES = 6;
    const DEFAULT_PER_MODULE_LIMIT = 5;

    public function name(): string
    {
        return 'content-egg/search-all-products';
    }

    public function label(): string
    {
        return __('Search All Products', 'content-egg');
    }

    public function description(): string
    {
        return 'Searches several active product modules for one keyword in a single call and '
            . 'returns the results grouped by module (each with its own count and, on failure, '
            . 'an error) — one broken module never sinks the others. Pass the specific module_ids '
            . '(from content-egg/list-modules; up to ' . self::MAX_MODULES . ' active, searchable '
            . 'product modules) — there is no implicit search-everything, to respect affiliate API '
            . 'quotas. NOTE: each module searched consumes that network\'s API quota and counts '
            . 'against the per-user search rate limit. Results are grouped, not merged/ranked, so '
            . 'compare across modules yourself. For a single module use content-egg/search-products.'
            . ' Each module group with results returns its own search_token for content-egg/add-products-to-post.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => 'object',
            'default' => array(),
            'properties' => array(
                'module_ids' => array(
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => self::MAX_MODULES,
                    'items' => array('type' => 'string'),
                    'description' => 'Active, searchable product module ids from content-egg/list-modules '
                        . '(up to ' . self::MAX_MODULES . ').',
                ),
                'keyword' => array(
                    'type' => 'string',
                    'minLength' => 2,
                    'description' => 'Search phrase applied to every listed module.',
                ),
                'limit' => array(
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => AbstractSearchAbility::MAX_LIMIT,
                    'default' => self::DEFAULT_PER_MODULE_LIMIT,
                    'description' => 'Results per module.',
                ),
                'fields' => array(
                    'type' => 'string',
                    'enum' => array('lean', 'full'),
                    'default' => 'lean',
                ),
            ),
            'required' => array('module_ids', 'keyword'),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'keyword' => array('type' => 'string'),
                'count_total' => array('type' => 'integer'),
                'modules' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'module_id' => array('type' => 'string'),
                            'count' => array('type' => 'integer'),
                            'notice' => array('type' => 'string'),
                            'results' => array('type' => 'array', 'items' => array('type' => 'object')),
                            'search_token' => array('type' => 'string'),
                            'error' => array('type' => 'string'),
                        ),
                    ),
                ),
            ),
        );
    }

    public function checkPermission($input = null): bool
    {
        return \current_user_can('edit_posts');
    }

    public function execute(array $input): array
    {
        $keyword = trim((string) ($input['keyword'] ?? ''));
        $limit = min(AbstractSearchAbility::MAX_LIMIT, max(1, (int) ($input['limit'] ?? self::DEFAULT_PER_MODULE_LIMIT)));
        $full = (($input['fields'] ?? 'lean') === 'full');

        $module_ids = is_array($input['module_ids'] ?? null) ? array_values($input['module_ids']) : array();
        $module_ids = array_values(array_unique(array_filter(array_map(
            static function ($id)
            {
                return trim((string) $id);
            },
            $module_ids
        ))));

        if (!$module_ids)
        {
            throw new AbilityInputException(
                "'module_ids' must list at least one active product module id (see content-egg/list-modules)."
            );
        }
        if (count($module_ids) > self::MAX_MODULES)
        {
            throw new AbilityInputException(
                'search-all-products accepts up to ' . self::MAX_MODULES . ' modules per call; '
                    . 'split the rest into another call.'
            );
        }

        // One rate-limit hit per module searched, so a wide fan-out cannot dodge
        // the per-user search limit. Charged up front, before any network call.
        $rate = (int) \apply_filters('content_egg_abilities_search_rate_limit', AbstractSearchAbility::DEFAULT_RATE_PER_MINUTE);
        $wait = 0;
        $bucket = 'search:' . \get_current_user_id();
        foreach ($module_ids as $unused)
        {
            $w = RateLimiter::hit($bucket, $rate);
            if ($w > $wait)
            {
                $wait = $w;
            }
        }
        if ($wait > 0)
        {
            throw new AbilityRateLimitException($wait);
        }

        $modules = array();
        $count_total = 0;
        $searchable_count = 0; // modules that passed validation (were actually searchable)

        foreach ($module_ids as $module_id)
        {
            try
            {
                $found = AbstractSearchAbility::searchOneModule(
                    $module_id,
                    $keyword,
                    $limit,
                    array(),
                    ParserModule::PARSER_TYPE_PRODUCT,
                    'product'
                );

                $searchable_count++;
                $items = $found['items'];
                $count_total += count($items);

                $full_items = array_values(array_map(array(LeanProduct::class, 'fullItem'), $items));
                $group = array(
                    'module_id' => $module_id,
                    'count' => count($items),
                    'notice' => $found['notice'],
                    'results' => $full ? $full_items : LeanProduct::mapList($items),
                );
                // Each module group carries its own token: one add call per module/search.
                if ($full_items)
                {
                    $token = SearchResultCache::store($module_id, $keyword, $full_items);
                    if ($token !== '')
                    {
                        $group['search_token'] = $token;
                    }
                }
                $modules[] = $group;
            }
            catch (AbilityInputException $e)
            {
                // Not a searchable product module (unknown/inactive/wrong-type):
                // a per-module input problem, does not count as searched.
                $modules[] = array('module_id' => $module_id, 'error' => $e->getMessage());
            }
            catch (FeedImportPendingException $e)
            {
                $searchable_count++;
                $modules[] = array(
                    'module_id' => $module_id,
                    'error' => "Feed '{$module_id}' is still importing — poll content-egg/get-feed-status, then retry.",
                );
            }
            catch (\Throwable $e)
            {
                // Module was searchable but its own search/API failed (bad key,
                // quota, network). Isolated so the other modules still return.
                $searchable_count++;
                $reason = trim(\wp_strip_all_tags(html_entity_decode((string) $e->getMessage(), ENT_QUOTES)));
                if (mb_strlen($reason) > 300)
                {
                    $reason = mb_substr($reason, 0, 300) . "\u{2026}";
                }
                $modules[] = array('module_id' => $module_id, 'error' => $reason);
            }
        }

        // If not one module was searchable, the whole module_ids list was an
        // input problem the agent should fix — fail rather than return an
        // all-errors 200.
        if ($searchable_count === 0)
        {
            throw new AbilityInputException(
                'None of the given module_ids is an active, searchable product module. '
                    . 'Call content-egg/list-modules (type "product") for valid ids.'
            );
        }

        return array(
            'keyword' => $keyword,
            'count_total' => $count_total,
            'modules' => $modules,
        );
    }
}
