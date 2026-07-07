<?php

namespace ContentEgg\application\EggBlocks\blocks\trustsignals\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;

defined('ABSPATH') || exit;

class InlineVariant
{
    public static function render(array $data, string $theme_class = '', string $data_theme = ''): void
    {
        $aggregate = $data['aggregate'];
        $metrics   = $data['metrics'];
        $badges    = $data['badges'];
        ?>
        <div class="eggb-block eggb-trust eggb-trust--inline<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> role="group" aria-label="<?php esc_attr_e('Trust signals', 'content-egg'); ?>">

            <?php if ($aggregate !== null): ?>
                <div class="eggb-trust-item eggb-trust-item--rating">
                    <span class="eggb-trust-stars" aria-hidden="true">
                        <?php foreach ($aggregate['stars'] as $icon): ?>
                            <?php echo EggbIcons::get($icon); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endforeach; ?>
                    </span>
                    <span class="eggb-trust-value"><?php echo esc_html($aggregate['rating']); ?></span>
                    <span class="eggb-trust-label"><?php echo esc_html(sprintf(
                        /* translators: %s: platform name */
                        __('on %s', 'content-egg'),
                        $aggregate['source']
                    )); ?></span>
                </div>
            <?php endif; ?>

            <?php foreach ($metrics as $metric): ?>
                <div class="eggb-trust-item">
                    <span class="eggb-trust-value"><?php echo esc_html($metric['value']); ?></span>
                    <?php if ($metric['label'] !== ''): ?>
                        <span class="eggb-trust-label"><?php echo esc_html($metric['label']); ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php foreach ($badges as $badge): ?>
                <div class="eggb-trust-item">
                    <?php echo EggbIcons::get($badge['icon']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <span><?php echo esc_html($badge['label']); ?></span>
                </div>
            <?php endforeach; ?>

        </div>
        <?php
    }
}
