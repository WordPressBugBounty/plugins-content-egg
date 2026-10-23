<?php
/*
 * Name: Price comparison widget
 * Modules:
 * Module Types: PRODUCT
 *
 */

use ContentEgg\application\helpers\TemplateHelper;

defined('\ABSPATH') || exit;

?>

<div class="container px-0 mb-5 mt-1" <?php $this->colorMode(); ?>>
    <table class="table table-sm table-bordered table-hover mb-0 cegg-price-comparison-table">
        <tbody>

            <?php foreach ($items as $i => $item): ?>
                <?php $this->setItem($item, $i); ?>
                <?php $merchant_visible = $this->isVisible('merchant'); ?>

                <?php $price_html = ''; ?>
                <?php if ($this->isVisible('price')): ?>
                <?php ob_start(); ?>
                <?php TemplateHelper::openATag($item, $params, array('class' => 'text-body text-decoration-none')); ?>
                <div>
                    <span class="fw-semibold<?php TemplateHelper::priceClass($item); ?>"><?php TemplateHelper::price($item); ?></span>
                    <?php if ($this->isVisible('priceOld', false)): ?>
                        <del class="small text-body-secondary ms-1"><?php TemplateHelper::oldPrice($item); ?></del>
                    <?php endif; ?>

                    <?php if ($this->isVisible('new_used_price')): ?>
                        <div class="small text-body-secondary lh-sm">
                            <small><?php TemplateHelper::newUsedPrice($item, '<br>'); ?></small>
                        </div>
                    <?php endif; ?>
                    <?php $condition_label = TemplateHelper::conditionLabel($item); ?>
                    <?php if ($this->isVisible('stock_status', false) || $condition_label !== ''): ?>
                        <div class="small text-body-secondary lh-sm">
                            <small>
                                <?php if ($this->isVisible('stock_status', false)): ?>
                                    <?php TemplateHelper::stockStatus($item); ?>
                                <?php endif; ?>
                                <?php if ($condition_label !== ''): ?>
                                    <span class="cegg-condition badge badge-secondary text-bg-secondary rounded-0"><?php echo esc_html($condition_label); ?></span>
                                <?php endif; ?>
                            </small>
                        </div>
                    <?php endif; ?>
                    <?php if ($this->isVisible('shipping_cost', false)): ?>
                        <div class="small text-body-secondary lh-sm cegg-shipping-cost">
                            <small><?php TemplateHelper::shippingCost($item); ?></small>
                        </div>
                    <?php endif; ?>
                </div>
                <?php TemplateHelper::closeATag(); ?>
                <?php $price_html = ob_get_clean(); ?>
                <?php endif; ?>

                <tr>
                    <?php if ($merchant_visible): ?>
                        <td class="col-7 col-sm-4 px-2 px-md-3 py-2 align-middle cegg-price-comparison-merchant">
                            <div class="d-flex flex-wrap align-items-center gap-1">
                                <?php TemplateHelper::openATag($item, $params, array('class' => 'text-body text-decoration-none overflow-hidden me-1')); ?>
                                <div class="d-flex align-items-center text-nowrap">
                                    <?php TemplateHelper::icon($item, $params, 'me-2 flex-shrink-0'); ?>
                                    <span class="text-truncate"><?php TemplateHelper::merchant($item); ?></span>
                                </div>
                                <?php TemplateHelper::closeATag(); ?>
                                <?php if ($this->isVisible('badge', false)): ?>
                                    <div class="small lh-1"><?php TemplateHelper::badge3($item); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php if ($price_html !== ''): ?>
                                <div class="d-sm-none mt-1"><?php echo $price_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by TemplateHelper above ?></div>
                            <?php endif; ?>
                            <?php if ($coupon = $this->couponChip()): ?>
                                <div class="cegg-coupon-wrap"><?php TemplateHelper::couponChip($item, $coupon, $params); ?></div>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td class="<?php echo esc_attr($merchant_visible ? 'd-none d-sm-table-cell col-sm-4' : 'col-7 col-sm-4'); ?> text-center align-middle py-2">
                        <?php echo $price_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by TemplateHelper above ?>
                    </td>
                    <td class="col-5 col-sm-4 text-center p-0 align-middle bg-primary cegg-price-comparison-cta">
                        <?php TemplateHelper::openATag($item, $params, array('class' => 'text-decoration-none')); ?>
                        <div class="btn btn-primary rounded-0 w-100 h-100">
                            <?php TemplateHelper::buyNowBtnText(true, $item, $btn_text); ?>
                        </div>
                        <?php TemplateHelper::closeATag(); ?>
                    </td>
                </tr>

            <?php endforeach; ?>
        </tbody>
    </table>

    <?php $this->renderBlock('disclaimer'); ?>

</div>