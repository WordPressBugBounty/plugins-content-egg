<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\components\ParserModule;
use ContentEgg\application\components\ProductDataService;

/**
 * AddProductsToPostAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class AddProductsToPostAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/add-products-to-post';
    }

    public function label(): string
    {
        return __('Add Products to Post', 'content-egg');
    }

    public function description(): string
    {
        return 'Attaches products to a post under one module. Preferred flow for searchable modules '
            . '(Amazon, Ebay, feeds...): run content-egg/search-products, then pass its search_token '
            . 'plus the chosen unique_ids as items — the server attaches its stored copy of each '
            . 'result, so full objects never need to be echoed back. (Passing full item objects from '
            . 'fields="full" without a token still works.) You MAY '
            . 'tailor editorial fields per item with an optional "overrides" object '
            . '(title, subtitle, description, short_description, badge, badge_color '
            . '[a named color: primary, secondary, success, danger, warning, info, light, dark — not hex], '
            . 'promo) — attached directly to an item, or alongside a unique_id as '
            . '{unique_id, overrides} in token mode — applied on top of the source item and '
            . 'surviving price/stock refreshes (which only update price, availability, url and '
            . 'image). For heavy rewriting, prefer Egg Blocks. For the manual "Offer" '
            . 'module (which has no search_token) you may construct items yourself; each needs '
            . 'unique_id, title and url (or orig_url), plus optional '
            . 'price/currencyCode/description/img. '
            . 'Pass the revision from content-egg/get-post-products to detect concurrent '
            . 'edits (409 cegg_conflict on mismatch). Returns the new revision. For the '
            . 'Offer module the response also reports monetization: how many added links '
            . 'became affiliate links and any domains still needing a deeplink configured '
            . 'in the Offer module settings.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => array('object', 'null'),
            'properties' => array(
                'post_id' => array('type' => 'integer', 'minimum' => 1),
                'module_id' => array('type' => 'string'),
                'search_token' => array(
                    'type' => 'string',
                    'description' => 'Token from the content-egg/search-products response. When set, items are '
                        . 'unique_id refs (string, or {unique_id, overrides}) and the server attaches its '
                        . 'stored copy of each result. Not applicable to the manual Offer module.',
                ),
                'items' => array(
                    'type' => 'array',
                    'minItems' => 1,
                    'items' => array(
                        'type' => array('string', 'object'),
                        'description' => 'With search_token: a unique_id string from the search results, or '
                            . '{unique_id, overrides} to tailor editorial fields. Without: a full product '
                            . 'object from content-egg/search-products (fields="full") passed unchanged '
                            . '(optionally with "overrides"), or a constructed item for the Offer module.',
                    ),
                ),
                'keyword' => array(
                    'type' => 'string',
                    'description' => 'Stored as the module search keyword for later auto-updates.',
                ),
                'revision' => array('type' => 'string'),
            ),
            'required' => array('post_id', 'module_id', 'items'),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'post_id' => array('type' => 'integer'),
                'module_id' => array('type' => 'string'),
                'added' => array('type' => 'array', 'items' => array('type' => 'string')),
                'count' => array('type' => 'integer'),
                'revision' => array('type' => 'string'),
                'monetization' => array(
                    'type' => 'object',
                    'description' => 'Offer module only: whether the added links became affiliate links.',
                    'properties' => array(
                        'monetized' => array('type' => 'integer'),
                        'unmonetized' => array('type' => 'integer'),
                        'domains_without_deeplink' => array(
                            'type' => 'array',
                            'items' => array('type' => 'string'),
                        ),
                    ),
                ),
            ),
        );
    }

    public function annotations(): array
    {
        return array('readonly' => false, 'destructive' => false, 'idempotent' => false);
    }

    public function checkPermission($input = null): bool
    {
        return PostScope::canEditPost($input);
    }

    public function execute(array $input): array
    {
        $post = PostScope::requirePost($input);
        $post_id = (int) $post->ID;
        $module_id = trim((string) ($input['module_id'] ?? ''));
        $items = is_array($input['items'] ?? null) ? array_values($input['items']) : array();
        $search_token = trim((string) ($input['search_token'] ?? ''));
        $cached_keyword = '';

        if (!$items)
        {
            throw new AbilityInputException("'items' must be a non-empty array of product objects.");
        }

        $manager = ModuleManager::getInstance();
        if (!$manager->moduleExists($module_id))
        {
            throw new AbilityInputException(
                "Unknown module '{$module_id}'. Call content-egg/list-modules for available module ids."
            );
        }
        try
        {
            $parser = ModuleManager::parserFactory($module_id);
        }
        catch (\Exception $e)
        {
            throw new AbilityInputException(
                "Module '{$module_id}' is not available in this build."
            );
        }

        // Activity-independent product check (isProductParserModule() is
        // gated on active state, which would defeat auto-activation below).
        if ($parser->getParserType() !== ParserModule::PARSER_TYPE_PRODUCT)
        {
            throw new AbilityInputException("Module '{$module_id}' is not a product module.");
        }

        if (!$parser->isSearchable())
        {
            if ($search_token !== '')
            {
                throw new AbilityInputException(
                    "search_token cannot be used with '{$module_id}': it is a manual module whose items "
                        . 'are constructed by the caller, not taken from a search. Pass full item objects instead.'
                );
            }

            // Manual module (Offer): auto-activate on first use, mirroring
            // PostProductsRestController::add_items.
            if (!$manager->isModuleActive($module_id))
            {
                $manager->activateModule($module_id);
            }

            $prepared = array();
            foreach ($items as $i => $item)
            {
                if (!is_array($item))
                {
                    throw new AbilityInputException("items[{$i}] must be an object.");
                }

                $clean = ProductDataService::applyFieldUpdates(array(), $item);
                $clean['unique_id'] = \sanitize_text_field((string) ($item['unique_id'] ?? ''));

                if ($clean['unique_id'] === '' || empty($clean['title']) || (empty($clean['url']) && empty($clean['orig_url'])))
                {
                    throw new AbilityInputException(
                        "items[{$i}] requires unique_id, title and url (or orig_url)."
                    );
                }

                if (empty($clean['orig_url']) && !empty($clean['url']))
                {
                    $clean['orig_url'] = $clean['url'];
                }

                // Seed the full field skeleton OfferModule::presavePrepare()
                // reads: sparse items trigger PHP 8 undefined-key warnings
                // and url-less items are silently dropped at save time.
                $clean += array(
                    'subtitle' => '',
                    'badge' => '',
                    'badge_color' => '',
                    'description' => '',
                    'img' => '',
                    'logo' => '',
                    'ratingDecimal' => '',
                    'order' => 0,
                    'price' => '',
                    'priceOld' => '',
                    'merchant' => '',
                    'domain' => '',
                );

                $prepared[] = $clean;
            }
            $items = $prepared;
        }
        elseif (!$manager->isModuleActive($module_id))
        {
            throw new AbilityInputException(
                "Module '{$module_id}' is not active. An administrator can activate it with content-egg/activate-module."
            );
        }
        else
        {
            // Active searchable module (Amazon, Ebay, feeds…): keep each source
            // item intact, but apply any per-item "overrides" — editorial fields
            // only, through the same whitelist as update-product — so identity
            // and commerce data stay untouched.
            if ($search_token !== '')
            {
                $resolved = SearchTokenResolver::resolve($search_token, $items, $module_id);
                $items = $resolved['items'];
                $cached_keyword = (string) $resolved['keyword'];
            }
            $items = $this->applyItemOverrides($items);
        }

        RevisionGuard::check($post_id, $module_id, (string) ($input['revision'] ?? ''));

        $keyword = \sanitize_text_field((string) ($input['keyword'] ?? ''));
        if ($keyword === '' && $cached_keyword !== '')
        {
            // Default the stored auto-update keyword to what was actually searched.
            $keyword = \sanitize_text_field($cached_keyword);
        }
        $result = ProductDataService::addItems($post_id, $module_id, $items, $keyword);

        $response = array(
            'post_id' => $post_id,
            'module_id' => $module_id,
            'added' => array_values((array) $result['added']),
            'count' => count((array) $result['data']),
            'revision' => (string) ProductDataService::revision($post_id, $module_id),
        );

        // Manual Offer module: report whether each newly added link became an
        // affiliate link. presavePrepare() rewrites `url` from the per-domain
        // deeplink rule; with no matching rule `url` stays equal to `orig_url`
        // (stored as-is, earns nothing). Surface it so the agent can warn the
        // user and name the domains that still need a deeplink configured.
        if (!$parser->isSearchable())
        {
            $added_ids = array_flip(array_map('strval', (array) $result['added']));
            $monetized = 0;
            $domains_without_deeplink = array();
            foreach ((array) $result['data'] as $item)
            {
                $uid = (string) ($item['unique_id'] ?? '');
                if ($uid === '' || !isset($added_ids[$uid]))
                {
                    continue;
                }

                $url = (string) ($item['url'] ?? '');
                $orig = (string) ($item['orig_url'] ?? '');
                if ($url !== '' && $url !== $orig)
                {
                    $monetized++;
                }
                elseif ($domain = (string) ($item['domain'] ?? ''))
                {
                    $domains_without_deeplink[$domain] = true;
                }
            }

            $response['monetization'] = array(
                'monetized' => $monetized,
                'unmonetized' => count((array) $result['added']) - $monetized,
                'domains_without_deeplink' => array_keys($domains_without_deeplink),
            );
        }

        return $response;
    }

    /**
     * Apply each item's optional "overrides" onto its source item. Overrides go
     * through ProductDataService::applyFieldUpdates, which writes ONLY whitelisted
     * editorial fields (title, subtitle, description, badge…) and merges only the
     * editable `extra` sub-keys — so unique_id, ASIN/locale and commerce data
     * cannot be changed here. The `overrides` key itself is stripped before save.
     */
    private function applyItemOverrides(array $items): array
    {
        $out = array();
        foreach ($items as $i => $item)
        {
            if (!is_array($item))
            {
                throw new AbilityInputException(
                    "items[{$i}] must be an object. To reference search results by unique_id, "
                        . 'also pass the search_token from the search response.'
                );
            }

            $overrides = (isset($item['overrides']) && is_array($item['overrides'])) ? $item['overrides'] : array();
            unset($item['overrides']);

            if ($overrides)
            {
                $item = ProductDataService::applyFieldUpdates($item, $overrides);
            }

            $out[] = $item;
        }

        return $out;
    }
}
