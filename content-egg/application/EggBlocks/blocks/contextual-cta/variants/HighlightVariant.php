<?php

namespace ContentEgg\application\EggBlocks\blocks\contextualcta\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;

defined('ABSPATH') || exit;

class HighlightVariant
{
    public static function render(array $data, string $theme_class = '', string $data_theme = ''): void
    {
        $heading_tag     = $data['heading_tag'];
        $eyebrow         = $data['eyebrow'];
        $headline        = $data['headline'];
        $text            = $data['text'];
        $primary_label   = $data['primary_label'];
        $primary_url     = $data['primary_url'];
        $primary_meta    = $data['primary_meta'];
        $secondary_label = $data['secondary_label'];
        $secondary_url   = $data['secondary_url'];
        $context         = $data['context'];
        $logo            = $data['logo'];
        ?>
        <div class="eggb-block eggb-block--panel eggb-cta eggb-cta--highlight<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

            <?php if ($eyebrow !== ''): ?>
                <div class="eggb-cta-ribbon" aria-hidden="true"><span><?php echo esc_html($eyebrow); ?></span></div>
            <?php endif; ?>

            <div class="eggb-cta-feature">

                <?php if ($logo !== ''): ?>
                    <div class="eggb-cta-brand">
                        <span class="eggb-cta-logo">
                            <img src="<?php echo esc_url($logo); ?>" alt="">
                        </span>
                    </div>
                <?php endif; ?>

                <div class="eggb-cta-editorial">
                    <?php if ($headline !== ''): ?>
                        <<?php echo tag_escape($heading_tag); ?> class="eggb-block-title eggb-cta-headline"><?php echo esc_html($headline); ?></<?php echo tag_escape($heading_tag); ?>>
                    <?php endif; ?>

                    <?php if ($text !== ''): ?>
                        <div class="eggb-cta-text"><?php echo $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <?php endif; ?>

                    <?php if (($primary_label !== '' && $primary_url !== '') || ($secondary_label !== '' && $secondary_url !== '')): ?>
                        <div class="eggb-cta-actions">
                            <?php if ($primary_label !== '' && $primary_url !== ''): ?>
                                <a href="<?php echo esc_url($primary_url); ?>" class="eggb-btn eggb-btn--filled eggb-cta-primary">
                                    <?php echo esc_html($primary_label); ?>
                                    <?php echo EggbIcons::get('arrow-right-short'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </a>
                            <?php endif; ?>

                            <?php if ($secondary_label !== '' && $secondary_url !== ''): ?>
                                <a href="<?php echo esc_url($secondary_url); ?>" class="eggb-cta-secondary"><?php echo esc_html($secondary_label); ?></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($primary_meta !== ''): ?>
                        <div class="eggb-cta-primary-meta"><?php echo esc_html($primary_meta); ?></div>
                    <?php endif; ?>
                </div>

            </div>

            <?php if ($context !== ''): ?>
                <div class="eggb-cta-context">
                    <?php echo EggbIcons::get('info-circle-fill'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo $context; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            <?php endif; ?>

        </div>
        <?php
    }
}
