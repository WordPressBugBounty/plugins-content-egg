<?php

defined('\ABSPATH') || exit;

use ContentEgg\application\deals\DealView;

/** @var array $deal */
if ($deal['coupon_code'] === '')
    return;
?>
<div class="cegg-deal-coupon">
    <span class="cegg-deal-coupon__label"><?php \esc_html_e('Coupon code', 'content-egg-tpl'); ?></span>
    <span class="cegg-deal-coupon__row">
        <span class="cegg-deal-coupon__cut"><?php echo DealView::icon('scissors'); // phpcs:ignore ?></span>
        <code class="cegg-deal-coupon__code"><?php echo \esc_html($deal['coupon_code']); ?></code>
        <button type="button" class="cegg-deal-coupon__copy" data-code="<?php echo \esc_attr($deal['coupon_code']); ?>"><?php \esc_html_e('Copy', 'content-egg-tpl'); ?></button>
    </span>
    <?php if ($deal['coupon_note'] !== '') : ?><span class="cegg-deal-coupon__note"><?php echo \esc_html($deal['coupon_note']); ?></span><?php endif; ?>
</div>
