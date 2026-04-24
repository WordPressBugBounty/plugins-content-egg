<?php

namespace ContentEgg\application\EggBlocks\blocks\ratingbreakdown\variants;

defined('ABSPATH') || exit;

class DefaultVariant
{
    public static function render(?array $overall, array $categories, string $theme_class, string $data_theme): void
    {
        ?>
        <div class="eggb-block eggb-card eggb-rb eggb-rb--default <?php echo esc_attr($theme_class); ?> p-3 p-md-4"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="d-flex flex-column flex-sm-row gap-4">
                <?php if ($overall !== null) : ?>
                    <div class="d-flex flex-sm-column align-items-center justify-content-center text-center gap-2 flex-shrink-0">
                        <div>
                            <div class="eggb-rb-overall-score"><?php echo esc_html($overall['score']); ?></div>
                            <div class="eggb-label mt-1"><?php echo esc_html($overall['denom']); ?></div>
                        </div>
                        <?php if ($overall['stars_markup'] !== '') : ?>
                            <div class="eggb-rb-stars" aria-label="<?php echo esc_attr($overall['stars_aria']); ?>">
                                <?php echo $overall['stars_markup']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($overall !== null && !empty($categories)) : ?>
                    <div class="eggb-divider-v align-self-stretch d-none d-sm-block" aria-hidden="true"></div>
                <?php endif; ?>

                <?php if (!empty($categories)) : ?>
                    <div class="d-flex flex-column gap-3 flex-grow-1">
                        <?php foreach ($categories as $category) : ?>
                            <div>
                                <div class="d-flex justify-content-between align-items-baseline gap-2 mb-1">
                                    <span class="eggb-rb-cat-label"><?php echo esc_html($category['label']); ?></span>
                                    <span class="eggb-rb-cat-score"><?php echo esc_html($category['score']); ?></span>
                                </div>
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
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
