<?php

namespace ContentEgg\application\EggBlocks\blocks\methodology\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;

defined('ABSPATH') || exit;

class GridVariant
{
    public static function render(array $methodology, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <section class="eggb-block eggb-card eggb-methodology eggb-methodology--grid p-3 p-md-4<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
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

                <div class="row g-2">
                    <?php foreach ($methodology['items'] as $item): ?>
                        <div class="col-12 col-sm-6">
                            <div class="eggb-mt-card eggb-card p-3 h-100 d-flex flex-column gap-2">
                                <?php if ($item['title'] !== '' || $item['icon'] !== ''): ?>
                                    <div class="eggb-mt-card-title d-flex align-items-center gap-2">
                                        <?php echo EggbIcons::get($item['icon'], 'eggb-mt-card-icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        <?php if ($item['title'] !== ''): ?>
                                            <span><?php echo esc_html($item['title']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($item['description'] !== ''): ?>
                                    <div class="eggb-mt-card-desc"><?php echo $item['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($methodology['note'] !== ''): ?>
                    <div class="eggb-mt-note"><?php echo $methodology['note']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php endif; ?>
            </div>
        </section>
        <?php
    }
}
