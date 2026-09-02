<?php

namespace ContentEgg\application\EggBlocks\blocks\wheretobuy\variants;

use ContentEgg\application\EggBlocks\blocks\wheretobuy\WhereToBuyRenderer;

defined('ABSPATH') || exit;

class PlainVariant
{
    /**
     * On the shared rail at block level, the way rating-breakdown is: the
     * rail carries section_label, the content column carries the whole
     * offer list.
     *
     * Per-offer rails would break the price column, which is the one
     * alignment a comparison must protect. Block-level rails do not: the
     * prices still line up inside the content column, and the block joins
     * the article's rhythm instead of standing outside it.
     *
     * The derived rank counters are dropped — the offers are already
     * sorted cheapest-first, so a number column would restate the order.
     */
    public static function render(array $payload, string $theme_class, string $data_theme): void
    {
        $classes = 'eggb-block eggb-plain eggb-where-to-buy eggb-wtb--plain';
        if ($theme_class !== '')
        {
            $classes .= ' ' . $theme_class;
        }

        $has_rail = $payload['section_label'] !== '';
        if (!$has_rail)
        {
            $classes .= ' eggb-plain--norail';
        }

        $has_meta = $payload['offers_count_label'] !== '' || $payload['amazon_update_html'] !== '';
        ?>
        <section class="<?php echo esc_attr($classes); ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

            <?php if ($has_rail) : ?>
                <div class="eggb-plain-rail"><span class="eggb-rail-name"><?php echo esc_html($payload['section_label']); ?></span></div>
            <?php endif; ?>

            <div class="eggb-plain-col">

            <?php if ($payload['title'] !== '' || $has_meta) : ?>
                <div class="eggb-wtb-plain-head">
                    <?php if ($payload['title'] !== '') :
                        $ht = $payload['heading_tag']; ?>
                        <<?php echo esc_attr($ht); ?> class="eggb-block-title eggb-block-title--sm eggb-wtb-plain-heading"><?php echo esc_html($payload['title']); ?></<?php echo esc_attr($ht); ?>>
                    <?php endif; ?>

                    <?php if ($has_meta) : ?>
                        <span class="eggb-wtb-plain-meta">
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
            <?php endif; ?>

            <ul class="eggb-wtb-plain-offers">
                <?php foreach ($payload['offers'] as $offer) : ?>
                    <li class="eggb-wtb-plain-offer<?php echo $offer['is_best'] ? ' eggb-wtb-plain-offer--best' : ''; ?>">
                        <div class="eggb-wtb-plain-main">
                            <?php if ($offer['logo_url'] !== '') : ?>
                                <?php DefaultVariant::renderLogoLinkStart($offer, 'eggb-wtb-plain-logo-link', 'Visit ' . $offer['merchant_display'] . ' offer'); ?>
                                    <?php DefaultVariant::renderLogo($offer, 'eggb-wtb-plain-logo', $payload['logo_params'] ?? []); ?>
                                <?php DefaultVariant::renderLogoLinkEnd($offer); ?>
                            <?php endif; ?>

                            <div class="eggb-wtb-plain-id">
                                <?php if ($offer['merchant_display'] !== '') : ?>
                                    <div class="eggb-wtb-plain-merchant"><?php echo esc_html($offer['merchant_display']); ?></div>
                                <?php endif; ?>

                                <?php if ($offer['title'] !== '') : ?>
                                    <div class="eggb-wtb-plain-name"><?php DefaultVariant::renderOfferName($offer, 'eggb-wtb-plain-name-link'); ?></div>
                                <?php endif; ?>

                                <?php if (!empty($offer['chips'])) : ?>
                                    <div class="eggb-meta-run eggb-wtb-plain-chips">
                                        <?php foreach ($offer['chips'] as $i => $chip) : ?>
                                            <?php if ($i > 0) : ?><span class="eggb-meta-sep">&middot;</span><?php endif; ?>
                                            <?php echo esc_html($chip); ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="eggb-wtb-plain-right">
                            <div class="eggb-wtb-plain-price">
                                <?php DefaultVariant::renderPrice($offer, true, 'eggb-price eggb-price--lg'); ?>
                                <?php DefaultVariant::renderStockStatus($offer); ?>
                            </div>
                            <?php DefaultVariant::renderCta($offer, WhereToBuyRenderer::resolveCtaLabel($payload, $offer['product_item']), 'eggb-btn eggb-btn--filled eggb-wtb-plain-cta'); ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($payload['footer_note'] !== '') : ?>
                <div class="eggb-wtb-plain-note"><?php echo esc_html($payload['footer_note']); ?></div>
            <?php endif; ?>

            </div>
        </section>
        <?php
    }
}
