<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

use ContentEgg\application\helpers\TextHelper;

/**
 * ShopStore class file
 *
 * Every shop the operator has configured, keyed by normalized domain, in one
 * non-autoloaded option.
 *
 * Not a table, deliberately: actionExportPluginSettings() exports options and
 * nothing else, so a table would mean every operator who moves a site silently
 * loses their shop configuration on import - and does not find out until a
 * reader fails to see a coupon.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ShopStore
{
    const OPTION = 'content_egg_shops';
    const OPTION_LEGACY = 'content_egg_shops_legacy';
    const OPTION_MIGRATED = 'content_egg_shops_migrated';

    private static $cache = null;

    /**
     * A pasted URL and a typed domain must land on the same key.
     *
     * getHostName() does the whole job but needs a real URL: parse_url() on a
     * bare 'thalia.de' finds no host and returns ''. formatMerchantFields()
     * applied it only when it returned something, which is why a typed
     * 'www.thalia.de' has been stored verbatim - and matched nothing - for
     * years. Pure.
     */
    public static function normalizeDomain($input)
    {
        $d = strtolower(trim((string) $input));

        if ($d === '')
            return '';

        if ($host = TextHelper::getHostName($d))
            return $host;

        $d = preg_replace('/^www\./', '', $d);

        // The same reduction getHostName() applies, so both input forms agree.
        $d = TextHelper::getDomainWithoutSubdomain($d);

        return TextHelper::isValidDomainName($d) ? $d : '';
    }

    public static function newCouponId()
    {
        return substr(md5(uniqid('cegg', true)), 0, 12);
    }

    /**
     * A coupon with every key present, so no consumer has to isset()-guard.
     * Pure.
     */
    public static function sanitizeCoupon(array $c)
    {
        $terms = array();
        if (!empty($c['terms']) && is_array($c['terms']))
        {
            foreach ($c['terms'] as $t)
            {
                $t = (int) $t;
                if ($t > 0 && !in_array($t, $terms, true))
                    $terms[] = $t;
            }
        }

        // References to individual products this coupon is limited to. Empty
        // means every product of the shop, mirroring empty $terms meaning every
        // category. Sanitized on READ, so coupons stored before this key
        // existed acquire it without a migration.
        $products = array();
        if (!empty($c['products']) && is_array($c['products']))
        {
            foreach ($c['products'] as $p)
            {
                if (!is_array($p))
                    continue;

                $module_id = isset($p['module_id']) ? trim((string) $p['module_id']) : '';
                $unique_id = isset($p['unique_id']) ? trim((string) $p['unique_id']) : '';

                if ($module_id === '' || $unique_id === '')
                    continue;

                $products[] = array(
                    'module_id' => $module_id,
                    'unique_id' => $unique_id,
                    'label' => isset($p['label']) ? trim((string) $p['label']) : '',
                );
            }
        }

        return array(
            'id'       => !empty($c['id']) ? (string) $c['id'] : self::newCouponId(),
            'code'     => isset($c['code']) ? trim((string) $c['code']) : '',
            'title'    => isset($c['title']) ? trim((string) $c['title']) : '',
            // Longer text under the title on a coupon card. Imported coupons
            // usually carry one; a hand-typed coupon rarely needs it.
            'description' => isset($c['description']) ? trim((string) $c['description']) : '',
            'discount' => isset($c['discount']) ? trim((string) $c['discount']) : '',
            'link'     => isset($c['link']) ? trim((string) $c['link']) : '',
            'image'    => isset($c['image']) ? trim((string) $c['image']) : '',
            'start'    => isset($c['start']) ? (int) $c['start'] : 0,
            'end'      => isset($c['end']) ? (int) $c['end'] : 0,
            'terms'    => $terms,
            'products' => $products,
            'enabled'  => isset($c['enabled']) ? (bool) $c['enabled'] : true,
        );
    }

    /**
     * A shop row, or array() when its domain cannot be normalized.
     *
     * Returning the empty array rather than a row with an empty domain matters:
     * an empty-string key would collide every unusable shop onto one entry.
     * Pure.
     */
    public static function sanitizeShop(array $s)
    {
        $domain = self::normalizeDomain(isset($s['domain']) ? $s['domain'] : '');

        if ($domain === '')
            return array();

        $coupons = array();
        if (!empty($s['coupons']) && is_array($s['coupons']))
        {
            foreach ($s['coupons'] as $c)
            {
                if (!is_array($c))
                    continue;
                $coupons[] = self::sanitizeCoupon($c);
            }
        }

        return array(
            'domain'       => $domain,
            'name'         => isset($s['name']) ? trim((string) $s['name']) : '',
            'logo'         => isset($s['logo']) ? trim((string) $s['logo']) : '',
            'info'         => isset($s['info']) ? (string) $s['info'] : '',
            'coupons_html' => isset($s['coupons_html']) ? (string) $s['coupons_html'] : '',
            'coupons'      => $coupons,
        );
    }

    public static function all()
    {
        if (self::$cache !== null)
            return self::$cache;

        $stored = \get_option(self::OPTION, array());

        if (!is_array($stored))
            $stored = array();

        // Sanitized on read, not just on write. A row stored before a field
        // existed does not grow the key by itself, so every consumer would
        // otherwise have to isset()-guard each one - and the first that forgets
        // prints a warning into the page. Memoized, so this runs once.
        $all = array();
        foreach ($stored as $shop)
        {
            if (!is_array($shop))
                continue;

            if ($shop = self::sanitizeShop($shop))
                $all[$shop['domain']] = $shop;
        }

        self::$cache = $all;

        return self::$cache;
    }

    public static function get($domain)
    {
        $domain = self::normalizeDomain($domain);

        if ($domain === '')
            return null;

        $all = self::all();

        return isset($all[$domain]) ? $all[$domain] : null;
    }

    /**
     * Whether saving $domain would land on top of a DIFFERENT shop.
     *
     * The store is a domain-keyed array, so save() is an upsert - which is what
     * editing a shop in place needs, and exactly what makes an accidental
     * duplicate destructive: typing an existing domain on the Add form, or
     * renaming one shop onto another, silently replaces the whole record
     * including its coupons. The caller has to ask this first. Pure.
     */
    public static function collides(array $all, $domain, $previous_domain = '')
    {
        $domain = self::normalizeDomain($domain);

        if ($domain === '')
            return false;

        // Saving a shop under the domain it already has is the normal edit.
        if ($domain === self::normalizeDomain($previous_domain))
            return false;

        return isset($all[$domain]);
    }

    /**
     * Renaming a domain moves the entry rather than writing a second one.
     *
     * A key ABSENT from $shop keeps whatever the stored record holds; only a
     * key that is present overwrites. Without that this is a full replace, and
     * any caller that builds a partial array - adding a coupon, flipping a name
     * - silently blanks the shop's logo, description and legacy coupon HTML.
     * That is not hypothetical: it happened twice during development, and the
     * only reason it was recoverable was the migration archive.
     *
     * The admin form is unaffected either way, because prepareSubmission()
     * always supplies every field - including the empty string that clears one.
     */
    public static function save(array $shop, $previous_domain = '')
    {
        $domain = self::normalizeDomain(isset($shop['domain']) ? $shop['domain'] : '');

        if ($domain === '')
            return '';

        $previous_domain = self::normalizeDomain($previous_domain);

        // On a rename the base is the record being moved, not whatever happens
        // to sit under the new key.
        $base = $previous_domain !== '' ? self::get($previous_domain) : self::get($domain);

        if ($base)
            $shop = array_merge($base, $shop);

        if (!$shop = self::sanitizeShop($shop))
            return '';

        $all = self::all();

        if ($previous_domain !== '' && $previous_domain !== $shop['domain'])
            unset($all[$previous_domain]);

        $all[$shop['domain']] = $shop;

        self::replaceAll($all);

        return $shop['domain'];
    }

    public static function delete($domain)
    {
        $domain = self::normalizeDomain($domain);
        $all = self::all();

        if (!isset($all[$domain]))
            return;

        unset($all[$domain]);
        self::replaceAll($all);
    }

    public static function replaceAll(array $all)
    {
        ksort($all);

        // autoload 'no': this carries shop_info HTML and is needed only where a
        // block renders, not on every request the way merchants[] was.
        \update_option(self::OPTION, $all, 'no');

        self::$cache = $all;
    }

    public static function resetCache()
    {
        self::$cache = null;
    }
}
