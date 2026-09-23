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
     * Maximum keyword rows. Format 2's preg_split() stops at 30 pieces (the
     * 30th swallows the rest); format 1 has no cap of its own, but the limit
     * is enforced uniformly so it never depends on whether groups are in use.
     */
    const ROW_LIMIT = 30;

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
     * The keyword string as group/keyword rows, exactly as the update cron
     * will read it — parsed by the one parser production uses, never by a
     * client-side guess.
     *
     * `notice` flags a row that parsed faithfully but probably not as its
     * author intended: "comma" means the keyword still holds a comma, i.e. it
     * was written as "kw1, kw2" and the spaced comma defeated the split.
     */
    public static function keywordRows(string $keyword): array
    {
        list($keywords, $groups) = ContentManager::prepareMultipleKeywords($keyword);

        $rows = array();
        foreach ($keywords as $i => $kw)
        {
            if ($kw === '')
                continue;

            $rows[] = array(
                'keyword' => $kw,
                'group' => isset($groups[$i]) ? $groups[$i] : '',
                'notice' => strpos($kw, ',') !== false ? 'comma' : '',
            );
        }

        return $rows;
    }

    /**
     * Whether the rows view can represent this string without changing what
     * the cron would search — parse(compose(parse($s))) === parse($s).
     *
     * False for a string whose keyword survived the format 2 split holding a
     * spaced comma: recomposing sends it through format 1, whose explode(",")
     * splits that comma too, yielding a different set of searches. The panel
     * shows the raw field for those instead of silently rewriting them.
     */
    public static function rowsAreFaithful(string $keyword): bool
    {
        $rows = self::keywordRows($keyword);

        return self::keywordRows(ContentManager::composeMultipleKeywords($rows)) === $rows;
    }

    /**
     * Rows -> ['keyword' => composed string, 'errors' => [row index => field]].
     *
     * Sanitizes EACH ROW and then composes; never sanitizes the composed
     * string. sanitizeKeyword() routes a value starting with "[" (or a URL)
     * through FILTER_SANITIZE_URL, which strips spaces — run over a composed
     * string, one such row would silently eat the spaces out of every other
     * row's group name.
     */
    public static function composeRows(array $rows): array
    {
        $errors = ContentManager::invalidKeywordRows($rows);
        if ($errors)
            return array('keyword' => '', 'errors' => $errors);

        // Blank rows are dropped rather than stored, so they do not count.
        $kept = 0;
        foreach ($rows as $i => $row)
        {
            if (trim((string) ($row['keyword'] ?? '')) === '')
                continue;

            if (++$kept > self::ROW_LIMIT)
                $errors[$i] = 'limit';
        }
        if ($errors)
            return array('keyword' => '', 'errors' => $errors);

        $clean = array();
        foreach ($rows as $row)
        {
            $keyword = ContentManager::sanitizeKeyword((string) ($row['keyword'] ?? ''));
            if (trim($keyword) === '')
                continue;

            $clean[] = array(
                'keyword' => $keyword,
                'group' => \sanitize_text_field((string) ($row['group'] ?? '')),
            );
        }

        return array(
            'keyword' => ContentManager::composeMultipleKeywords($clean),
            'errors' => array(),
        );
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

        $result = array('global_keyword' => $global, 'modules' => $modules);

        // The rows view of the global keyword. Product-scoped, like the global
        // keyword itself — see getAutoupdateKeyword().
        if ($family === null || $family === '' || $family === 'PRODUCT')
        {
            $result['global_rows'] = self::keywordRows($global);
            $result['global_rows_ok'] = self::rowsAreFaithful($global);
            $result['rows_supported'] = self::rowsSupported();
        }

        return $result;
    }

    /**
     * The global keyword as it should be stored.
     *
     * A raw value typed into the keyword field is sanitized exactly as the
     * metabox sanitizes it. A value composed from rows is NOT: composeRows()
     * already sanitized each row, and sanitizing the composed string would
     * send a value that starts with "[" (or is URL-shaped) through
     * FILTER_SANITIZE_URL, stripping the spaces out of every row's group name.
     */
    public static function normalizeGlobalKeyword($global, bool $already_composed): string
    {
        $global = (string) $global;

        return $already_composed ? $global : ContentManager::sanitizeKeyword($global);
    }

    /**
     * Whether a site still parses group/multi-keyword syntax. Either filter
     * turned on makes the rows a lie — without group matching a group column
     * cannot be expressed, without multiple keywords a second row cannot — so
     * the panel falls back to the raw keyword field.
     */
    public static function rowsSupported(): bool
    {
        return !\apply_filters('cegg_disable_group_matching', false)
            && !\apply_filters('cegg_disable_multiple_keywords', false);
    }

    /**
     * Persist settings, faithful to EggMetabox::saveMeta:
     * - keyword truthy -> update_post_meta; empty -> delete_post_meta.
     * - params written only when the keyword is set (metabox coupling); empty -> delete.
     * Unknown/inactive module ids are ignored. Returns the re-read settings.
     */
    public static function saveUpdateSettings(int $post_id, $global, array $modules, ?string $family = null, bool $global_is_composed = false): array
    {
        // The global keyword is PRODUCT-only. Never let a non-product family's
        // Settings tab write it, even if a client sends it — guard server-side.
        if ($family !== null && $family !== 'PRODUCT')
            $global = null;

        if ($global !== null)
        {
            $global = self::normalizeGlobalKeyword($global, $global_is_composed);
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
