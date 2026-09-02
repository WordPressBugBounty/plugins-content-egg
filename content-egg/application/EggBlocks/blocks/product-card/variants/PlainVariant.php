<?php

namespace ContentEgg\application\EggBlocks\blocks\productcard\variants;

defined('ABSPATH') || exit;

class PlainVariant
{
    private const ALLOWED_HEADING_TAGS = ['h1', 'h2', 'h3', 'h4', 'div'];

    /**
     * Label-rail layout.
     *
     * The rail carries this block's identifying marks — section_label,
     * badge, and rank or score. section_label is rendered here rather
     * than through DefaultVariant::renderBlockHeader(), which emits a
     * separate block above the card.
     *
     * The product image is NOT a rail mark. It is the block's primary
     * content, so it sits in the content column beside the product name,
     * where it can be sized properly.
     *
     * Everything transactional — name, specs, price, CTA, price age —
     * sits beside the image as one product unit. Only the author's
     * description gets its own full-width area below, which also fills
     * the space that would otherwise sit empty beside a tall image.
     *
     * Chips become a muted middot run; DefaultVariant::renderChips() is
     * deliberately not used because it emits .eggb-chip.
     */
    public static function render(array $card, string $theme_class, string $data_theme): void
    {
        $classes = 'eggb-block eggb-plain eggb-product-card eggb-product-card--plain';
        if ($theme_class !== '')
        {
            $classes .= ' ' . $theme_class;
        }

        $chips     = !empty($card['chips']) && is_array($card['chips']) ? $card['chips'] : [];
        $ht        = in_array($card['heading_tag'] ?? 'h2', self::ALLOWED_HEADING_TAGS, true) ? $card['heading_tag'] : 'h2';
        $has_price = DefaultVariant::hasPriceData($card);
        $has_buy   = $has_price || $card['cta_label'] !== '';

        $has_rail = $card['section_label'] !== ''
            || $card['badge'] !== ''
            || !empty($card['has_rank'])
            || $card['score'] !== '';
        if (!$has_rail)
        {
            $classes .= ' eggb-plain--norail';
        }
        ?>
        <div class="<?php echo esc_attr($classes); ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

            <?php if ($card['block_title'] !== '') : ?>
                <<?php echo esc_attr($ht); ?> class="eggb-block-title eggb-plain-title"><?php echo esc_html($card['block_title']); ?></<?php echo esc_attr($ht); ?>>
            <?php endif; ?>

            <?php if ($has_rail) : ?>
            <div class="eggb-plain-rail">
                <?php if ($card['section_label'] !== '') : ?>
                    <span class="eggb-rail-name"><?php echo esc_html($card['section_label']); ?></span>
                <?php endif; ?>

                <?php if ($card['badge'] !== '') : ?>
                    <span class="eggb-rail-badge"><?php echo esc_html($card['badge']); ?></span>
                <?php endif; ?>

                <?php if (!empty($card['has_rank'])) : ?>
                    <span class="eggb-rail-rank" role="img" aria-label="<?php echo esc_attr('Rank ' . $card['rank']); ?>"><?php echo esc_html($card['rank_display']); ?></span>
                <?php endif; ?>

                <?php if ($card['score'] !== '') : ?>
                    <span class="eggb-rail-score" role="img" aria-label="<?php echo esc_attr(trim($card['score'] . ' ' . $card['score_denom'])); ?>"><?php echo esc_html($card['score']); ?><?php if ($card['score_denom'] !== '') : ?><span class="eggb-rail-denom"> <?php echo esc_html($card['score_denom']); ?></span><?php endif; ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="eggb-plain-col">
                <div class="eggb-pc-plain-top">
                    <?php if (DefaultVariant::hasImage($card)) : ?>
                        <?php DefaultVariant::renderImageLinkStart($card, 'eggb-pc-plain-figure'); ?>
                            <?php DefaultVariant::renderImage($card, 'eggb-pc-plain-img', 600, 600); ?>
                        <?php DefaultVariant::renderImageLinkEnd($card); ?>
                    <?php endif; ?>

                    <div class="eggb-pc-plain-head">
                        <?php DefaultVariant::renderTitle($card, 'eggb-block-title eggb-block-title--sm eggb-pc-plain-title'); ?>

                        <?php if ($card['subtitle'] !== '') : ?>
                            <div class="eggb-pc-plain-sub"><?php echo wp_kses_post($card['subtitle']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        <?php endif; ?>

                        <?php if (!empty($chips)) : ?>
                            <div class="eggb-meta-run eggb-pc-plain-meta">
                                <?php foreach ($chips as $i => $chip) : ?>
                                    <?php if ($i > 0) : ?><span class="eggb-meta-sep">&middot;</span><?php endif; ?>
                                    <?php echo esc_html($chip); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($has_buy) : ?>
                            <div class="eggb-pc-plain-buy">
                                <?php if ($has_price) : ?>
                                    <?php DefaultVariant::renderPriceLine($card, 'eggb-price eggb-price--lg', 'eggb-pc-plain-store', true); ?>
                                <?php endif; ?>
                                <?php DefaultVariant::renderCta($card, 'eggb-btn eggb-btn--filled eggb-pc-plain-cta'); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (DefaultVariant::hasSupplementalMeta($card)) : ?>
                            <div class="eggb-pc-plain-updated"><?php DefaultVariant::renderPriceMeta($card); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($card['description'] !== '') : ?>
                    <div class="eggb-pc-plain-desc"><?php echo wp_kses_post($card['description']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
