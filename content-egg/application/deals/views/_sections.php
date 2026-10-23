<?php

defined('\ABSPATH') || exit;

use ContentEgg\application\deals\DealPin;
use ContentEgg\application\deals\DealTemplates;
use ContentEgg\application\deals\DealView;

/** @var int $post_id */
/** @var array $deal */
/** @var array|null $item */
/** @var array|null $pin */

$section = function (string $title, string $body, string $mod = '')
{
    if (trim($body) === '')
        return '';

    return '<section class="cegg-deal-section' . ($mod ? ' cegg-deal-section--' . $mod : '') . '"><h2 class="cegg-deal-section__title">' . \esc_html($title) . '</h2>' . $body . '</section>';
};

// CE appends store coupons after every block it renders; the deal page shows
// them once, in its own section, so every embedded block turns them off.
if ($item && $pin && DealPin::shortcodeSafe($pin))
{
    $ref = \esc_attr($pin['module_id'] . ':' . $pin['unique_id']);

    // One stored price draws an empty grid, not a history.
    if (DealView::historyPoints($pin) >= 2)
        echo $section(__('Price history', 'content-egg-tpl'), \do_shortcode('[content-egg-block template=price_history products="' . $ref . '" coupons_display="off"]'), 'history'); // phpcs:ignore

    $alert = \do_shortcode('[content-egg-block template=price_alert products="' . $ref . '" coupons_display="off"]');
    // The template prints an empty wrapper when it has no form to show.
    if (trim(\wp_strip_all_tags($alert)) !== '')
        echo '<div class="cegg-deal-section cegg-deal-section--alert">' . $alert . '</div>'; // phpcs:ignore
}

if (DealView::pricedItemCount($post_id) > 1 && (!$pin || DealPin::shortcodeSafe($pin)))
    echo $section(__('All offers', 'content-egg-tpl'), \do_shortcode('[content-egg-block template=offers_list coupons_display="off"]')); // phpcs:ignore

if ($item && ($coupons = DealView::couponsHtml($item)))
{
    $store = $deal['store'] ? $deal['store']['name'] : '';
    /* translators: %s: store name */
    echo $section($store ? sprintf(__('%s coupons', 'content-egg-tpl'), $store) : __('Coupons', 'content-egg-tpl'), $coupons, 'coupons'); // phpcs:ignore
}

if ($related = DealView::related($post_id, 4))
    echo $section(__('Related deals', 'content-egg-tpl'), DealTemplates::part('_grid', array('post_ids' => $related, 'columns' => 4)), 'related'); // phpcs:ignore
