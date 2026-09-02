<?php

namespace ContentEgg\application\EggBlocks\blocks\proscons\variants;

defined('ABSPATH') || exit;

class PlainVariant
{
    /**
     * Label-rail layout: one grid, one row per populated group.
     *
     * $pros_col / $cons_col are deliberately absent from the signature.
     * They are Bootstrap column classes for the side-by-side variants,
     * and these rows stack.
     */
    public static function render(array $pros, array $cons, array $best_for, array $not_for, string $quick_take, array $labels, string $theme_class = '', string $data_theme = ''): void
    {
        $classes = 'eggb-block eggb-plain eggb-pros-cons eggb-pros-cons--plain';
        if ($theme_class !== '')
        {
            $classes .= ' ' . $theme_class;
        }
        ?>
        <div class="<?php echo esc_attr($classes); ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

            <?php if ($quick_take !== '') : ?>
                <div class="eggb-plain-rail"><span class="eggb-rail-name"><?php echo esc_html($labels['quick_take']); ?></span></div>
                <div class="eggb-plain-col"><?php echo wp_kses_post($quick_take); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php endif; ?>

            <?php self::renderRow($labels['pros'], $pros, 'eggb-dot-list--positive'); ?>
            <?php self::renderRow($labels['cons'], $cons, 'eggb-dot-list--negative'); ?>
            <?php self::renderRow($labels['best_for'], $best_for, 'eggb-dot-list--muted'); ?>
            <?php self::renderRow($labels['not_for'], $not_for, 'eggb-dot-list--muted'); ?>

        </div>
        <?php
    }

    private static function renderRow(string $label, array $items, string $tone): void
    {
        if (empty($items))
        {
            return;
        }
        ?>
        <div class="eggb-plain-rail"><span class="eggb-rail-name"><?php echo esc_html($label); ?></span></div>
        <div class="eggb-plain-col">
            <ul class="eggb-dot-list <?php echo esc_attr($tone); ?>">
                <?php foreach ($items as $item) : ?>
                    <li><?php echo wp_kses_post($item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }
}
