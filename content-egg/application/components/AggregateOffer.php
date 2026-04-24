<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\WooIntegrator;
use ContentEgg\application\components\ContentManager;
use ContentEgg\application\helpers\TemplateHelper;
use ContentEgg\application\components\ContentProduct;

use function ContentEgg\prnx;

/**
 * AggregateOffer class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class AggregateOffer
{

    public static function initAction()
    {
        if (GeneralConfig::getInstance()->option('aggregate_offer') !== 'enabled')
            return;

        \add_action('woocommerce_structured_data_product', array(__CLASS__, 'addStructuredDataProduct'), 10, 2);
    }

    public static function addStructuredDataProduct($markup, $product)
    {
        if (!is_array($markup) || !is_object($product) || !method_exists($product, 'get_id'))
        {
            return $markup;
        }

        $post_id = (int) $product->get_id();

        if (!WooIntegrator::getMetaSyncUniqueId($post_id))
        {
            return $markup;
        }

        $data = ContentManager::getViewProductData($post_id);
        if (empty($data) || !is_array($data))
        {
            return $markup;
        }

        // Keep only in-stock offers (strict).
        $data = array_values(array_filter($data, static function ($d)
        {
            return isset($d['stock_status']) && $d['stock_status'] !== ContentProduct::STOCK_STATUS_OUT_OF_STOCK;
        }));

        $offer_count = count($data);
        if ($offer_count <= 1)
        {
            return $markup;
        }

        // Sort and find min/max price items.
        $data           = TemplateHelper::sortByPrice($data);
        $min_price_item = TemplateHelper::getMinPriceItem($data);
        $max_price_item = TemplateHelper::getMaxPriceItem($data);

        if (empty($min_price_item) || empty($max_price_item))
        {
            return $markup;
        }

        // Build individual Offer objects (better schema).
        $offers_list = [];
        foreach ($data as $d)
        {
            $price = $d['price'] ?? null;
            if (!is_numeric($price))
            {
                continue;
            }

            $currency = $d['currencyCode'] ?? ($min_price_item['currencyCode'] ?? null);
            if (empty($currency))
            {
                continue; // AggregateOffer should have a single currency.
            }

            $offer = [
                '@type'         => 'Offer',
                'price'         => number_format((float) $price, 2, '.', ''),
                'priceCurrency' => $currency,
            ];

            // Optional fields
            if (isset($d['stock_status']) && $d['stock_status'] == ContentProduct::STOCK_STATUS_IN_STOCK)
            {
                $offer['availability'] = 'https://schema.org/InStock';
            }
            $seller_name = $d['merchant'] ?? '';
            if ($seller_name === '' && !empty($d['domain']))
            {
                $seller_name = $d['domain'];
            }

            if ($seller_name !== '')
            {
                $offer['seller'] = [
                    '@type' => 'Organization',
                    'name'  => $seller_name,
                ];
            }

            $offers_list[] = $offer;
        }

        // Recompute count based on valid offers we actually built.
        $offer_count = count($offers_list);
        if ($offer_count <= 1)
        {
            return $markup;
        }

        // AggregateOffer object.
        $aggregate = [
            '@type'      => 'AggregateOffer',
            'offerCount' => $offer_count,
            'offers'     => $offers_list,
        ];

        // Set low/high from min/max items
        if (isset($min_price_item['price']) && is_numeric($min_price_item['price']))
        {
            $aggregate['lowPrice'] = number_format((float) $min_price_item['price'], 2, '.', '');
        }
        if (isset($max_price_item['price']) && is_numeric($max_price_item['price']))
        {
            $aggregate['highPrice'] = number_format((float) $max_price_item['price'], 2, '.', '');
        }

        // Currency once
        if (!empty($min_price_item['currencyCode']))
        {
            $aggregate['priceCurrency'] = $min_price_item['currencyCode'];
        }

        /**
         * Yoast WPSEO WooCommerce expects $markup['offers'] to be an array of offers.
         */
        $markup['offers'] = [$aggregate];

        return $markup;
    }
}
