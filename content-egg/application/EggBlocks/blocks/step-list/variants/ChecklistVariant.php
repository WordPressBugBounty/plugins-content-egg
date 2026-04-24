<?php

namespace ContentEgg\application\EggBlocks\blocks\steplist\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;

defined('ABSPATH') || exit;

class ChecklistVariant
{
    public static function render(array $step_list, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <section class="eggb-block eggb-step-list eggb-step-list--checklist<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
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

                <ul class="eggb-sl-list d-flex flex-column gap-3 mb-0">
                    <?php foreach ($step_list['steps'] as $step): ?>
                        <li class="eggb-sl-check-card eggb-card d-flex align-items-start gap-3 p-3">
                            <span class="eggb-sl-check" aria-hidden="true">
                                <?php echo EggbIcons::get('check2'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </span>
                            <div class="d-flex flex-column gap-1">
                                <div class="eggb-sl-title"><?php echo esc_html($step['title']); ?></div>
                                <?php if ($step['description'] !== ''): ?>
                                    <div class="eggb-sl-desc"><?php echo $step['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($step_list['note'] !== ''): ?>
                    <div class="eggb-sl-note"><?php echo $step_list['note']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php endif; ?>
            </div>
        </section>
        <?php
    }
}
