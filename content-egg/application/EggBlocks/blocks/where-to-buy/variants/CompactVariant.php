<?php

namespace ContentEgg\application\EggBlocks\blocks\wheretobuy\variants;

use ContentEgg\application\EggBlocks\blocks\wheretobuy\WhereToBuyRenderer;

defined('ABSPATH') || exit;

class CompactVariant
{
    public static function render(array $payload, string $theme_class, string $data_theme): void
    {
        ?>
        <div class="eggb-block eggb-card eggb-where-to-buy eggb-wtb--compact<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php DefaultVariant::renderHeader($payload, 'eggb-wtb-head', 'eggb-wtb-updated'); ?>

            <?php foreach ($payload['offers'] as $offer) : ?>
                <div class="eggb-wtb-compact-row<?php echo empty($offer['chips']) ? ' eggb-wtb-compact-row--no-chips' : ''; ?>">
                    <?php DefaultVariant::renderLogoLinkStart($offer, 'eggb-wtb-compact-logo-link', 'Visit ' . $offer['merchant_display'] . ' offer'); ?>
                        <div class="eggb-wtb-compact-logo">
                            <?php DefaultVariant::renderLogo($offer, 'eggb-wtb-compact-logo-img'); ?>
                        </div>
                    <?php DefaultVariant::renderLogoLinkEnd($offer); ?>

                    <div class="eggb-wtb-compact-details">
                        <?php DefaultVariant::renderChips($offer['chips']); ?>
                    </div>

                    <div class="eggb-wtb-compact-stock">
                        <?php DefaultVariant::renderStockStatus($offer); ?>
                    </div>

                    <div class="eggb-wtb-compact-price">
                        <div class="eggb-wtb-price">
                            <?php DefaultVariant::renderPrice($offer, false, 'eggb-price eggb-price--md'); ?>
                        </div>
                    </div>

                    <div class="eggb-wtb-action">
                        <?php DefaultVariant::renderCta($offer, WhereToBuyRenderer::resolveCtaLabel($payload, $offer['product_item']), 'eggb-btn eggb-btn--filled'); ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($payload['footer_note'] !== '') : ?>
                <div class="eggb-wtb-foot">
                    <div class="eggb-wtb-foot-note"><?php echo esc_html($payload['footer_note']); ?></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
