<?php

defined('\ABSPATH') || exit;

use ContentEgg\application\deals\DealTemplates;
use ContentEgg\application\deals\DealView;
use ContentEgg\application\helpers\TemplateHelper;

/** @var int $post_id */
/** @var array $deal */
/** @var array|null $item */
$expired = $deal['status'] === 'expired';
$cat = DealView::primaryCategory($post_id);
$cat_link = $cat ? \get_term_link($cat) : '';
$comments = DealView::commentCount($post_id);
?>
<div class="cegg-deal-hero<?php echo $expired ? ' is-expired' : ''; ?>">
    <div class="cegg-deal-hero__media">
        <?php if ($deal['discount'] > 0) : ?><span class="cegg-deal-badge">-<?php echo (int) $deal['discount']; ?>%</span><?php endif; ?>
        <?php if (\has_post_thumbnail($post_id)) : ?>
            <?php echo \get_the_post_thumbnail($post_id, 'large', array('class' => 'cegg-deal-hero__img')); ?>
        <?php elseif ($deal['img']) : ?>
            <img class="cegg-deal-hero__img" src="<?php echo \esc_url($deal['img']); ?>" alt="<?php echo \esc_attr(\get_the_title($post_id)); ?>">
        <?php endif; ?>
    </div>
    <div class="cegg-deal-hero__info">
        <div class="cegg-deal-hero__meta">
            <?php if ($deal['store']) : ?>
                <a class="cegg-deal-store" href="<?php echo \esc_url($deal['store']['link']); ?>">
                    <?php if ($deal['store']['icon']) : ?><img class="cegg-deal-store__logo" src="<?php echo \esc_url($deal['store']['icon']); ?>" alt=""><?php endif; ?>
                    <span><?php echo \esc_html($deal['store']['name']); ?></span>
                </a>
            <?php endif; ?>
            <?php if ($cat && !\is_wp_error($cat_link)) : ?><a class="cegg-deal-chip cegg-deal-chip--sm" href="<?php echo \esc_url($cat_link); ?>"><?php echo \esc_html($cat->name); ?></a><?php endif; ?>
            <span class="cegg-deal-hero__posted"><?php /* translators: %s: e.g. "5 hours ago" */ echo \esc_html(sprintf(__('Posted %s', 'content-egg-tpl'), DealView::ago((int) \get_post_time('U', true, $post_id)))); ?></span>
        </div>
        <?php if ($expired) : ?><div class="cegg-deal-flag"><?php echo \esc_html(DealView::reasonLabel($deal['reason'])); ?></div><?php endif; ?>
        <h1 class="cegg-deal-hero__title"><?php echo \esc_html(\get_the_title($post_id)); ?></h1>
        <div class="cegg-deal-price">
            <span class="cegg-deal-price__now"><?php echo \esc_html(DealView::price($deal['price'], $deal['currency'])); ?></span>
            <?php if ($deal['price_old'] > 0) : ?><del class="cegg-deal-price__old"><?php echo \esc_html(DealView::price($deal['price_old'], $deal['currency'])); ?></del><?php endif; ?>
            <?php if ($deal['discount'] > 0) : ?><span class="cegg-deal-price__off">-<?php echo (int) $deal['discount']; ?>%</span><?php endif; ?>
            <?php if (DealView::isHot($deal)) : ?><span class="cegg-deal-hot cegg-deal-hot--inline"><?php echo DealView::icon('hot'); // phpcs:ignore ?><?php \esc_html_e('Hot', 'content-egg-tpl'); ?></span><?php endif; ?>
        </div>
        <div class="cegg-deal-meta">
            <?php if ($deal['verified']) : ?>
                <span class="cegg-deal-meta__checked"><?php printf(\esc_html__('Checked %s ago', 'content-egg-tpl'), \esc_html(\human_time_diff($deal['verified'], time()))); ?></span>
            <?php endif; ?>
            <?php if (!$expired && DealView::isThirtyDayLow($post_id)) : ?>
                <span class="cegg-deal-lowest"><?php \esc_html_e('Lowest price in 30 days', 'content-egg-tpl'); ?></span>
            <?php endif; ?>
            <?php if (!$expired && $deal['ends'] > time()) : ?>
                <span class="cegg-deal-meta__ends"><?php echo DealView::icon('clock'); // phpcs:ignore ?><span class="cegg-deal-countdown" data-ends="<?php echo (int) $deal['ends']; ?>"></span></span>
            <?php endif; ?>
        </div>
        <?php echo DealTemplates::part('_coupon', array('deal' => $deal)); // phpcs:ignore ?>
        <?php if ($item && !empty($item['url'])) : ?>
            <a class="cegg-deal-btn" href="<?php echo \esc_url($item['url']); ?>" target="_blank"<?php TemplateHelper::printRel(); ?>>
                <?php echo $expired ? \esc_html__('Check current price', 'content-egg-tpl') : \esc_html(DealView::buttonText($item)); ?>
            </a>
        <?php endif; ?>
        <?php
        $votes = DealTemplates::part('_votes', array('post_id' => $post_id, 'deal' => $deal));
        $show_comments = \comments_open($post_id) || $comments > 0;
        if (trim($votes) !== '' || $show_comments) : ?>
        <div class="cegg-deal-hero__actions">
            <?php echo $votes; // phpcs:ignore ?>
            <?php if ($show_comments) : ?>
                <a class="cegg-deal-hero__comments" href="#cegg-deal-comments"><?php echo DealView::icon('comment'); // phpcs:ignore ?>
                    <?php /* translators: %d: number of comments */ echo \esc_html(sprintf(\_n('%d comment', '%d comments', $comments, 'content-egg-tpl'), $comments)); ?></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
