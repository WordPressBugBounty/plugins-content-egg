<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\components\ProductSearchService;
use ContentEgg\application\components\feed\FeedImportPendingException;

/**
 * AbstractSearchAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Shared base for the keyword-search abilities (products, images, videos,
 * coupons). The search plumbing (ProductSearchService::search) is generic
 * across parser types; subclasses only declare which module type they accept
 * and how to shape the results.
 */
abstract class AbstractSearchAbility extends AbilityBase
{
    const DEFAULT_LIMIT = 10;
    const MAX_LIMIT = 30;
    const DEFAULT_RATE_PER_MINUTE = 30;

    // Some module APIs reject a tiny page size (e.g. Pixabay requires
    // per_page >= 3). Fetch at least this many and slice to the caller's limit.
    const MIN_FETCH_PAGE = 10;

    /** ParserModule::PARSER_TYPE_* this ability searches. */
    abstract protected function moduleType(): string;

    /** Short kind label used in the wrong-type error, e.g. "image". */
    abstract protected function moduleKind(): string;

    /** Map raw result items to this type's lean envelope. */
    abstract protected function mapLean(array $items): array;

    public function inputSchema(): array
    {
        return array(
            'type' => array('object', 'null'),
            'properties' => array(
                'module_id' => array(
                    'type' => 'string',
                    'description' => 'Active module id from content-egg/list-modules.',
                ),
                'keyword' => array(
                    'type' => 'string',
                    'minLength' => 2,
                    'description' => 'Search phrase or product URL (for modules with URL search).',
                ),
                'limit' => array(
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => self::MAX_LIMIT,
                    'default' => self::DEFAULT_LIMIT,
                ),
                'fields' => array(
                    'type' => 'string',
                    'enum' => array('lean', 'full'),
                    'default' => 'lean',
                ),
                'filters' => array(
                    'type' => 'object',
                    'description' => 'Module-specific query parameters, passed through to the module '
                        . '(same keys the Content Egg search UI sends).',
                    'additionalProperties' => true,
                ),
            ),
            'required' => array('module_id', 'keyword'),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'module_id' => array('type' => 'string'),
                'keyword' => array('type' => 'string'),
                'count' => array('type' => 'integer'),
                'notice' => array('type' => 'string'),
                'results' => array('type' => 'array', 'items' => array('type' => 'object')),
                'search_token' => array(
                    'type' => 'string',
                    'description' => 'Short-lived token for the add-* abilities: pass it with bare unique_ids '
                        . 'to attach items from this search without echoing them back. Expires in ~30 minutes. '
                        . 'Omitted when there are no results.',
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
        $module_id = trim((string) ($input['module_id'] ?? ''));
        $keyword = trim((string) ($input['keyword'] ?? ''));
        $limit = min(self::MAX_LIMIT, max(1, (int) ($input['limit'] ?? self::DEFAULT_LIMIT)));

        $rate = (int) \apply_filters('content_egg_abilities_search_rate_limit', self::DEFAULT_RATE_PER_MINUTE);
        $wait = RateLimiter::hit('search:' . \get_current_user_id(), $rate);
        if ($wait > 0)
        {
            throw new AbilityRateLimitException($wait);
        }

        $filters = (!empty($input['filters']) && is_array($input['filters'])) ? $input['filters'] : array();
        $found = self::searchOneModule($module_id, $keyword, $limit, $filters, $this->moduleType(), $this->moduleKind());

        $full = (($input['fields'] ?? 'lean') === 'full');

        $full_items = array_values(array_map(array(LeanProduct::class, 'fullItem'), $found['items']));

        $out = array(
            'module_id' => $module_id,
            'keyword' => $keyword,
            'count' => count($found['items']),
            'notice' => $found['notice'],
            'results' => $full ? $full_items : $this->mapLean($found['items']),
        );

        // Rehydrate-by-id (design spec 2026-07-30): stash the mapped items so add-*
        // can attach them by unique_id via SearchTokenResolver — the server, not the
        // agent, owns what gets stored. No results -> nothing to reference -> no token.
        if ($full_items)
        {
            $token = SearchResultCache::store($module_id, $keyword, $full_items);
            if ($token !== '')
            {
                $out['search_token'] = $token;
            }
        }

        return $out;
    }

    /**
     * Validate one module (exists, active, right type, searchable), run its
     * keyword search with a sane page floor, and return the sliced raw items
     * plus notice. Rate limiting is the caller's concern (a single search hits
     * once; a multi-module search hits once per module). Throws
     * AbilityInputException for a bad/wrong-type/unsearchable module, or
     * AbilitySearchException when the module's own API fails.
     *
     * @return array{notice: string, items: array}
     */
    public static function searchOneModule(string $module_id, string $keyword, int $limit, array $filters, string $module_type, string $module_kind): array
    {
        $module_id = trim($module_id);
        $keyword = trim($keyword);
        $limit = min(self::MAX_LIMIT, max(1, $limit));

        $manager = ModuleManager::getInstance();
        // Distinguish "you sent nothing" from "you sent a bad id": these abilities
        // do not auto-select a module, so an omitted module_id used to surface as
        // "Unknown module ''", which reads like a wrong guess and invites an agent
        // to keep guessing rather than call list-modules.
        if ($module_id === '')
        {
            throw new AbilityInputException(
                'module_id is required. Call content-egg/list-modules with the matching type to '
                    . 'see the active modules on this site; if none of that type are active, '
                    . 'activating one requires Content Egg Pro.'
            );
        }
        if (!$manager->moduleExists($module_id))
        {
            throw new AbilityInputException(
                "Unknown module '{$module_id}'. Call content-egg/list-modules for available module ids."
            );
        }
        if (!$manager->isModuleActive($module_id))
        {
            throw new AbilityInputException(
                "Module '{$module_id}' is not active. An administrator can activate it in Content Egg settings."
            );
        }

        $module = ModuleManager::parserFactory($module_id);
        if (!$module || $module->getParserType() !== $module_type)
        {
            throw new AbilityInputException(
                "This ability searches {$module_kind} modules, but '{$module_id}' is a different type. "
                    . "Each module kind has its own search: content-egg/search-products, search-images, "
                    . "search-videos, search-coupons. Call content-egg/list-modules to see each module's type."
            );
        }
        if (!$module->isSearchable())
        {
            throw new AbilityInputException(
                "Module '{$module_id}' does not support keyword search."
            );
        }

        // Same per-call overlay the editor UI and TMN use; configFactory()
        // returns the cached per-module instance the parser reads from. Fetch a
        // sane minimum page (some APIs reject per_page < 3) and slice to $limit.
        $fetch = min(self::MAX_LIMIT, max($limit, self::MIN_FETCH_PAGE));
        ModuleManager::configFactory($module_id)->applayCustomOptions(array('entries_per_page' => $fetch));

        // Allowlist agent-supplied filters to the module's declared search
        // filter keys, mirroring ModulesRestController::search().
        $query = array();
        if ($filters)
        {
            $allowed_keys = array_column((array) $module->getSearchFilters(), 'key');
            $query = ProductSearchService::filterQueryKeys($filters, $allowed_keys);
        }
        $query['keyword'] = $keyword;

        try
        {
            $result = ProductSearchService::search($module_id, $query);
        }
        catch (\InvalidArgumentException $e)
        {
            throw $e; // input problems keep their cegg_validation_failed mapping
        }
        catch (FeedImportPendingException $e)
        {
            throw $e; // feed still importing keeps its cegg_feed_pending mapping
        }
        catch (\Throwable $e)
        {
            // The module's own API/search failed (bad key, quota, network,
            // out-of-range parameter): surface the real reason instead of a
            // generic internal error. Clean it to plain text for the response.
            $reason = trim(\wp_strip_all_tags(html_entity_decode((string) $e->getMessage(), ENT_QUOTES)));
            if (mb_strlen($reason) > 300)
            {
                $reason = mb_substr($reason, 0, 300) . "\u{2026}";
            }
            throw new AbilitySearchException($module_id, $reason);
        }

        return array(
            'notice' => (string) $result['notice'],
            'items' => array_slice((array) $result['results'], 0, $limit),
        );
    }
}
