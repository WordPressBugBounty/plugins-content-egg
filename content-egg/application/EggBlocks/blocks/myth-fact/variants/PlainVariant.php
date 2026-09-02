<?php

namespace ContentEgg\application\EggBlocks\blocks\myth_fact\variants;

defined('ABSPATH') || exit;

class PlainVariant
{
    /**
     * Three rail rows per item — myth, fact, and optionally why — which is
     * the same definition-list shape pros-cons/plain uses.
     *
     * The block's own section_label and title span both columns, because
     * the per-item rails are already spoken for by the myth/fact labels.
     *
     * Myth text is set muted and fact text in full ink: the claim being
     * corrected should read as the quieter of the two. That signal is
     * typographic rather than coloured, so it survives in any theme and
     * does not add another red to the page.
     */
    public static function render(array $data, string $theme_class = '', string $data_theme = ''): void
    {
        $classes = 'eggb-block eggb-plain eggb-myth-fact eggb-myth-fact--plain';
        if ($theme_class !== '')
        {
            $classes .= ' ' . $theme_class;
        }
        ?>
        <section class="<?php echo esc_attr($classes); ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

            <?php if ($data['section_label'] !== '' || $data['title'] !== '') : ?>
                <div class="eggb-mf-plain-head">
                    <?php if ($data['section_label'] !== '') : ?>
                        <span class="eggb-mf-plain-eyebrow"><?php echo esc_html($data['section_label']); ?></span>
                    <?php endif; ?>
                    <?php if ($data['title'] !== '') : ?>
                        <<?php echo esc_attr($data['heading_tag']); ?> class="eggb-block-title eggb-block-title--sm eggb-mf-plain-heading"><?php echo esc_html($data['title']); ?></<?php echo esc_attr($data['heading_tag']); ?>>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php foreach ($data['items'] as $index => $item) : ?>

                <?php if ($index > 0) : ?>
                    <div class="eggb-mf-plain-sep"></div>
                <?php endif; ?>

                <?php if ($item['myth_text'] !== '') : ?>
                    <div class="eggb-plain-rail"><span class="eggb-rail-name"><?php echo esc_html($data['label_myth']); ?></span></div>
                    <div class="eggb-plain-col"><div class="eggb-mf-plain-myth"><?php echo esc_html($item['myth_text']); ?></div></div>
                <?php endif; ?>

                <?php if ($item['fact_text'] !== '') : ?>
                    <div class="eggb-plain-rail"><span class="eggb-rail-name"><?php echo esc_html($data['label_fact']); ?></span></div>
                    <div class="eggb-plain-col"><div class="eggb-mf-plain-fact"><?php echo wp_kses_post($item['fact_text']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></div>
                <?php endif; ?>

                <?php if ($item['why_text'] !== '') : ?>
                    <div class="eggb-plain-rail"><span class="eggb-rail-name"><?php echo esc_html($data['label_why']); ?></span></div>
                    <div class="eggb-plain-col"><div class="eggb-mf-plain-why"><?php echo wp_kses_post($item['why_text']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></div>
                <?php endif; ?>

            <?php endforeach; ?>

        </section>
        <?php
    }
}
