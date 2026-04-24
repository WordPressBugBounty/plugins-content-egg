<?php

namespace ContentEgg\application\EggBlocks\blocks\specifications\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;

defined('ABSPATH') || exit;

class DefaultVariant
{
    public static function render(array $specs, array $specs_data, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <div class="eggb-block eggb-block--panel eggb-block--accented eggb-specs eggb-specs--default<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
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

            <?php if (!empty($specs)): ?>
                <div class="row g-0">
                    <?php foreach ($specs as $spec): ?>
                        <?php $item_classes = 'eggb-ks-item col-6 col-sm-4 d-flex align-items-start pb-3 pe-3'; ?>
                        <div class="<?php echo esc_attr($item_classes . ($spec['icon'] !== '' ? ' gap-2' : '')); ?>">
                            <?php if ($spec['icon'] !== ''): ?>
                                <?php echo EggbIcons::get($spec['icon'], 'eggb-ks-icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php endif; ?>
                            <div>
                                <div class="eggb-ks-value"><?php echo esc_html($spec['value']); ?></div>
                                <?php if ($spec['label'] !== ''): ?>
                                    <div class="eggb-ks-label mt-1"><?php echo esc_html($spec['label']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
