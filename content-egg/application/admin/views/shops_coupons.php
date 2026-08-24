<?php

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\ShopsController;

?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php \esc_html_e('Shops', 'content-egg'); ?></h1>
    <a class="page-title-action" href="<?php echo \esc_url(\admin_url('admin.php?page=' . ShopsController::SLUG_EDIT)); ?>"><?php \esc_html_e('Add shop manually', 'content-egg'); ?></a>
    <hr class="wp-header-end">

    <?php include __DIR__ . '/_shops_tabs.php'; ?>

    <p class="description" style="max-width:70em;margin:1em 0">
        <?php \esc_html_e('Coupon codes you entered on your shops, shown beside that shop\'s offers in product blocks.', 'content-egg'); ?>
    </p>

    <form id="cegg-coupons-table" method="get">
        <input type="hidden" name="page" value="<?php echo \esc_attr(ShopsController::SLUG); ?>" />
        <input type="hidden" name="tab" value="coupons" />
        <?php if (!empty($_REQUEST['state'])): ?>
            <input type="hidden" name="state" value="<?php echo \esc_attr(\sanitize_key(\wp_unslash($_REQUEST['state']))); ?>" />
        <?php endif; ?>
        <?php $table->views(); ?>
        <?php $table->display(); ?>
    </form>
</div>

<style>
    .cegg-coupon-state { font-size: 12px; padding: 2px 8px; border-radius: 10px; background: #f0f0f1; white-space: nowrap; }
    .cegg-coupon-state-live { background: #d7f5dd; }
    .cegg-coupon-state-expired { background: #fbe2e2; }
    .cegg-coupon-state-scheduled { background: #fcf3d7; }
    .cegg-coupon-state-hidden_deal { background: #e5e5e5; white-space: normal; }
</style>
