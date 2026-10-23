<?php

namespace ContentEgg\application\components;

use ContentEgg\application\admin\GeneralConfig;

defined('\ABSPATH') || exit;

/**
 * Brings every stored price onto one VAT basis (the site's "Normalize shop
 * prices" setting) using the basis and rate of the item's shop.
 *
 * Everything except the three small WP-reading wrappers at the bottom is pure,
 * so the rules are unit-tested without WordPress (tests/vat/).
 *
 * The shop's own values live in {field}_raw next to the normalized field, with
 * the factor applied in vat_factor. Re-saves of stored data are recognized by
 * comparing the incoming value with round(raw x factor, 2), so they never
 * compound. Known limit: a genuine new shop price exactly equal (to the cent)
 * to the previous normalized value is read as a re-save.
 */
class VatNormalizer
{
    const FIELDS = array('price', 'priceOld', 'shipping_cost');

    public static function factorFor($target, $shop)
    {
        if (!$shop || ($target !== 'excl' && $target !== 'incl'))
            return 1.0;

        $basis = isset($shop['vat_included']) ? $shop['vat_included'] : '';
        $rate = isset($shop['vat_rate']) ? (float) $shop['vat_rate'] : 0.0;

        if ($rate <= 0)
            return 1.0;

        if ($target === 'excl' && $basis === 'yes')
            return 1 / (1 + $rate / 100);

        if ($target === 'incl' && $basis === 'no')
            return 1 + $rate / 100;

        return 1.0;
    }

    public static function normalizeItem(array $item, $factor, $stored = null)
    {
        $factor = (float) $factor;
        $stored = is_array($stored) ? $stored : null;

        if (self::isOne($factor) && !self::hasRaw($item) && !($stored && self::hasRaw($stored)))
            return $item;

        $touched = false;
        foreach (self::FIELDS as $field)
        {
            if (!array_key_exists($field, $item) || $item[$field] === null || $item[$field] === '')
                continue;

            // Zero values are left as they are (no raw field, value unchanged)
            if ((float) $item[$field] == 0)
                continue;

            $raw = self::resolveRaw($item, $stored, $field);
            $item[$field . '_raw'] = $raw;
            $item[$field] = self::fmt(round((float) $raw * $factor, 2));
            $touched = true;
        }

        if ($touched)
            $item['vat_factor'] = $factor;

        return $item;
    }

    public static function absorbManualEdit(array $row)
    {
        if (!isset($row['vat_factor']))
            return $row;

        $factor = (float) $row['vat_factor'];
        if ($factor <= 0)
            return $row;

        foreach (self::FIELDS as $field)
        {
            $raw_key = $field . '_raw';
            if (!isset($row[$field]) || $row[$field] === '')
                continue;

            // Zero values are left as they are
            if ((float) $row[$field] == 0)
                continue;

            // If raw field exists, check if the field matches the calculated value
            if (isset($row[$raw_key]))
            {
                if (self::matches($row[$field], (float) $row[$raw_key] * $factor))
                    continue;
            }

            // Either raw is missing or the field doesn't match; update/create raw field
            $row[$raw_key] = self::fmt(round((float) $row[$field] / $factor, 4));
        }

        return $row;
    }

    public static function basisOnlyChanges(array $old_data, array $new_data)
    {
        $keys = array();
        foreach ($new_data as $key => $new)
        {
            if (!is_array($new) || !isset($old_data[$key]) || !is_array($old_data[$key]))
                continue;

            $old = $old_data[$key];
            $old_raw = isset($old['price_raw']) ? $old['price_raw'] : (isset($old['price']) ? $old['price'] : null);
            $new_raw = isset($new['price_raw']) ? $new['price_raw'] : (isset($new['price']) ? $new['price'] : null);

            if ($old_raw === null || $new_raw === null || $old_raw === '' || $new_raw === '')
                continue;

            $old_f = isset($old['vat_factor']) ? (float) $old['vat_factor'] : 1.0;
            $new_f = isset($new['vat_factor']) ? (float) $new['vat_factor'] : 1.0;

            if (self::matches($new_raw, (float) $old_raw) && abs($old_f - $new_f) > 0.000001)
                $keys[] = $key;
        }

        return $keys;
    }

