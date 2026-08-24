<?php

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\ShopsController;

/**
 * One menu item, two tabs.
 *
 * Coupons is deliberately NOT its own menu item: Content Egg already ships
 * seven coupon-importing modules, so a top-level "Coupons" would promise the
 * coupons a network imported and deliver only the ones typed here.
 */
$current = ShopsController::currentTab();

?>

<h2 class="nav-tab-wrapper">
    <a href="<?php echo \esc_url(ShopsController::tabUrl()); ?>"
       class="nav-tab<?php echo $current === 'shops' ? ' nav-tab-active' : ''; ?>"><?php \esc_html_e('Shops', 'content-egg'); ?></a>
    <a href="<?php echo \esc_url(ShopsController::tabUrl('coupons')); ?>"
       class="nav-tab<?php echo $current === 'coupons' ? ' nav-tab-active' : ''; ?>"><?php \esc_html_e('Coupons', 'content-egg'); ?></a>
</h2>
