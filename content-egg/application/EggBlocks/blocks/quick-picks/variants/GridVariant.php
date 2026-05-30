<?php

namespace ContentEgg\application\EggBlocks\blocks\quickpicks\variants;

use ContentEgg\application\EggBlocks\blocks\quickpicks\QuickPicksRenderer;

defined('ABSPATH') || exit;

class GridVariant
{
    public static function render(array $payload, string $theme_class, string $data_theme): void
    {
        ?>
        <div class="eggb-block eggb-quick-picks eggb-quick-picks--grid<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ($payload['section_label'] !== '' || $payload['title'] !== '') : ?>
                <div class="d-flex flex-column gap-1 mb-3">
                    <?php if ($payload['section_label'] !== '') : ?>
                        <div class="eggb-section-title eggb-section-title--muted mb-0"><?php echo esc_html($payload['section_label']); ?></div>
                    <?php endif; ?>
                    <?php if ($payload['title'] !== '') :
                        $ht = $payload['heading_tag']; ?>
                        <<?php echo esc_attr($ht); ?> class="eggb-block-title eggb-block-title--sm eggb-qp-heading"><?php echo esc_html($payload['title']); ?></<?php echo esc_attr($ht); ?>>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="row g-3">
                <?php foreach ($payload['items'] as $item) : ?>
                    <div class="col-12 col-md-6">
                        <article class="eggb-qp-grid-card eggb-card h-100 p-3 d-flex flex-column gap-3<?php echo $item['is_featured'] ? ' eggb-qp-grid-card--featured' : ''; ?>">
                            <div class="d-flex align-items-start justify-content-end gap-3">
                                <?php if ($item['badge'] !== '') : ?>
                                    <span class="eggb-award"><?php echo esc_html($item['badge']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex align-items-start gap-3">
                                <?php if ($payload['has_any_image']) : ?>
                                    <?php self::renderGridMedia($item); ?>
                                <?php endif; ?>
                                <div class="d-flex flex-column gap-2 min-w-0">
                                    <?php DefaultVariant::renderTitle($item, 'eggb-qp-grid-title'); ?>
                                    <?php if ($item['subtitle'] !== '') : ?>
                                        <div class="eggb-qp-grid-fit"><?php echo esc_html($item['subtitle']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($item['description'] !== '') : ?>
                                <div class="eggb-qp-grid-copy"><?php echo esc_html($item['description']); ?></div>
                            <?php endif; ?>

                            <?php DefaultVariant::renderChips($item['chips']); ?>

                                <div class="eggb-qp-grid-footer d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                    <div class="d-flex flex-column">
                                        <?php DefaultVariant::renderPrice($item, 'eggb-price eggb-price--md eggb-qp-grid-price'); ?>
                                        <?php DefaultVariant::renderMerchant($item, 'eggb-qp-store'); ?>
                                    </div>
                                    <?php if ($item['score'] !== '') : ?>
                                        <div class="eggb-qp-grid-score" aria-label="<?php echo esc_attr(trim($item['score'] . ' / 10')); ?>">
                                            <span class="eggb-score-num"><?php echo esc_html($item['score']); ?></span>
                                            <span class="eggb-score-denom">/ 10</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php DefaultVariant::renderCta($item, QuickPicksRenderer::resolveCtaLabel($payload, $item['product_item']), 'eggb-btn eggb-btn--filled'); ?>
                                </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($payload['amazon_update_html'] !== '') : ?>
                <div class="eggb-price-meta eggb-qp-grid-update"><?php echo $payload['amazon_update_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function renderGridMedia(array $item): void
    {
        if ($item['has_link']) : ?>
            <?php \ContentEgg\application\helpers\TemplateHelper::openATag($item['product_item'], ['title' => $item['title']], ['class' => 'eggb-qp-grid-media']); ?>
                <?php DefaultVariant::renderImage($item); ?>
            <?php \ContentEgg\application\helpers\TemplateHelper::closeATag(); ?>
        <?php else : ?>
            <div class="eggb-qp-grid-media">
                <?php DefaultVariant::renderImage($item); ?>
            </div>
        <?php
        endif;
    }
}
