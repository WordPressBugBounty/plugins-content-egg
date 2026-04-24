<?php

namespace ContentEgg\application\EggBlocks\blocks\proscons\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;

defined('ABSPATH') || exit;

class DefaultVariant
{
    public static function render(array $pros, array $cons, array $best_for, array $not_for, string $pros_col, string $cons_col, array $labels, string $theme_class = '', string $data_theme = ''): void
    {
        $has_best_for = !empty($best_for);
        $has_not_for  = !empty($not_for);
        $has_fit_for  = $has_best_for || $has_not_for;
        $best_col     = $has_not_for ? 'col-12 col-md-6' : 'col-12';
        ?>
        <div class="eggb-block eggb-block--panel eggb-block--accented eggb-pros-cons eggb-pros-cons--default d-flex flex-column gap-3<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

            <?php if ($has_fit_for): ?>
            <div class="row g-3">
                <?php if ($has_best_for): ?>
                <div class="<?php echo esc_attr($best_col); ?>">
                    <span class="eggb-section-title eggb-section-title--muted"><?php echo esc_html($labels['best_for']); ?></span>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach ($best_for as $item): ?>
                            <span class="eggb-chip"><?php echo esc_html($item); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($has_not_for): ?>
                <div class="col-12 col-md-6">
                    <span class="eggb-section-title eggb-section-title--muted"><?php echo esc_html($labels['not_for']); ?></span>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach ($not_for as $item): ?>
                            <span class="eggb-chip"><?php echo esc_html($item); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="eggb-rule"></div>
            <?php endif; ?>

            <div class="row gy-3 gx-4">
                <?php if (!empty($pros)): ?>
                <div class="<?php echo esc_attr($pros_col); ?>">
                    <span class="eggb-section-title eggb-section-title--positive"><?php echo esc_html($labels['pros']); ?></span>
                    <ul class="eggb-item-list">
                        <?php foreach ($pros as $item): ?>
                        <li>
                            <?php echo EggbIcons::get('check-circle', 'eggb-item-icon eggb-item-icon--positive'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
                            <?php echo EggbIcons::get('x-circle', 'eggb-item-icon eggb-item-icon--negative'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php echo wp_kses_post($item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>

        </div>
        <?php
    }
}
