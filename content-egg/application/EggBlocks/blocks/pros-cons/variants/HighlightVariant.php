<?php

namespace ContentEgg\application\EggBlocks\blocks\proscons\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;

defined('ABSPATH') || exit;

class HighlightVariant
{
    public static function render(array $pros, array $cons, array $best_for, array $not_for, string $quick_take, string $pros_col, string $cons_col, array $labels, string $theme_class = '', string $data_theme = ''): void
    {
        $has_fit_for = !empty($best_for) || !empty($not_for);
        ?>
        <div class="eggb-block eggb-block--panel eggb-pros-cons eggb-pros-cons--highlight p-3 p-md-4 d-flex flex-column gap-3<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

            <?php if ($has_fit_for): ?>
            <div class="d-flex flex-column gap-2">
                <?php if (!empty($best_for)): ?>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="eggb-section-title eggb-section-title--muted"><?php echo esc_html($labels['best_for']); ?></span>
                    <?php foreach ($best_for as $item): ?>
                        <span class="eggb-chip eggb-chip--positive"><?php echo esc_html($item); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($not_for)): ?>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="eggb-section-title eggb-section-title--muted"><?php echo esc_html($labels['not_for']); ?></span>
                    <?php foreach ($not_for as $item): ?>
                        <span class="eggb-chip eggb-chip--negative"><?php echo esc_html($item); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="row gy-3 gx-4">
                <?php if (!empty($pros)): ?>
                <div class="<?php echo esc_attr($pros_col); ?>">
                    <span class="eggb-section-title eggb-section-title--positive"><?php echo esc_html($labels['pros']); ?></span>
                    <ul class="eggb-item-list">
                        <?php foreach ($pros as $item): ?>
                        <li>
                            <?php echo EggbIcons::get('plus-circle-fill', 'eggb-item-icon eggb-item-icon--positive'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php echo wp_kses_post($item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if (!empty($cons)): ?>
                <div class="<?php echo esc_attr($cons_col); ?>">
                    <span class="eggb-section-title eggb-section-title--negative"><?php echo esc_html($labels['cons']); ?></span>
                    <ul class="eggb-item-list">
                        <?php foreach ($cons as $item): ?>
                        <li>
                            <?php echo EggbIcons::get('dash-circle-fill', 'eggb-item-icon eggb-item-icon--negative'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php echo wp_kses_post($item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($quick_take)): ?>
            <p class="eggb-pc-quicktake mb-0">
                <strong><?php echo esc_html($labels['quick_take']); ?></strong>
                <?php echo esc_html($quick_take); ?>
            </p>
            <?php endif; ?>

        </div>
        <?php
    }
}
