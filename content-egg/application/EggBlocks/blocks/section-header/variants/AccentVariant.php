<?php

namespace ContentEgg\application\EggBlocks\blocks\sectionheader\variants;

defined('ABSPATH') || exit;

class AccentVariant
{
    public static function render(array $section, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <div class="eggb-block eggb-section-header eggb-section-header--accent<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="d-flex flex-column gap-1">
                <?php if ($section['kicker'] !== ''): ?>
                    <div class="eggb-sh-kicker"><?php echo esc_html($section['kicker']); ?></div>
                <?php endif; ?>
                <?php if ($section['step_label'] !== ''): ?>
                    <div class="eggb-sh-step-label"><?php echo esc_html($section['step_label']); ?></div>
                <?php endif; ?>
                <?php if ($section['title'] !== ''): ?>
                    <<?php echo esc_attr($section['heading_tag']); ?> class="eggb-sh-title mb-0"><?php echo esc_html($section['title']); ?></<?php echo esc_attr($section['heading_tag']); ?>>
                <?php endif; ?>
                <?php if ($section['subtitle'] !== ''): ?>
                    <div class="eggb-sh-subtitle mt-1"><?php echo esc_html($section['subtitle']); ?></div>
                <?php endif; ?>
                <?php if ($section['meta'] !== ''): ?>
                    <div class="eggb-sh-meta"><?php echo esc_html($section['meta']); ?></div>
                <?php endif; ?>
                <?php if (!empty($section['chips'])): ?>
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        <?php foreach ($section['chips'] as $chip): ?>
                            <span class="eggb-chip"><?php echo esc_html($chip); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
