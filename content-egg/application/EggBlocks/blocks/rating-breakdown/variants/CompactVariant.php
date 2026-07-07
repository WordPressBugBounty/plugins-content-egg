<?php

namespace ContentEgg\application\EggBlocks\blocks\ratingbreakdown\variants;

defined('ABSPATH') || exit;

class CompactVariant
{
    public static function render(?array $overall, array $categories, string $theme_class, string $data_theme): void
    {
        ?>
        <div class="eggb-block eggb-rb eggb-rb--compact <?php echo esc_attr($theme_class); ?> p-2 p-md-3"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="d-flex align-items-stretch gap-3">
                <?php if ($overall !== null) : ?>
                    <div class="d-flex flex-column align-items-center justify-content-center text-center flex-shrink-0">
                        <div class="eggb-rb-overall-score"><?php echo esc_html($overall['score']); ?></div>
                        <?php if ($overall['stars_markup'] !== '') : ?>
                            <div class="eggb-rb-stars mt-1" role="img" aria-label="<?php echo esc_attr($overall['stars_aria']); ?>">
                                <?php echo $overall['stars_markup']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($overall !== null && !empty($categories)) : ?>
                    <div class="eggb-divider-v align-self-stretch" aria-hidden="true"></div>
                <?php endif; ?>

                <?php if (!empty($categories)) : ?>
                    <div class="d-flex flex-column gap-1 flex-grow-1">
                        <?php foreach ($categories as $category) : ?>
                            <div class="d-flex align-items-center gap-2">
                                <span class="eggb-rb-cat-label eggb-rb-cat-label--compact"><?php echo esc_html($category['label']); ?></span>
                                <div class="eggb-rb-bar-track flex-grow-1">
                                    <div
                                        class="eggb-rb-bar-fill"
                                        role="progressbar"
                                        style="width:<?php echo esc_attr((string) $category['bar_percent']); ?>%;"
                                        aria-valuenow="<?php echo esc_attr((string) $category['bar_percent']); ?>"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                    ></div>
                                </div>
                                <span class="eggb-rb-cat-score eggb-rb-cat-score--compact"><?php echo esc_html($category['score']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
