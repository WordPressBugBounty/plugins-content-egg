<?php

namespace ContentEgg\application\EggBlocks\blocks\trustsignals\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;

defined('ABSPATH') || exit;

class DefaultVariant
{
    public static function render(array $data, string $theme_class = '', string $data_theme = ''): void
    {
        $aggregate = $data['aggregate'];
        $metrics   = $data['metrics'];
        $badges    = $data['badges'];
        ?>
        <section class="eggb-block eggb-block--panel eggb-trust eggb-trust--default<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-label="<?php esc_attr_e('Trust signals', 'content-egg'); ?>">
            <div class="eggb-trust-items">

                <?php if ($aggregate !== null): ?>
                    <div class="eggb-trust-item eggb-trust-item--rating">
                        <div class="eggb-trust-value-row">
                            <div class="eggb-trust-stars" aria-hidden="true">
                                <?php foreach ($aggregate['stars'] as $icon): ?>
                                    <?php echo EggbIcons::get($icon); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <?php endforeach; ?>
                            </div>
                            <span class="eggb-trust-value">
                                <?php echo esc_html($aggregate['rating']); ?>
                                <span class="eggb-trust-value-max">/ <?php echo esc_html($aggregate['max']); ?></span>
                            </span>
                        </div>
                        <span class="eggb-trust-label">
                            <?php
                            $parts = [];
                            if ($aggregate['count'] > 0) {
                                $parts[] = number_format_i18n($aggregate['count']) . ' ' . esc_html__('reviews', 'content-egg');
                            }
                            $parts[] = $aggregate['source'];
                            echo esc_html(implode(' · ', $parts));
                            ?>
                        </span>
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
                    <div class="eggb-trust-item eggb-trust-item--badge">
                        <?php echo EggbIcons::get($badge['icon']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <span><?php echo esc_html($badge['label']); ?></span>
                    </div>
                <?php endforeach; ?>

            </div>
        </section>
        <?php
    }
}
