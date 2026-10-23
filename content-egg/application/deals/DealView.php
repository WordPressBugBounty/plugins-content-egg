<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ContentManager;
use ContentEgg\application\components\ShopCoupons;
use ContentEgg\application\components\ShopStore;
use ContentEgg\application\helpers\TemplateHelper;
use ContentEgg\application\models\PriceHistoryModel;

/**
 * DealView class file
 *
 * What the deal templates read. Cards render from post meta alone; only the
 * single page loads Content Egg data.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealView
{
    public static function deal(int $post_id): array
    {
        $m = function ($key) use ($post_id)
        {
            return \get_post_meta($post_id, $key, true);
        };

        return array(
            'price' => (float) $m(DealPostType::META_PRICE),
            'price_old' => (float) $m(DealPostType::META_PRICE_OLD),
            'discount' => (int) $m(DealPostType::META_DISCOUNT),
            'currency' => (string) $m(DealPostType::META_CURRENCY),
            'img' => (string) $m(DealPostType::META_IMG),
            'verified' => (int) $m(DealPostType::META_VERIFIED),
            'ends' => (int) $m(DealPostType::META_ENDS),
            'status' => (string) $m(DealPostType::META_STATUS) ?: DealStatus::ACTIVE,
            'reason' => (string) $m(DealPostType::META_REASON),
            'votes_up' => (int) $m(DealPostType::META_VOTES_UP),
            'votes_down' => (int) $m(DealPostType::META_VOTES_DOWN),
            'score' => (int) $m(DealPostType::META_SCORE),
            'store' => self::store($post_id),
            'coupon_code' => (string) $m(DealPostType::META_COUPON_CODE),
            'coupon_note' => (string) $m(DealPostType::META_COUPON_NOTE),
        );
    }

    public static function store(int $post_id): ?array
    {
        $terms = \get_the_terms($post_id, DealPostType::TAX_STORE);
        if (!$terms || \is_wp_error($terms))
            return null;

        $term = reset($terms);
        $domain = (string) \get_term_meta($term->term_id, DealPostType::TERM_META_DOMAIN, true);
        $shop = $domain !== '' ? ShopStore::get($domain) : null;
        $logo = ($shop && !empty($shop['logo'])) ? (string) $shop['logo'] : (string) \get_term_meta($term->term_id, DealPostType::TERM_META_LOGO, true);
        if ($logo === '' && $domain !== '')
            $logo = (string) TemplateHelper::getMerchantLogoUrl(array('domain' => $domain));
        $link = \get_term_link($term);

        return array(
            'name' => $term->name,
            'link' => \is_wp_error($link) ? '' : $link,
            'logo' => $logo,
            // Wordmark logos are unreadable at 16-24px; small spots use the square icon.
            'icon' => $domain !== '' ? (string) TemplateHelper::getMerchantIconUrl(array('domain' => $domain)) : '',
            'domain' => $domain,
        );
    }

    public static function item(int $post_id): ?array
    {
        $pin = DealService::getPin($post_id);

        return $pin ? ContentManager::getProductbyUniqueId($pin['unique_id'], $pin['module_id'], $post_id) : null;
    }

    public static function price(float $price, string $currency): string
    {
        return $price > 0 ? (string) TemplateHelper::formatPriceCurrency($price, $currency) : '';
    }

    /**
     * The deal's buy button. Settings → Deals → Deal button text wins (with the
     * product-button tokens); empty means "Get deal at {merchant}", or plain
     * "Get deal" when the product has no merchant.
     */
    public static function buttonText(array $item): string
    {
        $merchant = trim((string) ($item['merchant'] ?? ''));
        /* translators: %s: store name */
        $default = $merchant !== '' ? sprintf(__('Get deal at %s', 'content-egg-tpl'), $merchant) : __('Get deal', 'content-egg-tpl');

        return (string) TemplateHelper::btnText('deals_btn_text', $default, false, $item);
    }

    public static function reasonLabel(string $reason): string
    {
        switch ($reason)
        {
            case DealStatus::REASON_OUT_OF_STOCK:
                return __('Expired: out of stock', 'content-egg-tpl');
            case DealStatus::REASON_PRICE_UP:
                return __('Expired: the price went up', 'content-egg-tpl');
            case DealStatus::REASON_ENDED:
                return __('Expired: the deal has ended', 'content-egg-tpl');
            case DealStatus::REASON_UNAVAILABLE:
                return __('Expired: no longer available', 'content-egg-tpl');
            default:
                return __('Expired', 'content-egg-tpl');
        }
    }

    /**
     * The cegg_deal_is_hot filter gets the final say, with the deal's data
     * (score, votes_up, votes_down, status, discount, …).
     */
    public static function isHot(array $deal): bool
    {
        $hot = DealBadges::isHot((int) $deal['score'], DealsSettings::hotScore(), (string) $deal['status']);

        return (bool) \apply_filters('cegg_deal_is_hot', $hot, $deal);
    }

    public static function commentCount(int $post_id): int
    {
        return (int) \get_comments_number($post_id);
    }

    /** "5 hours ago" for a Unix timestamp; '' for none. */
    public static function ago(int $timestamp): string
    {
        if ($timestamp <= 0)
            return '';

        /* translators: %s: human time difference, e.g. "5 hours" */
        return sprintf(__('%s ago', 'content-egg-tpl'), \human_time_diff($timestamp, time()));
    }

    public static function primaryCategory(int $post_id): ?\WP_Term
    {
        $terms = \get_the_terms($post_id, DealPostType::TAX_CATEGORY);
        if (!$terms || \is_wp_error($terms))
            return null;

        $term = DealNav::deepest($terms, function ($t)
        {
            return count(\get_ancestors($t->term_id, DealPostType::TAX_CATEGORY, 'taxonomy'));
        });

        return $term instanceof \WP_Term ? $term : null;
    }

    /** Price-history rows for the pinned product; the chart needs two to draw a line. */
    public static function historyPoints(?array $pin): int
    {
        if (!$pin)
            return 0;

        return (int) PriceHistoryModel::model()->count(array('unique_id = %s AND module_id = %s', array($pin['unique_id'], $pin['module_id'])));
    }

    /**
     * Small inline SVG icons (currentColor, 1em). Decorative: aria-hidden.
     */
    public static function icon(string $name): string
    {
        $paths = array(
            'hot' => '<path d="M12 2c1 3.5-1.5 5.5-1.5 8a2.5 2.5 0 0 0 5 0c0-1-.3-1.8-.8-2.6C17.4 9 19 11.4 19 14a7 7 0 0 1-14 0c0-4.2 3.3-6.4 5-9 .8-1.2 1.5-2 2-3z" fill="currentColor"/>',
            'up' => '<path d="M7 11v9H4v-9h3zm2 9h7.6a2 2 0 0 0 2-1.6l1.3-6.5A2 2 0 0 0 18 9.5h-4.6l.7-3.6a1.6 1.6 0 0 0-2.9-1.2L9 9.3V20z" fill="currentColor"/>',
            'down' => '<path d="M17 13V4h3v9h-3zm-2-9H7.4a2 2 0 0 0-2 1.6L4.1 12a2 2 0 0 0 2 2.4h4.6l-.7 3.6a1.6 1.6 0 0 0 2.9 1.2l2.1-4.6V4z" fill="currentColor"/>',
            'comment' => '<path d="M4 4h16v12H8l-4 4V4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>',
            'scissors' => '<circle cx="6" cy="6" r="3" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="6" cy="18" r="3" fill="none" stroke="currentColor" stroke-width="2"/><path d="M20 4 8.1 15.9M14.5 14.5 20 20M8.1 8.1 12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
            'clock' => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 7v5l3 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
        );
        if (!isset($paths[$name]))
            return '';

        return '<svg class="cegg-deal-icon cegg-deal-icon--' . $name . '" viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
    }

    public static function isThirtyDayLow(int $post_id): bool
    {
        $pin = DealService::getPin($post_id);
        if (!$pin)
            return false;

        $rows = PriceHistoryModel::model()->findAll(array(
            'select' => 'create_date, price',
            'where' => array('unique_id = %s AND module_id = %s', array($pin['unique_id'], $pin['module_id'])),
            'order' => 'create_date ASC',
        ));

        // create_date is written with current_time('mysql'), so compare on the same clock.
        return DealBadges::isThirtyDayLow((array) $rows, (float) \get_post_meta($post_id, DealPostType::META_PRICE, true), (int) \current_time('timestamp'));
    }

    public static function pricedItemCount(int $post_id): int
    {
        $count = 0;
        foreach (DealService::itemsByModule($post_id) as $items)
        {
            foreach ($items as $item)
            {
                if (is_array($item) && (float) ($item['price'] ?? 0) > 0)
                    $count++;
            }
        }

        return $count;
    }

    public static function couponsHtml(array $item): string
    {
        $params = array('coupons_display' => 'cards');
        $by_domain = ShopCoupons::resolve(array($item), $params);

        return $by_domain ? (string) ShopCoupons::cards($by_domain, array($item), $params) : '';
    }

    /**
     * Same category first, then same store, never the deal itself.
     */
    public static function related(int $post_id, int $limit = 4): array
    {
        $ids = array();
        foreach (array(DealPostType::TAX_CATEGORY, DealPostType::TAX_STORE) as $taxonomy)
        {
            if (count($ids) >= $limit)
                break;

            $terms = \wp_get_post_terms($post_id, $taxonomy, array('fields' => 'ids'));
            if (!$terms || \is_wp_error($terms))
                continue;

            $args = DealQuery::args(array('limit' => $limit - count($ids), 'hide_expired' => true, 'exclude' => array_merge(array($post_id), $ids)), time());
            $args['tax_query'] = array(array('taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $terms));
            $args['no_found_rows'] = true;

            // Full posts (not fields=>ids) so the post, meta, term and thumbnail
            // caches are primed for the cards rendered from these ids.
            $query = new \WP_Query($args);
            \update_post_thumbnail_cache($query);

            $ids = array_merge($ids, array_map('intval', \wp_list_pluck($query->posts, 'ID')));
        }

        return $ids;
    }
}
