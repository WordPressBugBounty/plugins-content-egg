<?php

namespace ContentEgg\application\EggBlocks\blocks\quickpicks\variants;

use ContentEgg\application\EggBlocks\blocks\quickpicks\QuickPicksRenderer;

defined('ABSPATH') || exit;

class CompactVariant
{
    public static function render(array $payload, string $theme_class, string $data_theme): void
    {
        ?>
        <div class="eggb-block eggb-quick-picks eggb-quick-picks--compact<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
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

            <div class="eggb-qp-items d-flex flex-column">
                <?php foreach ($payload['items'] as $item) : ?>
                    <article class="eggb-qp-item">
                        <div class="eggb-qp-rank-wrap" aria-hidden="true">
                            <span class="eggb-qp-rank"><?php echo esc_html($item['rank_display']); ?></span>
                        </div>

                        <?php DefaultVariant::renderThumb($item); ?>

                        <div class="eggb-qp-main d-flex flex-column gap-1">
                            <div class="eggb-qp-topline d-flex flex-wrap align-items-center gap-2">
                                <?php DefaultVariant::renderTitle($item, 'eggb-qp-name'); ?>
                                <?php if ($item['badge'] !== '') : ?>
                                    <span class="eggb-award"><?php echo esc_html($item['badge']); ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if ($item['subtitle'] !== '') : ?>
                                <div class="eggb-qp-fit"><?php echo esc_html($item['subtitle']); ?></div>
                            <?php endif; ?>

                            <?php if ($item['description'] !== '') : ?>
                                <div class="eggb-qp-note"><?php echo esc_html($item['description']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="eggb-qp-rail d-flex align-items-center gap-3">
                            <div class="eggb-qp-score eggb-qp-compact-meta" aria-label="<?php echo esc_attr(self::buildCompactMetaLabel($item)); ?>">
                                <?php if ($item['score'] !== '') : ?>
                                    <span class="eggb-score-num"><?php echo esc_html($item['score']); ?></span>
                                    <span class="eggb-score-denom">/ 10</span>
                                <?php endif; ?>
                                <?php DefaultVariant::renderPrice($item, 'eggb-price eggb-price--md eggb-qp-price'); ?>
                            </div>

                            <?php DefaultVariant::renderCta($item, QuickPicksRenderer::resolveCtaLabel($payload, $item['product_item']), 'eggb-btn eggb-btn--filled eggb-qp-cta'); ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($payload['amazon_update_html'] !== '') : ?>
                <div class="eggb-price-meta eggb-qp-compact-update"><?php echo $payload['amazon_update_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function buildCompactMetaLabel(array $item): string
    {
        $parts = [];

        if ($item['score'] !== '') {
            $parts[] = sprintf('Score %s out of 10', $item['score']);
        }

        $price_html = DefaultVariant::captureOutput(static function () use ($item): void {
            DefaultVariant::renderPrice($item, 'eggb-price eggb-price--md eggb-qp-price');
        });
        $price_text = trim(wp_strip_all_tags($price_html));

        if ($price_text !== '' && $price_text !== '–') {
            $parts[] = 'Price ' . $price_text;
        }

        return implode('. ', $parts);
    }
}
