<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

/**
 * ShopCoupon class file
 *
 * Every decision about a single coupon - what it is, whether it applies, and
 * where it ranks - as pure functions over the ShopStore::sanitizeCoupon() shape.
 *
 * Pure on purpose: this is the whole risky surface of the feature (dates across
 * timezones, category intersection, ranking across two sources) and it is
 * testable without WordPress.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ShopCoupon
{
    const TYPE_CODE = 'code';
    const TYPE_DEAL = 'deal';

    const TYPES_CODES = 'codes';
    const TYPES_ALL = 'codes_and_deals';

    /**
     * Derived, never stored - the same rule Cashback Tracker uses in
     * CouponImport::typeFor(), so a sourced coupon classifies identically with
     * no translation step. Pure.
     */
    public static function typeOf(array $c)
    {
        return isset($c['code']) && trim((string) $c['code']) !== ''
            ? self::TYPE_CODE
            : self::TYPE_DEAL;
    }

    /**
     * The last second of the day $ts falls in, in a timezone $offset seconds
     * from UTC.
     *
     * An operator entering "ends Aug 31" means the end of Aug 31 where they
     * live. Storing the bare midnight would retire the coupon a whole day early
     * for any site east of UTC.
     *
     * intdiv() rather than %: a negative local timestamp (a pre-1970 date, or a
     * far-western offset on the epoch boundary) makes % return a negative
     * remainder and floor the wrong way. Pure.
     */
    public static function endOfDay($ts, $offset)
    {
        $ts = (int) $ts;
        $offset = (int) $offset;

        $local = $ts + $offset;
        $days = (int) floor($local / DAY_IN_SECONDS);
        $local_midnight = $days * DAY_IN_SECONDS;

        return $local_midnight + DAY_IN_SECONDS - 1 - $offset;
    }

    public static function isLive(array $c, $now)
    {
        if (empty($c['enabled']))
            return false;

        $now = (int) $now;

        if (!empty($c['start']) && (int) $c['start'] > $now)
            return false;

        if (!empty($c['end']) && (int) $c['end'] < $now)
            return false;

        return true;
    }

    /**
     * Why a coupon does or does not render, as one word for the admin list.
     *
     * hidden_deal is the one that matters: without naming it, a deal suppressed
     * by the codes-only setting is indistinguishable from a bug, and that is the
     * support question this feature would otherwise generate. Pure.
     */
    public static function stateOf(array $c, $now, $types)
    {
        if (empty($c['enabled']))
            return 'disabled';

        $now = (int) $now;

        if (!empty($c['end']) && (int) $c['end'] < $now)
            return 'expired';

        if (!empty($c['start']) && (int) $c['start'] > $now)
            return 'scheduled';

        // A bound deal is exempt from codes-only (see ShopCoupons::pick), so
        // reporting it as hidden here would be a lie the operator acts on.
        if ($types !== self::TYPES_ALL && self::typeOf($c) === self::TYPE_DEAL && !self::isBound($c))
            return 'hidden_deal';

        // Last of the states, because the others explain an absence everywhere
        // while this one explains it only on products the coupon is not bound
        // to. Named anyway: a live coupon that appears on almost no page is
        // otherwise indistinguishable from a bug.
        if (self::isBound($c))
            return 'bound';

        return 'live';
    }

    /**
     * Empty terms means everywhere. $post_terms is expected to already carry the
     * post's own terms AND their ancestors - see ShopCoupons::postTerms(). Pure.
     */
    public static function matchesTerms(array $c, array $post_terms)
    {
        if (empty($c['terms']))
            return true;

        if (!$post_terms)
            return false;

        foreach ($c['terms'] as $t)
        {
            if (in_array((int) $t, $post_terms, true))
                return true;
        }

        return false;
    }

    public static function isTargeted(array $c)
    {
        return !empty($c['terms']);
    }

    public static function isNative(array $c)
    {
        return empty($c['source']);
    }

    public static function isBound(array $c)
    {
        return !empty($c['products']);
    }

    /**
     * Whether this coupon may appear beside $item.
     *
     * An unbound coupon matches everything, so the shop-wide behaviour is
     * unchanged. Matching is a comparison against the item the block already
     * holds - no resolver, no query - which is what makes binding cheap enough
     * to evaluate per row.
     *
     * Compared as strings: a feed module's unique_id arrives as an int from
     * some stores and as a numeric string from others. Pure.
     */
    public static function matchesProduct(array $c, array $item)
    {
        if (empty($c['products']))
            return true;

        $module_id = isset($item['module_id']) ? (string) $item['module_id'] : '';
        $unique_id = isset($item['unique_id']) ? (string) $item['unique_id'] : '';

        if ($module_id === '' || $unique_id === '')
            return false;

        foreach ($c['products'] as $p)
        {
            if (!is_array($p) || !isset($p['module_id']) || !isset($p['unique_id']))
                continue;

            if ((string) $p['module_id'] === $module_id && (string) $p['unique_id'] === $unique_id)
                return true;
        }

        return false;
    }

    /**
     * Bound to THIS item first, then native before sourced, targeted before
     * sitewide, then the order the operator put them in.
     *
     * The specific offer displaces the generic one, which is what makes the
     * default limit of one coupon per shop show a product's bonus rather than
     * the shop's standing voucher.
     *
     * No discount heuristic: 'discount' is free text across sources ('10%',
     * '10 EUR', 'up to 50%') and cannot be compared. The operator has a drag
     * handle, which beats guessing.
     *
     * The incoming index is carried as the final key rather than relying on a
     * stable usort - sorting was only made stable in PHP 8.0 and this build
     * still runs on 7.4. Pure.
     */
    public static function sort(array $coupons, array $post_terms, array $item = array())
    {
        $keyed = array();
        foreach (array_values($coupons) as $i => $c)
        {
            $keyed[] = array(
                'rank' => array(
                    $item && self::isBound($c) && self::matchesProduct($c, $item) ? 0 : 1,
                    self::isNative($c) ? 0 : 1,
                    self::isTargeted($c) && self::matchesTerms($c, $post_terms) ? 0 : 1,
                    $i,
                ),
                'c' => $c,
            );
        }

        usort($keyed, function ($a, $b)
        {
            for ($k = 0; $k < 4; $k++)
            {
                if ($a['rank'][$k] !== $b['rank'][$k])
                    return $a['rank'][$k] < $b['rank'][$k] ? -1 : 1;
            }

            return 0;
        });

        $out = array();
        foreach ($keyed as $row)
            $out[] = $row['c'];

        return $out;
    }

    /**
     * One code wins once, matched case-insensitively - the caller has already
     * scoped this to a single shop.
     *
     * Keeps the FIRST occurrence, so callers must sort() before dedupe() or the
     * native-wins rule does not hold.
     *
     * Deals carry no code and are never collapsed: two different deals would
     * otherwise erase each other. Pure.
     */
    public static function dedupe(array $coupons)
    {
        $seen = array();
        $out = array();

        foreach ($coupons as $c)
        {
            if (self::typeOf($c) === self::TYPE_DEAL)
            {
                $out[] = $c;
                continue;
            }

            $key = strtoupper(trim((string) $c['code']));

            if (isset($seen[$key]))
                continue;

            $seen[$key] = true;
            $out[] = $c;
        }

        return $out;
    }
}
