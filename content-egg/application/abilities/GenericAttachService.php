<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\components\ProductDataService;

/**
 * GenericAttachService class file
 *
 * Shared, type-checked attach core for the non-product attach abilities
 * (coupons, images, videos). Products keep their own ability
 * (AddProductsToPostAbility) because of Offer auto-activation, field-skeleton
 * seeding, monetization reporting and the product overrides whitelist — none of
 * which apply here. The storage path (ProductDataService::addItems ->
 * ContentManager::saveData by module_id) is identical across all families, so
 * this service just validates and stores.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class GenericAttachService
{
    /**
     * Validate and attach $items to $post_id under $module_id, requiring the
     * module to be of $expected_type (a ParserModule::PARSER_TYPE_* constant)
     * and active. Returns {post_id, module_id, added[], count, revision}.
     *
     * @throws AbilityInputException  unknown/unavailable module, wrong type,
     *                                inactive module, empty or malformed items
     * @throws AbilityConflictException  revision mismatch (via RevisionGuard)
     *
     * When $search_token is non-empty, $items are unique_id refs resolved
     * against that search's cached results (SearchTokenResolver) — the cached
     * item is what gets stored. Empty token = legacy full-object mode.
     */
    public static function attach(int $post_id, string $module_id, string $expected_type, array $items, string $revision, string $search_token = ''): array
    {
        $items = array_values($items);
        if (!$items)
        {
            throw new AbilityInputException("'items' must be a non-empty array of objects.");
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
            throw new AbilityInputException("Module '{$module_id}' is not available in this build.");
        }

        if ($parser->getParserType() !== $expected_type)
        {
            throw new AbilityInputException(
                "Module '{$module_id}' is not a " . strtolower($expected_type) . " module. "
                    . 'Call content-egg/list-modules and use a module whose type matches this ability.'
            );
        }

        if (!$manager->isModuleActive($module_id))
        {
            throw new AbilityInputException(
                "Module '{$module_id}' is not active. An administrator can activate it with content-egg/activate-module."
            );
        }

        if ($search_token !== '')
        {
            $resolved = SearchTokenResolver::resolve($search_token, $items, $module_id);
            $items = array_values($resolved['items']);
        }

        foreach ($items as $i => $item)
        {
            if (!is_array($item))
            {
                throw new AbilityInputException(
                    "items[{$i}] must be an object. To reference search results by unique_id, "
                        . 'also pass the search_token from the search response.'
                );
            }
            // Editorial "overrides" are a product-only feature; drop the key so a
            // stray one never lands in stored coupon/media data.
            unset($items[$i]['overrides']);
        }

        RevisionGuard::check($post_id, $module_id, $revision);

        // Never pass a keyword: it would arm the module's auto-update, which
        // re-runs the search and REPLACES the attached items. Human-only
        // setting; see AddProductsToPostAbility for the full reasoning.
        $result = ProductDataService::addItems($post_id, $module_id, $items, '');

        return array(
            'post_id' => $post_id,
            'module_id' => $module_id,
            'added' => array_values((array) $result['added']),
            'count' => count((array) $result['data']),
            'revision' => (string) ProductDataService::revision($post_id, $module_id),
        );
    }
}
