<?php

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\ShopsController;
use ContentEgg\application\components\ShopScan;

$scan = ShopScan::results();

?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php \esc_html_e('Shops', 'content-egg'); ?></h1>
    <a class="page-title-action" href="<?php echo \esc_url(\admin_url('admin.php?page=' . ShopsController::SLUG_EDIT)); ?>"><?php \esc_html_e('Add shop manually', 'content-egg'); ?></a>
    <hr class="wp-header-end">

    <?php include __DIR__ . '/_shops_tabs.php'; ?>

    <?php if ($message): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo \esc_html($message); ?></p></div>
    <?php endif; ?>

    <?php
    $refresh = \wp_nonce_url(\add_query_arg('action', 'scan', ShopsController::tabUrl()), 'cegg_shop_scan');
    ?>
    <p class="description" style="max-width:80em;margin:1em 0">
        <?php \esc_html_e('Shops come from your product data automatically. Customize one to set its display name, logo, or a coupon shown beside its offers.', 'content-egg'); ?>

        <?php // The bound is disclosed only when the scan actually hit it. When
        // every row was read there is nothing to disclose, and the view filters
        // below already show the count. ?>
        <?php if (ShopScan::isTruncated($scan)): ?>
            <?php
            printf(
                /* translators: %s = number of product rows read */
                \esc_html__('Read your %s most recent products; older ones were not.', 'content-egg'),
                '<strong>' . \esc_html(\number_format_i18n($scan['scanned'])) . '</strong>'
            );
            ?>
            <a href="<?php echo \esc_url(\add_query_arg('limit', ShopScan::MAX_LIMIT, $refresh)); ?>"><?php \esc_html_e('Scan all', 'content-egg'); ?></a> &middot;
        <?php endif; ?>
        <a href="<?php echo \esc_url($refresh); ?>"><?php \esc_html_e('Refresh', 'content-egg'); ?></a>
    </p>

    <form id="cegg-shops-table" method="get">
        <input type="hidden" name="page" value="<?php echo \esc_attr(ShopsController::SLUG); ?>" />
        <?php $table->views(); ?>
        <?php $table->search_box(__('Search shops', 'content-egg'), 'cegg-shop-search'); ?>
        <?php $table->display(); ?>
    </form>

</div>
