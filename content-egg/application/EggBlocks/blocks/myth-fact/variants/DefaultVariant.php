<?php

namespace ContentEgg\application\EggBlocks\blocks\myth_fact\variants;

defined('ABSPATH') || exit;

class DefaultVariant
{
    public static function render(array $data, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <section class="eggb-block eggb-myth-fact eggb-myth-fact--default<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
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

                <div class="d-flex flex-column gap-3">
                    <?php foreach ($data['items'] as $item): ?>
                        <div class="eggb-mf-pair eggb-card">
                            <div class="eggb-mf-myth">
                                <?php if ($data['label_myth'] !== ''): ?>
                                    <div class="eggb-mf-side-label eggb-mf-side-label--myth"><?php echo esc_html($data['label_myth']); ?></div>
                                <?php endif; ?>
                                <div class="mb-0"><?php echo esc_html($item['myth_text']); ?></div>
                            </div>
                            <div class="eggb-mf-fact">
                                <?php if ($data['label_fact'] !== ''): ?>
                                    <div class="eggb-mf-side-label eggb-mf-side-label--fact"><?php echo esc_html($data['label_fact']); ?></div>
                                <?php endif; ?>
                                <div class="mb-0"><?php echo $item['fact_text']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                            </div>
                            <?php if ($item['why_text'] !== ''): ?>
                                <div class="eggb-mf-why">
                                    <?php if ($data['label_why'] !== ''): ?>
                                        <div class="eggb-mf-why-label"><?php echo esc_html($data['label_why']); ?></div>
                                    <?php endif; ?>
                                    <div class="eggb-mf-why-text mb-0"><?php echo $item['why_text']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php
    }
}
