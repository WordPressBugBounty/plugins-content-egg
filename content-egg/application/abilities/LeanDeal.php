<?php

namespace ContentEgg\application\abilities;

use ContentEgg\application\deals\DealPostType;
use ContentEgg\application\deals\DealService;
use ContentEgg\application\deals\DealView;

defined('\ABSPATH') || exit;

/**
 * LeanDeal class file
 *
 * One deal as the agent abilities return it: the scalar facts the deal page
 * and cards show, its status and why, and the offer it is pinned to.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class LeanDeal
{
    public static function row(int $post_id): array
    {
        $post = \get_post($post_id);
        $d = DealView::deal($post_id);

        $categories = \wp_get_post_terms($post_id, DealPostType::TAX_CATEGORY, array('fields' => 'names'));

        return array(
            'id' => $post_id,
            'title' => $post ? (string) $post->post_title : '',
            'post_status' => $post ? (string) $post->post_status : '',
            'deal_status' => $d['status'],
            'reason' => $d['reason'],
            'price' => $d['price'],
            'price_old' => $d['price_old'],
            'base_price' => (float) \get_post_meta($post_id, DealPostType::META_BASE_PRICE, true),
            'discount' => $d['discount'],
            'currency' => $d['currency'],
            'store' => $d['store'] ? (string) $d['store']['name'] : null,
            'categories' => \is_wp_error($categories) ? array() : array_values(array_map('strval', $categories)),
            'ends' => self::iso($d['ends']),
            'verified' => self::iso($d['verified']),
            'coupon_code' => $d['coupon_code'],
            'votes_up' => $d['votes_up'],
            'votes_down' => $d['votes_down'],
            'score' => $d['score'],
            'pin' => DealService::getPin($post_id),
            'permalink' => (string) \get_permalink($post_id),
            'edit_url' => (string) \get_edit_post_link($post_id, 'raw'),
        );
    }

    /** ISO 8601 UTC, or null for 0 (not set). */
    public static function iso(int $timestamp): ?string
    {
        return $timestamp > 0 ? gmdate('Y-m-d\TH:i:s\Z', $timestamp) : null;
    }

    public static function schema(): array
    {
        $num = array('type' => 'number');
        $int = array('type' => 'integer');
        $time = array('type' => array('string', 'null'), 'description' => 'ISO 8601 UTC, or null.');

        return array(
            'type' => 'object',
            'properties' => array(
                'id' => $int,
                'title' => array('type' => 'string'),
                'post_status' => array('type' => 'string'),
                'deal_status' => array('type' => 'string', 'enum' => array('active', 'expired')),
                'reason' => array('type' => 'string', 'description' => 'Why the deal expired: manual, ended, unavailable, out_of_stock or price_up; empty while active.'),
                'price' => $num,
                'price_old' => $num,
                'base_price' => array('type' => 'number', 'description' => 'The price when the deal was pinned or last reactivated; a rise above it by the site threshold expires the deal.'),
                'discount' => $int,
                'currency' => array('type' => 'string'),
                'store' => array('type' => array('string', 'null')),
                'categories' => array('type' => 'array', 'items' => array('type' => 'string')),
                'ends' => $time,
                'verified' => array('type' => array('string', 'null'), 'description' => 'When the price was last checked, ISO 8601 UTC.'),
                'coupon_code' => array('type' => 'string'),
                'votes_up' => $int,
                'votes_down' => $int,
                'score' => $int,
                'pin' => array(
                    'type' => array('object', 'null'),
                    'description' => 'The offer the deal follows.',
                    'properties' => array('module_id' => array('type' => 'string'), 'unique_id' => array('type' => 'string')),
                ),
                'permalink' => array('type' => 'string'),
                'edit_url' => array('type' => 'string'),
                'match' => array('type' => 'string', 'enum' => array('key', 'url', 'gtin'), 'description' => 'Lookups only: what matched. gtin = the same product, possibly from another store, not necessarily the same offer.'),
            ),
        );
    }
}
