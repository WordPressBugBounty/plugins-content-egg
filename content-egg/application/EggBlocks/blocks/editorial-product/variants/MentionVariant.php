<?php

namespace ContentEgg\application\EggBlocks\blocks\editorialproduct\variants;

use ContentEgg\application\helpers\TemplateHelper;

defined('ABSPATH') || exit;

class MentionVariant
{
    public static function render(array $block, string $theme_class = '', string $data_theme = ''): void
    {
        $classes = 'eggb-block eggb-editorial-product eggb-editorial-product--mention';
        if ($theme_class !== '')
        {
            $classes .= ' ' . $theme_class;
        }
        ?>
        <div class="<?php echo esc_attr($classes); ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if (!empty($block['product_item'])) : ?>
                <div class="eggb-ep-mention-thumb">
                    <?php TemplateHelper::openATag($block['product_item'], [], ['class' => 'eggb-ep-img-link']); ?>
                        <?php TemplateHelper::displayImage($block['product_item'], 160, 160, FigureVariant::imgParams($block)); ?>
                    <?php TemplateHelper::closeATag(); ?>
                </div>
            <?php endif; ?>
            <div class="eggb-ep-mention-body">
                <?php if ($block['section_label'] !== '') : ?>
                    <span class="eggb-ep-label"><?php echo esc_html($block['section_label']); ?></span>
                <?php endif; ?>
                <div class="eggb-ep-body eggb-ep-body--mention"><?php echo $block['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php FigureVariant::renderMeta($block); ?>
            </div>
        </div>
        <?php
    }
}
