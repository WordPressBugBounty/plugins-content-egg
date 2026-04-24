<?php

namespace ContentEgg\application\EggBlocks\blocks\conclusion\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;

defined('ABSPATH') || exit;

class CompactVariant
{
    public static function render(array $conclusion, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <section class="eggb-block eggb-conclusion eggb-conclusion--compact<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="d-flex flex-column gap-2">
                <?php if ($conclusion['section_label'] !== '' || $conclusion['title'] !== ''): ?>
                    <div class="d-flex flex-column gap-1">
                        <?php if ($conclusion['section_label'] !== ''): ?>
                            <span class="eggb-section-title eggb-section-title--muted mb-0"><?php echo esc_html($conclusion['section_label']); ?></span>
                        <?php endif; ?>
                        <?php if ($conclusion['title'] !== ''): ?>
                            <<?php echo esc_attr($conclusion['heading_tag']); ?> class="eggb-block-title eggb-cn-title"><?php echo esc_html($conclusion['title']); ?></<?php echo esc_attr($conclusion['heading_tag']); ?>>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($conclusion['points'])): ?>
                    <ul class="eggb-item-list">
                        <?php foreach ($conclusion['points'] as $point): ?>
                            <li>
                                <?php echo EggbIcons::get('check-circle', 'eggb-item-icon eggb-cn-point-icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <span><?php echo esc_html($point); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <div class="eggb-cn-summary"><?php echo $conclusion['summary']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>

                <?php if (!empty($conclusion['next_steps'])): ?>
                    <div class="d-flex flex-column gap-1">
                        <?php if ($conclusion['next_steps_label'] !== ''): ?>
                            <span class="eggb-section-title eggb-section-title--muted mb-0"><?php echo esc_html($conclusion['next_steps_label']); ?></span>
                        <?php endif; ?>
                        <ul class="eggb-item-list">
                            <?php foreach ($conclusion['next_steps'] as $item): ?>
                                <li>
                                    <?php echo EggbIcons::get('arrow-right-short', 'eggb-item-icon eggb-cn-arrow'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    <span>
                                        <?php if ($item['url'] !== ''): ?>
                                            <a href="<?php echo esc_url($item['url']); ?>" class="eggb-cn-link"><?php echo esc_html($item['text']); ?></a>
                                        <?php else: ?>
                                            <span><?php echo esc_html($item['text']); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php
    }
}
