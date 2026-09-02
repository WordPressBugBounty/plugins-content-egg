<?php

namespace ContentEgg\application\EggBlocks\blocks\verdict\variants;

defined('ABSPATH') || exit;

class PlainVariant
{
    /**
     * Label-rail layout.
     *
     * The rail carries band_label, score_label and the score; the award
     * pill and the chips become muted text lines. Emphasis comes from
     * scale and whitespace, not from a panel.
     */
    public static function render(array $data, string $theme_class = '', string $data_theme = ''): void
    {
        $classes = 'eggb-block eggb-plain eggb-verdict eggb-verdict--plain';
        if ($theme_class !== '')
        {
            $classes .= ' ' . $theme_class;
        }

        $chips = !empty($data['chips']) && is_array($data['chips']) ? $data['chips'] : [];

        $has_rail = $data['band_label'] !== '' || $data['score'] !== '';
        if (!$has_rail)
        {
            $classes .= ' eggb-plain--norail';
        }
        ?>
        <div class="<?php echo esc_attr($classes); ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ($has_rail) : ?>
            <div class="eggb-plain-rail">
                <?php if ($data['band_label'] !== '') : ?>
                    <span class="eggb-rail-name"><?php echo esc_html($data['band_label']); ?></span>
                <?php endif; ?>

                <?php if ($data['score'] !== '') : ?>
                    <?php if ($data['score_label'] !== '') : ?>
                        <span class="eggb-rail-sub"><?php echo esc_html($data['score_label']); ?></span>
                    <?php endif; ?>
                    <span class="eggb-rail-score" role="img" aria-label="<?php echo esc_attr(trim($data['score'] . ' ' . $data['score_denom'])); ?>"><?php echo esc_html($data['score']); ?><?php if ($data['score_denom'] !== '') : ?><span class="eggb-rail-denom"> <?php echo esc_html($data['score_denom']); ?></span><?php endif; ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="eggb-plain-col">
                <?php if ($data['title'] !== '') : ?>
                    <div class="eggb-block-title eggb-vd-plain-title"><?php echo esc_html($data['title']); ?></div>
                <?php endif; ?>

                <?php if ($data['award_label'] !== '') : ?>
                    <div class="eggb-vd-plain-award"><?php echo esc_html($data['award_label']); ?></div>
                <?php endif; ?>

                <?php if (!empty($chips)) : ?>
                    <div class="eggb-meta-run eggb-vd-plain-meta">
                        <?php foreach ($chips as $i => $chip) : ?>
                            <?php if ($i > 0) : ?><span class="eggb-meta-sep">&middot;</span><?php endif; ?>
                            <?php echo esc_html($chip); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($data['verdict_text'] !== '') : ?>
                    <div class="eggb-vd-plain-text"><?php echo wp_kses_post($data['verdict_text']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php endif; ?>

                <?php if ($data['cta_label'] !== '' && !empty($data['has_cta_target'])) : ?>
                    <div class="eggb-vd-plain-cta">
                        <?php DefaultVariant::renderCta($data, 'filled'); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
