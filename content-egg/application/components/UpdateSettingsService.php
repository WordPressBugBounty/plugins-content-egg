<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

/**
 * UpdateSettingsService class file
 *
 * Read/write of a post's auto-update settings for the editor Product Manager:
 * per-module keyword, per-module Min/Max price params, and the post-global
 * keyword. These live in SEPARATE post meta (not _cegg_data_*) and are written
 * with update_post_meta — faithful to EggMetabox::saveMeta, NOT saveData.
 *
 * Price params use each module's getPriceParamMap() (['min'=>realKey,
 * 'max'=>realKey]) — the same resolver ProductSearchService uses — so the
 * editor writes the exact keys the metabox and updateByKeyword consume.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class UpdateSettingsService
{
    const GLOBAL_KEYWORD_META = '_cegg_global_autoupdate_keyword';

    /**
     * Generic {min,max} -> {realMinKey: float, realMaxKey: float}. A blank or
     * missing bound is omitted (never stored as 0). Empty map -> []. Pure.
     */
    public static function translatePriceParams(array $generic, array $map): array
    {
        $out = array();
        if (empty($map))
            return $out;

        foreach (array('min', 'max') as $bound)
        {
            if (!isset($map[$bound]))
                continue;
            $raw = $generic[$bound] ?? '';
            if ($raw === '' || $raw === null)
                continue;
            $out[$map[$bound]] = floatval($raw);
        }

        return $out;
    }

    /**
     * Stored real-key params + map -> generic {min,max} ('' when absent). Pure.
     */
    public static function reverseParams(array $stored, array $map): array
    {
        $out = array('min' => '', 'max' => '');
        foreach (array('min', 'max') as $bound)
        {
            if (isset($map[$bound]) && isset($stored[$map[$bound]]))
                $out[$bound] = $stored[$map[$bound]];
        }

        return $out;
    }

    /**
     * The module's ['min'=>realKey,'max'=>realKey] map, or [] when the module
     * has no price filter / is unknown.
     */
    public static function priceMapFor(string $module_id): array
    {
        $module = ModuleManager::factory($module_id);
        if (!$module || !method_exists($module, 'getPriceParamMap'))
            return array();

        $map = $module->getPriceParamMap();
        return is_array($map) ? $map : array();
    }

    /**
     * Active modules ([id => label]) whose per-module keyword this panel manages
     * for a family. $family is a parser type key (PRODUCT/COUPON/IMAGE/VIDEO) —
     * each family edits only its own modules. A null family (legacy callers)
     * keeps the old behavior: all active affiliate modules (product + coupon).
     */
    private static function familyModulesList(?string $family): array
    {
        if ($family === null || $family === '')
            return ModuleManager::getInstance()->getAffiliteModulesList(true);

        $result = array();
        foreach (ModuleManager::getInstance()->getParserModulesByTypes($family, true) as $module_id => $module)
            $result[$module_id] = $module->getName();

        return $result;
    }

    /**
     * Read the post's auto-update settings for a family's active modules.
     * `keyword` is the RAW per-module value (blank = inherit global). The
     * post-global keyword is a PRODUCT concept — see getAutoupdateKeyword() — so
     * it's only meaningful to the PRODUCT family; other families ignore it.
     */
    public static function getUpdateSettings(int $post_id, ?string $family = null): array
    {
        $global = (string) \get_post_meta($post_id, self::GLOBAL_KEYWORD_META, true);

        $modules = array();
        foreach (self::familyModulesList($family) as $module_id => $label)
        {
            $keyword = (string) \get_post_meta($post_id, ContentManager::META_PREFIX_KEYWORD . $module_id, true);
            $map = self::priceMapFor($module_id);

            $entry = array(
                'id' => $module_id,
                'label' => $label,
                'keyword' => $keyword,
                'price_filter' => !empty($map),
            );

            if (!empty($map))
            {
                $stored = \get_post_meta($post_id, ContentManager::META_PREFIX_UPDATE_PARAMS . $module_id, true);
                $entry['params'] = self::reverseParams(is_array($stored) ? $stored : array(), $map);
            }

            $modules[] = $entry;
        }

        return array('global_keyword' => $global, 'modules' => $modules);
    }

    /**
     * Persist settings, faithful to EggMetabox::saveMeta:
     * - keyword truthy -> update_post_meta; empty -> delete_post_meta.
     * - params written only when the keyword is set (metabox coupling); empty -> delete.
     * Unknown/inactive module ids are ignored. Returns the re-read settings.
     */
    public static function saveUpdateSettings(int $post_id, $global, array $modules, ?string $family = null): array
    {
        // The global keyword is PRODUCT-only. Never let a non-product family's
        // Settings tab write it, even if a client sends it — guard server-side.
        if ($family !== null && $family !== 'PRODUCT')
            $global = null;

        if ($global !== null)
        {
            $global = ContentManager::sanitizeKeyword((string) $global);
            if ($global !== '')
                \update_post_meta($post_id, self::GLOBAL_KEYWORD_META, $global);
            else
                \delete_post_meta($post_id, self::GLOBAL_KEYWORD_META);
        }

        $active = self::familyModulesList($family);

        foreach ($modules as $module_id => $fields)
        {
            if (!isset($active[$module_id]) || !is_array($fields))
                continue;

            $keyword = ContentManager::sanitizeKeyword((string) ($fields['keyword'] ?? ''));
            $kw_meta = ContentManager::META_PREFIX_KEYWORD . $module_id;
            $params_meta = ContentManager::META_PREFIX_UPDATE_PARAMS . $module_id;

            if ($keyword !== '')
                \update_post_meta($post_id, $kw_meta, $keyword);
            else
                \delete_post_meta($post_id, $kw_meta);

            $map = self::priceMapFor($module_id);
            $params = ($keyword !== '' && !empty($map))
                ? self::translatePriceParams(
                    array('min' => $fields['min'] ?? '', 'max' => $fields['max'] ?? ''),
                    $map
                )
                : array();

            if (!empty($params))
                \update_post_meta($post_id, $params_meta, $params);
            else
                \delete_post_meta($post_id, $params_meta);
        }

        return self::getUpdateSettings($post_id, $family);
    }
}
