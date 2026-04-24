<?php

namespace ContentEgg\application\EggBlocks\blocks\steplist\variants;

defined('ABSPATH') || exit;

class DefaultVariant
{
    public static function render(array $step_list, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <section class="eggb-block eggb-step-list eggb-step-list--default<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="d-flex flex-column gap-3">
                <?php if ($step_list['section_label'] !== '' || $step_list['title'] !== ''): ?>
                    <div class="d-flex flex-column gap-1">
                        <?php if ($step_list['section_label'] !== ''): ?>
                            <span class="eggb-section-title eggb-section-title--muted mb-0"><?php echo esc_html($step_list['section_label']); ?></span>
                        <?php endif; ?>
                        <?php if ($step_list['title'] !== ''):
                            $ht = $step_list['heading_tag']; ?>
                            <<?php echo esc_attr($ht); ?> class="eggb-block-title eggb-block-title--sm eggb-sl-heading"><?php echo esc_html($step_list['title']); ?></<?php echo esc_attr($ht); ?>>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <ol class="eggb-sl-list d-flex flex-column mb-0">
                    <?php foreach ($step_list['steps'] as $index => $step): ?>
                        <li class="d-flex align-items-stretch gap-3">
                            <div class="eggb-timeline-col">
                                <span class="eggb-step-badge" aria-hidden="true"><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></span>
                                <div class="eggb-timeline-connector"></div>
                            </div>
                            <div class="d-flex flex-column gap-1<?php echo $index === count($step_list['steps']) - 1 ? '' : ' pb-3'; ?>">
                                <div class="eggb-sl-title"><?php echo esc_html($step['title']); ?></div>
                                <?php if ($step['description'] !== ''): ?>
                                    <div class="eggb-sl-desc"><?php echo $step['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>

                <?php if ($step_list['note'] !== ''): ?>
                    <div class="eggb-sl-note"><?php echo $step_list['note']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php endif; ?>
            </div>
        </section>
        <?php
    }
}
