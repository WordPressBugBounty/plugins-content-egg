<?php

namespace ContentEgg\application\EggBlocks\blocks\wheretobuy\variants;

use ContentEgg\application\EggBlocks\blocks\wheretobuy\WhereToBuyRenderer;
use ContentEgg\application\helpers\TemplateHelper;

defined('ABSPATH') || exit;

class DefaultVariant
{
    public static function render(array $payload, string $theme_class, string $data_theme): void
    {
        ?>
        <div class="eggb-block eggb-block--panel eggb-where-to-buy eggb-wtb--default<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php self::renderHeader($payload, 'eggb-wtb-head', 'eggb-wtb-updated'); ?>

            <div class="eggb-wtb-offers">
                <?php foreach ($payload['offers'] as $offer) : ?>
                    <div class="eggb-wtb-offer<?php echo $offer['is_best'] ? ' eggb-wtb-offer--best' : ''; ?>">
                        <span class="eggb-wtb-rank" aria-hidden="true"><?php echo esc_html($offer['rank_display']); ?></span>

                        <div class="eggb-wtb-logo-wrap">
                            <?php self::renderLogoLinkStart($offer, 'eggb-wtb-logo-link', 'Visit ' . $offer['merchant_display'] . ' offer'); ?>
                                <div class="eggb-wtb-logo">
                                    <?php self::renderLogo($offer, 'eggb-wtb-logo-img', $payload['logo_params'] ?? []); ?>
                                </div>
                            <?php self::renderLogoLinkEnd($offer); ?>
                        </div>

                        <div class="eggb-wtb-info">
                            <?php if ($offer['title'] !== '') : ?>
                                <div class="eggb-wtb-offer-name">
                                    <?php self::renderOfferName($offer, 'eggb-wtb-offer-name-link'); ?>
                                </div>
                            <?php endif; ?>
                            <?php self::renderChips($offer['chips']); ?>
                        </div>

                        <div class="eggb-wtb-pricecol">
                            <div class="eggb-wtb-price">
                                <?php self::renderPrice($offer, true, 'eggb-price eggb-price--lg'); ?>
                            </div>
                            <?php self::renderStockStatus($offer); ?>
                        </div>

                        <div class="eggb-wtb-action">
                            <?php self::renderCta($offer, WhereToBuyRenderer::resolveCtaLabel($payload, $offer['product_item']), 'eggb-btn eggb-btn--filled'); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($payload['footer_note'] !== '') : ?>
                <div class="eggb-wtb-foot">
                    <div class="eggb-wtb-foot-note"><?php echo esc_html($payload['footer_note']); ?></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function renderHeader(array $payload, string $headerClass, string $metaClass): void
    {
        $has_section_label = $payload['section_label'] !== '';
        $has_title = $payload['title'] !== '';
        $has_meta = $payload['offers_count_label'] !== '' || $payload['amazon_update_html'] !== '';

        if (!$has_section_label && !$has_title && !$has_meta) {
            return;
        }
        ?>
        <div class="<?php echo esc_attr($headerClass); ?>">
            <div class="eggb-wtb-head-left">
                <?php if ($has_section_label) : ?>
                    <div class="eggb-section-title eggb-section-title--muted mb-0"><?php echo esc_html($payload['section_label']); ?></div>
                <?php endif; ?>
                <?php if ($has_title) :
                    $ht = $payload['heading_tag']; ?>
                    <<?php echo esc_attr($ht); ?> class="eggb-block-title eggb-block-title--sm eggb-wtb-heading"><?php echo esc_html($payload['title']); ?></<?php echo esc_attr($ht); ?>>
                <?php endif; ?>
            </div>

            <?php if ($has_meta) : ?>
                <span class="<?php echo esc_attr($metaClass); ?>">
                    <?php echo esc_html($payload['offers_count_label']); ?>
                    <?php if ($payload['offers_count_label'] !== '' && $payload['amazon_update_html'] !== '') : ?>
                        <span aria-hidden="true"> &middot; </span>
                    <?php endif; ?>
                    <?php if ($payload['amazon_update_html'] !== '') : ?>
                        <?php echo $payload['amazon_update_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endif; ?>
                </span>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function renderLogoLinkStart(array $offer, string $class, string $label): void
    {
        if ($offer['has_link']) : ?>
            <?php TemplateHelper::openATag($offer['product_item'], ['aria-label' => $label], ['class' => $class]); ?>
        <?php else : ?>
            <div class="<?php echo esc_attr($class); ?>">
        <?php
        endif;
    }

    public static function renderLogoLinkEnd(array $offer): void
    {
        if ($offer['has_link']) {
            TemplateHelper::closeATag();
            return;
        }

        echo '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public static function renderLogo(array $offer, string $class, array $params = []): void
    {
        $logo_html = self::captureOutput(static function () use ($offer, $class, $params): void {
            TemplateHelper::logo($offer['product_item'], $params, 'object-fit-scale ' . $class);
        });

        if ($logo_html !== '') : ?>
            <?php echo $logo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php else : ?>
            <span class="eggb-wtb-logo-fallback"><?php echo esc_html($offer['merchant_display']); ?></span>
        <?php
        endif;
    }

    public static function renderOfferName(array $offer, string $class): void
    {
        if ($offer['title'] === '') {
            return;
        }

        if ($offer['has_link']) : ?>
            <?php TemplateHelper::openATag($offer['product_item'], ['title' => $offer['title']], ['class' => $class]); ?>
                <?php echo esc_html($offer['title']); ?>
            <?php TemplateHelper::closeATag(); ?>
        <?php else : ?>
            <span class="<?php echo esc_attr($class); ?>" title="<?php echo esc_attr($offer['title']); ?>"><?php echo esc_html($offer['title']); ?></span>
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

    public static function renderPrice(array $offer, bool $showOldPrice, string $currentClass): void
    {
        $current_price_html = self::captureOutput(static function () use ($offer): void {
            TemplateHelper::price($offer['product_item']);
        });
        $old_price_html = self::captureOutput(static function () use ($offer): void {
            TemplateHelper::oldPrice($offer['product_item']);
        });

        if ($showOldPrice && $offer['old_price'] !== null && $old_price_html !== '') : ?>
            <span class="eggb-price-old"><?php echo $old_price_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        <?php endif; ?>

        <?php if ($offer['current_price'] !== null && $current_price_html !== '') : ?>
            <span class="<?php echo esc_attr($currentClass); ?>"><?php echo $current_price_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        <?php else : ?>
            <span class="<?php echo esc_attr($currentClass); ?>">&ndash;</span>
        <?php
        endif;
    }

    public static function renderStockStatus(array $offer): void
    {
        $stock_html = self::captureOutput(static function () use ($offer): void {
            TemplateHelper::stockStatus($offer['product_item']);
        });

        if ($stock_html === '') {
            return;
        }
        ?>
        <div class="eggb-wtb-status"><?php echo $stock_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        <?php
    }

    public static function renderCta(array $offer, string $label, string $class): void
    {
        if (!$offer['has_link']) {
            return;
        }
        ?>
        <?php TemplateHelper::openATag($offer['product_item'], [], ['class' => $class]); ?>
            <?php echo esc_html($label); ?>
        <?php TemplateHelper::closeATag(); ?>
        <?php
    }

    public static function captureOutput(callable $callback): string
    {
        ob_start();
        $callback();
        return trim((string) ob_get_clean());
    }
}
