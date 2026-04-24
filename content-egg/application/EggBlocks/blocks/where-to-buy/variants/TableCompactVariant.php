<?php

namespace ContentEgg\application\EggBlocks\blocks\wheretobuy\variants;

use ContentEgg\application\EggBlocks\blocks\wheretobuy\WhereToBuyRenderer;
use ContentEgg\application\helpers\TemplateHelper;

defined('ABSPATH') || exit;

class TableCompactVariant
{
    public static function render(array $payload, string $theme_class, string $data_theme): void
    {
        ?>
        <div class="eggb-block eggb-card eggb-where-to-buy eggb-wtb--table-compact<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php DefaultVariant::renderHeader($payload, 'eggb-wtb-head', 'eggb-wtb-updated'); ?>

            <div class="table-responsive eggb-wtb-table-compact-wrap p-0">
                <table class="eggb-wtb-table-compact table table-sm align-middle mb-0">
                    <tbody>
                        <?php foreach ($payload['offers'] as $offer) : ?>
                            <tr>
                                <td class="px-2 py-2 align-middle">
                                    <?php if ($offer['has_link']) : ?>
                                        <?php TemplateHelper::openATag($offer['product_item'], ['aria-label' => 'Visit ' . $offer['merchant_display'] . ' offer'], ['class' => 'eggb-wtb-table-compact-merchant-link']); ?>
                                            <?php self::renderMerchantLinkInner($offer); ?>
                                        <?php TemplateHelper::closeATag(); ?>
                                    <?php else : ?>
                                        <span class="eggb-wtb-table-compact-merchant-link">
                                            <?php self::renderMerchantLinkInner($offer); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="eggb-wtb-table-compact-price px-3 py-2 align-middle">
                                    <?php DefaultVariant::renderPrice($offer, false, 'eggb-price eggb-price--sm'); ?>
                                </td>
                                <td class="eggb-wtb-table-compact-buy px-2 py-2 align-middle">
                                    <?php DefaultVariant::renderCta($offer, WhereToBuyRenderer::resolveCtaLabel($payload, $offer['product_item']), 'eggb-btn eggb-btn--filled'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($payload['footer_note'] !== '') : ?>
                <div class="eggb-wtb-foot">
                    <div class="eggb-wtb-foot-note"><?php echo esc_html($payload['footer_note']); ?></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function renderMerchantLinkInner(array $offer): void
    {
        $logo_html = DefaultVariant::captureOutput(static function () use ($offer): void {
            TemplateHelper::icon($offer['product_item'], [], 'eggb-wtb-table-compact-icon');
        });

        if ($logo_html !== '') : ?>
            <span class="eggb-wtb-table-compact-icon-wrap">
                <?php echo $logo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
        <?php endif; ?>
        <span class="eggb-wtb-table-compact-name"><?php echo esc_html($offer['merchant_display']); ?></span>
        <?php
    }
}
