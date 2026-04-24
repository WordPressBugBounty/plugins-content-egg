<?php

namespace ContentEgg\application\EggBlocks\blocks\ratingbreakdown\variants;

defined('ABSPATH') || exit;

class CategoryGridVariant
{
    public static function render(array $categories, string $theme_class, string $data_theme): void
    {
        ?>
        <div class="eggb-block eggb-rb eggb-rb--category-grid <?php echo esc_attr($theme_class); ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="row g-3">
                <?php foreach ($categories as $category) : ?>
                    <div class="col-12 col-sm-6 col-md-4">
                        <div class="d-flex flex-column gap-2">
                            <div class="d-flex justify-content-between align-items-baseline gap-2">
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
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}
