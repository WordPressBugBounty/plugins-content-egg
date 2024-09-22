<?php
/*
 * Name: Sorted list with store logos and shipping price
 * Modules:
 * Module Types: PRODUCT
 *
 */

defined('\ABSPATH') || exit;

__('Sorted list with store logos and shipping price', 'content-egg-tpl');

use ContentEgg\application\helpers\TemplateHelper;
use ContentEgg\application\helpers\TextHelper;

use function ContentEgg\prn;

if (isset($data['Amazon']) || isset($data['AmazonNoApi']))
    \wp_enqueue_script('cegg-frontend', \ContentEgg\PLUGIN_RES . '/js/frontend.js', array('jquery'));

$all_items = TemplateHelper::sortAllByPrice($data, $order, $sort);
$amazon_last_updated = TemplateHelper::getLastUpdateFormattedAmazon($data);
$delivery_at_checkout = false;

?>

<div class="egg-container">
    <?php if ($title) : ?>
        <h3><?php echo \esc_html($title); ?></h3>
    <?php endif; ?>

    <div class="egg-listcontainer cegg-list-withlogos">
        <?php foreach ($all_items as $key => $item) : ?>
            <div class="cegg-list-logo-title cegg-mt10 visible-xs text-center">
                <a<?php TemplateHelper::printRel(); ?> target="_blank" href="<?php echo esc_url_raw($item['url']); ?>"><?php echo esc_html(TextHelper::truncate($item['title'], 100)); ?></a>
            </div>

            <div class="row-products">
                <div class="col-md-2 col-sm-2 col-xs-12 cegg-image-cell">
                    <?php if ($logo = TemplateHelper::getMerhantLogoUrl($item, true)) : ?>
                        <a<?php TemplateHelper::printRel(); ?> target="_blank" href="<?php echo esc_url_raw($item['url']); ?>">
                            <img class="cegg-merhant-logo" src="<?php echo \esc_attr($logo); ?>" alt="<?php echo \esc_attr($item['domain']); ?>" />
                            </a>
                        <?php endif; ?>
                </div>

                <div class="col-md-4 col-sm-4 col-xs-12 cegg-price-cell text-center">
                    <div class="cegg-price-row">

                        <?php if ($item['price']) : ?>
                            <div class="cegg-price cegg-price-color cegg-price-<?php echo \esc_attr(TemplateHelper::getStockStatusClass($item)); ?>"><?php echo esc_html(TemplateHelper::formatPriceCurrency($item['price'], $item['currencyCode'])); ?></div>
                        <?php endif; ?>
                        <?php if ($item['priceOld']) : ?>
                            <div class="text-muted"><s><?php echo esc_html(TemplateHelper::formatPriceCurrency($item['priceOld'], $item['currencyCode'])); ?></s></div>
                        <?php endif; ?>
                        <?php if ($stock_status = TemplateHelper::getStockStatusStr($item)) : ?>
                            <div title="<?php echo \esc_attr(sprintf(TemplateHelper::__('Last updated on %s'), TemplateHelper::getLastUpdateFormatted($item['module_id'], $post_id))); ?>" class="cegg-lineheight15 stock-status status-<?php echo \esc_attr(TemplateHelper::getStockStatusClass($item)); ?>">
                                <?php echo \esc_html($stock_status); ?>
                            </div>
                        <?php endif; ?>

                        <div class="visible-xs cegg-shipping-xs cegg-shipping-details-xs">
                            <?php if ($item['price']) : ?>
                                <?php if (!isset($item['shipping_cost']) || $item['shipping_cost'] == '') : ?>
                                    <?php echo esc_html(TemplateHelper::__('+ Delivery *')); ?>
                                <?php else : ?>
                                    <?php if (is_numeric($item['shipping_cost']) && (float) $item['shipping_cost'] == 0) : ?>
                                        <?php echo esc_html(TemplateHelper::__('Free delivery')); ?>
                                    <?php else : ?>
                                        <?php echo esc_html(sprintf(TemplateHelper::__('%s incl. delivery'), TemplateHelper::formatPriceCurrency($item['total_price'], $item['currencyCode']))); ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>

                        </div>

                        <?php if ($item['module_id'] == 'Amazon') : ?>

                            <?php if (!empty($item['extra']['totalNew']) && $item['extra']['totalNew'] > 1) : ?>
                                <div class="cegg-font60 cegg-lineheight15">
                                    <?php echo esc_html(sprintf(TemplateHelper::__('%d new from %s'), $item['extra']['totalNew'], TemplateHelper::formatPriceCurrency($item['extra']['lowestNewPrice'], $item['currencyCode']))); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($item['extra']['totalUsed'])) : ?>
                                <div class="cegg-font60 cegg-lineheight15">
                                    <?php echo esc_html(sprintf(TemplateHelper::__('%d used from %s'), $item['extra']['totalUsed'], TemplateHelper::formatPriceCurrency($item['extra']['lowestUsedPrice'], $item['currencyCode']))); ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($item['price'] && ($item['module_id'] == 'Amazon' || $item['module_id'] == 'AmazonNoApi')) : ?>
                            <div class="cegg-font60 cegg-lineheight15">
                                <?php echo esc_html(sprintf(TemplateHelper::__('as of %s'), TemplateHelper::dateFormatFromGmtAmazon($item['module_id'], $item['last_update']))); ?>
                                <?php TemplateHelper::printAmazonDisclaimer(); ?>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>

                <div class="col-md-4 col-sm-4 text-center hidden-xs cegg-shipping-cell">
                    <?php if ($item['price']) : ?>
                        <?php echo esc_html(TemplateHelper::formatPriceCurrency($item['total_price'], $item['currencyCode'])); ?>
                        <div class="cegg-shipping-details">
                            <?php if (!isset($item['shipping_cost']) || $item['shipping_cost'] == '') : ?>
                                <?php $delivery_at_checkout = true; ?>
                                <?php echo esc_html(TemplateHelper::__('+ Delivery *')); ?>
                            <?php else : ?>
                                <?php if (is_numeric($item['shipping_cost']) && (float) $item['shipping_cost'] == 0) : ?>
                                    <?php echo esc_html(TemplateHelper::__('Free delivery')); ?>
                                <?php else : ?>
                                    <?php echo esc_html(sprintf(TemplateHelper::__('Incl. %s delivery'), TemplateHelper::formatPriceCurrency($item['shipping_cost'], $item['currencyCode']))); ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-2 col-sm-2 col-xs-12 cegg-btn-cell">
                    <div class="cegg-btn-row">
                        <a<?php TemplateHelper::printRel(); ?> target="_blank" href="<?php echo esc_url_raw($item['url']); ?>" class="btn btn-danger btn-block"><span><?php TemplateHelper::buyNowBtnText(true, $item, $btn_text); ?></span></a>
                    </div>
                    <?php if ($merchant = TemplateHelper::getMerchantName($item)) : ?>
                        <div class="text-center">
                            <small class="text-muted title-case">
                                <?php echo \esc_html($merchant); ?>
                                <?php TemplateHelper::printShopInfo($item); ?>
                            </small>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        <?php endforeach; ?>

    </div>
    <?php if ($delivery_at_checkout) : ?>
        <div class="row cegg-no-top-margin" style="padding: 0px;">
            <div class="col-md-12 text-right text-muted cegg-delivery-cost-notice">
                <?php echo esc_html(sprintf(TemplateHelper::__('* Delivery cost shown at checkout.'), $amazon_last_updated)); ?>
            </div>
        </div>
    <?php endif; ?>
</div>