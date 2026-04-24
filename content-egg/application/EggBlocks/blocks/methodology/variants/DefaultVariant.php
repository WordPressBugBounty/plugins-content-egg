<?php

namespace ContentEgg\application\EggBlocks\blocks\methodology\variants;

defined('ABSPATH') || exit;

class DefaultVariant
{
    public static function render(array $methodology, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <section class="eggb-block eggb-card eggb-methodology eggb-methodology--default p-3 p-md-4<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="d-flex flex-column gap-3">
                <?php if ($methodology['section_label'] !== '' || $methodology['title'] !== ''): ?>
                    <div class="d-flex flex-column gap-1">
                        <?php if ($methodology['section_label'] !== ''): ?>
                            <span class="eggb-section-title eggb-section-title--muted mb-0"><?php echo esc_html($methodology['section_label']); ?></span>
                        <?php endif; ?>
                        <?php if ($methodology['title'] !== ''): ?>
                            <<?php echo esc_attr($methodology['heading_tag']); ?> class="eggb-block-title eggb-block-title--sm eggb-mt-title"><?php echo esc_html($methodology['title']); ?></<?php echo esc_attr($methodology['heading_tag']); ?>>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($methodology['description'] !== ''): ?>
                    <div class="eggb-mt-desc"><?php echo $methodology['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php endif; ?>

                <ul class="eggb-mt-list d-flex flex-column gap-2 mb-0">
                    <?php foreach ($methodology['items'] as $item): ?>
                    <li class="d-flex flex-column flex-md-row gap-1 gap-md-3">
                            <?php if ($item['title'] !== ''): ?>
                                <span class="eggb-mt-item-label"><?php echo esc_html($item['title']); ?></span>
                            <?php endif; ?>
                            <?php if ($item['description'] !== ''): ?>
                                <span class="eggb-mt-item-desc"><?php echo $item['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($methodology['note'] !== ''): ?>
                    <div class="eggb-mt-note"><?php echo $methodology['note']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php endif; ?>
            </div>
        </section>
        <?php
    }
}
