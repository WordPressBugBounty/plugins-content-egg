<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

/**
 * DealQuery class file
 *
 * WP_Query arguments for every deal listing: the archives, the Deals block and
 * related deals. args() is pure; applyToMainQuery() is the archive adapter.
 *
 * Sorting by meta uses named meta_query clauses, so a sort and the "active
 * only" filter can share one meta_query without one replacing the other.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealQuery
{
    const SORTS = array('new', 'discount', 'popular', 'ending');

    public static function initAction(): void
    {
        \add_action('pre_get_posts', array(__CLASS__, 'applyToMainQuery'));
    }

    public static function args(array $p, int $now): array
    {
        $sort = in_array($p['sort'] ?? '', self::SORTS, true) ? $p['sort'] : 'new';

        $args = array(
            'post_type' => DealPostType::POST_TYPE,
            'post_status' => 'publish',
            'ignore_sticky_posts' => true,
            'meta_query' => array('relation' => 'AND'),
            'tax_query' => array(),
            // A password-protected deal's price and coupon stay behind its password.
            'has_password' => false,
        );

        if (!empty($p['limit']))
            $args['posts_per_page'] = max(1, min(100, (int) $p['limit']));

        if (!empty($p['hide_expired']))
            $args['meta_query']['status_clause'] = array('key' => DealPostType::META_STATUS, 'value' => DealStatus::ACTIVE);

        if (!empty($p['coupon']))
            $args['meta_query']['coupon_clause'] = array('key' => DealPostType::META_HAS_COUPON, 'value' => '1');

        switch ($sort)
        {
            case 'discount':
                $args['meta_query']['discount_clause'] = array('key' => DealPostType::META_DISCOUNT, 'type' => 'NUMERIC');
                $args['orderby'] = array('discount_clause' => 'DESC', 'date' => 'DESC');
                break;
            case 'popular':
                $args['meta_query']['score_clause'] = array('key' => DealPostType::META_SCORE, 'type' => 'NUMERIC');
                $args['orderby'] = array('score_clause' => 'DESC', 'date' => 'DESC');
                break;
            case 'ending':
                $args['meta_query']['ends_clause'] = array('key' => DealPostType::META_ENDS, 'value' => $now, 'compare' => '>', 'type' => 'NUMERIC');
                $args['orderby'] = array('ends_clause' => 'ASC');
                break;
            default:
                $args['orderby'] = array('date' => 'DESC');
        }

        if (!empty($p['store']))
            $args['tax_query'][] = array('taxonomy' => DealPostType::TAX_STORE, 'field' => 'slug', 'terms' => array_values((array) $p['store']));

        if (!empty($p['category']))
            $args['tax_query'][] = array('taxonomy' => DealPostType::TAX_CATEGORY, 'field' => 'slug', 'terms' => array_values((array) $p['category']), 'include_children' => true);

        if (!empty($p['days']))
            $args['date_query'] = array(array('after' => gmdate('Y-m-d H:i:s', $now - min(3650, max(0, (int) $p['days'])) * DAY_IN_SECONDS), 'column' => 'post_date_gmt', 'inclusive' => true));

        if (!empty($p['exclude']))
            $args['post__not_in'] = array_map('intval', (array) $p['exclude']);

        if (count($args['meta_query']) === 1)
            unset($args['meta_query']);

        if (!$args['tax_query'])
            unset($args['tax_query']);

        return $args;
    }

    public static function sortFromRequest(array $get): string
    {
        $sort = isset($get['sort']) ? strtolower(preg_replace('/[^a-zA-Z]/', '', (string) $get['sort'])) : '';

        return in_array($sort, self::SORTS, true) ? $sort : 'new';
    }

    public static function couponFromRequest(array $get): bool
    {
        return isset($get['coupon']) && (string) $get['coupon'] === '1';
    }

    /**
     * Store and category filters need no code: the taxonomies' own query vars
     * (?deal_store=, ?deal_category=) already narrow the main query.
     */
    public static function applyToMainQuery($q): void
    {
        if (\is_admin() || !$q->is_main_query())
            return;

        if (!$q->is_post_type_archive(DealPostType::POST_TYPE) && !$q->is_tax(array(DealPostType::TAX_STORE, DealPostType::TAX_CATEGORY)))
            return;

        $args = self::args(array(
            'sort' => self::sortFromRequest($_GET), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'hide_expired' => DealsSettings::hideExpired(),
            'coupon' => self::couponFromRequest($_GET), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ), time());

        $q->set('post_type', DealPostType::POST_TYPE);
        $q->set('posts_per_page', DealsSettings::perPage());
        $q->set('ignore_sticky_posts', true);
        $q->set('has_password', false);
        $q->set('orderby', $args['orderby']);

        if (isset($args['meta_query']))
            $q->set('meta_query', $args['meta_query']);
    }
}
