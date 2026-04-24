<?php

namespace ContentEgg\application\EggBlocks\blocks\productcard\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;

defined('ABSPATH') || exit;

class FeaturedVariant
{
    public static function render(array $card, string $theme_class, string $data_theme): void
    {
        $has_top_row = $card['badge'] !== '' || $card['score'] !== '' || (!DefaultVariant::hasImage($card) && $card['has_rank']);
        $has_bottom_row = DefaultVariant::hasPriceData($card) || $card['cta_label'] !== '';
        ?>
        <?php DefaultVariant::renderBlockHeader($card, $theme_class, $data_theme); ?>
        <div class="d-flex flex-column gap-2">
            <div class="eggb-block eggb-card eggb-block--accented eggb-product-card eggb-product-card--featured d-flex<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                <?php if (DefaultVariant::hasImage($card)) : ?>
                    <div class="eggb-pc-featured-img-zone">
                        <?php DefaultVariant::renderImageLinkStart($card, 'eggb-pc-featured-img-link d-block'); ?>
                            <?php DefaultVariant::renderImage($card, 'eggb-pc-featured-img', 450, 450); ?>
                        <?php DefaultVariant::renderImageLinkEnd($card); ?>
                        <?php if ($card['has_rank']) : ?>
                            <span class="eggb-pc-featured-rank" aria-label="<?php echo esc_attr('Rank ' . $card['rank']); ?>">
                                <span class="eggb-pc-featured-rank-num"><?php echo esc_html($card['rank_display']); ?></span>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="eggb-pc-featured-body d-flex flex-column gap-2 flex-grow-1 min-w-0">
                    <?php if ($has_top_row) : ?>
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center gap-2 flex-wrap min-w-0">
                                <?php if (!DefaultVariant::hasImage($card) && $card['has_rank']) : ?>
                                    <span class="eggb-pc-rank" aria-label="<?php echo esc_attr('Rank ' . $card['rank']); ?>"><?php echo esc_html($card['rank_display']); ?></span>
                                <?php endif; ?>
                                <?php if ($card['badge'] !== '') : ?>
                                    <span class="eggb-award">
                                        <?php echo EggbIcons::get('award-fill', 'eggb-pc-award-icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        <?php echo esc_html($card['badge']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($card['score'] !== '') : ?>
                                <div class="d-flex align-items-baseline gap-1" aria-label="<?php echo esc_attr(trim($card['score'] . ' ' . $card['score_denom'])); ?>">
                                    <span class="eggb-pc-featured-score-num"><?php echo esc_html($card['score']); ?></span>
                                    <span class="eggb-score-denom"><?php echo esc_html($card['score_denom']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php DefaultVariant::renderTitle($card, 'eggb-pc-featured-title'); ?>

                    <?php if ($card['subtitle'] !== '') : ?>
                        <div class="eggb-pc-featured-best-for"><?php echo $card['subtitle']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <?php endif; ?>

                    <?php DefaultVariant::renderChips($card['chips']); ?>

                    <?php if ($card['featured_text'] !== '') : ?>
                        <div class="eggb-pc-featured-verdict"><?php echo $card['featured_text']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <?php endif; ?>

                    <?php if ($has_bottom_row) : ?>
                        <div class="eggb-pc-featured-rule pt-2 mt-auto d-flex align-items-center gap-3 flex-wrap">
                            <?php if (DefaultVariant::hasPriceData($card)) : ?>
                                <div class="d-flex flex-column">
                                    <?php DefaultVariant::renderPriceLine($card, 'eggb-price eggb-price--lg', 'eggb-pc-featured-store', true); ?>
                                </div>
                            <?php endif; ?>
                            <?php DefaultVariant::renderCta($card, 'eggb-btn eggb-btn--filled eggb-pc-featured-cta'); ?>
                        </div>
                    <?php endif; ?>
                </div>
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
