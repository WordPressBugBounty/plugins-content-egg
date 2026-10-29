<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ContentManager;
use ContentEgg\application\components\ShopStore;

/**
 * DealService class file
 *
 * Keeps a deal's scalar meta and status in step with its Content Egg data.
 * Everything that changes a deal comes through here, so the rules in
 * DealStatus - and the base-price resets - apply however the change arrives.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealService
{
    const IMPORT_UID_META = '_cegg_import_unique_id';

    public static function initAction(): void
    {
        // Every saveData() fires this, once per module - an import fires it for
        // the source module and again for each comparison module. refresh() is
        // idempotent, so that is only repeated work, not repeated effects.
        \add_action('content_egg_save_data', array(__CLASS__, 'onSaveData'), 20, 4);
        \add_action('save_post_' . DealPostType::POST_TYPE, array(__CLASS__, 'onSavePost'), 20, 1);
        \add_action('cegg_vat_factor_changed', array(__CLASS__, 'onVatFactorChanged'), 10, 5);
    }

    public static function onSaveData($data, $module_id, $post_id, $is_last_iteration): void
    {
        if (\get_post_type($post_id) === DealPostType::POST_TYPE)
            self::refresh((int) $post_id);
    }

    /**
     * A VAT basis change restates the pinned item's price, so the base follows
     * it - otherwise refresh() reads the switch as a price move and expires the
     * deal (or misses a real rise). Fires before content_egg_save_data.
     */
    public static function onVatFactorChanged($post_id, $module_id, $unique_id, $old_factor, $new_factor): void
    {
        $post_id = (int) $post_id;
        if (\get_post_type($post_id) !== DealPostType::POST_TYPE)
            return;

        $pin = self::getPin($post_id);
        if (!$pin || $pin['module_id'] !== (string) $module_id || $pin['unique_id'] !== (string) $unique_id)
            return;

        $base = (float) \get_post_meta($post_id, DealPostType::META_BASE_PRICE, true);
        if ($base > 0 && (float) $old_factor > 0)
            \update_post_meta($post_id, DealPostType::META_BASE_PRICE, round($base / (float) $old_factor * (float) $new_factor, 2));
    }

    public static function onSavePost($post_id): void
    {
        if (\wp_is_post_revision($post_id) || \wp_is_post_autosave($post_id))
            return;

        self::refresh((int) $post_id);
    }

    public static function itemsByModule(int $post_id): array
    {
        $out = array();
        foreach (array_keys((array) \get_post_meta($post_id)) as $key)
        {
            if (strpos($key, ContentManager::META_PREFIX_DATA) !== 0)
                continue;

            $module_id = substr($key, strlen(ContentManager::META_PREFIX_DATA));
            if ($data = ContentManager::getData($post_id, $module_id))
                $out[$module_id] = $data;
        }

        return $out;
    }

    public static function getPin(int $post_id): ?array
    {
        $pin = \get_post_meta($post_id, DealPostType::META_ITEM, true);

        if (!is_array($pin) || empty($pin['module_id']) || !isset($pin['unique_id']) || $pin['unique_id'] === '')
            return null;

        return array('module_id' => (string) $pin['module_id'], 'unique_id' => (string) $pin['unique_id']);
    }

    public static function pinnedItem(int $post_id): ?array
    {
        $pin = self::getPin($post_id);

        return $pin ? DealPin::find(self::itemsByModule($post_id), $pin) : null;
    }

    public static function refresh(int $post_id): void
    {
        $items = self::itemsByModule($post_id);
        $pin = self::getPin($post_id);

        if (!$pin)
        {
            $pin = DealPin::choose($items, (string) \get_post_meta($post_id, self::IMPORT_UID_META, true));

            // No priced product yet: nothing to show, and no status, so the
            // deal stays out of every listing until it has one.
            if (!$pin)
                return;

            self::savePin($post_id, $pin, DealPin::find($items, $pin));
        }

        $item = DealPin::find($items, $pin);

        // When the item is gone, the last known price stays on the card - it
        // renders greyed as expired rather than blank.
        if ($item)
        {
            $meta = DealMeta::compute($item);
            \update_post_meta($post_id, DealPostType::META_PRICE, $meta['price']);
            \update_post_meta($post_id, DealPostType::META_PRICE_OLD, $meta['price_old']);
            \update_post_meta($post_id, DealPostType::META_DISCOUNT, $meta['discount']);
            \update_post_meta($post_id, DealPostType::META_CURRENCY, $meta['currency']);
            \update_post_meta($post_id, DealPostType::META_IMG, $meta['img']);
            if ($meta['verified'])
                \update_post_meta($post_id, DealPostType::META_VERIFIED, $meta['verified']);

            // A pin made while the item had no price stored a zero base, which
            // would switch off price-rise expiry for good. The first real price
            // becomes the base; a set base is never moved here.
            if ((float) \get_post_meta($post_id, DealPostType::META_BASE_PRICE, true) <= 0 && $meta['price'] > 0)
                \update_post_meta($post_id, DealPostType::META_BASE_PRICE, (float) $meta['price']);

            self::assignStore($post_id, $meta);
            // Follows the item, so a changed product URL moves the URL key too.
            self::writeKeys($post_id, $pin['module_id'], $item);
        }

        // The end date first: an "offer" end follows the store's own deal end
        // time, and a store extension can revive a deal that ended.
        $signals = $item ? DealSignals::fromItem($pin['module_id'], $item) : null;
        $current_ends = (int) \get_post_meta($post_id, DealPostType::META_ENDS, true);
        $source = (string) \get_post_meta($post_id, DealPostType::META_ENDS_SOURCE, true);
        // Recorded on first sight, so a deal refreshed before the v100 upgrade ran
        // (or restored from the trash) keeps an end date a person set.
        if ($source === '')
        {
            $source = DealEnds::sourceFor('', $current_ends);
            \update_post_meta($post_id, DealPostType::META_ENDS_SOURCE, $source);
        }
        $ends = DealEnds::resolve(array(
            'source' => $source,
            'ends' => $current_ends,
            'signal_ends' => (int) ($signals['ends'] ?? 0),
            'status' => (string) \get_post_meta($post_id, DealPostType::META_STATUS, true),
            'reason' => (string) \get_post_meta($post_id, DealPostType::META_REASON, true),
            'now' => time(),
        ));
        if ($ends['ends'] > 0)
            \update_post_meta($post_id, DealPostType::META_ENDS, $ends['ends']);
        else
            \delete_post_meta($post_id, DealPostType::META_ENDS);

        $result = DealStatus::evaluate(array(
            'item' => $item,
            'base_price' => (float) \get_post_meta($post_id, DealPostType::META_BASE_PRICE, true),
            'ends' => $ends['ends'],
            'status' => $ends['status'],
            'reason' => $ends['reason'],
            'threshold' => DealsSettings::threshold(),
            'auto_reactivate' => DealsSettings::autoReactivate(),
            'now' => time(),
        ));

        \update_post_meta($post_id, DealPostType::META_STATUS, $result['status']);
        \update_post_meta($post_id, DealPostType::META_REASON, $result['reason']);

        self::ensureCounters($post_id);
    }

    public static function pin(int $post_id, string $module_id, string $unique_id): bool
    {
        $pin = array('module_id' => $module_id, 'unique_id' => $unique_id);
        $item = DealPin::find(self::itemsByModule($post_id), $pin);

        if (!$item)
            return false;

        self::savePin($post_id, $pin, $item);
        self::refresh($post_id);

        return true;
    }

    public static function markExpired(int $post_id): void
    {
        \update_post_meta($post_id, DealPostType::META_STATUS, DealStatus::EXPIRED);
        \update_post_meta($post_id, DealPostType::META_REASON, DealStatus::REASON_MANUAL);
    }

    /**
     * Resets the base to today's price, and drops an end date that has already
     * passed - otherwise the next cron run would expire the deal again.
     */
    public static function markActive(int $post_id): void
    {
        // A person reactivating overrules the store: otherwise the next
        // refresh() would write the store's past end back and expire it again.
        \update_post_meta($post_id, DealPostType::META_ENDS_SOURCE, DealEnds::SOURCE_MANUAL);

        if ($item = self::pinnedItem($post_id))
            \update_post_meta($post_id, DealPostType::META_BASE_PRICE, (float) ($item['price'] ?? 0));

        $ends = (int) \get_post_meta($post_id, DealPostType::META_ENDS, true);
        if ($ends > 0 && $ends <= time())
            \delete_post_meta($post_id, DealPostType::META_ENDS);

        \update_post_meta($post_id, DealPostType::META_STATUS, DealStatus::ACTIVE);
        \update_post_meta($post_id, DealPostType::META_REASON, '');

        self::refresh($post_id);
    }

    /**
     * A deal that expired because its end date passed comes back when the date
     * moves into the future or is cleared; refresh() then applies its normal
     * rules. Any other expiry reason is left to refresh().
     */
    public static function setEnds(int $post_id, int $timestamp, string $source = DealEnds::SOURCE_MANUAL): void
    {
        // A manual 0 means "no end", and store dates are ignored from then on.
        \update_post_meta($post_id, DealPostType::META_ENDS_SOURCE, $source);

        if ($timestamp > 0)
            \update_post_meta($post_id, DealPostType::META_ENDS, $timestamp);
        else
            \delete_post_meta($post_id, DealPostType::META_ENDS);

        if (
            (string) \get_post_meta($post_id, DealPostType::META_STATUS, true) === DealStatus::EXPIRED
            && (string) \get_post_meta($post_id, DealPostType::META_REASON, true) === DealStatus::REASON_ENDED
            && ($timestamp <= 0 || $timestamp > time())
        )
        {
            \update_post_meta($post_id, DealPostType::META_STATUS, DealStatus::ACTIVE);
            \update_post_meta($post_id, DealPostType::META_REASON, '');
        }

        self::refresh($post_id);
    }

    /** Back to following the store's own deal end time. */
    public static function followStoreEnds(int $post_id): void
    {
        \update_post_meta($post_id, DealPostType::META_ENDS_SOURCE, DealEnds::SOURCE_OFFER);
        self::refresh($post_id);
    }

    /**
     * A deal's own coupon code: shown next to its price and flagged for the
     * "With coupon" filter. An empty code removes it. It shares the deal's end
     * date rather than carrying one of its own.
     */
    public static function setCoupon(int $post_id, string $code, string $note): void
    {
        $code = trim(\sanitize_text_field($code));
        $note = trim(\sanitize_text_field($note));

        if (function_exists('mb_substr'))
        {
            $code = mb_substr($code, 0, 64);
            $note = mb_substr($note, 0, 200);
        }

        if ($code === '')
        {
            \delete_post_meta($post_id, DealPostType::META_COUPON_CODE);
            \delete_post_meta($post_id, DealPostType::META_COUPON_NOTE);
            \delete_post_meta($post_id, DealPostType::META_HAS_COUPON);
            return;
        }

        \update_post_meta($post_id, DealPostType::META_COUPON_CODE, $code);
        \update_post_meta($post_id, DealPostType::META_HAS_COUPON, 1);

        if ($note !== '')
            \update_post_meta($post_id, DealPostType::META_COUPON_NOTE, $note);
        else
            \delete_post_meta($post_id, DealPostType::META_COUPON_NOTE);
    }

    public static function schema(int $post_id): ?array
    {
        if (\post_password_required($post_id))
            return null;

        return DealSchema::build(
            self::pinnedItem($post_id),
            (int) \get_post_meta($post_id, DealPostType::META_ENDS, true),
            (string) \get_permalink($post_id),
            \sanitize_text_field(\get_the_title($post_id))
        );
    }

    /**
     * The pinned offer's keys (DealKey) for duplicate lookups. An empty key is
     * deleted rather than stored, so it can never match another deal.
     */
    public static function writeKeys(int $post_id, string $module_id, ?array $item): void
    {
        $keys = $item ? DealKey::fromItem($module_id, $item) : array('key' => '', 'url_key' => '', 'gtin' => '');
        $map = array(
            DealPostType::META_KEY => $keys['key'],
            DealPostType::META_URL_KEY => $keys['url_key'],
            DealPostType::META_GTIN => $keys['gtin'],
        );

        foreach ($map as $meta_key => $value)
        {
            if ($value !== '')
                \update_post_meta($post_id, $meta_key, $value);
            else
                \delete_post_meta($post_id, $meta_key);
        }
    }

    private static function savePin(int $post_id, array $pin, ?array $item): void
    {
        \update_post_meta($post_id, DealPostType::META_ITEM, $pin);
        self::writeKeys($post_id, $pin['module_id'], $item);
        \update_post_meta($post_id, DealPostType::META_BASE_PRICE, $item ? (float) ($item['price'] ?? 0) : 0.0);
        \update_post_meta($post_id, DealPostType::META_STATUS, DealStatus::ACTIVE);
        \update_post_meta($post_id, DealPostType::META_REASON, '');
    }

    /**
     * Sorting by a meta key drops posts that lack it, so every deal carries the
     * keys the archive sorts on from the start.
     */
    private static function ensureCounters(int $post_id): void
    {
        foreach (array(DealPostType::META_VOTES_UP, DealPostType::META_VOTES_DOWN, DealPostType::META_SCORE, DealPostType::META_DISCOUNT) as $key)
        {
            if (!\metadata_exists('post', $post_id, $key))
                \add_post_meta($post_id, $key, 0, true);
        }
    }

    /**
     * An existing store term is never renamed, so an editor's rename sticks.
     */
    private static function assignStore(int $post_id, array $meta): void
    {
        $domain = ShopStore::normalizeDomain($meta['domain']);
        if ($domain === '')
            return;

        $slug = \sanitize_title($domain);
        $term = \get_term_by('slug', $slug, DealPostType::TAX_STORE);

        if ($term)
        {
            $term_id = (int) $term->term_id;
        }
        else
        {
            $created = \wp_insert_term($meta['merchant'] !== '' ? $meta['merchant'] : $domain, DealPostType::TAX_STORE, array('slug' => $slug));
            if (\is_wp_error($created))
                return;

            $term_id = (int) $created['term_id'];
            \update_term_meta($term_id, DealPostType::TERM_META_DOMAIN, $domain);
        }

        if ($meta['logo'] !== '' && !\get_term_meta($term_id, DealPostType::TERM_META_LOGO, true))
            \update_term_meta($term_id, DealPostType::TERM_META_LOGO, \esc_url_raw($meta['logo']));

        \wp_set_object_terms($post_id, array($term_id), DealPostType::TAX_STORE, false);
    }
}
