<?php

namespace ContentEgg\application\EggBlocks\blocks\productcard\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;
use ContentEgg\application\helpers\TemplateHelper;

defined('ABSPATH') || exit;

class DefaultVariant
{
    public static function render(array $card, string $theme_class, string $data_theme): void
    {
        $has_meta_row = $card['has_rank'] || $card['badge'] !== '' || $card['score'] !== '';
        $has_bottom_row = self::hasPriceData($card) || $card['cta_label'] !== '';
        ?>
        <?php self::renderBlockHeader($card, $theme_class, $data_theme); ?>
        <div class="eggb-block eggb-card eggb-product-card d-flex<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if (self::hasImage($card)) : ?>
                <div class="eggb-pc-img-col">
                    <?php self::renderImageLinkStart($card, 'eggb-pc-img-link d-block'); ?>
                        <?php self::renderImage($card, 'eggb-pc-img', 350, 350); ?>
                    <?php self::renderImageLinkEnd($card); ?>
                </div>
            <?php endif; ?>

            <div class="eggb-pc-body d-flex flex-column gap-2 flex-grow-1 min-w-0">
                <?php if ($has_meta_row) : ?>
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <div class="d-flex align-items-center gap-2 flex-wrap min-w-0">
                            <?php if ($card['has_rank']) : ?>
                                <span class="eggb-pc-rank" role="img" aria-label="<?php echo esc_attr('Rank ' . $card['rank']); ?>"><?php echo esc_html($card['rank_display']); ?></span>
                            <?php endif; ?>
                            <?php if ($card['badge'] !== '') : ?>
                                <span class="eggb-award"><?php echo esc_html($card['badge']); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($card['score'] !== '') : ?>
                            <div class="eggb-pc-score" role="img" aria-label="<?php echo esc_attr(trim($card['score'] . ' ' . $card['score_denom'])); ?>">
                                <span class="eggb-score-num"><?php echo esc_html($card['score']); ?></span>
                                <span class="eggb-score-denom"><?php echo esc_html($card['score_denom']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php self::renderTitle($card, 'eggb-pc-title'); ?>

                <?php if ($card['subtitle'] !== '') : ?>
                    <div class="eggb-pc-best-for"><?php echo $card['subtitle']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php endif; ?>

                <?php self::renderChips($card['chips']); ?>

                <?php if ($card['description'] !== '') : ?>
                    <div class="eggb-pc-desc"><?php echo $card['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php endif; ?>

                <?php if ($has_bottom_row) : ?>
                    <div class="eggb-pc-footer d-flex flex-wrap align-items-center gap-2 mt-auto">
                        <?php if (self::hasPriceData($card)) : ?>
                            <div class="d-flex flex-column gap-1 flex-grow-1 min-w-0">
                                <?php self::renderPriceLine($card, 'eggb-price eggb-price--md', 'eggb-pc-store', true); ?>
                                <?php self::renderPriceMeta($card); ?>
                            </div>
                        <?php endif; ?>
                        <?php self::renderCta($card, 'eggb-btn eggb-btn--filled eggb-pc-cta-main'); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public static function renderBlockHeader(array $card, string $theme_class = '', string $data_theme = ''): void
    {
        if ($card['section_label'] === '' && $card['block_title'] === '') {
            return;
        }
        ?>
        <div class="eggb-block eggb-pc-block-header d-flex flex-column gap-1<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ($card['section_label'] !== '') : ?>
                <div class="eggb-section-title eggb-section-title--muted mb-0"><?php echo esc_html($card['section_label']); ?></div>
            <?php endif; ?>
            <?php if ($card['block_title'] !== '') :
                $ht = $card['heading_tag']; ?>
                <<?php echo esc_attr($ht); ?> class="eggb-pc-heading"><?php echo esc_html($card['block_title']); ?></<?php echo esc_attr($ht); ?>>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function renderTitle(array $card, string $class): void
    {
        if ($card['title'] === '') {
            return;
        }

        if (!empty($card['product_item'])) : ?>
            <?php TemplateHelper::openATag($card['product_item'], [], ['class' => $class]); ?>
                <?php echo esc_html($card['title']); ?>
            <?php TemplateHelper::closeATag(); ?>
        <?php else : ?>
            <div class="<?php echo esc_attr($class); ?>"><?php echo esc_html($card['title']); ?></div>
        <?php
        endif;
    }

    public static function renderImageLinkStart(array $card, string $class): void
    {
        if (!empty($card['product_item'])) : ?>
            <?php TemplateHelper::openATag($card['product_item'], [], ['class' => $class]); ?>
        <?php else : ?>
            <div class="<?php echo esc_attr($class); ?>">
        <?php
        endif;
    }

    public static function renderImageLinkEnd(array $card): void
    {
        if (!empty($card['product_item'])) {
            TemplateHelper::closeATag();
            return;
        }

        echo '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public static function renderImage(array $card, string $class, int $maxWidth, int $maxHeight): void
    {
        if (!empty($card['product_item'])) : ?>
            <?php TemplateHelper::displayImage($card['product_item'], $maxWidth, $maxHeight, ['class' => $class]); ?>
        <?php
        endif;
    }

    public static function renderChips(array $chips): void
    {
        if (empty($chips)) {
            return;
        }
        ?>
        <div class="d-flex flex-wrap gap-1">
            <?php foreach ($chips as $chip) : ?>
                <span class="eggb-chip"><?php echo esc_html($chip); ?></span>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public static function renderPriceLine(array $card, string $priceClass, string $storeClass, bool $showOldPrice): void
    {
        if (empty($card['product_item'])) {
            return;
        }

        $price_html = self::captureOutput(static function () use ($card): void {
            TemplateHelper::price($card['product_item']);
        });
        $old_price_html = self::captureOutput(static function () use ($card): void {
            TemplateHelper::oldPrice($card['product_item']);
        });
        $merchant_html = $card['merchant'] !== ''
            ? esc_html($card['merchant'])
            : self::captureOutput(static function () use ($card): void {
                TemplateHelper::merchant($card['product_item']);
            });

        if ($price_html === '' && (!$showOldPrice || $old_price_html === '') && $merchant_html === '') {
            return;
        }

        ?>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <?php if ($price_html !== '') : ?>
                <span class="<?php echo esc_attr($priceClass); ?>"><?php echo $price_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <?php endif; ?>
            <?php if ($showOldPrice && $old_price_html !== '') : ?>
                <span class="eggb-price-old"><?php echo $old_price_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <?php endif; ?>
            <?php if ($merchant_html !== '') : ?>
                <span class="<?php echo esc_attr($storeClass); ?>"><?php echo $merchant_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function renderCta(array $card, string $class): void
    {
        if ($card['cta_label'] === '') {
            return;
        }

        if (!empty($card['product_item'])) : ?>
            <?php TemplateHelper::openATag($card['product_item'], [], ['class' => $class]); ?>
                <?php echo esc_html($card['cta_label']); ?>
                <?php echo EggbIcons::get('arrow-right-short', 'eggb-pc-cta-icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php TemplateHelper::closeATag(); ?>
        <?php else : ?>
            <span class="<?php echo esc_attr($class); ?>">
                <?php echo esc_html($card['cta_label']); ?>
                <?php echo EggbIcons::get('arrow-right-short', 'eggb-pc-cta-icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
        <?php
        endif;
    }

    public static function hasPriceData(array $card): bool
    {
        return !empty($card['product_item']);
    }

    public static function hasImage(array $card): bool
    {
        return !empty($card['product_item']['img']);
    }

    public static function hasSupplementalMeta(array $card): bool
    {
        if (empty($card['product_item'])) {
            return false;
        }

        $amazon_update_html = self::captureAmazonPriceUpdate($card);

        return $amazon_update_html !== '';
    }

    public static function renderPriceMeta(array $card): void
    {
        if (empty($card['product_item'])) {
            return;
        }

        $amazon_update_html = self::captureAmazonPriceUpdate($card);

        if ($amazon_update_html === '') {
            return;
        }
        ?>
        <div class="eggb-price-meta">
            <span class="eggb-price-meta-item"><?php echo $amazon_update_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        </div>
        <?php
    }

    private static function captureOutput(callable $callback): string
    {
        ob_start();
        $callback();
        return trim((string) ob_get_clean());
    }

    private static function captureAmazonPriceUpdate(array $card): string
    {
        if (empty($card['product_item'])) {
            return '';
        }

        $module_id = (string) ($card['product_item']['module_id'] ?? $card['product_ref']['module_id'] ?? '');
        if ($module_id === '' || stripos($module_id, 'Amazon') === false) {
            return '';
        }

        return self::captureOutput(static function () use ($card, $module_id): void {
            $items = [$module_id => [$card['product_item']]];
            TemplateHelper::priceUpdateAmazon($items);
        });
    }
}
