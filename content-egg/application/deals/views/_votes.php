<?php

defined('\ABSPATH') || exit;

use ContentEgg\application\deals\DealsSettings;
use ContentEgg\application\deals\DealView;

/** @var int $post_id */
/** @var array $deal */
if (!DealsSettings::votingEnabled())
    return;

$closed = $deal['status'] === 'expired';
?>
<div class="cegg-deal-votes" data-deal-id="<?php echo (int) $post_id; ?>"<?php echo $closed ? ' data-closed="1"' : ''; ?>>
    <span class="cegg-deal-votes__label"><?php \esc_html_e('Deal score', 'content-egg-tpl'); ?></span>
    <span class="cegg-deal-votes__score" aria-live="polite"><?php echo (int) $deal['score']; ?></span>
    <button type="button" class="cegg-deal-votes__btn cegg-deal-votes__btn--up" data-dir="up" aria-label="<?php \esc_attr_e('Good deal', 'content-egg-tpl'); ?>" title="<?php \esc_attr_e('Good deal', 'content-egg-tpl'); ?>"<?php \disabled($closed); ?>><?php echo DealView::icon('up'); // phpcs:ignore ?></button>
    <button type="button" class="cegg-deal-votes__btn cegg-deal-votes__btn--down" data-dir="down" aria-label="<?php \esc_attr_e('Not a good deal', 'content-egg-tpl'); ?>" title="<?php \esc_attr_e('Not a good deal', 'content-egg-tpl'); ?>"<?php \disabled($closed); ?>><?php echo DealView::icon('down'); // phpcs:ignore ?></button>
</div>
