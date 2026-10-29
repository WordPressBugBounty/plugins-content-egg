<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

/**
 * DealPostType class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealPostType
{
    const POST_TYPE = 'cegg_deal';
    const TAX_STORE = 'deal_store';
    const TAX_CATEGORY = 'deal_category';

    const META_ITEM = '_cegg_deal_item';
    const META_PRICE = '_cegg_deal_price';
    const META_PRICE_OLD = '_cegg_deal_price_old';
    const META_DISCOUNT = '_cegg_deal_discount';
    const META_CURRENCY = '_cegg_deal_currency';
    const META_IMG = '_cegg_deal_img';
    const META_BASE_PRICE = '_cegg_deal_base_price';
    const META_VERIFIED = '_cegg_deal_verified';
    const META_ENDS = '_cegg_deal_ends';
    const META_STATUS = '_cegg_deal_status';
    const META_REASON = '_cegg_deal_reason';
    const META_VOTES_UP = '_cegg_deal_votes_up';
    const META_VOTES_DOWN = '_cegg_deal_votes_down';
    const META_SCORE = '_cegg_deal_score';
    const META_COUPON_CODE = '_cegg_deal_coupon_code';
    const META_COUPON_NOTE = '_cegg_deal_coupon_note';
    const META_HAS_COUPON = '_cegg_deal_has_coupon';
    // Identity of the pinned offer, as scalars a meta query can match (DealKey).
    const META_KEY = '_cegg_deal_key';
    const META_URL_KEY = '_cegg_deal_url_key';
    const META_GTIN = '_cegg_deal_gtin';
    // Where the end date comes from: DealEnds::SOURCE_OFFER or SOURCE_MANUAL.
    const META_ENDS_SOURCE = '_cegg_deal_ends_source';

    const TERM_META_DOMAIN = '_cegg_domain';
    const TERM_META_LOGO = '_cegg_logo';

    public static function register(): void
    {
        $archive = DealsSettings::archiveSlug();

        \register_post_type(self::POST_TYPE, array(
            'labels' => array(
                'name' => __('Deals', 'content-egg'),
                'singular_name' => __('Deal', 'content-egg'),
                'add_new_item' => __('Add New Deal', 'content-egg'),
                'edit_item' => __('Edit Deal', 'content-egg'),
                'new_item' => __('New Deal', 'content-egg'),
                'view_item' => __('View Deal', 'content-egg'),
                'all_items' => __('All Deals', 'content-egg'),
                'search_items' => __('Search Deals', 'content-egg'),
                'not_found' => __('No deals found.', 'content-egg'),
                'menu_name' => __('Deals', 'content-egg'),
            ),
            'public' => true,
            'has_archive' => $archive,
            'rewrite' => array('slug' => DealsSettings::singleSlug(), 'with_front' => false),
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-tag',
            'menu_position' => 26,
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'comments', 'author', 'custom-fields'),
        ));

        \register_taxonomy(self::TAX_STORE, self::POST_TYPE, array(
            'labels' => array(
                'name' => __('Stores', 'content-egg'),
                'singular_name' => __('Store', 'content-egg'),
            ),
            'public' => true,
            'hierarchical' => false,
            'show_in_rest' => true,
            'show_admin_column' => true,
            'rewrite' => array('slug' => $archive . '/store', 'with_front' => false),
        ));

        \register_taxonomy(self::TAX_CATEGORY, self::POST_TYPE, array(
            'labels' => array(
                'name' => __('Deal Categories', 'content-egg'),
                'singular_name' => __('Deal Category', 'content-egg'),
            ),
            'public' => true,
            'hierarchical' => true,
            'show_in_rest' => true,
            'show_admin_column' => true,
            'rewrite' => array('slug' => $archive . '/category', 'with_front' => false, 'hierarchical' => true),
        ));
    }
}
