<?php

namespace ContentEgg\application\EggBlocks\blocks\criteria\variants;

defined('ABSPATH') || exit;

class ListVariant
{
    public static function render(array $header, array $items, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <section class="eggb-block eggb-criteria eggb-criteria--list<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="d-flex flex-column gap-3">
                <?php if ($header['section_label'] !== '' || $header['title'] !== ''): ?>
                    <div class="d-flex flex-column gap-1">
                        <?php if ($header['section_label'] !== ''): ?>
                            <span class="eggb-section-title eggb-section-title--muted mb-0"><?php echo esc_html($header['section_label']); ?></span>
                        <?php endif; ?>
                        <?php if ($header['title'] !== ''): ?>
                            <<?php echo esc_attr($header['heading_tag']); ?> class="eggb-block-title eggb-block-title--sm eggb-cr-heading"><?php echo esc_html($header['title']); ?></<?php echo esc_attr($header['heading_tag']); ?>>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <ol class="eggb-cr-list d-flex flex-column mb-0">
                    <?php foreach ($items as $index => $item): ?>
                        <li class="d-flex align-items-stretch gap-3">
                            <div class="eggb-timeline-col">
                                <span class="eggb-step-badge" aria-hidden="true"><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></span>
                                <div class="eggb-timeline-connector"></div>
                            </div>
                            <div class="d-flex flex-column gap-2<?php echo $index === count($items) - 1 ? '' : ' pb-3'; ?>">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <?php if ($item['title'] !== ''): ?>
                                        <div class="eggb-cr-title"><?php echo esc_html($item['title']); ?></div>
                                    <?php endif; ?>
                                    <span class="eggb-cr-importance eggb-cr-importance--<?php echo esc_attr($item['importance']); ?>"><?php echo esc_html($item['importance_label']); ?></span>
                                </div>
                                <?php if ($item['description'] !== ''): ?>
                                    <div class="eggb-cr-desc"><?php echo esc_html($item['description']); ?></div>
                                <?php endif; ?>
                                <?php if ($item['look_for'] !== ''): ?>
                                    <div class="d-flex flex-column gap-1">
                                        <span class="eggb-section-title eggb-cr-sub-label--positive mb-0"><?php echo esc_html__('Look for', 'content-egg-tpl'); ?></span>
                                        <div class="eggb-cr-good eggb-cr-sub-text"><?php echo esc_html($item['look_for']); ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($item['avoid'] !== ''): ?>
                                    <div class="d-flex flex-column gap-1">
                                        <span class="eggb-section-title eggb-cr-sub-label--negative mb-0"><?php echo esc_html__('Avoid', 'content-egg-tpl'); ?></span>
                                        <div class="eggb-cr-avoid eggb-cr-sub-text"><?php echo esc_html($item['avoid']); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </section>
        <?php
    }
}