    /**
     * Keys of stored items whose VAT factor changed in this save, whether or
     * not the shop price moved too. Their history and alert targets must be
     * restated in the new basis before anything compares prices with them.
     *
     * A new item without vat_factor carries no price to normalize (e.g. out of
     * stock with an empty price): it states no basis, so it is no change -
     * otherwise history, alerts and a deal's base would round-trip to the shop
     * basis and back, drifting by cents. A stored item without one is in the
     * shop basis (1.0), so switching the feature on is still detected.
     */
    public static function factorChanges(array $old_data, array $new_data): array
    {
        $keys = array();
        foreach ($new_data as $key => $new)
        {
            if (!is_array($new) || !isset($old_data[$key]) || !is_array($old_data[$key]))
                continue;

            if (!isset($new['vat_factor']) || $new['vat_factor'] === '')
                continue;

            if (abs(self::storedFactor($old_data[$key]) - self::storedFactor($new)) > 0.000001)
                $keys[] = $key;
        }

        return $keys;
    }

    /** The factor an item's prices are stored in; 1 when never normalized. */
    public static function storedFactor(array $item)
    {
        return isset($item['vat_factor']) && $item['vat_factor'] !== '' ? (float) $item['vat_factor'] : 1.0;
    }

    public static function shippingFor(array $item, array $ranges)
    {
        if (isset($item['shipping_cost']) && $item['shipping_cost'] !== '' && $item['shipping_cost'] !== null)
            return null;

        $shop_price = isset($item['price_raw']) && $item['price_raw'] !== '' ? $item['price_raw'] : (isset($item['price']) ? $item['price'] : '');
        if ($shop_price === '' || $shop_price === null || !$ranges || (float) $shop_price <= 0)
            return null;

        $cost = null;
        foreach ($ranges as $row)
        {
            if ((float) $row['from'] <= (float) $shop_price)
                $cost = (float) $row['cost'];
        }

        if ($cost === null)
            return null;

        $factor = isset($item['vat_factor']) ? (float) $item['vat_factor'] : 1.0;

        return round($cost * $factor, 2);
    }

    public static function signature($target, $shop)
    {
        $basis = $shop && isset($shop['vat_included']) ? $shop['vat_included'] : '';
        $rate = $shop && isset($shop['vat_rate']) ? (float) $shop['vat_rate'] : 0.0;

        return md5($target . '|' . $basis . '|' . $rate);
    }

    public static function itemsForDomain(array $data, $domain)
    {
        $keys = array();
        foreach ($data as $key => $item)
        {
            if (is_array($item) && !empty($item['domain']) && ShopStore::normalizeDomain($item['domain']) === $domain)
                $keys[] = $key;
        }

        return $keys;
    }

    // --- WordPress-reading wrappers ------------------------------------

    public static function siteTarget()
    {
        $t = GeneralConfig::getInstance()->option('vat_normalize');

        return ($t === 'excl' || $t === 'incl') ? $t : '';
    }

    public static function shopForItem(array $item)
    {
        if (empty($item['domain']))
            return null;

        return ShopStore::get($item['domain']);
    }

    public static function factorForItem(array $item)
    {
        return self::factorFor(self::siteTarget(), self::shopForItem($item));
    }

    // --- helpers ---------------------------------------------------------

    private static function resolveRaw(array $item, $stored, $field)
    {
        $raw_key = $field . '_raw';
        $incoming = $item[$field];

        if (isset($item[$raw_key]) && $item[$raw_key] !== '')
        {
            $f = isset($item['vat_factor']) ? (float) $item['vat_factor'] : 1.0;
            if (self::matches($incoming, (float) $item[$raw_key] * $f))
                return $item[$raw_key];
        }

        if ($stored && isset($stored[$raw_key]) && $stored[$raw_key] !== '')
        {
            $f = isset($stored['vat_factor']) ? (float) $stored['vat_factor'] : 1.0;
            if (self::matches($incoming, (float) $stored[$raw_key] * $f))
                return $stored[$raw_key];
        }

        return $incoming;
    }

    private static function matches($value, $expected)
    {
        return abs((float) $value - round((float) $expected, 2)) < 0.005;
    }

    private static function hasRaw(array $item)
    {
        foreach (self::FIELDS as $field)
        {
            if (isset($item[$field . '_raw']))
                return true;
        }

        return false;
    }

    private static function isOne($f)
    {
        return abs($f - 1.0) < 0.000001;
    }

    private static function fmt($v)
    {
        return (string) (0 + $v);
    }
}
