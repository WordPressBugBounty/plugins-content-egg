<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

/**
 * DealMeta class file
 *
 * The scalar facts a deal copies from its pinned Content Egg item, so a query
 * can sort and filter on them and a card can render without unserializing
 * product data. Pure.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealMeta
{
    public static function compute(?array $item): array
    {
        if (!$item)
        {
            return array(
                'price' => 0.0, 'price_old' => 0.0, 'discount' => 0, 'currency' => '', 'verified' => 0,
                'domain' => '', 'merchant' => '', 'img' => '', 'logo' => '',
            );
        }

        $price = (float) ($item['price'] ?? 0);
        $old = (float) ($item['priceOld'] ?? 0);

        return array(
            'price' => $price,
            'price_old' => $old > $price ? $old : 0.0,
            'discount' => self::discount($price, $old, $item['percentageSaved'] ?? null),
            'currency' => (string) ($item['currencyCode'] ?? ''),
            'verified' => (int) ($item['last_update'] ?? 0),
            'domain' => (string) ($item['domain'] ?? ''),
            'merchant' => (string) ($item['merchant'] ?? ''),
            'img' => (string) ($item['img'] ?? ''),
            'logo' => (string) ($item['logo'] ?? ''),
        );
    }

    /**
     * Prices first: percentageSaved is only as fresh as the last full refetch,
     * while price and priceOld move together on every items update.
     */
    public static function discount(float $price, float $old, $saved): int
    {
        if ($price > 0 && $old > $price)
            return (int) round(($old - $price) / $old * 100);

        if (is_numeric($saved) && (float) $saved > 0)
            return (int) round((float) $saved);

        return 0;
    }
}
