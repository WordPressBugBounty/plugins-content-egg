<?php

namespace ContentEgg\application\EggBlocks\blocks\criteria\variants;

defined('ABSPATH') || exit;

class PlainVariant
{
    /**
     * One rail row per criterion. The rail carries the criterion's name and
     * its importance, so reading the left edge gives the reader the list of
     * things being judged — the same "rail is an index" idea the other rail
     * blocks use.
     *
     * The block's own section_label and title span both columns above the
     * rows. The "01 / 02" counters of the default variant are dropped: they
     * are derived from the loop index rather than authored, and the rail of
     * names indexes the list better than numbers do.
     */
    public static function render(array $header, array $items, array $labels, string $theme_class = '', string $data_theme = ''): void
    {
        $classes = 'eggb-block eggb-plain eggb-criteria eggb-criteria--plain';
        if ($theme_class !== '')
        {
            $classes .= ' ' . $theme_class;
        }
        ?>
        <section class="<?php echo esc_attr($classes); ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

            <?php if ($header['section_label'] !== '' || $header['title'] !== '') : ?>
                <div class="eggb-cr-plain-head">
                    <?php if ($header['section_label'] !== '') : ?>
                        <span class="eggb-cr-plain-eyebrow"><?php echo esc_html($header['section_label']); ?></span>
                    <?php endif; ?>
                    <?php if ($header['title'] !== '') : ?>
                        <<?php echo esc_attr($header['heading_tag']); ?> class="eggb-block-title eggb-block-title--sm eggb-cr-plain-heading"><?php echo esc_html($header['title']); ?></<?php echo esc_attr($header['heading_tag']); ?>>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php foreach ($items as $item) : ?>
                <div class="eggb-plain-rail">
                    <?php if ($item['title'] !== '') : ?>
                        <span class="eggb-rail-name"><?php echo esc_html($item['title']); ?></span>
                    <?php endif; ?>
                    <span class="eggb-cr-plain-dots" role="img" aria-label="<?php echo esc_attr($item['importance_label']); ?>">
                        <?php foreach ($item['importance_dots'] as $on) : ?>
                            <span class="eggb-cr-plain-dot<?php echo $on ? ' eggb-cr-plain-dot--on' : ''; ?>"></span>
                        <?php endforeach; ?>
                    </span>
                </div>

                <div class="eggb-plain-col">
                    <?php if ($item['description'] !== '') : ?>
                        <div class="eggb-cr-plain-desc"><?php echo $item['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <?php endif; ?>

                    <?php if ($item['look_for'] !== '') : ?>
                        <div class="eggb-cr-plain-sub">
                            <span class="eggb-cr-plain-sub-label eggb-cr-plain-sub-label--positive"><?php echo esc_html($labels['look_for']); ?></span>
                            <?php echo esc_html($item['look_for']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($item['avoid'] !== '') : ?>
                        <div class="eggb-cr-plain-sub">
                            <span class="eggb-cr-plain-sub-label eggb-cr-plain-sub-label--negative"><?php echo esc_html($labels['avoid']); ?></span>
                            <?php echo esc_html($item['avoid']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

        </section>
        <?php
    }
}
