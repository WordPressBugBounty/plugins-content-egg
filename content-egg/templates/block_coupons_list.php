<?php

/*
 * Name: Coupon list
 * Module Types: COUPON
 */

defined('\ABSPATH') || exit;

use ContentEgg\application\helpers\TemplateHelper;

// The list-style COUPON block template (id: coupons_list), rendered via
// [content-egg-block template=coupons_list] → ModuleViewer::viewBlockData, which
// aggregates every active COUPON module into one merged $items list. Unlike the
// module path it receives no global $module_id, so the per-module branch below
// reads $item['module_id']. Started as a copy of application/templates/data_coupon.php
// but has since been polished independently (refined discount pill, date chips, card
// hover); its CSS lives under cegg_coupons_list_css_enqueue() — a distinct function
// name from the module partial's, so the block's styles win even when both render on
// one page. See block_coupons_ticket.php for the voucher-style variant.

$div_id = TemplateHelper::generateGlobalId('cegg_coupons_');

$advertiser_ids = array();
foreach ($items as $item)
{
    $advertiser_ids[$item['domain']] = 'cegg_coupon_' . crc32($item['domain']);
}

if (!$params['btn_variant'])
    $params['btn_variant'] = 'primary';

TemplateHelper::addShopInfoOffcanvases($items, $params);

?>

<div id="<?php echo esc_attr($div_id); ?>" class="container px-0 mb-5 mt-1 cegg-coupon-list" <?php $this->colorMode(); ?>>
    <?php foreach ($items as $i => $item): ?>
        <?php $this->setItem($item, $i); ?>

        <div data-advertiser-id="<?php echo esc_attr($advertiser_ids[$item['domain']]); ?>" class="<?php echo esc_attr($advertiser_ids[$item['domain']]); ?> cegg-coupon-list-card cegg-card <?php echo $i < count($items) - 1 ? ' mb-3' : ''; ?><?php TemplateHelper::border($params); ?>">

            <?php if ($this->isVisible('number', true)): ?>
                <div class="position-absolute top-50 z-3 start-0 translate-middle">
                    <?php TemplateHelper::number($item, $params, $i, 'outline-primary'); ?>
                </div>
            <?php endif; ?>

            <div class="row p-2 px-md-3">

                <div class="col-2 align-self-center">
                    <?php if ($this->isVisible('img')): ?>
                        <div class="position-relative">
                            <div class="ratio<?php TemplateHelper::imgRatio($params, 'ratio-1x1'); ?>">
                                <?php TemplateHelper::displayImage($item, 190, 170, array('class' => 'object-fit-scale rounded')); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col align-self-center">
                    <div class="cegg-coupon-list-card-body">

                        <?php if ($this->isVisible('badge')): ?>
                            <?php TemplateHelper::badge3($item); ?>
                        <?php endif; ?>

                        <?php if (! empty($item['extra']['discount'])): ?>
                            <span class="badge rounded-pill text-bg-danger fw-semibold px-2 py-1 mb-1">
                                <?php echo esc_html($item['extra']['discount']); ?> <?php TemplateHelper::esc_html_e('OFF') ?>
                            </span>&nbsp;
                        <?php endif; ?>

                        <?php if ($item['module_id'] == 'TradedoublerCoupons' && $item['extra']['discountAmount']): ?>
                            <span class="badge rounded-pill text-bg-danger fw-semibold px-2 py-1 mb-2">
                                <?php if (!(bool) $item['extra']['isPercentage']) echo esc_html(TemplateHelper::currencyTyping($item['extra']['currencyId'])); ?>
                                <?php echo esc_html($item['extra']['discountAmount']); ?>
                                <?php if ((bool) $item['extra']['isPercentage']) echo '%'; ?>
                                <?php TemplateHelper::esc_html_e('OFF') ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($this->isVisible('title')): ?>
                            <?php TemplateHelper::title($item, 'card-title h5 fw-normal cegg-text-truncate-2', 'div', $params); ?>
                        <?php endif; ?>

                        <?php if ($this->isVisible('subtitle')): ?>
                            <div class="card-subtitle fs-6 text-body-secondary cegg-text-truncate-2"><?php TemplateHelper::subtitle($item); ?></div>
                        <?php endif; ?>

                        <?php if ($this->isVisible('rating')): ?>
                            <div class="pt-0 fs-5">
                                <?php TemplateHelper::ratingStars($item, true); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($this->isVisible('description')): ?>
                            <div class="cegg-desc-small card-text small lh-sm"><?php echo \wp_kses_post($item['description']); ?></div>
                        <?php endif; ?>

                    </div>
                </div>

                <div class="col-6 col-md-3 align-self-center offset-2 offset-md-0 py-3 text-center">

                    <div class="d-grid position-relative">
                        <?php if (!empty($item['code']) && $this->isVisible('code') && $this->isVisible('coupon_reveal')): ?>
                            <span data-advertiser-id="<?php echo esc_attr($advertiser_ids[$item['domain']]); ?>" data-uri="<?php echo \esc_url($item['url']); ?>" class="cegg_coupon_btn">
                                <div class="cegg_coupon_hidden_code rounded rounded-2 position-absolute top-0 start-0 fw-medium" data-code="<?php echo \esc_attr($item['code']); ?>"></div>
                                <div class="cegg_coupon_btn_txt bg-primary rounded-start "><?php echo esc_html(TemplateHelper::__('Show Code')); ?></div>
                            </span>
                        <?php elseif (!empty($item['code']) && $this->isVisible('code') && !$this->isVisible('coupon_reveal')) : ?>
                            <div class="cegg_coupon_hidden_code rounded rounded-2 fw-medium mb-2"><?php echo \esc_attr($item['code']); ?></div>
                            <div class="d-grid"> <?php TemplateHelper::button($item, $params, array(), 'link', true); ?></div>
                        <?php else : ?>
                            <div class="d-grid"> <?php TemplateHelper::button($item, $params, array(), 'link', true); ?></div>
                        <?php endif; ?>

                        <?php if ($this->isVisible('shop_info')) : ?>
                            <div class="position-relative fs-6 z-3 small text-truncate">
                                <small><?php TemplateHelper::shopInfo($item); ?></small>
                            </div>
                        <?php elseif ($this->isVisible('merchant')): ?>
                            <div class="cegg-merchant small fs-6 text-body-secondary text-truncate">
                                <small><?php TemplateHelper::merchant($item); ?></small>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
                <?php if ($this->isVisible('endDate') && !empty($item['endDate'])): ?>

                    <div class="col-10 offset-md-2">
                        <div class="cegg-coupon-dates d-flex flex-wrap gap-2 mt-1">
                            <span class="cegg-coupon-date">
                                <svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.3" /><path d="M8 4.6V8l2.4 1.6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                <?php echo esc_html(sprintf(TemplateHelper::__('Valid until %s'), TemplateHelper::formatDate($item['endDate']))); ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php endforeach; ?>

    <?php $this->renderBlock('disclaimer'); ?>
