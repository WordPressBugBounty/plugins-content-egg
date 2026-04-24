<?php

namespace ContentEgg\application\EggBlocks\blocks\proscons\variants;

defined('ABSPATH') || exit;

class CompactVariant
{
    public static function render(array $pros, array $cons, array $best_for, array $not_for, string $pros_col, string $cons_col, array $labels, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <div class="eggb-block eggb-pros-cons eggb-pros-cons--compact<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="row gy-2 gx-4">

                <?php if (!empty($pros)): ?>
                <div class="<?php echo esc_attr($pros_col); ?>">
                    <span class="eggb-section-title eggb-section-title--positive"><?php echo esc_html($labels['pros']); ?></span>
                    <ul class="eggb-item-list small">
                        <?php foreach ($pros as $item): ?>
                        <li>
                            <span class="eggb-marker--positive flex-shrink-0" aria-hidden="true">+</span>
                            <?php echo wp_kses_post($item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if (!empty($cons)): ?>
                <div class="<?php echo esc_attr($cons_col); ?>">
                    <span class="eggb-section-title eggb-section-title--negative"><?php echo esc_html($labels['cons']); ?></span>
                    <ul class="eggb-item-list small">
                        <?php foreach ($cons as $item): ?>
                        <li>
                            <span class="eggb-marker--negative flex-shrink-0" aria-hidden="true">&minus;</span>
                            <?php echo wp_kses_post($item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if (!empty($best_for)): ?>
                <div class="<?php echo esc_attr($pros_col); ?> mt-3">
                    <span class="eggb-section-title eggb-section-title--muted"><?php echo esc_html($labels['best_for']); ?></span>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach ($best_for as $item): ?>
                            <span class="eggb-chip"><?php echo esc_html($item); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($not_for)): ?>
                <div class="<?php echo esc_attr($cons_col); ?> mt-3">
                    <span class="eggb-section-title eggb-section-title--muted"><?php echo esc_html($labels['not_for']); ?></span>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach ($not_for as $item): ?>
                            <span class="eggb-chip"><?php echo esc_html($item); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php
    }
}
