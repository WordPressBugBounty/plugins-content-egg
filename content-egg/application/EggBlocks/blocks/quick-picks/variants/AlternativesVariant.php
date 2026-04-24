<?php

namespace ContentEgg\application\EggBlocks\blocks\quickpicks\variants;

use ContentEgg\application\EggBlocks\blocks\quickpicks\QuickPicksRenderer;
use ContentEgg\application\helpers\TemplateHelper;

defined('ABSPATH') || exit;

class AlternativesVariant
{
    public static function render(array $payload, string $theme_class, string $data_theme): void
    {
        ?>
        <div class="eggb-block eggb-quick-picks eggb-quick-picks--alternatives<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
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

            <div class="d-flex flex-column">
                <?php foreach ($payload['items'] as $item) : ?>
                    <article class="eggb-qp-alt-item">
                        <div class="eggb-qp-alt-media">
                            <?php self::renderAltThumb($item); ?>

                            <?php if ($item['score'] !== '') : ?>
                                <div class="eggb-qp-alt-score" aria-label="<?php echo esc_attr(trim($item['score'] . ' / 10')); ?>">
                                    <span class="eggb-score-num"><?php echo esc_html($item['score']); ?></span>
                                    <span class="eggb-score-denom">/ 10</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="eggb-qp-alt-content d-flex flex-column gap-3">
                            <div class="d-flex flex-column gap-2">
                                <?php if ($item['badge'] !== '') : ?>
                                    <span class="eggb-qp-alt-switch eggb-section-title mb-0"><?php echo esc_html($item['badge']); ?></span>
                                <?php endif; ?>

                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <?php DefaultVariant::renderTitle($item, 'eggb-qp-alt-title'); ?>
                                </div>

                                <?php if ($item['subtitle'] !== '') : ?>
                                    <div class="eggb-qp-alt-why"><?php echo esc_html($item['subtitle']); ?></div>
                                <?php endif; ?>

                                <?php if ($item['description'] !== '') : ?>
                                    <div class="eggb-qp-alt-delta"><?php echo esc_html($item['description']); ?></div>
                                <?php endif; ?>
                            </div>

                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                                    <?php DefaultVariant::renderChips($item['chips']); ?>

                                    <div class="d-flex align-items-center gap-3 flex-wrap">
                                        <div class="d-flex flex-column align-items-start">
                                            <?php DefaultVariant::renderPrice($item, 'eggb-price eggb-price--md eggb-qp-alt-price'); ?>
                                            <?php DefaultVariant::renderMerchant($item, 'eggb-qp-store'); ?>
                                        </div>
                                        <?php DefaultVariant::renderCta($item, QuickPicksRenderer::resolveCtaLabel($payload, $item['product_item']), 'eggb-btn eggb-btn--filled'); ?>
                                    </div>
                                </div>
                            </div>

                        <div class="eggb-qp-alt-rank" aria-hidden="true"><?php echo esc_html($item['rank_display']); ?></div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($payload['amazon_update_html'] !== '') : ?>
                <div class="eggb-price-meta eggb-qp-alt-update"><?php echo $payload['amazon_update_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function renderAltThumb(array $item): void
    {
        if ($item['has_link']) : ?>
            <?php \ContentEgg\application\helpers\TemplateHelper::openATag($item['product_item'], ['title' => $item['title']], ['class' => 'eggb-qp-alt-thumb']); ?>
                <?php DefaultVariant::renderImage($item); ?>
            <?php \ContentEgg\application\helpers\TemplateHelper::closeATag(); ?>
        <?php else : ?>
            <div class="eggb-qp-alt-thumb">
                <?php DefaultVariant::renderImage($item); ?>
            </div>
        <?php
        endif;
    }
}
