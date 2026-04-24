<?php

namespace ContentEgg\application\EggBlocks\blocks\specifications\variants;

defined('ABSPATH') || exit;

class HighlightVariant
{
    public static function render(array $specs, array $specs_data, string $theme_class = '', string $data_theme = ''): void
    {
        $rows = array_chunk($specs, 5);
        ?>
        <div class="eggb-block eggb-specs eggb-specs--highlight<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ($specs_data['section_label'] !== '' || $specs_data['title'] !== ''): ?>
                <div class="d-flex flex-column gap-1 mb-3">
                    <?php if ($specs_data['section_label'] !== ''): ?>
                        <span class="eggb-section-title eggb-section-title--muted mb-0"><?php echo esc_html($specs_data['section_label']); ?></span>
                    <?php endif; ?>
                    <?php if ($specs_data['title'] !== ''):
                        $ht = $specs_data['heading_tag']; ?>
                        <<?php echo esc_attr($ht); ?> class="eggb-block-title eggb-block-title--sm eggb-sp-heading"><?php echo esc_html($specs_data['title']); ?></<?php echo esc_attr($ht); ?>>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="eggb-specs-hl-stack">
                <?php foreach ($rows as $row): ?>
                    <div class="eggb-specs-hl-band">
                        <?php foreach ($row as $spec): ?>
                            <div class="eggb-specs-hl-item">
                                <span class="eggb-specs-hl-value"><?php echo esc_html($spec['value']); ?></span>
                                <?php if ($spec['label'] !== ''): ?>
                                    <span class="eggb-ks-label mt-2"><?php echo esc_html($spec['label']); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}
