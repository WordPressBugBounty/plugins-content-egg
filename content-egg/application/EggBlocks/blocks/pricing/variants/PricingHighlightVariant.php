<?php

namespace ContentEgg\application\EggBlocks\blocks\pricing\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;

defined('ABSPATH') || exit;

class PricingHighlightVariant
{
    public static function render(array $data, string $theme_class = '', string $data_theme = ''): void
    {
        $heading_tag = $data['heading_tag'];
        $title       = $data['title'];
        $intro_text  = $data['intro_text'];
        $items       = $data['items'];
        $promotions  = $data['promotions'];
        $note        = $data['general_price_note'];
        $cta_label   = $data['cta_label'];
        $cta_url     = $data['cta_url'];

        $featured_label = $data['featured_label'];
        $has_head = ($title !== '' || $intro_text !== '');

        // First isFeatured item is the hero; fall back to first item
        $hero_index = 0;
        foreach ($items as $i => $item)
        {
            if ($item['is_featured'])
            {
                $hero_index = $i;
                break;
            }
        }
        $hero = $items[$hero_index];
        $alts = array_values(array_filter($items, static fn($_, $i) => $i !== $hero_index, ARRAY_FILTER_USE_BOTH));
        ?>
        <div class="eggb-block eggb-pricing eggb-pricing--highlight<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

            <?php if ($has_head): ?>
                <div class="eggb-pricing-head">
                    <?php if ($title !== ''): ?>
                        <<?php echo tag_escape($heading_tag); ?> class="eggb-block-title eggb-pricing-title"><?php echo esc_html($title); ?></<?php echo tag_escape($heading_tag); ?>>
                    <?php endif; ?>
                    <?php if ($intro_text !== ''): ?>
                        <div class="eggb-pricing-intro"><?php echo wp_kses_post($intro_text); ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Hero featured plan -->
            <div class="eggb-block--panel eggb-block--accented">
                <div class="eggb-pricing-hero">

                    <div class="eggb-pricing-hero-body">
                        <div class="eggb-pricing-hero-badge"><?php echo esc_html($featured_label); ?></div>
                        <div class="eggb-pricing-name"><?php echo wp_kses_post($hero['name']); ?></div>
                        <?php if ($hero['description'] !== ''): ?>
                            <div class="eggb-pricing-description"><?php echo wp_kses_post($hero['description']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($hero['features'])): ?>
                            <ul class="eggb-item-list eggb-pricing-hero-features">
                                <?php foreach ($hero['features'] as $feature): ?>
                                    <li>
                                        <?php echo EggbIcons::get('check2', 'eggb-item-icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        <span><?php echo wp_kses_post($feature); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div class="eggb-pricing-hero-action">
                        <?php if ($hero['price_display'] !== '' || $hero['billing_period'] !== ''): ?>
                            <div class="eggb-pricing-hero-price">
                                <?php if ($hero['price_display'] !== ''): ?>
                                    <span class="eggb-pricing-hero-amount"><?php echo esc_html($hero['price_display']); ?></span>
                                <?php endif; ?>
                                <?php if ($hero['billing_period'] !== ''): ?>
                                    <span class="eggb-pricing-period"><?php echo esc_html($hero['billing_period']); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($hero['cta_label'] !== '' && $hero['cta_url'] !== ''): ?>
                            <a href="<?php echo esc_url($hero['cta_url']); ?>" class="eggb-btn eggb-btn--filled">
                                <?php echo esc_html($hero['cta_label']); ?>
                                <?php echo EggbIcons::get('arrow-right-short'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </a>
                        <?php endif; ?>
                    </div>

                </div>

                <?php foreach ($promotions as $promo): ?>
                    <div class="eggb-pricing-promo">
                        <div class="eggb-pricing-promo-title"><?php echo wp_kses_post($promo['title']); ?></div>
                        <?php if ($promo['description'] !== ''): ?>
                            <div class="eggb-pricing-promo-description"><?php echo wp_kses_post($promo['description']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Alternatives -->
            <?php if (!empty($alts)): ?>
                <div class="eggb-pricing-alts">
                    <?php foreach ($alts as $alt): ?>
                        <div class="eggb-pricing-alt">
                            <div class="eggb-pricing-alt-body">
                                <div class="eggb-pricing-alt-name"><?php echo wp_kses_post($alt['name']); ?></div>
                                <?php if ($alt['description'] !== ''): ?>
                                    <div class="eggb-pricing-alt-description"><?php echo wp_kses_post($alt['description']); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php if ($alt['price_display'] !== '' || $alt['billing_period'] !== ''): ?>
                                <div class="eggb-pricing-alt-price">
                                    <?php echo esc_html($alt['price_display']); ?>
                                    <?php if ($alt['billing_period'] !== ''): ?>
                                        <span class="eggb-pricing-alt-period"><?php echo esc_html($alt['billing_period']); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($alt['cta_label'] !== '' && $alt['cta_url'] !== ''): ?>
                                <a href="<?php echo esc_url($alt['cta_url']); ?>" class="eggb-btn"><?php echo esc_html($alt['cta_label']); ?></a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($note !== ''): ?>
                <div class="eggb-pricing-note">
                    <?php echo EggbIcons::get('info-circle-fill'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo wp_kses_post($note); ?>
                </div>
            <?php endif; ?>

            <?php if ($cta_label !== '' && $cta_url !== ''): ?>
                <div class="eggb-pricing-cta">
                    <a href="<?php echo esc_url($cta_url); ?>" class="eggb-btn eggb-btn--filled">
                        <?php echo esc_html($cta_label); ?>
                        <?php echo EggbIcons::get('arrow-right-short'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </a>
                </div>
            <?php endif; ?>

        </div>
        <?php
    }
}
