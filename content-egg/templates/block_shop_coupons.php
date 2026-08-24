<?php

/*
 * Name: Shop coupons
 * Module Types: SHOP_COUPON
 */

defined('\ABSPATH') || exit;

/**
 * Coupon cards for the shops in a product block.
 *
 * Modelled on block_coupons_ticket, but owned by this feature rather than
 * borrowed from it. The coupon-module templates expect an item produced by a
 * parser module; feeding them a shop coupon meant synthesising a module_id and
 * a handful of empty fields, and only worked because every consumer happened to
 * be guarded by moduleExists(). Tuning that template for coupon modules would
 * have broken shop coupons silently.
 *
 * Deliberately simpler than the ticket: no number badge, no rating, no price,
 * no per-module special cases. A shop coupon is a discount, a title, a date and
 * either a code or a button.
 *
 * Receives $coupons (rows built by ShopCoupons::cards()) rather than $items, and
 * calls nothing on the template manager - so it can render inside a block that
 * is already rendering. The card itself lives in blocks/coupon_card.php, which
 * item_row includes too.
 */

if (empty($coupons))
    return;

?>

<div class="cegg-shop-coupons cegg5-container">
    <?php foreach ($coupons as $row): ?>
        <?php include \ContentEgg\PLUGIN_PATH . 'application/templates/blocks/coupon_card.php'; ?>
    <?php endforeach; ?>
</div>
