<?php

namespace ContentEgg\application\EggBlocks\blocks\proscons\variants;

defined('ABSPATH') || exit;

class InlineVariant
{
    public static function render(array $pros, array $cons, array $best_for, array $not_for, array $labels, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <div class="eggb-block eggb-pros-cons eggb-pros-cons--inline d-flex flex-column gap-1 ps-3<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

            <?php if (!empty($best_for)): ?>
            <div class="d-flex gap-2">
                <span class="eggb-pc-inline-label flex-shrink-0"><?php echo esc_html($labels['best_for']); ?></span>
                <span><?php echo esc_html(implode(' · ', $best_for)); ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($not_for)): ?>
            <div class="d-flex gap-2">
                <span class="eggb-pc-inline-label flex-shrink-0"><?php echo esc_html($labels['not_for']); ?></span>
                <span><?php echo esc_html(implode(' · ', $not_for)); ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($pros)): ?>
            <div class="d-flex gap-2">
                <span class="eggb-pc-inline-label eggb-pc-inline-label--positive flex-shrink-0"><?php echo esc_html($labels['pros']); ?></span>
                <span><?php echo esc_html(implode(' · ', $pros)); ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($cons)): ?>
            <div class="d-flex gap-2">
                <span class="eggb-pc-inline-label eggb-pc-inline-label--negative flex-shrink-0"><?php echo esc_html($labels['cons']); ?></span>
                <span><?php echo esc_html(implode(' · ', $cons)); ?></span>
            </div>
            <?php endif; ?>

        </div>
        <?php
    }
}
