<?php

/*
 * Name: Coupon ticket
 * Module Types: COUPON
 */

defined('\ABSPATH') || exit;

use ContentEgg\application\helpers\TemplateHelper;

// A "ticket / voucher" block template for COUPON modules — an alternative layout to
// the horizontal list (block_coupons.php). Each coupon is a two-part ticket: an offer
// body and a tear-off code stub, split by a dashed perforation with semicircle notches
// punched via CSS mask (theme-agnostic — works on any page background). The trade-off
// of the mask is that it clips box-shadow/border on the notch curve, so the ticket sits
// on a subtle background fill instead of a shadowed card. The reveal uses ticket-only
// class names (.cegg-ticket__reveal / __code-box / __code-cta) driven by this file's own
// inline script, deliberately NOT the shared .cegg_coupon_btn classes — so the list
// template's unprefixed coupon CSS can't leak in when both render on the same page. Its
// CSS lives under cegg_coupons_ticket_css_enqueue() to avoid any function-name collision.

$div_id = TemplateHelper::generateGlobalId('cegg_coupons_ticket_');

$advertiser_ids = array();
foreach ($items as $item)
{
    $advertiser_ids[$item['domain']] = 'cegg_coupon_' . crc32($item['domain']);
}

if (!$params['btn_variant'])
    $params['btn_variant'] = 'primary';

TemplateHelper::addShopInfoOffcanvases($items, $params);

?>

<div id="<?php echo esc_attr($div_id); ?>" class="container px-0 mb-5 mt-1 cegg-ticket-list" <?php $this->colorMode(); ?>>
    <div class="cegg-ticket-grid">
        <?php foreach ($items as $i => $item): ?>
            <?php $this->setItem($item, $i); ?>

            <div data-advertiser-id="<?php echo esc_attr($advertiser_ids[$item['domain']]); ?>" class="<?php echo esc_attr($advertiser_ids[$item['domain']]); ?> cegg-ticket">

                <div class="cegg-ticket__body">

                    <div class="cegg-ticket__head">
                        <?php if ($this->isVisible('number', true)): ?>
                            <span class="cegg-ticket__number">
                                <?php TemplateHelper::number($item, $params, $i, 'primary'); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($this->isVisible('img')): ?>
                            <span class="cegg-ticket__logo">
                                <?php TemplateHelper::displayImage($item, 190, 170, array('class' => 'object-fit-contain')); ?>
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($item['extra']['discount'])): ?>
                            <span class="badge rounded-pill text-bg-danger fw-semibold cegg-ticket__discount">
                                <?php echo esc_html($item['extra']['discount']); ?> <?php TemplateHelper::esc_html_e('OFF') ?>
                            </span>
                        <?php elseif ($item['module_id'] == 'TradedoublerCoupons' && $item['extra']['discountAmount']): ?>
                            <span class="badge rounded-pill text-bg-danger fw-semibold cegg-ticket__discount">
                                <?php if (!(bool) $item['extra']['isPercentage']) echo esc_html(TemplateHelper::currencyTyping($item['extra']['currencyId'])); ?><?php echo esc_html($item['extra']['discountAmount']); ?><?php if ((bool) $item['extra']['isPercentage']) echo '%'; ?> <?php TemplateHelper::esc_html_e('OFF') ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($this->isVisible('badge')): ?>
                        <div class="cegg-ticket__badges"><?php TemplateHelper::badge3($item); ?></div>
                    <?php endif; ?>

                    <?php if ($this->isVisible('title')): ?>
                        <?php TemplateHelper::title($item, 'cegg-ticket__title h5 fw-semibold cegg-text-truncate-2', 'div', $params); ?>
                    <?php endif; ?>

                    <?php if ($this->isVisible('subtitle')): ?>
                        <div class="card-subtitle fs-6 text-body-secondary cegg-text-truncate-2"><?php TemplateHelper::subtitle($item); ?></div>
                    <?php endif; ?>

                    <?php if ($this->isVisible('rating')): ?>
                        <div class="fs-6"><?php TemplateHelper::ratingStars($item, true); ?></div>
                    <?php endif; ?>

                    <?php if ($this->isVisible('description')): ?>
                        <div class="cegg-desc-small card-text small lh-sm cegg-text-truncate-2"><?php echo \wp_kses_post($item['description']); ?></div>
                    <?php endif; ?>

                    <div class="cegg-ticket__meta">
                        <?php if ($this->isVisible('shop_info')): ?>
                            <span class="cegg-ticket__shop text-truncate"><small><?php TemplateHelper::shopInfo($item); ?></small></span>
                        <?php elseif ($this->isVisible('merchant')): ?>
                            <span class="cegg-ticket__shop text-body-secondary text-truncate"><small><?php TemplateHelper::merchant($item); ?></small></span>
                        <?php endif; ?>

                        <?php if ($this->isVisible('endDate') && !empty($item['endDate'])): ?>
                            <span class="cegg-coupon-date">
                                <svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.3" /><path d="M8 4.6V8l2.4 1.6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                <?php echo esc_html(sprintf(TemplateHelper::__('Valid until %s'), TemplateHelper::formatDate($item['endDate']))); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                </div>

                <div class="cegg-ticket__stub">
                    <?php if (!empty($item['code']) && $this->isVisible('code') && $this->isVisible('coupon_reveal')): ?>
                        <span data-advertiser-id="<?php echo esc_attr($advertiser_ids[$item['domain']]); ?>" data-uri="<?php echo \esc_url($item['url']); ?>" class="cegg-ticket__reveal">
                            <span class="cegg-ticket__code-box" data-code="<?php echo \esc_attr($item['code']); ?>"></span>
                            <span class="cegg-ticket__code-cta"><?php echo esc_html(TemplateHelper::__('Show Code')); ?></span>
                        </span>
                    <?php elseif (!empty($item['code']) && $this->isVisible('code')): ?>
                        <span class="cegg-ticket__code-chip"><?php echo \esc_html($item['code']); ?></span>
                        <div class="d-grid w-100"><?php TemplateHelper::button($item, $params, array(), 'link', true); ?></div>
                    <?php else: ?>
                        <div class="d-grid w-100"><?php TemplateHelper::button($item, $params, array(), 'link', true); ?></div>
                    <?php endif; ?>
                </div>

            </div>
        <?php endforeach; ?>
    </div>

    <?php $this->renderBlock('disclaimer'); ?>
