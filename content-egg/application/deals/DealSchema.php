<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

/**
 * DealSchema class file
 *
 * Product JSON-LD for a deal page. A deal is one offer, so this is a single
 * Offer from the pinned item, never an AggregateOffer over the cheapest.
 * Availability follows the item: a deal that expired because its price rose is
 * still in stock at the new price, and the markup describes the offer as it is.
 * Pure.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealSchema
{
    public static function build(?array $item, int $ends, string $permalink, string $name): ?array
    {
        if (!$item || (float) ($item['price'] ?? 0) <= 0)
            return null;

        $offer = array(
            '@type' => 'Offer',
            'price' => sprintf('%0.2f', (float) $item['price']),
            'priceCurrency' => (string) ($item['currencyCode'] ?? ''),
            'url' => $permalink,
        );

        $stock = isset($item['stock_status']) ? (int) $item['stock_status'] : 0;
        if ($stock === 1)
            $offer['availability'] = 'https://schema.org/InStock';
        elseif ($stock === -1)
            $offer['availability'] = 'https://schema.org/OutOfStock';

        if ($ends > 0)
            $offer['priceValidUntil'] = gmdate('Y-m-d', $ends);

        $markup = array(
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            '@id' => $permalink . '#product',
            'name' => $name,
            'url' => $permalink,
            'offers' => $offer,
        );

        if (!empty($item['img']))
            $markup['image'] = (string) $item['img'];

        if (!empty($item['manufacturer']))
            $markup['brand'] = array('@type' => 'Brand', 'name' => (string) $item['manufacturer']);

        return $markup;
    }
}
