<?php

namespace ContentEgg\application\EggBlocks\blocks\productcard\variants;

defined('ABSPATH') || exit;

class CompactVariant
{
    public static function render(array $card, string $theme_class, string $data_theme): void
    {
        ?>
        <?php DefaultVariant::renderBlockHeader($card, $theme_class, $data_theme); ?>
        <div class="d-flex flex-column gap-2<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="eggb-block eggb-product-card eggb-product-card--compact d-flex align-items-center gap-3<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                <?php if ($card['has_rank']) : ?>
                    <span class="eggb-pc-rank" role="img" aria-label="<?php echo esc_attr('Rank ' . $card['rank']); ?>"><?php echo esc_html($card['rank_display']); ?></span>
                <?php endif; ?>

                <?php if (DefaultVariant::hasImage($card)) : ?>
                    <?php DefaultVariant::renderImageLinkStart($card, 'flex-shrink-0'); ?>
                        <?php DefaultVariant::renderImage($card, 'eggb-pc-compact-img', 190, 190); ?>
                    <?php DefaultVariant::renderImageLinkEnd($card); ?>
                <?php endif; ?>

                <div class="d-flex flex-column gap-1 flex-grow-1 min-w-0">
                    <?php DefaultVariant::renderTitle($card, 'eggb-pc-compact-title'); ?>
                    <?php if (DefaultVariant::hasPriceData($card)) : ?>
                        <?php DefaultVariant::renderPriceLine($card, 'eggb-price eggb-price--sm', 'eggb-pc-compact-store', false); ?>
                    <?php endif; ?>
                </div>

                <?php DefaultVariant::renderCta($card, 'eggb-btn eggb-btn--text flex-shrink-0'); ?>
            </div>
            <?php if (DefaultVariant::hasSupplementalMeta($card)) : ?>
                <div class="mb-0 text-start text-md-end">
                    <?php DefaultVariant::renderPriceMeta($card); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