</div>

<?php if (!function_exists('cegg_coupons_ticket_css_enqueue')): ?>
    <?php function cegg_coupons_ticket_css_enqueue()
    { ?>
        <style>
            .cegg-ticket-grid {
                display: flex;
                flex-direction: column;
                gap: 1rem;
            }

            .cegg-ticket {
                --stub: 150px;
                --notch: 12px;
                position: relative;
                display: flex;
                align-items: stretch;
                background: var(--bs-tertiary-bg, #f6f7f9);
                border-radius: .5rem;
                -webkit-mask:
                    radial-gradient(var(--notch) at calc(100% - var(--stub)) 0, #0000 98%, #000) top / 100% 51% no-repeat,
                    radial-gradient(var(--notch) at calc(100% - var(--stub)) 100%, #0000 98%, #000) bottom / 100% 51% no-repeat;
                mask:
                    radial-gradient(var(--notch) at calc(100% - var(--stub)) 0, #0000 98%, #000) top / 100% 51% no-repeat,
                    radial-gradient(var(--notch) at calc(100% - var(--stub)) 100%, #0000 98%, #000) bottom / 100% 51% no-repeat;
            }

            .cegg-ticket__number {
                display: inline-flex;
                flex: 0 0 auto;
                align-items: center;
            }

            .cegg-ticket__body {
                flex: 1 1 auto;
                min-width: 0;
                display: flex;
                flex-direction: column;
                justify-content: center;
                gap: .4rem;
                padding: 1rem 1.25rem;
            }

            .cegg-ticket__head {
                display: flex;
                align-items: center;
                gap: .6rem;
            }

            .cegg-ticket__logo {
                display: inline-flex;
                flex: 0 0 auto;
                width: 44px;
                height: 44px;
            }

            .cegg-ticket__logo img {
                width: 100%;
                height: 100%;
                object-fit: contain;
            }

            .cegg-ticket__discount {
                font-size: .95rem;
            }

            .cegg-ticket__title {
                margin: 0;
            }

            .cegg-ticket__meta {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: .35rem .75rem;
                margin-top: .15rem;
                min-width: 0;
            }

            .cegg-ticket__shop {
                display: inline-block;
                max-width: 100%;
                font-size: .8125rem;
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

            .cegg-ticket__stub {
                flex: 0 0 var(--stub);
                width: var(--stub);
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: .5rem;
                padding: 1rem .85rem;
                border-left: 2px dashed var(--bs-border-color, rgba(0, 0, 0, .18));
                text-align: center;
            }

            .cegg-ticket__reveal {
                position: relative;
                display: block;
                width: 100%;
                min-height: 40px;
                cursor: pointer;
            }

            .cegg-ticket__code-box {
                position: absolute;
                inset: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0;
                padding: 0 .4rem;
                border: 2px dashed rgba(var(--cegg-primary-rgb));
                border-radius: .375rem;
                background: var(--cegg-primary-bg-subtle);
                font-weight: 600;
                letter-spacing: .04em;
                white-space: nowrap;
                overflow: hidden;
            }

            .cegg-ticket__code-box::before {
                content: attr(data-code);
            }

            /* Once revealed the CTA overlay is gone, so drop the absolute overlay and
               let the box flow + wrap to fit codes of any length (no clipping). */
            .cegg-ticket__code-box.is-revealed {
                position: relative;
                inset: auto;
                height: auto;
                min-height: 40px;
                padding: .3rem .5rem;
                gap: .35rem;
                letter-spacing: .02em;
                white-space: normal;
                overflow: visible;
                cursor: pointer;
            }

            .cegg-ticket__code-box.is-revealed::before {
                content: none;
            }

            .cegg-ticket__code-text {
                min-width: 0;
                word-break: break-word;
            }

            .cegg-ticket__code-box svg {
                width: .95em;
                height: .95em;
                flex: 0 0 auto;
                opacity: .75;
            }

            .cegg-ticket__code-box.is-copied {
                color: var(--bs-success, #198754);
                border-color: var(--bs-success, #198754);
            }

            .cegg-ticket__code-box.is-copied svg {
                opacity: 1;
            }

            .cegg-ticket__code-cta {
                position: absolute;
                inset: 0;
                z-index: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: .375rem;
                background: var(--bs-primary, #0d6efd);
                color: #fff;
                font-weight: 600;
                font-size: .95rem;
                white-space: nowrap;
                transition: filter .2s ease;
            }

            .cegg-ticket__reveal:hover .cegg-ticket__code-cta {
                filter: brightness(.94);
            }

            .cegg-ticket__code-chip {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 100%;
                min-height: 40px;
                padding: .3rem .5rem;
                border: 2px dashed rgba(var(--cegg-primary-rgb));
                border-radius: .375rem;
                background: var(--cegg-primary-bg-subtle);
                font-weight: 600;
                letter-spacing: .02em;
                white-space: normal;
                word-break: break-word;
                text-align: center;
            }

            @media (max-width: 575.98px) {
                .cegg-ticket {
                    --stub: 116px;
                }

                .cegg-ticket__body {
                    padding: .85rem 1rem;
                }

                .cegg-ticket__stub {
                    padding: .85rem .5rem;
                }
            }
        </style>
    <?php } ?>
    <?php cegg_coupons_ticket_css_enqueue();
    ?>
<?php endif; ?>

<?php if ($this->isVisible('coupon_reveal')) : ?>
    <script>
        "use strict";
        document.addEventListener("DOMContentLoaded", function() {
            const scope = "div#<?php echo esc_attr($div_id); ?>";
            const copyLabel = "<?php echo esc_js(TemplateHelper::__('Copy code')); ?>";
            const copiedLabel = "<?php echo esc_js(TemplateHelper::__('Copied!')); ?>";
            const copyIcon = '<svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="5.5" y="5.5" width="9" height="9" rx="1.5" stroke="currentColor" stroke-width="1.3" /><path d="M3.2 10.5H3A1.5 1.5 0 0 1 1.5 9V3A1.5 1.5 0 0 1 3 1.5h6A1.5 1.5 0 0 1 10.5 3v.2" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" /></svg>';
            const checkIcon = '<svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8.4l3.2 3.2L13 4.8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg>';

            const copyText = function(text) {
                if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
                    return navigator.clipboard.writeText(text);
                }
                return new Promise(function(resolve, reject) {
                    try {
                        const ta = document.createElement("textarea");
                        ta.value = text;
                        ta.setAttribute("readonly", "");
                        ta.style.position = "absolute";
                        ta.style.left = "-9999px";
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand("copy");
                        document.body.removeChild(ta);
                        resolve();
                    } catch (e) {
                        reject(e);
                    }
                });
            };

            // Turn a revealed code box into a click-to-copy control: shows the code +
            // a copy glyph, and on click copies it and flips to a "Copied!" state.
            const setupCopy = function(code, value) {
                const renderCode = function() {
                    code.classList.remove("is-copied");
                    code.textContent = "";
                    const txt = document.createElement("span");
                    txt.className = "cegg-ticket__code-text";
                    txt.textContent = value;
                    code.appendChild(txt);
                    code.insertAdjacentHTML("beforeend", copyIcon);
                };

                code.classList.add("is-revealed");
                code.setAttribute("role", "button");
                code.setAttribute("tabindex", "0");
                code.setAttribute("title", copyLabel);
                code.setAttribute("aria-label", copyLabel);
                renderCode();

                let resetTimer = null;
                const doCopy = function(ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    copyText(value).then(function() {
                        code.classList.add("is-copied");
                        code.textContent = "";
                        code.insertAdjacentHTML("beforeend", checkIcon);
                        const done = document.createElement("span");
                        done.textContent = copiedLabel;
                        code.appendChild(done);
                        clearTimeout(resetTimer);
                        resetTimer = setTimeout(renderCode, 1600);
                    }).catch(function() {});
                };

                code.addEventListener("click", doCopy);
                code.addEventListener("keydown", function(ev) {
                    if (ev.key === "Enter" || ev.key === " ") {
                        doCopy(ev);
                    }
                });
            };

            const elements = document.querySelectorAll(scope + " .cegg-ticket__reveal");

            const clickHandler = function(event) {
                window.open(this.getAttribute("data-uri"), "_blank");

                const advertiserId = this.getAttribute("data-advertiser-id");
                const btnElements = document.querySelectorAll(
                    scope + " [data-advertiser-id='" + advertiserId + "'] .cegg-ticket__reveal"
                );

                btnElements.forEach(function(btnElem) {
                    const btnTxt = btnElem.querySelector(".cegg-ticket__code-cta");
                    if (btnTxt) {
                        btnTxt.style.visibility = "hidden";
                        btnTxt.style.pointerEvents = "none";
                    }

                    const code = btnElem.querySelector(".cegg-ticket__code-box");
                    if (code) {
                        const value = code.getAttribute("data-code") || "";
                        code.removeAttribute("data-code");
                        setupCopy(code, value);
                    }

                    btnElem.removeEventListener("click", clickHandler);
                });
            };

            elements.forEach(function(elem) {
                elem.addEventListener("click", clickHandler);
            });
        });
    </script>
<?php endif; ?>
