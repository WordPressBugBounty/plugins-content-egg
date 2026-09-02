<?php

namespace ContentEgg\application\EggBlocks\blocks\editorialproduct\variants;

use ContentEgg\application\helpers\TemplateHelper;

defined('ABSPATH') || exit;

class ClaimVariant
{
    public static function render(array $block, string $theme_class = '', string $data_theme = ''): void
    {
        $classes = 'eggb-block eggb-editorial-product eggb-editorial-product--claim';
        if ($theme_class !== '')
        {
            $classes .= ' ' . $theme_class;
        }
        ?>
        <figure class="<?php echo esc_attr($classes); ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ($block['section_label'] !== '') : ?>
                <span class="eggb-ep-label"><?php echo esc_html($block['section_label']); ?></span>
            <?php endif; ?>
            <div class="eggb-ep-body eggb-ep-statement"><?php echo $block['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php if (!empty($block['product_item'])) : ?>
                <hr class="eggb-rule eggb-ep-claim-rule">
                <div class="eggb-ep-evidence">
                    <div class="eggb-ep-thumb">
                        <?php TemplateHelper::openATag($block['product_item'], [], ['class' => 'eggb-ep-img-link']); ?>
                            <?php TemplateHelper::displayImage($block['product_item'], 200, 200, FigureVariant::imgParams($block)); ?>
                        <?php TemplateHelper::closeATag(); ?>
                    </div>
                    <div class="eggb-ep-evidence-body">
                        <?php if ($block['title'] !== '') : ?>
                            <div class="eggb-ep-evidence-name">
                                <?php TemplateHelper::openATag($block['product_item'], [], ['class' => 'eggb-ep-name']); ?><?php echo esc_html($block['title']); ?><?php TemplateHelper::closeATag(); ?>
                            </div>
                        <?php endif; ?>
                        <?php self::renderInlineSpecs($block); ?>
                    </div>
                </div>
            <?php else : ?>
                <?php // No product bound: authored attributes and the footnote still render.
                      // renderInlineSpecs already emits the footnote, so renderMeta would double it. ?>
                <?php self::renderInlineSpecs($block); ?>
            <?php endif; ?>
        </figure>
        <?php
    }

    private static function renderInlineSpecs(array $block): void
    {
        $parts = [];
        foreach ($block['specs'] as $spec)
        {
            $parts[] = $spec['value'];
        }

        if ($block['merchant'] !== '')
        {
            $parts[] = $block['merchant'];
        }

        if ($block['context'] !== '')
        {
            $parts[] = $block['context'];
        }

        if (empty($parts))
        {
            return;
        }
        ?>
        <div class="eggb-ep-specs eggb-ep-specs--inline">
            <?php foreach ($parts as $i => $part) : ?>
                <?php if ($i > 0) : ?><span class="eggb-ep-sep" aria-hidden="true"></span><?php endif; ?>
                <span><?php echo esc_html($part); ?></span>
            <?php endforeach; ?>
        </div>
        <?php
    }
}
