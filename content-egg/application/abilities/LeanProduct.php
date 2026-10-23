<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\OfferCondition;
use ContentEgg\application\helpers\Gtin;

/**
 * LeanProduct class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Maps Content Egg product items to a compact, LLM-friendly envelope
 * (design spec §5: lean envelopes by default, `fields=full` opts out).
 */
final class LeanProduct
{
    const EXCERPT_LENGTH = 300;

    // fields="match": feature rows kept per item (identity, not a spec sheet).
    const MATCH_FEATURES_LIMIT = 15;

    // Billiger.de keeps the manufacturer part number in `sku`.
    const MPN_IN_SKU_BASE_ID = 'Billigerde';

    // Feature rows that hold the part number or the model when the module has
    // no field for it (Amazon: "Part Number", "Model"), by their name in
    // lowercase letters only.
    const MPN_FEATURES = array('partnumber', 'manufacturerpartnumber', 'mpn', 'herstellernummer', 'herstellerartikelnummer');
    const MODEL_FEATURES = array('model', 'modelnumber', 'itemmodelnumber', 'modell', 'modellnummer');

    // A feature value longer than this is a description, not a code.
    const MAX_CODE_LENGTH = 64;

    /**
     * @param object|array $item
     */
    public static function map($item): array
    {
        $a = is_object($item) ? get_object_vars($item) : (array) $item;

        $excerpt = (string) ($a['_descriptionText'] ?? '');
        $truncated = false;

        if ($excerpt === '' && !empty($a['description']))
        {
            list($excerpt, $truncated) = self::excerpt((string) $a['description']);
        }

        return array(
            'unique_id' => (string) ($a['unique_id'] ?? ''),
            'title' => (string) ($a['title'] ?? ''),
            // Emit null (not 0) when there is no usable price: some modules
            // return 0 / empty for in-stock items whose price is unavailable,
            // and a literal 0 reads as "free" to an agent.
            'price' => self::usablePrice($a),
            'price_old' => self::usablePriceOld($a),
            'currency' => (string) ($a['currencyCode'] ?? ''),
            'merchant' => (string) ($a['merchant'] ?? ''),
            'domain' => (string) ($a['domain'] ?? ''),
            'img' => (string) ($a['img'] ?? ''),
            'rating' => isset($a['rating']) && $a['rating'] !== '' ? (float) $a['rating'] : null,
            'description_excerpt' => $excerpt,
            'description_truncated' => $truncated,
        );
    }

    public static function mapList(array $items): array
    {
        return array_values(array_map(array(self::class, 'map'), $items));
    }

    /**
     * The fields="match" profile: what an agent needs to decide whether two
     * offers are the same product (GTINs, brand, MPN, model, condition) and
     * how they differ commercially (price, stock, shipping), without the
     * description and images of the full item.
     *
     * @param object|array $item
     * @param string $module_id used when the item does not carry its own module_id
     */
    public static function matchItem($item, string $module_id = ''): array
    {
        $a = self::toArray($item);

        // Raw module results hold ExtraData objects; Gtin and OfferCondition read arrays.
        $extra = self::toArray($a['extra'] ?? null);
        if (isset($extra['data']))
        {
            $extra['data'] = self::toArray($extra['data']);
        }
        $a['extra'] = $extra;

        $own_module_id = self::text($a['module_id'] ?? null);
        $module_id = $own_module_id !== '' ? $own_module_id : $module_id;

        $sku = self::text($a['sku'] ?? null);
        $mpn = self::firstText(array($extra['mpn'] ?? null, $extra['Mpn'] ?? null, $a['mpn'] ?? null));
        if ($mpn === '' && ModuleCapabilities::baseId($module_id) === self::MPN_IN_SKU_BASE_ID)
        {
            $mpn = $sku;
        }
        if ($mpn === '')
        {
            $mpn = self::featureCode($a['features'] ?? null, self::MPN_FEATURES);
        }
        $model = self::text($extra['modelNumber'] ?? null);
        if ($model === '')
        {
            $model = self::featureCode($a['features'] ?? null, self::MODEL_FEATURES);
        }

        return array(
            'unique_id' => self::text($a['unique_id'] ?? null),
            'module_id' => $module_id,
            'title' => self::text($a['title'] ?? null),
            'gtins' => Gtin::fromItem($a),
            'mpn' => $mpn,
            'brand' => self::text($a['manufacturer'] ?? null),
            'sku' => $sku,
            'model' => $model,
            // The saved canonical value when there is one; raw search results carry none.
            'condition' => OfferCondition::resolve($a),
            'stock_status' => (int) ($a['stock_status'] ?? 0),
            'price' => self::usablePrice($a),
            'price_old' => self::usablePriceOld($a),
            'currency' => self::text($a['currencyCode'] ?? null),
            // 0 is free shipping; only a missing or non-numeric cost is null.
            'shipping_cost' => isset($a['shipping_cost']) && is_numeric($a['shipping_cost']) ? (float) $a['shipping_cost'] : null,
            'merchant' => self::text($a['merchant'] ?? null),
            // The marketplace seller (eBay), whatever the module shows as the merchant.
            'seller' => self::text(self::toArray($extra['seller'] ?? null)['username'] ?? null),
            'domain' => self::text($a['domain'] ?? null),
            'orig_url' => self::text($a['orig_url'] ?? null),
            'img' => self::text($a['img'] ?? null),
            'category' => self::text($a['category'] ?? null),
            'features' => self::matchFeatures($a['features'] ?? null),
            'group' => self::text($a['group'] ?? null),
            'last_update' => (int) ($a['last_update'] ?? 0),
        );
    }

