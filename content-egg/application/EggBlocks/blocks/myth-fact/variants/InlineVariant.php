<?php

namespace ContentEgg\application\EggBlocks\blocks\myth_fact\variants;

defined('ABSPATH') || exit;

class InlineVariant
{
    public static function render(array $data, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <section class="eggb-block eggb-myth-fact eggb-myth-fact--inline<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="d-flex flex-column gap-3">
                <?php if ($data['section_label'] !== '' || $data['title'] !== ''): ?>
                    <div class="d-flex flex-column gap-1">
                        <?php if ($data['section_label'] !== ''): ?>
                            <span class="eggb-section-title eggb-section-title--muted mb-0"><?php echo esc_html($data['section_label']); ?></span>
                        <?php endif; ?>
                        <?php if ($data['title'] !== ''):
                            $ht = $data['heading_tag']; ?>
                            <<?php echo esc_attr($ht); ?> class="eggb-block-title eggb-block-title--sm eggb-mf-heading"><?php echo esc_html($data['title']); ?></<?php echo esc_attr($ht); ?>>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="d-flex flex-column gap-0">
                    <?php foreach ($data['items'] as $item): ?>
                        <div class="eggb-mf-item py-3">
                            <span class="eggb-mf-verdict<?php echo $item['verdict_class'] ? ' ' . esc_attr($item['verdict_class']) : ''; ?>">
                                <?php echo esc_html($item['verdict']); ?>
                            </span>
                            <div class="eggb-mf-myth-text mb-0"><?php echo esc_html($item['myth_text']); ?></div>
                            <?php if ($item['verdict_text'] !== ''): ?>
                                <div class="eggb-mf-verdict-text mb-0"><?php echo esc_html($item['verdict_text']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php
    }
}
