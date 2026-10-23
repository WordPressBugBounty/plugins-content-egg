<?php

defined('\ABSPATH') || exit;

use ContentEgg\application\deals\DealView;

/** @var int $post_id */
/** @var array $deal */
$expired = $deal['status'] === 'expired';
$link = \get_permalink($post_id);
$title = \get_the_title($post_id);
$comments = DealView::commentCount($post_id);
?>
<article class="cegg-deal-card<?php echo $expired ? ' is-expired' : ''; ?>">
    <a class="cegg-deal-card__media" href="<?php echo \esc_url($link); ?>" tabindex="-1" aria-hidden="true">
        <?php if ($deal['discount'] > 0) : ?><span class="cegg-deal-badge">-<?php echo (int) $deal['discount']; ?>%</span><?php endif; ?>
        <?php if (DealView::isHot($deal)) : ?><span class="cegg-deal-hot"><?php echo DealView::icon('hot'); // phpcs:ignore ?><?php \esc_html_e('Hot', 'content-egg-tpl'); ?></span><?php endif; ?>
        <?php if (\has_post_thumbnail($post_id)) : ?>
            <?php echo \get_the_post_thumbnail($post_id, 'medium', array('class' => 'cegg-deal-card__img', 'loading' => 'lazy')); ?>
        <?php elseif ($deal['img']) : ?>
            <img class="cegg-deal-card__img" src="<?php echo \esc_url($deal['img']); ?>" alt="" loading="lazy">
        <?php endif; ?>
    </a>
    <div class="cegg-deal-card__body">
        <?php if ($deal['store'] || $deal['coupon_code'] !== '') : ?>
            <div class="cegg-deal-card__store">
                <?php if ($deal['store']) : ?>
                    <a class="cegg-deal-card__store-link" href="<?php echo \esc_url($deal['store']['link']); ?>">
                        <?php if ($deal['store']['icon']) : ?><img class="cegg-deal-card__store-logo" src="<?php echo \esc_url($deal['store']['icon']); ?>" alt="" loading="lazy"><?php endif; ?>
                        <span><?php echo \esc_html($deal['store']['name']); ?></span>
                    </a>
                <?php endif; ?>
                <?php if ($deal['coupon_code'] !== '') : ?><span class="cegg-deal-card__coupon"><?php \esc_html_e('Coupon', 'content-egg-tpl'); ?></span><?php endif; ?>
            </div>
        <?php endif; ?>
        <h3 class="cegg-deal-card__title"><a href="<?php echo \esc_url($link); ?>"><?php echo \esc_html($title); ?></a></h3>
        <?php if ($expired) : ?>
            <span class="cegg-deal-flag"><?php echo \esc_html(DealView::reasonLabel($deal['reason'])); ?></span>
        <?php else : ?>
            <div class="cegg-deal-price cegg-deal-price--sm">
                <span class="cegg-deal-price__now"><?php echo \esc_html(DealView::price($deal['price'], $deal['currency'])); ?></span>
                <?php if ($deal['price_old'] > 0) : ?><del class="cegg-deal-price__old"><?php echo \esc_html(DealView::price($deal['price_old'], $deal['currency'])); ?></del><?php endif; ?>
                <?php if ($deal['discount'] > 0) : ?><span class="cegg-deal-price__off">-<?php echo (int) $deal['discount']; ?>%</span><?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="cegg-deal-card__foot">
            <span class="cegg-deal-card__score" title="<?php \esc_attr_e('Deal score', 'content-egg-tpl'); ?>"><?php echo DealView::icon('up'); // phpcs:ignore ?><?php echo (int) $deal['score']; ?></span>
            <?php if ($comments > 0) : ?><span class="cegg-deal-card__comments" title="<?php \esc_attr_e('Comments', 'content-egg-tpl'); ?>"><?php echo DealView::icon('comment'); // phpcs:ignore ?><?php echo (int) $comments; ?></span><?php endif; ?>
            <span class="cegg-deal-card__ago"><?php echo \esc_html(DealView::ago((int) \get_post_time('U', true, $post_id))); ?></span>
        </div>
    </div>
</article>
