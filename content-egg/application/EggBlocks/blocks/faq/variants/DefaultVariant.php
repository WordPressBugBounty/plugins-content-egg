<?php

namespace ContentEgg\application\EggBlocks\blocks\faq\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;

defined('ABSPATH') || exit;

class DefaultVariant
{
    public static function render(array $header, array $items, bool $collapsed_by_default = false, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <section class="eggb-block eggb-faq eggb-faq--default<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="d-flex flex-column gap-3">
                <?php if ($header['section_label'] !== '' || $header['title'] !== ''): ?>
                    <div class="d-flex flex-column gap-1">
                        <?php if ($header['section_label'] !== ''): ?>
                            <span class="eggb-section-title eggb-section-title--muted mb-0"><?php echo esc_html($header['section_label']); ?></span>
                        <?php endif; ?>
                        <?php if ($header['title'] !== ''): ?>
                            <<?php echo esc_attr($header['heading_tag']); ?> class="eggb-block-title eggb-block-title--sm eggb-faq-heading"><?php echo esc_html($header['title']); ?></<?php echo esc_attr($header['heading_tag']); ?>>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="d-flex flex-column gap-2">
                    <?php foreach ($items as $item): ?>
                        <details class="eggb-faq-item eggb-card"<?php echo $collapsed_by_default ? '' : ' open'; ?>>
                            <summary class="d-flex align-items-center justify-content-between gap-3">
                                <span class="eggb-faq-q fw-semibold"><?php echo esc_html($item['question']); ?></span>
                                <?php echo EggbIcons::get('chevron-down', 'eggb-faq-chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </summary>
                            <div class="eggb-faq-body"><?php echo $item['answer_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        </details>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php
    }
}
