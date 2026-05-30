<?php

namespace ContentEgg\application\EggBlocks\blocks\pricing\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;

defined('ABSPATH') || exit;

class PricingListVariant
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
        <div class="eggb-block eggb-block--panel eggb-pricing eggb-pricing--list<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

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

            <ul class="eggb-pricing-items">
                <?php foreach ($items as $item): ?>
                    <li class="eggb-pricing-item<?php echo $item['is_featured'] ? ' eggb-pricing-item--featured' : ''; ?>">

                        <div class="eggb-pricing-item-body">
                            <div class="eggb-pricing-name">
                                <?php echo wp_kses_post($item['name']); ?>
                                <?php if ($item['is_featured']): ?>
                                    <span class="eggb-pricing-featured-tag"><?php echo esc_html($featured_label); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($item['description'] !== ''): ?>
                                <div class="eggb-pricing-description"><?php echo wp_kses_post($item['description']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="eggb-pricing-price-block">
                            <?php if ($item['price_display'] !== '' || $item['billing_period'] !== ''): ?>
                                <div>
                                    <?php if ($item['price_from'] !== '' && $item['price_to'] === ''): ?>
                                        <span class="eggb-pricing-price-from"><?php esc_html_e('From', 'content-egg-tpl'); ?></span>
                                        <span class="eggb-pricing-price"><?php echo esc_html($item['price_from']); ?></span>
                                    <?php elseif ($item['price_display'] !== ''): ?>
                                        <span class="eggb-pricing-price"><?php echo esc_html($item['price_display']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($item['billing_period'] !== ''): ?>
                                        <span class="eggb-pricing-period"><?php echo esc_html($item['billing_period']); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($item['cta_label'] !== '' && $item['cta_url'] !== ''): ?>
                                <a href="<?php echo esc_url($item['cta_url']); ?>" class="eggb-btn<?php echo $item['is_featured'] ? ' eggb-btn--filled' : ''; ?>">
                                    <?php echo esc_html($item['cta_label']); ?>
                                </a>
                            <?php endif; ?>
                        </div>

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
