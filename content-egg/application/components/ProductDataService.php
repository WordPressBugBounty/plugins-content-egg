<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

use ContentEgg\application\helpers\TextHelper;

/**
 * ProductDataService class file
 *
 * Write orchestration for per-post product data shared by the metabox save
 * path and the REST API. All persistence goes through ContentManager::saveData()
 * so diffing, price history, price alerts and the content_egg_save_data action
 * keep working for every UI.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ProductDataService
{

    /**
     * Fields the editor may change via PATCH. Identity (unique_id, module_id),
     * cloaked links (aff_url, bridge_url), computed/`_`-prefixed values, and
     * integration internals (woo_sync, logo, keyword, last_update…) are never
     * editable — anything absent from this list is silently dropped.
     *
     * Each field is paired with a sanitizer kind (see sanitizeField()); the keys
     * are the whitelist.
     */
    const FIELD_SANITIZERS = array(
        // Plain text
        'title'         => 'text',
        'subtitle'      => 'text',
        'badge'         => 'text',
        'badge_color'   => 'badge_color',
        'promo'         => 'text',
        'availability'  => 'text',
        'merchant'      => 'text',
        'domain'        => 'text',
        'manufacturer'  => 'text',
        'currencyCode'  => 'text',
        'ean'           => 'text',
        'upc'           => 'text',
        'sku'           => 'text',
        'isbn'          => 'text',
        // Rich text (safe HTML allowed)
        'description'       => 'html',
        'short_description' => 'html',
        // URLs
        'url'      => 'url', // affiliate/primary link (aff_url is derived from it)
        'orig_url' => 'url', // direct/original merchant link
        'img'      => 'url',
        'logo'     => 'url', // merchant logo
        // Floats
        'price'         => 'float',
        'priceOld'      => 'float',
        'rating'        => 'float',
        'ratingDecimal' => 'float',
        'shipping_cost' => 'float',
        // Integers
        'order_num'    => 'int',
        'reviewsCount' => 'int',
        // Enum / special
        'stock_status' => 'stock',
        'group'        => 'group',
        'features'     => 'features', // list of {name, value} attribute rows
        // Coupon fields
        'code'      => 'text', // coupon/voucher code
        'startDate' => 'date', // unix seconds (accepts JS ms or a date string)
        'endDate'   => 'date',
    );

    /**
     * Editable sub-keys of the `extra` array (Offer's custom XPath price selector
     * and deeplink). Only these are merged; parser-set keys (last_error,
     * DealAccessType, AvailabilityMessage…) are preserved untouched.
     */
    const EXTRA_EDITABLE = array('priceXpath', 'deeplink', 'discount');

    /** Whole-post singleton flags — written only via setExclusiveWooFlag, never the PATCH whitelist. */
    const WOO_FLAG_FIELDS = array('woo_sync', 'woo_attr');

    /**
     * The whitelist of editable field names (keys of FIELD_SANITIZERS).
     */
    const EDITABLE_FIELDS = array(
        'title', 'subtitle', 'badge', 'badge_color', 'promo', 'availability',
        'merchant', 'domain', 'manufacturer', 'currencyCode',
        'ean', 'upc', 'sku', 'isbn',
        'description', 'short_description',
        'url', 'orig_url', 'img',
        'price', 'priceOld', 'rating', 'ratingDecimal', 'shipping_cost',
        'order_num', 'reviewsCount',
        'stock_status', 'group',
    );

    /**
     * The editable whitelist for COUPON-type modules. A coupon (ContentCoupon)
     * is a product with a different field set — no price/stock/rating, but a
     * code and validity window. `discount` is edited via the `extra` sub-key
     * (see EXTRA_EDITABLE), not here.
     */
    const EDITABLE_FIELDS_COUPON = array(
        'title', 'description', 'code', 'startDate', 'endDate',
        'merchant', 'domain', 'url', 'orig_url', 'img', 'logo',
        'badge', 'badge_color', 'group',
    );

    /**
     * Editable whitelist keyed by parser-type family. A PATCH is filtered by the
     * family of the item's module (see whitelistForModule) so product fields
     * can't be written onto a coupon and vice versa.
     */
    /**
     * Editable whitelist for IMAGE/VIDEO modules. Media items expose only light
     * metadata; source identity (img URL, extra.guid, extra.video_url) is NOT
     * editable — the render keys on it, so it stays read-only.
     */
    const EDITABLE_FIELDS_MEDIA = array(
        'title', 'description', 'url', 'group', 'order_num',
    );

    const EDITABLE_FIELDS_BY_TYPE = array(
        'PRODUCT' => self::EDITABLE_FIELDS,
        'COUPON'  => self::EDITABLE_FIELDS_COUPON,
        'IMAGE'   => self::EDITABLE_FIELDS_MEDIA,
        'VIDEO'   => self::EDITABLE_FIELDS_MEDIA,
    );

    /**
     * Resolve the editable-field whitelist for a module by its parser-type
     * family. Runtime helper (touches ModuleManager) — not used by the pure
     * merge/apply functions, which take an explicit $allowed list instead.
     * Unknown families fall back to the product whitelist.
     */
    public static function whitelistForModule($module_id): array
    {
        $type = ModuleManager::factory($module_id)->getParserType();
        return self::EDITABLE_FIELDS_BY_TYPE[$type] ?? self::EDITABLE_FIELDS;
    }

    /** Read a stored woo flag as bool (metabox stores the string 'true'). */
    public static function wooFlagTruthy($value): bool
    {
        return (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Apply whole-post radio exclusivity for a woo flag: clear $field on every
     * item across every module, then set the target item to 'true' when $value.
     * Pure (no I/O). Returns the transformed map and the ids of modules whose
     * flag truthiness actually changed (so the caller saves only those).
     */
    public static function applyExclusiveWooFlag(array $by_module, string $module_id, string $unique_id, string $field, bool $value): array
    {
        $changed = array();
        foreach ($by_module as $mid => $items)
        {
            if (!is_array($items))
                continue;
            foreach ($items as $i => $item)
            {
                if (!is_array($item))
                    continue;
                $uid = isset($item['unique_id']) ? (string) $item['unique_id'] : '';
                $is_target = ($mid === $module_id && $uid === $unique_id);
                $next = ($is_target && $value) ? 'true' : '';
                $was_on = self::wooFlagTruthy($item[$field] ?? '');
                $now_on = ($next === 'true');
                if ($was_on !== $now_on)
                {
                    $by_module[$mid][$i][$field] = $next;
                    $changed[$mid] = true;
                }
            }
        }
        return array('by_module' => $by_module, 'changed' => array_keys($changed));
    }

    /**
     * Set a woo flag on one item with whole-post exclusivity, persisting only the
     * modules that changed. Each saveData fires content_egg_save_data, which
     * WooIntegrator handles (it re-reads the full post, so save order is safe).
     */
    public static function setExclusiveWooFlag($post_id, $module_id, $unique_id, $field, $value): void
    {
        $by_module = array();
        foreach (ModuleManager::getInstance()->getProductParserModulesIdList(true) as $mid)
        {
            $data = ContentManager::getData($post_id, $mid);
            if (is_array($data) && $data)
                $by_module[$mid] = array_values($data);
        }

        $result = self::applyExclusiveWooFlag(
            $by_module,
            (string) $module_id,
            (string) $unique_id,
            (string) $field,
            (bool) $value
        );

        foreach ($result['changed'] as $mid)
            ContentManager::saveData($result['by_module'][$mid], $mid, $post_id, true);
    }

    /**
     * Normalize raw item arrays before save.
     * Extracted from EggMetabox::dataPrepare() — behavior must stay identical.
     */
    public static function prepareItems($data): array
    {
        if (!is_array($data) || empty($data))
        {
            return [];
        }

        foreach ($data as $i => &$row)
        {
            if (!is_array($row))
            {
                unset($data[$i]);
                continue;
            }

            // dinamic fields
            $row['aff_url'] = null;
            $row['bridge_url'] = null;
            $row['target_post_id'] = null;

            if (isset($row['description']) && is_string($row['description']))
            {
                $desc = trim($row['description']);
                if ($desc !== '' && !TextHelper::isHtmlTagDetected($desc))
                {
                    $desc = str_replace(["\r\n", "\r"], "\n", $desc);
                    $row['description'] = TextHelper::nl2br($desc);
                }
                else
                {
                    $row['description'] = $desc;
                }
            }

            if (array_key_exists('price', $row))
            {
                $row['price'] = (float) $row['price'];
            }
            if (array_key_exists('priceOld', $row))
            {
                $row['priceOld'] = (float) $row['priceOld'];
            }
        }
        unset($row);

        return $data;
    }

    /**
     * Merge incoming items into existing module data, deduping by unique_id.
     * Pure function.
     *
     * @return array array('data' => array, 'added' => string[] appended unique_ids)
     */
    public static function mergeItems(array $existing, array $incoming, $keyword = '')
    {
        $added = array();
        $existing_ids = array();

        foreach ($existing as $item)
        {
            if (is_array($item) && isset($item['unique_id']))
                $existing_ids[(string) $item['unique_id']] = true;
        }

        $merged = array_values($existing);

        foreach ($incoming as $item)
        {
            if (!is_array($item) || empty($item['unique_id']))
                continue;

            $unique_id = (string) $item['unique_id'];

            if (isset($existing_ids[$unique_id]))
                continue;

            if ($keyword && empty($item['keyword']))
                $item['keyword'] = $keyword;

            $merged[] = $item;
            $existing_ids[$unique_id] = true;
            $added[] = $unique_id;
        }

        return array('data' => $merged, 'added' => $added);
    }

    /**
     * Append items to a post's module data (write-through).
     *
     * @return array array('data' => stored module data, 'added' => string[])
     */
    public static function addItems($post_id, $module_id, array $items, $keyword = '')
    {
        $existing = ContentManager::getData($post_id, $module_id);
        if (!is_array($existing))
            $existing = array();

        $incoming = self::prepareItems($items);
        $result = self::mergeItems($existing, $incoming, $keyword);

        if ($result['added'])
            ContentManager::saveData($result['data'], $module_id, $post_id, true);

        $saved = ContentManager::getData($post_id, $module_id);

        return array(
            'data' => is_array($saved) ? $saved : array(),
            'added' => $result['added'],
        );
    }

    /**
     * Overwrite only whitelisted fields on a single item array, sanitizing each
     * value on the way in (the REST payload is untrusted). Pure.
     *
     * @param array      $allowed Family whitelist. When non-null, a field is
     *                            applied only if it is BOTH a known sanitizer key
     *                            AND present in this list — so a product field
     *                            can't be written onto a coupon and vice versa.
     *                            Null keeps the legacy behavior (all sanitizer
     *                            keys), used where the family is not yet resolved.
     */
    public static function applyFieldUpdates(array $item, array $fields, ?array $allowed = null): array
    {
        foreach ($fields as $field => $value)
        {
            if (!array_key_exists($field, self::FIELD_SANITIZERS))
                continue;
            if ($allowed !== null && !in_array($field, $allowed, true))
                continue;

            // Never blank a live product's title: ignore an empty title update
            // instead of overwriting a real title with "" (client also blocks
            // this — belt and braces).
            if ($field === 'title' && trim((string) $value) === '')
                continue;

            $item[$field] = self::sanitizeField($field, $value);
        }

        // Rating (the star value) is derived from the precise ratingDecimal —
        // the editor edits ratingDecimal only, as the legacy metabox does. Mirror
        // ContentManager's canonical rule: clamp to [0,5] and round to halves.
        if (array_key_exists('ratingDecimal', $fields))
        {
            $rd = max(0.0, min(5.0, (float) ($item['ratingDecimal'] ?? 0)));
            $item['rating'] = $rd ? round($rd * 2) / 2 : '';
        }

        // Merge the editable `extra` sub-keys (Offer XPath + deeplink) into the
        // item's existing extra so parser-set keys are preserved. Neither is a
        // plain URL — the deeplink is a link template and priceXpath an XPath
        // selector — so both are sanitized as text, not esc_url'd.
        if (isset($fields['extra']) && is_array($fields['extra']))
        {
            $extra = (isset($item['extra']) && is_array($item['extra'])) ? $item['extra'] : array();
            foreach (self::EXTRA_EDITABLE as $k)
            {
                if (array_key_exists($k, $fields['extra']))
                    $extra[$k] = \sanitize_text_field((string) $fields['extra'][$k]);
            }
            $item['extra'] = $extra;
        }

        return $item;
    }

    /**
     * Sanitize one incoming field value by its declared kind. Text is stripped
     * of tags/control chars, rich text is limited to post-safe HTML, URLs are
     * cleaned, numbers are cast, and stock_status is clamped to a known enum.
     */
    public static function sanitizeField($field, $value)
    {
        $kind = self::FIELD_SANITIZERS[$field] ?? 'text';

        switch ($kind)
        {
            case 'html':
                return \wp_kses_post((string) $value);
            case 'url':
                return \esc_url_raw((string) $value);
            case 'float':
                return (float) $value;
            case 'int':
                return (int) $value;
            case 'date':
                // Store unix seconds. Accept a numeric epoch (JS milliseconds
                // when it's clearly too large to be seconds, else seconds) or a
                // parseable date string. Blank/unparseable → '' (mirrors
                // CouponModule::presavePrepare so hide_expired/future stay sane).
                if (is_numeric($value))
                {
                    $n = (int) $value;
                    return $n > 100000000000 ? intdiv($n, 1000) : $n;
                }
                $ts = \strtotime((string) $value);
                return $ts ? $ts : '';
            case 'stock':
                $v = (int) $value;
                return in_array($v, array(-1, 0, 1), true) ? $v : 0;
            case 'group':
                return self::sanitizeGroup($value);
            case 'features':
                return self::sanitizeFeatures($value);
            case 'badge_color':
                // Limit to the named Bootstrap colors the picker offers (as the
                // legacy metabox does); anything else clears the color.
                $v = \sanitize_text_field((string) $value);
                $allowed = array('', 'primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark');
                return in_array($v, $allowed, true) ? $v : '';
            case 'text':
            default:
                return \sanitize_text_field((string) $value);
        }
    }

    /**
     * Sanitize the product attributes list — a list of {name, value} rows.
     * Non-array input becomes an empty list. Each name/value is passed through
     * sanitize_text_field() so only safe, single-line, tag-free strings reach the
     * DB. An attribute requires BOTH a name and a value; any incomplete row
     * (empty or half-filled) is dropped silently. Order is preserved.
     */
    public static function sanitizeFeatures($value): array
    {
        if (!is_array($value))
            return array();

        $out = array();
        foreach ($value as $row)
        {
            if (!is_array($row))
                continue;

            $name = \sanitize_text_field((string) ($row['name'] ?? ''));
            $val  = \sanitize_text_field((string) ($row['value'] ?? ''));

            if ($name === '' || $val === '')
                continue;

            $out[] = array('name' => $name, 'value' => $val);
        }

        return $out;
    }

    /**
     * Strip characters that would break shortcode parsing from a group name — it
     * can later be used as a comma-separated `groups="…"` shortcode attribute, so
     * remove brackets, quotes, angle brackets, '&', '=', and the list-separating
     * comma, then collapse whitespace. Mirrors the client's sanitizeGroupName().
     */
    public static function sanitizeGroup($value): string
    {
        $value = (string) $value;
        $value = preg_replace('~[\[\]<>"\',&=]~', '', $value);
        $value = preg_replace('~\s+~', ' ', $value);

        return trim($value);
    }

    /**
     * Apply a whitelisted field update to one stored item (write-through).
     *
     * @return array array('data' => stored items (list), 'updated' => bool)
     */
    public static function updateItem($post_id, $module_id, $unique_id, array $fields, ?array $allowed = null)
    {
        $data = ContentManager::getData($post_id, $module_id);
        $data = is_array($data) ? array_values($data) : array();

        $unique_id = (string) $unique_id;
        $updated = false;

        foreach ($data as $key => $item)
        {
            if (!is_array($item) || (string) ($item['unique_id'] ?? '') !== $unique_id)
                continue;

            $data[$key] = self::applyFieldUpdates($item, $fields, $allowed);
            $updated = true;
            break;
        }

        if (!$updated)
            return array('data' => $data, 'updated' => false);

        // Reuse shared normalization (description nl2br, dynamic-field nulling),
        // then persist the whole module array through the sanctioned path.
        $data = self::prepareItems($data);
        ContentManager::saveData($data, $module_id, $post_id, true);

        $saved = ContentManager::getData($post_id, $module_id);

        return array(
            'data' => is_array($saved) ? array_values($saved) : array(),
            'updated' => true,
        );
    }

    /**
     * Overlay per-item field updates onto existing module items, matched by
     * unique_id. For each existing item with a matching update, only the
     * $allowed keys present in that update are applied (via applyFieldUpdates,
     * which sanitizes and whitelists). Items with no matching update, and
     * updates with no matching item, are left as-is / ignored. Order preserved.
     * Pure.
     *
     * @param array $existing stored module items
     * @param array $updates  list of items carrying new values (must have unique_id)
     * @param array $allowed  field names eligible for overlay (subset of the whitelist)
     */
    public static function mergeItemFields(array $existing, array $updates, array $allowed): array
    {
        $byId = array();
        foreach ($updates as $u)
        {
            if (is_array($u) && isset($u['unique_id']))
                $byId[(string) $u['unique_id']] = $u;
        }

        $out = array();
        foreach ($existing as $item)
        {
            if (!is_array($item))
            {
                $out[] = $item;
                continue;
            }

            $uid = (string) ($item['unique_id'] ?? '');
            if ($uid !== '' && isset($byId[$uid]))
            {
                $fields = array();
                foreach ($allowed as $field)
                {
                    if (array_key_exists($field, $byId[$uid]))
                        $fields[$field] = $byId[$uid][$field];
                }
                $item = self::applyFieldUpdates($item, $fields);
            }

            $out[] = $item;
        }

        return $out;
    }

    /**
     * Overlay per-item $allowed fields from $items onto a post's stored module
     * data and persist through the sanctioned path (write-through). Returns the
     * full post-merge item list for the module (so callers can setModuleItems).
     */
    public static function applyItemFields($post_id, $module_id, array $items, array $allowed): array
    {
        $existing = ContentManager::getData($post_id, $module_id);
        $existing = is_array($existing) ? array_values($existing) : array();

        $merged = self::mergeItemFields($existing, $items, $allowed);
        $merged = self::prepareItems($merged);
        ContentManager::saveData($merged, $module_id, $post_id, true);

        $saved = ContentManager::getData($post_id, $module_id);

        return is_array($saved) ? array_values($saved) : array();
    }

    /**
     * Return the list without the item whose unique_id matches; drops
     * non-array rows. Reindexed. Pure.
     */
    public static function filterOutItem(array $items, $unique_id): array
    {
        $unique_id = (string) $unique_id;

        return array_values(array_filter($items, function ($item) use ($unique_id)
        {
            return is_array($item) && (string) ($item['unique_id'] ?? '') !== $unique_id;
        }));
    }

    /**
     * Index click-aggregate rows (as returned by
     * LinkClicksDailyModel::aggregatesForPost) by "module_id|unique_id", keeping
     * only the 30-day and total counts, int-cast (the batch query returns
     * strings). Malformed rows are skipped; missing counts default to 0. Pure.
     */
    public static function indexClickAggregates(array $rows): array
    {
        $index = array();
        foreach ($rows as $row)
        {
            if (!is_array($row) || !isset($row['module_id'], $row['unique_id']))
                continue;

            $key = $row['module_id'] . '|' . $row['unique_id'];
            $index[$key] = array(
                'd30' => (int) ($row['d30'] ?? 0),
                'total' => (int) ($row['total'] ?? 0),
            );
        }

        return $index;
    }

    /**
     * Remove one item from a module (write-through). An emptied module array
     * makes ContentManager::saveData() delegate to deleteData().
     *
     * @return array array('data' => remaining items (list))
     */
    public static function removeItem($post_id, $module_id, $unique_id)
    {
        $data = ContentManager::getData($post_id, $module_id);
        $data = is_array($data) ? array_values($data) : array();

        $next = self::filterOutItem($data, $unique_id);
        ContentManager::saveData($next, $module_id, $post_id, true);

        $saved = ContentManager::getData($post_id, $module_id);

        return array('data' => is_array($saved) ? array_values($saved) : array());
    }

    /**
     * Group a flat list of targets — [{module_id, unique_id}, ...] spanning
     * modules — into a map of module_id => list<unique_id>. Malformed entries
     * (non-arrays, empty module_id/unique_id) are skipped. Pure.
     *
     * @param array $targets list of array('module_id' => .., 'unique_id' => ..)
     * @return array<string, list<string>>
     */
    public static function groupTargetsByModule(array $targets): array
    {
        $byModule = array();
        foreach ($targets as $t)
        {
            if (!is_array($t))
                continue;
            $mid = (string) ($t['module_id'] ?? '');
            $uid = (string) ($t['unique_id'] ?? '');
            if ($mid === '' || $uid === '')
                continue;
            $byModule[$mid][] = $uid;
        }

        return $byModule;
    }

    /**
     * Remove multiple items across modules in a single write per module
     * (write-through). Same effect as calling removeItem() per target, but a
     * bulk delete of N products touches each module's storage once instead of N
     * times.
     *
     * @param array $targets list of array('module_id' => .., 'unique_id' => ..)
     */
    public static function removeItems($post_id, array $targets): void
    {
        foreach (self::groupTargetsByModule($targets) as $module_id => $unique_ids)
        {
            $data = ContentManager::getData($post_id, $module_id);
            $data = is_array($data) ? array_values($data) : array();
            if (!$data)
                continue;

            foreach ($unique_ids as $uid)
                $data = self::filterOutItem($data, $uid);

            ContentManager::saveData($data, $module_id, $post_id, true);
        }
    }

    /**
     * Apply the same whitelisted field update to multiple items across modules
     * in a single write per module (write-through) — e.g. assigning a group to
     * N selected products. Same effect as calling updateItem() per target.
     *
     * @param array $targets list of array('module_id' => .., 'unique_id' => ..)
     * @param array $fields  field => value (whitelisted by applyFieldUpdates)
     */
    public static function updateItems($post_id, array $targets, array $fields): void
    {
        if (!$fields)
            return;

        foreach (self::groupTargetsByModule($targets) as $module_id => $unique_ids)
        {
            $data = ContentManager::getData($post_id, $module_id);
            $data = is_array($data) ? array_values($data) : array();
            if (!$data)
                continue;

            $wanted = array_flip(array_map('strval', $unique_ids));
            $changed = false;
            foreach ($data as $key => $item)
            {
                if (!is_array($item) || !isset($wanted[(string) ($item['unique_id'] ?? '')]))
                    continue;
                $data[$key] = self::applyFieldUpdates($item, $fields);
                $changed = true;
            }

            if (!$changed)
                continue;

            // Reuse shared normalization, then persist through the sanctioned
            // path — mirroring updateItem().
            $data = self::prepareItems($data);
            ContentManager::saveData($data, $module_id, $post_id, true);
        }
    }

    /**
     * Remove all products for a post — one module, or every parser module.
     */
    public static function removeAll($post_id, $module_id = ''): void
    {
        if ($module_id)
        {
            ContentManager::deleteData($module_id, $post_id, true);
            return;
        }

        foreach (ModuleManager::getInstance()->getParserModulesIdList(true) as $mid)
            ContentManager::deleteData($mid, $post_id, true);
    }

    /**
     * Turn a flat desired order — [{module_id, unique_id}, ...] spanning every
     * module — into a GLOBAL numbering plan. order_num is assigned by absolute
     * position (1..N) so cross-module interleaving is preserved: CE stores each
     * module in its own array, but order_num is the global position the render
     * pipeline (mergeAll + sortByNumber) sorts by. Numbering from 1 (not 0) also
     * clears ContentManager's `if (!empty($d['order_num']))` guard, which treats
     * 0 as unset. Pure.
     *
     * @param array $order list of array('module_id' => .., 'unique_id' => ..)
     * @return array array('numbers' => array<string,array<string,int>>,
     *                     'sequence' => array<string,list<string>>)
     */
    public static function buildOrderPlan(array $order): array
    {
        $numbers  = array();
        $sequence = array();
        $n = 1;
        foreach ($order as $entry)
        {
            if (!is_array($entry))
                continue;
            $mid = (string) ($entry['module_id'] ?? '');
            $uid = (string) ($entry['unique_id'] ?? '');
            if ($mid === '' || $uid === '')
                continue;
            $numbers[$mid][$uid] = $n++;
            $sequence[$mid][]    = $uid;
        }

        return array('numbers' => $numbers, 'sequence' => $sequence);
    }

    /**
     * Reorder one module's items to match $uniqueIdOrder, stamping each item's
     * order_num from $orderNumMap (unique_id => global number). Items present in
     * storage but absent from the request (data drift) are appended after the
     * listed ones, each taking the next value above the highest mapped number.
     * Pure.
     *
     * @return array array('items' => list)
     */
    public static function reorderModule(array $items, array $uniqueIdOrder, array $orderNumMap): array
    {
        $byId = array();
        foreach ($items as $item)
        {
            if (is_array($item) && isset($item['unique_id']))
                $byId[(string) $item['unique_id']] = $item;
        }

        $ordered = array();
        foreach ($uniqueIdOrder as $uid)
        {
            $uid = (string) $uid;
            if (!isset($byId[$uid]))
                continue;

            $item = $byId[$uid];
            if (isset($orderNumMap[$uid]))
                $item['order_num'] = $orderNumMap[$uid];
            $ordered[] = $item;
            unset($byId[$uid]);
        }

        $next = $orderNumMap ? (max($orderNumMap) + 1) : 1;
        foreach ($byId as $item)
        {
            $item['order_num'] = $next++;
            $ordered[] = $item;
        }

        return array('items' => $ordered);
    }

    /**
     * Apply a global desired order across all modules of a post (write-through).
     * order_num is rewritten by global position via buildOrderPlan(); each
     * touched module's array is reordered and saved.
     *
     * @param array $order list of array('module_id' => .., 'unique_id' => ..)
     */
    public static function reorder($post_id, array $order): void
    {
        $plan = self::buildOrderPlan($order);

        foreach ($plan['sequence'] as $mid => $uniqueIdOrder)
        {
            $data = ContentManager::getData($post_id, $mid);
            $data = is_array($data) ? array_values($data) : array();

            $result = self::reorderModule($data, $uniqueIdOrder, $plan['numbers'][$mid]);
            ContentManager::saveData($result['items'], $mid, $post_id, true);
        }
    }

    /**
     * Re-run listings (updateAllByKeyword) or prices (updateAllItems) for a
     * post — the same operations the metabox 'cegg_update_products' ajax runs.
     * Synchronous; bounded by the same PHP timeout as the metabox today.
     */
    public static function refresh($post_id, $type): void
    {
        if ($type === 'listings')
            ContentManager::updateAllByKeyword($post_id);
        elseif ($type === 'prices')
            ContentManager::updateAllItems($post_id);
        else
            throw new \InvalidArgumentException('Unknown refresh type.');
    }

    /**
     * Opaque per-module content hash for optimistic-concurrency checks.
     */
    public static function revision($post_id, $module_id)
    {
        $data = ContentManager::getData($post_id, $module_id);

        return md5(serialize(is_array($data) ? $data : array()));
    }
}
