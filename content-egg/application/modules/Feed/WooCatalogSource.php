<?php

namespace ContentEgg\application\modules\Feed;

defined('\ABSPATH') || exit;

/**
 * WooCatalogSource class file
 *
 * Reads this site's WooCommerce products as Feed rows, keyed by the Feed's own
 * field names so FeedModule::mapProduct() can pass them through unchanged.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class WooCatalogSource
{
    const SOURCE = 'woocommerce';
    const BATCH = 100;

    // The Feed's legacy mapping key carries two zero-width spaces.
    const IMAGE_LINK = "image \u{200B}\u{200B}link";

    public static function isAvailable(): bool
    {
        return class_exists('\WooCommerce') && function_exists('\wc_get_products');
    }

    /**
     * @return \Generator<array>
     */
    public static function rows(string $module_id): \Generator
    {
        $args = \apply_filters('cegg_feed_woo_query_args', array(
            'status' => 'publish',
            'type' => array('simple', 'external', 'variable'),
            'visibility' => 'catalog',
            'limit' => self::BATCH,
            'orderby' => 'ID',
            'order' => 'ASC',
            'return' => 'objects',
        ), $module_id);

        for ($page = 1;; $page++)
        {
            $products = \wc_get_products(array_merge($args, array('page' => $page)));
            if (!$products)
                break;

            foreach ($products as $product)
            {
                if ($product->get_post_password())
                    continue;

                $row = \apply_filters('cegg_feed_woo_product_row', self::productRow($product), $product, $module_id);
                if ($row)
                    yield $row;
            }

            if (count($products) < $args['limit'])
                break;

            // Long catalogs: keep the object cache from growing for the whole run.
            \wp_cache_flush_runtime();
        }
    }

    public static function productRow(\WC_Product $product): array
    {
        $link = (string) \get_permalink($product->get_id());

        if ($product->is_type('variable'))
        {
            $price = $product->get_variation_regular_price('min');
            $sale = $product->get_variation_sale_price('min');
            $sale = ($sale !== '' && (float) $sale < (float) $price) ? $sale : '';
        }
        else
        {
            $price = $product->get_regular_price();
            if ($price === '')
                $price = $product->get_price();
            $sale = $product->is_on_sale() ? $product->get_sale_price() : '';
        }

        $gallery = array();
        foreach ($product->get_gallery_image_ids() as $image_id)
        {
            if ($url = self::imageUrl($image_id))
                $gallery[] = $url;
        }

        return array(
            'id' => (string) $product->get_id(),
            'title' => $product->get_name(),
            'description' => self::plainText($product->get_description()),
            'short description' => self::plainText($product->get_short_description()),
            'affiliate link' => $link,
            'direct link' => $link,
            self::IMAGE_LINK => self::imageUrl($product->get_image_id()),
            'additional image link' => implode(',', $gallery),
            'price' => (string) $price,
            'sale price' => (string) $sale,
            'currency' => \get_woocommerce_currency(),
            'availability' => $product->is_in_stock() ? 'in stock' : 'out of stock',
            'gtin' => method_exists($product, 'get_global_unique_id') ? (string) $product->get_global_unique_id() : '',
            'brand' => self::brand($product),
            'category' => self::categoryPath($product),
            'attributes' => self::attributes($product),
        );
    }

    private static function imageUrl($image_id): string
    {
        if (!$image_id)
            return '';

        // External images of EI and CE resolve through this filter too.
        $src = \wp_get_attachment_image_src($image_id, 'full');

        return $src ? (string) $src[0] : '';
    }

    private static function plainText($html): string
    {
        return trim(preg_replace('/\s+/u', ' ', \wp_strip_all_tags(\strip_shortcodes((string) $html))));
    }

    private static function brand(\WC_Product $product): string
    {
        if (\taxonomy_exists('product_brand'))
        {
            $terms = \get_the_terms($product->get_id(), 'product_brand');
            if ($terms && !\is_wp_error($terms))
                return \html_entity_decode($terms[0]->name);
        }

        return (string) $product->get_attribute('pa_brand');
    }

    /**
     * The deepest assigned category with its parents: "Parent > Child".
     * WooCommerce's default "Uncategorized" says nothing about the product.
     */
    private static function categoryPath(\WC_Product $product): string
    {
        $default = (int) \get_option('default_product_cat');

        $best = array();
        foreach ($product->get_category_ids() as $term_id)
        {
            if ((int) $term_id === $default)
                continue;

            $path = array_reverse(\get_ancestors($term_id, 'product_cat', 'taxonomy'));
            $path[] = $term_id;
            if (count($path) > count($best))
                $best = $path;
        }

        $names = array();
        foreach ($best as $term_id)
        {
            $term = \get_term($term_id, 'product_cat');
            if ($term && !\is_wp_error($term))
                $names[] = \html_entity_decode($term->name);
        }

        return implode(' > ', $names);
    }

    private static function attributes(\WC_Product $product): array
    {
        $attributes = array();
        foreach ($product->get_attributes() as $attribute)
        {
            if (!$attribute->get_visible())
                continue;

            $name = \wc_attribute_label($attribute->get_name(), $product);
            $value = $attribute->is_taxonomy()
                ? implode(', ', \wc_get_product_terms($product->get_id(), $attribute->get_name(), array('fields' => 'names')))
                : implode(', ', $attribute->get_options());

            if ($name !== '' && $value !== '')
                $attributes[] = array('name' => $name, 'value' => $value);
        }

        return $attributes;
    }
}