</div>

<?php if (!function_exists('cegg_coupons_list_css_enqueue')): ?>
    <?php function cegg_coupons_list_css_enqueue()
    { ?>
        <style>
            .cegg-coupon-list-card {
                transition: box-shadow .2s ease, transform .2s ease;
            }

            .cegg-coupon-list-card:hover {
                box-shadow: 0 .5rem 1.25rem rgba(0, 0, 0, .09);
            }

            .cegg-coupon-dates {
                line-height: 1;
            }

            .cegg-coupon-date {
                display: inline-flex;
                align-items: center;
                gap: .3rem;
                padding: .25rem .6rem;
                border-radius: 50rem;
                font-size: .8125rem;
                background: var(--cegg-secondary-bg, rgba(0, 0, 0, .05));
                color: var(--bs-secondary-color, #6c757d);
            }

            .cegg-coupon-date svg {
                width: .9em;
                height: .9em;
                flex: 0 0 auto;
            }

            .cegg_coupon_hidden_code {
                overflow: hidden;
                z-index: 1;
                width: 100%;
                height: 38px;
                border: 2px dashed rgba(var(--cegg-primary-rgb));
                border-radius: .375rem;
                background-color: var(--cegg-primary-bg-subtle) !important;
                line-height: 34px;
                letter-spacing: .03em;
            }

            .cegg_coupon_hidden_code:before {
                position: absolute;
                right: 6px;
                display: block;
                content: attr(data-code);
                white-space: nowrap;
            }

            .cegg_coupon_btn_txt:before {
                border-top: 38px solid rgba(var(--cegg-primary-rgb), var(--cegg-bg-opacity));
                border-right: 14px solid transparent;
                border-left: 0 solid transparent;
                content: "";
                display: inherit;
                height: 0;
                position: absolute;
                left: 100%;
                top: 0;
                transition: all .25s ease-out;
                width: 0;
            }

            .cegg_coupon_btn_txt {
                position: relative;
                z-index: 1;
                display: block;
                height: 38px;
                font-size: 16px;
                font-weight: 500;
                color: #fff;
                line-height: 38px;
                text-shadow: 0 1px 0 rgb(0 0 0 / 20%);
                padding: 0 10px;
                background: #0d6efd;
                border-radius: .375rem 0 0 .375rem;
                text-align: center;
                transition: width 0.5s ease;
            }

            .cegg_coupon_btn_txt {
                width: calc(100% - 32px);
                cursor: pointer;
            }

            .cegg_coupon_btn:hover .cegg_coupon_btn_txt {
                width: calc(100% - 45px);
            }
        </style>

    <?php } ?>
    <?php cegg_coupons_list_css_enqueue();
    ?>
<?php endif; ?>

<?php if ($this->isVisible('coupon_reveal')) : ?>
    <script>
        "use strict";
        document.addEventListener("DOMContentLoaded", function() {
            const elements = document.querySelectorAll("div#<?php echo esc_attr($div_id); ?> .cegg_coupon_btn");

            const clickHandler = function(event) {
                window.open(this.getAttribute("data-uri"), "_blank");

                const advertiserId = this.getAttribute("data-advertiser-id");
                const btnElements = document.querySelectorAll(
                    "div#<?php echo esc_attr($div_id); ?> [data-advertiser-id='" + advertiserId + "'] .cegg_coupon_btn"
                );

                btnElements.forEach((btnElem) => {
                    const btnTxt = btnElem.querySelector(".cegg_coupon_btn_txt");
                    if (btnTxt) {
                        btnTxt.style.visibility = "hidden";
                        btnTxt.style.pointerEvents = "none";
                    }

                    const code = btnElem.querySelector(".cegg_coupon_hidden_code");
                    if (code) {
                        code.innerHTML = code.getAttribute("data-code");
                        code.removeAttribute("data-code");
                        code.style.textAlign = "center";
                    }

                    btnElem.removeEventListener("click", clickHandler);
                });
            };

            elements.forEach((elem) => {
                elem.addEventListener("click", clickHandler);
            });
        });
    </script>
<?php endif; ?>
