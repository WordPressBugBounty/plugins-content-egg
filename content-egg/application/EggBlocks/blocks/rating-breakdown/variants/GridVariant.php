<?php

namespace ContentEgg\application\EggBlocks\blocks\ratingbreakdown\variants;

defined('ABSPATH') || exit;

class GridVariant
{
    public static function render(?array $overall, array $categories, string $theme_class, string $data_theme): void
    {
        ?>
        <div class="eggb-block eggb-card eggb-rb eggb-rb--grid <?php echo esc_attr($theme_class); ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="d-flex flex-column flex-sm-row">
                <?php if ($overall !== null) : ?>
                    <div class="eggb-rb-hero d-flex flex-column align-items-center justify-content-center text-center p-3 flex-shrink-0">
                        <div class="eggb-rb-overall-score"><?php echo esc_html($overall['score']); ?></div>
                        <div class="eggb-label mt-1 mb-2"><?php echo esc_html($overall['denom']); ?></div>
                        <?php if ($overall['stars_markup'] !== '') : ?>
                            <div class="eggb-rb-stars" aria-label="<?php echo esc_attr($overall['stars_aria']); ?>">
                                <?php echo $overall['stars_markup']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($categories)) : ?>
                    <div class="eggb-rb-grid-cells flex-grow-1">
                        <?php foreach ($categories as $category) : ?>
                            <div class="eggb-rb-cell d-flex flex-column justify-content-between p-2">
                                <span class="eggb-rb-cat-label eggb-rb-cell-label"><?php echo esc_html($category['label']); ?></span>
                                <div>
                                    <div class="eggb-rb-cat-score eggb-rb-cell-score"><?php echo esc_html($category['score']); ?></div>
                                    <div class="eggb-rb-bar-track">
                                        <div
                                            class="eggb-rb-bar-fill"
                                            role="progressbar"
                                            style="width:<?php echo esc_attr((string) $category['bar_percent']); ?>%;"
                                            aria-valuenow="<?php echo esc_attr((string) $category['bar_percent']); ?>"
                                            aria-valuemin="0"
                                            aria-valuemax="100"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
