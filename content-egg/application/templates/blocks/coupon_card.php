<?php

defined('\ABSPATH') || exit;

use ContentEgg\application\helpers\TemplateHelper;

/**
 * One shop-coupon card.
 *
 * Extracted from block_shop_coupons so the same card renders after a block (in
 * a loop, for the cards display mode) and inside item_row (once, for a coupon
 * bound to that product). One markup, one stylesheet, one set of behaviours -
 * which is the whole reason coupons live in one store instead of being
 * duplicated onto product fields.
 *
 * Receives $row from ShopCoupons::cardRows(). Calls nothing on the template
 * manager, so it is safe to render inside a block that is already rendering.
 */

if (empty($row) || empty($row['coupon']))
    return;

TemplateHelper::shopCouponCss();

?>

    <?php
    $c = $row['coupon'];
    $code = trim((string) $c['code']);
    $discount = trim((string) $c['discount']);
    $title = trim((string) $c['title']);
    $description = isset($c['description']) ? trim((string) $c['description']) : '';
    $image = isset($c['image']) ? trim((string) $c['image']) : '';
    $note = TemplateHelper::couponDateNote($c);
    ?>
<?php

// Compact cards put the coupon image above the button instead of in a leading
// column of its own: in a product's column the horizontal room is what is
// scarce and the stub's vertical room is going spare, so the text gets the
// width back and the artwork sits with the call to action.
$compact = !empty($row['compact']);

// With no button the stub holds nothing, so it goes entirely - and with it the
// perforation and the notch punched at it. The artwork returns to the leading
// slot, which is the only one left.
$has_stub = empty($row['hide_button']);
$image_in_stub = $compact && $has_stub;

?>

    <div class="cegg-shop-coupon<?php echo $compact ? ' cegg-shop-coupon--compact' : ''; ?><?php echo $has_stub ? '' : ' cegg-shop-coupon--nostub'; ?>">

        <?php // The coupon's own image, when it has one. Distinct from the shop
              // logo in the head: this is artwork for THIS offer, so it gets a
              // slot of its own and the logo stays a small identifier. ?>
        <?php if ($image !== '' && !$image_in_stub): ?>
            <div class="cegg-shop-coupon__image">
                <img src="<?php echo \esc_url($image); ?>" alt="<?php echo \esc_attr($title !== '' ? $title : $row['shop']); ?>" loading="lazy"
                     onerror="this.closest('.cegg-shop-coupon__image').remove()" />
            </div>
        <?php endif; ?>

        <div class="cegg-shop-coupon__body">

            <div class="cegg-shop-coupon__head">
                <?php if ($row['logo']): ?>
                    <span class="cegg-shop-coupon__logo">
                        <?php // The provider logo can 404 - Clearbit, the historical
                              // default, stopped serving images entirely. A broken-image
                              // icon in a card is worse than no logo, and the shop name
                              // is right beside it either way. ?>
                        <img src="<?php echo \esc_url($row['logo']); ?>" alt="<?php echo \esc_attr($row['shop']); ?>" loading="lazy"
                             onerror="this.closest('.cegg-shop-coupon__logo').remove()" />
                    </span>
                <?php endif; ?>

                <?php if ($discount !== ''): ?>
                    <span class="badge rounded-pill text-bg-danger fw-semibold cegg-shop-coupon__discount"><?php echo \esc_html($discount); ?></span>
                <?php endif; ?>

                <span class="cegg-shop-coupon__shop text-body-secondary"><?php echo \esc_html($row['shop']); ?></span>
            </div>

            <?php // The title is the offer when there is no code, which is the
                  // majority of imported coupons - it is never truncated away. ?>
            <?php if ($title !== ''): ?>
                <div class="cegg-shop-coupon__title fw-semibold"><?php echo \esc_html($title); ?></div>
            <?php endif; ?>

            <?php // Collapsed, whatever its length: an imported description runs
                  // from one line to a paragraph, and a list of cards has to stay
                  // scannable. Native details/summary - no JavaScript. Skipped
                  // when it only repeats the title, which some feeds do. ?>
            <?php if ($description !== '' && $description !== $title): ?>
                <details class="cegg-shop-coupon__details">
                    <summary class="cegg-shop-coupon__summary"><?php echo \esc_html(TemplateHelper::__('See details')); ?></summary>
                    <div class="cegg-shop-coupon__desc"><?php echo \esc_html($description); ?></div>
                </details>
            <?php endif; ?>

            <?php if ($note !== ''): ?>
                <div class="cegg-shop-coupon__meta">
                    <span class="cegg-coupon-date">
                        <svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.3" /><path d="M8 4.6V8l2.4 1.6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        <?php echo \esc_html($note); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($has_stub): ?>
        <div class="cegg-shop-coupon__stub">
            <?php if ($image !== '' && $image_in_stub): ?>
                <div class="cegg-shop-coupon__image cegg-shop-coupon__image--stub">
                    <img src="<?php echo \esc_url($image); ?>" alt="<?php echo \esc_attr($title !== '' ? $title : $row['shop']); ?>" loading="lazy"
                         onerror="this.closest('.cegg-shop-coupon__image').remove()" />
                </div>
            <?php endif; ?>
            <?php if ($code !== ''): ?>
                <?php // "Show Code" rather than the bare code, matching the coupon
                      // modules: one click opens the shop AND reveals the code, so
                      // the visit happens before the code is used. Reveal and copy
                      // both come from the shared handler. ?>
                <a class="btn btn-<?php echo \esc_attr($row['btn_variant']); ?> w-100 cegg-shop-coupon__reveal"
                   href="<?php echo \esc_url($row['url']); ?>" target="_blank" rel="nofollow sponsored noopener"
                   data-cegg-coupon-reveal
                   data-cegg-coupon-code="<?php echo \esc_attr($code); ?>"
                   data-cegg-copied-label="<?php echo \esc_attr(TemplateHelper::__('Copied!')); ?>"
                   title="<?php echo \esc_attr(TemplateHelper::couponTooltip($c)); ?>"><?php echo \esc_html(TemplateHelper::__('Show Code')); ?></a>
            <?php else: ?>
                <a class="btn btn-<?php echo \esc_attr($row['btn_variant']); ?> w-100" href="<?php echo \esc_url($row['url']); ?>"
                   target="_blank" rel="nofollow sponsored noopener"><?php echo \esc_html($row['btn_text']); ?></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
