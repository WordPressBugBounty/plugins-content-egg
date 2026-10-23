<?php

defined('\ABSPATH') || exit;

use ContentEgg\application\deals\DealPostType;
use ContentEgg\application\deals\DealQuery;

$current = DealQuery::sortFromRequest($_GET); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$labels = array(
    'new' => __('Newest', 'content-egg-tpl'),
    'discount' => __('Biggest discount', 'content-egg-tpl'),
    'popular' => __('Popular', 'content-egg-tpl'),
    'ending' => __('Ending soon', 'content-egg-tpl'),
);
$stores = \get_terms(array('taxonomy' => DealPostType::TAX_STORE, 'hide_empty' => true));
$cats = \get_terms(array('taxonomy' => DealPostType::TAX_CATEGORY, 'hide_empty' => true));
$sel_store = isset($_GET['deal_store']) ? \sanitize_title(\wp_unslash($_GET['deal_store'])) : ''; // phpcs:ignore
$with_coupon = DealQuery::couponFromRequest($_GET); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$sel_cat = isset($_GET['deal_category']) ? \sanitize_title(\wp_unslash($_GET['deal_category'])) : ''; // phpcs:ignore
?>
<div class="cegg-deal-toolbar">
    <nav class="cegg-deal-sort" aria-label="<?php \esc_attr_e('Sort deals', 'content-egg-tpl'); ?>">
        <?php foreach ($labels as $key => $label) : ?>
            <a class="cegg-deal-sort__link<?php echo $key === $current ? ' is-current' : ''; ?>" href="<?php echo \esc_url(\add_query_arg('sort', $key)); ?>"><?php echo \esc_html($label); ?></a>
        <?php endforeach; ?>
        <a class="cegg-deal-sort__link cegg-deal-sort__link--coupon<?php echo $with_coupon ? ' is-current' : ''; ?>" href="<?php echo \esc_url($with_coupon ? \remove_query_arg('coupon') : \add_query_arg('coupon', '1')); ?>"<?php echo $with_coupon ? ' aria-pressed="true"' : ''; ?>><?php \esc_html_e('With coupon', 'content-egg-tpl'); ?></a>
    </nav>
    <?php
    // /deals/?deal_store=x is both the post type archive and a taxonomy query:
    // keep the form there so filters can be changed, combined and cleared.
    // Only the store/category archives themselves (/deal-store/x/) hide it.
    if ((\is_post_type_archive(DealPostType::POST_TYPE) || !\is_tax()) && (($stores && !\is_wp_error($stores)) || ($cats && !\is_wp_error($cats)))) : ?>
        <form class="cegg-deal-filter" method="get" action="<?php echo \esc_url(\get_post_type_archive_link(DealPostType::POST_TYPE)); ?>">
            <input type="hidden" name="sort" value="<?php echo \esc_attr($current); ?>">
            <?php if ($with_coupon) : ?><input type="hidden" name="coupon" value="1"><?php endif; ?>
            <?php if ($stores && !\is_wp_error($stores)) : ?>
                <select name="deal_store" aria-label="<?php \esc_attr_e('Store', 'content-egg-tpl'); ?>" onchange="this.form.submit()">
                    <option value=""><?php \esc_html_e('All stores', 'content-egg-tpl'); ?></option>
                    <?php foreach ($stores as $t) : ?><option value="<?php echo \esc_attr($t->slug); ?>" <?php \selected($sel_store, $t->slug); ?>><?php echo \esc_html($t->name); ?></option><?php endforeach; ?>
                </select>
            <?php endif; ?>
            <?php if ($cats && !\is_wp_error($cats)) : ?>
                <select name="deal_category" aria-label="<?php \esc_attr_e('Category', 'content-egg-tpl'); ?>" onchange="this.form.submit()">
                    <option value=""><?php \esc_html_e('All categories', 'content-egg-tpl'); ?></option>
                    <?php foreach ($cats as $t) : ?><option value="<?php echo \esc_attr($t->slug); ?>" <?php \selected($sel_cat, $t->slug); ?>><?php echo \esc_html($t->name); ?></option><?php endforeach; ?>
                </select>
            <?php endif; ?>
            <noscript><button type="submit"><?php \esc_html_e('Filter', 'content-egg-tpl'); ?></button></noscript>
        </form>
    <?php endif; ?>
</div>
