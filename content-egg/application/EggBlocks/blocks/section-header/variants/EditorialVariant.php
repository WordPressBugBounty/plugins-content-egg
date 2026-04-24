<?php

namespace ContentEgg\application\EggBlocks\blocks\sectionheader\variants;

defined('ABSPATH') || exit;

class EditorialVariant
{
    public static function render(array $section, string $theme_class = '', string $data_theme = ''): void
    {
        $has_chips = !empty($section['chips']);
        ?>
        <div class="eggb-block eggb-section-header eggb-section-header--editorial d-flex flex-column gap-2<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ($section['kicker'] !== ''): ?>
                <div class="eggb-sh-kicker"><?php echo esc_html($section['kicker']); ?></div>
            <?php endif; ?>
            <?php if ($section['step_label'] !== ''): ?>
                <div class="eggb-sh-step-label"><?php echo esc_html($section['step_label']); ?></div>
            <?php endif; ?>
            <?php if ($section['title'] !== ''): ?>
                <<?php echo esc_attr($section['heading_tag']); ?> class="eggb-sh-title mb-0"><?php echo esc_html($section['title']); ?></<?php echo esc_attr($section['heading_tag']); ?>>
            <?php endif; ?>
            <?php if ($has_chips): ?>
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($section['chips'] as $chip): ?>
                        <span class="eggb-chip"><?php echo esc_html($chip); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($section['subtitle'] !== ''): ?>
                <div class="eggb-sh-subtitle"><?php echo esc_html($section['subtitle']); ?></div>
            <?php endif; ?>
            <?php if ($section['meta'] !== ''): ?>
                <div class="eggb-sh-meta"><?php echo esc_html($section['meta']); ?></div>
            <?php endif; ?>
        </div>
        <?php
    }
}
