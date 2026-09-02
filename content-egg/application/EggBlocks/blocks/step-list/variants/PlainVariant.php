<?php

namespace ContentEgg\application\EggBlocks\blocks\steplist\variants;

defined('ABSPATH') || exit;

class PlainVariant
{
    /**
     * Rail-aligned, but not built from rail cells.
     *
     * The step text lines up with every other block's content column, so
     * the block joins the article's rhythm. The numeral is set large
     * enough to hold the gutter on its own — at label size it left the
     * column looking broken.
     *
     * The markup stays a real ordered list numbered by a CSS counter:
     * these steps are genuinely ordered, so the numbering belongs to the
     * document. Each li carries its own grid on the same --eggb-rail-w
     * token as .eggb-plain, which keeps the alignment in sync without
     * `display: contents` — that has historically stripped list semantics
     * in some browsers.
     */
    public static function render(array $step_list, string $theme_class = '', string $data_theme = ''): void
    {
        $classes = 'eggb-block eggb-step-list eggb-step-list--plain';
        if ($theme_class !== '')
        {
            $classes .= ' ' . $theme_class;
        }
        ?>
        <section class="<?php echo esc_attr($classes); ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

            <?php if ($step_list['section_label'] !== '' || $step_list['title'] !== '') : ?>
                <div class="eggb-sl-plain-head">
                    <?php if ($step_list['section_label'] !== '') : ?>
                        <span class="eggb-sl-plain-eyebrow"><?php echo esc_html($step_list['section_label']); ?></span>
                    <?php endif; ?>
                    <?php if ($step_list['title'] !== '') : ?>
                        <<?php echo esc_attr($step_list['heading_tag']); ?> class="eggb-block-title eggb-block-title--sm eggb-sl-plain-heading"><?php echo esc_html($step_list['title']); ?></<?php echo esc_attr($step_list['heading_tag']); ?>>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <ol class="eggb-sl-plain-steps">
                <?php foreach ($step_list['steps'] as $step) : ?>
                    <li>
                        <div class="eggb-sl-plain-body">
                            <?php if ($step['title'] !== '') : ?>
                                <div class="eggb-sl-plain-title"><?php echo esc_html($step['title']); ?></div>
                            <?php endif; ?>
                            <?php if ($step['description'] !== '') : ?>
                                <div class="eggb-sl-plain-desc"><?php echo wp_kses_post($step['description']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>

            <?php if ($step_list['note'] !== '') : ?>
                <div class="eggb-sl-plain-note"><?php echo wp_kses_post($step_list['note']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php endif; ?>

        </section>
        <?php
    }
}
