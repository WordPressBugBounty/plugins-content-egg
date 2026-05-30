<?php

namespace ContentEgg\application\EggBlocks\blocks\contextualcta\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;

defined('ABSPATH') || exit;

class InlineVariant
{
    public static function render(array $data, string $theme_class = '', string $data_theme = ''): void
    {
        $eyebrow       = $data['eyebrow'];
        $headline      = $data['headline'];
        // Pre-sanitized text may be wrapped by wpautop in <p>; the inline-row context
        // cannot host block-level tags, so strip <p>/</p>.
        $text          = trim(preg_replace('/<\/?p>/', '', $data['text']));
        $primary_label = $data['primary_label'];
        $primary_url   = $data['primary_url'];

        $has_message  = ($headline !== '') || ($text !== '');
        ?>
        <aside class="eggb-block eggb-cta eggb-cta--inline<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

            <?php if ($eyebrow !== ''): ?>
                <div class="eggb-cta-eyebrow"><?php echo esc_html($eyebrow); ?></div>
            <?php endif; ?>

            <?php if ($has_message): ?>
                <div class="eggb-cta-message">
                    <?php if ($headline !== ''): ?>
                        <span class="eggb-cta-headline"><?php echo esc_html($headline); ?></span>
                    <?php endif; ?>
                    <?php if ($headline !== '' && $text !== ''): ?>
                        <span class="eggb-cta-divider" aria-hidden="true">—</span>
                    <?php endif; ?>
                    <?php if ($text !== ''): ?>
                        <span class="eggb-cta-text"><?php echo $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($primary_label !== '' && $primary_url !== ''): ?>
                <a href="<?php echo esc_url($primary_url); ?>" class="eggb-btn eggb-btn--filled eggb-cta-primary">
                    <?php echo esc_html($primary_label); ?>
                    <?php echo EggbIcons::get('arrow-right-short'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </a>
            <?php endif; ?>

        </aside>
        <?php
    }
}
