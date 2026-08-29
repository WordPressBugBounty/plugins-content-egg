<?php

namespace ContentEgg\application\EggBlocks\blocks\editorialproduct\variants;

use ContentEgg\application\helpers\TemplateHelper;

defined('ABSPATH') || exit;

class SpecimenVariant
{
    public static function render(array $block, string $theme_class = '', string $data_theme = ''): void
    {
        $classes = 'eggb-block eggb-editorial-product eggb-editorial-product--specimen';
        if ($theme_class !== '')
        {
            $classes .= ' ' . $theme_class;
        }
        ?>
        <figure class="<?php echo esc_attr($classes); ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if (!empty($block['product_item'])) : ?>
                <div class="eggb-ep-specimen-img">
                    <?php TemplateHelper::openATag($block['product_item'], [], ['class' => 'eggb-ep-img-link']); ?>
                        <?php TemplateHelper::displayImage($block['product_item'], 600, 600, ['class' => 'eggb-ep-img']); ?>
                    <?php TemplateHelper::closeATag(); ?>
                </div>
            <?php endif; ?>
            <div class="eggb-ep-specimen-body">
                <?php if ($block['section_label'] !== '') : ?>
                    <span class="eggb-ep-label"><?php echo esc_html($block['section_label']); ?></span>
                <?php endif; ?>
                <div class="eggb-ep-body eggb-ep-lead"><?php echo $block['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php self::renderSpecRows($block['specs']); ?>
                <?php FigureVariant::renderMeta($block); ?>
            </div>
        </figure>
        <?php
    }

    private static function renderSpecRows(array $specs): void
    {
        if (empty($specs))
        {
            return;
        }
        ?>
        <dl class="eggb-ep-specs eggb-ep-specs--rows">
            <?php foreach ($specs as $spec) : ?>
                <div class="eggb-ep-spec-row">
                    <dt><?php echo esc_html($spec['label']); ?></dt>
                    <dd><?php echo esc_html($spec['value']); ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
        <?php
    }
}