    public static function matchList(array $items, string $module_id = ''): array
    {
        return array_values(array_map(
            static function ($item) use ($module_id)
            {
                return self::matchItem($item, $module_id);
            },
            $items
        ));
    }

    /**
     * Raw item fields minus internal underscore-prefixed keys.
     *
     * @param object|array $item
     */
    public static function fullItem($item): array
    {
        $a = is_object($item) ? get_object_vars($item) : (array) $item;

        return array_filter($a, static function ($key)
        {
            return !is_string($key) || strpos($key, '_') !== 0;
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Price as a float, or null when there is no usable one: some modules
     * return 0 / empty for in-stock items whose price is unavailable, and a
     * literal 0 reads as "free" to an agent.
     */
    private static function usablePrice(array $a): ?float
    {
        return isset($a['price']) && $a['price'] !== '' && (float) $a['price'] > 0 ? (float) $a['price'] : null;
    }

    private static function usablePriceOld(array $a): ?float
    {
        return !empty($a['priceOld']) ? (float) $a['priceOld'] : null;
    }

    /**
     * The first MATCH_FEATURES_LIMIT usable feature rows as {name, value}.
     *
     * @param mixed $features
     */
    private static function matchFeatures($features): array
    {
        $rows = array();
        if (!is_array($features))
        {
            return $rows;
        }

        foreach ($features as $feature)
        {
            if (!is_array($feature) && !is_object($feature))
            {
                continue;
            }

            $row = self::toArray($feature);
            $name = self::text($row['name'] ?? null);
            $value = self::text($row['value'] ?? null);
            if ($name === '' && $value === '')
            {
                continue;
            }

            $rows[] = array('name' => $name, 'value' => $value);
            if (count($rows) >= self::MATCH_FEATURES_LIMIT)
            {
                break;
            }
        }

        return $rows;
    }

    /**
     * The value of the first feature row named one of $names (see
     * MPN_FEATURES), '' when there is none or it is not a code ("N/A").
     *
     * @param mixed $features
     */
    private static function featureCode($features, array $names): string
    {
        if (!is_array($features))
        {
            return '';
        }

        foreach ($features as $feature)
        {
            $row = self::toArray($feature);
            $name = (string) preg_replace('/[^a-z]/', '', strtolower(self::text($row['name'] ?? null)));
            if (!in_array($name, $names, true))
            {
                continue;
            }
            $value = self::text($row['value'] ?? null);
            if ($value !== '' && strlen($value) <= self::MAX_CODE_LENGTH
                && !preg_match('/^(?:n\/?a|none|unknown|-+|does not apply|not applicable)$/i', $value))
            {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param mixed $value
     */
    private static function toArray($value): array
    {
        if (is_object($value))
        {
            return get_object_vars($value);
        }

        return is_array($value) ? $value : array();
    }

    /**
     * A trimmed string for a scalar, '' for anything else (null, array, object).
     *
     * @param mixed $value
     */
    private static function text($value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * The first non-empty text of the candidates, or ''.
     */
    private static function firstText(array $candidates): string
    {
        foreach ($candidates as $candidate)
        {
            $text = self::text($candidate);
            if ($text !== '')
            {
                return $text;
            }
        }

        return '';
    }

    /**
     * @return array [string $text, bool $truncated]
     */
    private static function excerpt(string $html): array
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($html)));

        if (mb_strlen($text) > self::EXCERPT_LENGTH)
        {
            return array(mb_substr($text, 0, self::EXCERPT_LENGTH) . "\u{2026}", true);
        }

        return array($text, false);
    }
}
