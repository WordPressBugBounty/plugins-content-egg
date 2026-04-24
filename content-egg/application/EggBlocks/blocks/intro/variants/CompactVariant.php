<?php

namespace ContentEgg\application\EggBlocks\blocks\intro\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;

defined('ABSPATH') || exit;

class CompactVariant
{
    public static function render(array $intro, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <section class="eggb-block eggb-intro eggb-intro--compact<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="eggb-intro-compact-inner d-flex flex-column gap-2">
                <?php if ($intro['section_label'] !== ''): ?>
                    <span class="eggb-section-title eggb-section-title--muted mb-0"><?php echo esc_html($intro['section_label']); ?></span>
                <?php endif; ?>

                <div class="d-flex flex-column gap-1">
                    <?php if ($intro['title'] !== ''): ?>
                        <<?php echo esc_attr($intro['heading_tag']); ?> class="eggb-block-title eggb-intro-title"><?php echo esc_html($intro['title']); ?></<?php echo esc_attr($intro['heading_tag']); ?>>
                    <?php endif; ?>
                    <?php if ($intro['lead'] !== ''): ?>
                        <p class="eggb-intro-lead mb-0"><?php echo esc_html($intro['lead']); ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($intro['body'] !== ''): ?>
                    <div class="eggb-intro-body"><?php echo $intro['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php endif; ?>

                <?php if (!empty($intro['points'])): ?>
                    <ul class="eggb-item-list">
                        <?php foreach ($intro['points'] as $point): ?>
                            <li>
                                <?php echo EggbIcons::get('check-circle', 'eggb-item-icon eggb-intro-point-icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <?php echo esc_html($point); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($intro['cta_label'] !== '' && $intro['cta_url'] !== ''): ?>
                    <div>
                        <a href="<?php echo esc_url($intro['cta_url']); ?>" class="eggb-btn eggb-btn--text"><?php echo esc_html($intro['cta_label']); ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php
    }
}
