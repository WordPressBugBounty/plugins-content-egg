<?php

namespace ContentEgg\application\EggBlocks\blocks\editorialproduct\variants;

use ContentEgg\application\helpers\TemplateHelper;

defined('ABSPATH') || exit;

class FigureVariant
{
    public static function render(array $block, string $theme_class = '', string $data_theme = ''): void
    {
        $classes = 'eggb-block eggb-editorial-product eggb-editorial-product--figure';
        if ($theme_class !== '')
        {
            $classes .= ' ' . $theme_class;
        }
        ?>
        <figure class="<?php echo esc_attr($classes); ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if (!empty($block['product_item'])) : ?>
                <div class="eggb-ep-figure-img">
                    <?php TemplateHelper::openATag($block['product_item'], [], ['class' => 'eggb-ep-img-link']); ?>
                        <?php TemplateHelper::displayImage($block['product_item'], 520, 520, ['class' => 'eggb-ep-img']); ?>
                    <?php TemplateHelper::closeATag(); ?>
                </div>
            <?php endif; ?>
            <figcaption class="eggb-ep-caption">
                <?php if ($block['section_label'] !== '') : ?>
                    <span class="eggb-ep-label"><?php echo esc_html($block['section_label']); ?></span>
                <?php endif; ?>
                <div class="eggb-ep-body eggb-ep-body--caption"><?php echo $block['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php self::renderMeta($block); ?>
            </figcaption>
        </figure>
        <?php
    }

    public static function renderMeta(array $block): void
    {
        if (empty($block['product_item']) && $block['context'] === '')
        {
            return;
        }
        ?>
        <div class="eggb-ep-meta">
            <?php if (!empty($block['product_item']) && $block['title'] !== '') : ?>
                <?php TemplateHelper::openATag($block['product_item'], [], ['class' => 'eggb-ep-name']); ?><?php echo esc_html($block['title']); ?><?php TemplateHelper::closeATag(); ?>
                <?php if ($block['merchant'] !== '') : ?>
                    <span class="eggb-ep-merchant"><?php echo esc_html($block['merchant']); ?></span>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($block['context'] !== '') : ?>
                <span class="eggb-ep-context"><?php echo esc_html($block['context']); ?></span>
            <?php endif; ?>
        </div>
        <?php
    }
}
