<?php

namespace ContentEgg\application\EggBlocks\blocks\contextualcta\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;

defined('ABSPATH') || exit;

class SplitVariant
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

        $has_primary   = ($primary_label !== '' && $primary_url !== '');
        $has_secondary = ($secondary_label !== '' && $secondary_url !== '');
        ?>
        <div class="eggb-block eggb-block--panel eggb-cta eggb-cta--split<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

            <div class="eggb-cta-copy">
                <?php if ($eyebrow !== ''): ?>
                    <div class="eggb-cta-eyebrow"><?php echo esc_html($eyebrow); ?></div>
                <?php endif; ?>
                <?php if ($headline !== ''): ?>
                    <<?php echo tag_escape($heading_tag); ?> class="eggb-block-title eggb-cta-headline"><?php echo esc_html($headline); ?></<?php echo tag_escape($heading_tag); ?>>
                <?php endif; ?>
                <?php if ($text !== ''): ?>
                    <div class="eggb-cta-text"><?php echo $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php endif; ?>
            </div>

            <?php if ($has_primary || $has_secondary): ?>
                <div class="eggb-cta-menu">
                    <?php if ($has_primary): ?>
                        <a href="<?php echo esc_url($primary_url); ?>" class="eggb-cta-row">
                            <div class="eggb-cta-row-body">
                                <?php echo esc_html($primary_label); ?>
                                <?php if ($primary_meta !== ''): ?>
                                    <span class="eggb-cta-primary-meta"><?php echo esc_html($primary_meta); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php echo EggbIcons::get('arrow-right-short', 'eggb-cta-row-arrow'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($has_secondary): ?>
                        <a href="<?php echo esc_url($secondary_url); ?>" class="eggb-cta-row eggb-cta-secondary">
                            <div class="eggb-cta-row-body">
                                <?php echo esc_html($secondary_label); ?>
                            </div>
                            <?php echo EggbIcons::get('arrow-right-short', 'eggb-cta-row-arrow'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

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
