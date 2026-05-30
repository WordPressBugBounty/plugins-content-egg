<?php

namespace ContentEgg\application\EggBlocks\blocks\pricing\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;

defined('ABSPATH') || exit;

class PricingCardsVariant
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
        ?>
        <div class="eggb-block eggb-pricing eggb-pricing--cards<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

            <?php if ($has_head): ?>
                <div class="eggb-pricing-head">
                    <?php if ($title !== ''): ?>
                        <<?php echo tag_escape($heading_tag); ?> class="eggb-block-title eggb-pricing-title"><?php echo wp_kses_post($title); ?></<?php echo tag_escape($heading_tag); ?>>
                    <?php endif; ?>
                    <?php if ($intro_text !== ''): ?>
                        <div class="eggb-pricing-intro"><?php echo wp_kses_post($intro_text); ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <ul class="eggb-pricing-items" data-count="<?php echo (int) count($items); ?>">
                <?php foreach ($items as $item): ?>
                    <li class="eggb-pricing-item<?php echo $item['is_featured'] ? ' eggb-pricing-item--featured' : ''; ?>">

                        <?php if ($item['is_featured']): ?>
                            <span class="eggb-pricing-featured-badge"><?php echo esc_html($featured_label); ?></span>
                        <?php endif; ?>

                        <div class="eggb-pricing-name"><?php echo wp_kses_post($item['name']); ?></div>

                        <?php if ($item['description'] !== ''): ?>
                            <div class="eggb-pricing-description"><?php echo wp_kses_post($item['description']); ?></div>
                        <?php endif; ?>

                        <?php if ($item['price_display'] !== '' || $item['billing_period'] !== ''): ?>
                            <div class="eggb-pricing-price-row">
                                <?php if ($item['price_display'] !== ''): ?>
                                    <span class="eggb-pricing-price"><?php echo esc_html($item['price_display']); ?></span>
                                <?php endif; ?>
                                <?php if ($item['billing_period'] !== ''): ?>
                                    <span class="eggb-pricing-period"><?php echo esc_html($item['billing_period']); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($item['features'])): ?>
                            <ul class="eggb-item-list eggb-pricing-features">
                                <?php foreach ($item['features'] as $feature): ?>
                                    <li>
                                        <?php echo EggbIcons::get('check2', 'eggb-item-icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        <span><?php echo wp_kses_post($feature); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if ($item['cta_label'] !== '' && $item['cta_url'] !== ''): ?>
                            <div class="eggb-pricing-item-cta">
                                <a href="<?php echo esc_url($item['cta_url']); ?>" class="eggb-btn<?php echo $item['is_featured'] ? ' eggb-btn--filled' : ''; ?>">
                                    <?php echo esc_html($item['cta_label']); ?>
                                </a>
                            </div>
                        <?php endif; ?>

                    </li>
                <?php endforeach; ?>
            </ul>

            <?php foreach ($promotions as $promo): ?>
                <div class="eggb-pricing-promo">
                    <div class="eggb-pricing-promo-title"><?php echo wp_kses_post($promo['title']); ?></div>
                    <?php if ($promo['description'] !== ''): ?>
                        <div class="eggb-pricing-promo-description"><?php echo wp_kses_post($promo['description']); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php if ($note !== ''): ?>
                <div class="eggb-pricing-note"><?php echo wp_kses_post($note); ?></div>
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
