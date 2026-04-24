<?php

namespace ContentEgg\application\EggBlocks\blocks\methodology\variants;

defined('ABSPATH') || exit;

class TimelineVariant
{
    public static function render(array $methodology, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <section class="eggb-block eggb-card eggb-methodology eggb-methodology--timeline p-3 p-md-4<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
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

                <ol class="eggb-mt-timeline d-flex flex-column mb-0">
                    <?php foreach ($methodology['items'] as $index => $item): ?>
                        <li class="d-flex align-items-stretch gap-3">
                            <div class="eggb-timeline-col">
                                <span class="eggb-step-badge" aria-hidden="true"><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></span>
                                <div class="eggb-timeline-connector"></div>
                            </div>
                            <div class="d-flex flex-column gap-1<?php echo $index === count($methodology['items']) - 1 ? '' : ' pb-3'; ?>">
                                <?php if ($item['title'] !== ''): ?>
                                    <div class="eggb-mt-step-title"><?php echo esc_html($item['title']); ?></div>
                                <?php endif; ?>
                                <?php if ($item['description'] !== ''): ?>
                                    <div class="eggb-mt-step-desc"><?php echo $item['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>

                <?php if ($methodology['note'] !== ''): ?>
                    <div class="eggb-mt-note"><?php echo $methodology['note']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php endif; ?>
            </div>
        </section>
        <?php
    }
}
