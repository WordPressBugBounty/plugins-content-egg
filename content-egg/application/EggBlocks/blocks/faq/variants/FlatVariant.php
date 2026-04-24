<?php

namespace ContentEgg\application\EggBlocks\blocks\faq\variants;

defined('ABSPATH') || exit;

class FlatVariant
{
    public static function render(array $header, array $items, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <section class="eggb-block eggb-faq eggb-faq--flat<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
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

                <div class="d-flex flex-column gap-3">
                    <?php foreach ($items as $item): ?>
                        <div class="eggb-faq-item d-flex flex-column gap-1">
                            <p class="fw-semibold mb-0"><?php echo esc_html($item['question']); ?></p>
                            <div class="eggb-faq-answer"><?php echo $item['answer_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php
    }
}
