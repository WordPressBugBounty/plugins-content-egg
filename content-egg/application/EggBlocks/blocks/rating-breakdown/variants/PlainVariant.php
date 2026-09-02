<?php

namespace ContentEgg\application\EggBlocks\blocks\ratingbreakdown\variants;

defined('ABSPATH') || exit;

class PlainVariant
{
    /**
     * Label-rail layout.
     *
     * This block has no section_label or band_label attribute, so its rail
     * carries the data it actually has — the overall score, its denominator
     * and the star row. No caption is invented for it.
     *
     * The category bars are dropped; the numbers they encoded stay on every
     * row as leader-dot entries.
     */
    public static function render(?array $overall, array $categories, string $theme_class = '', string $data_theme = ''): void
    {
        $classes = 'eggb-block eggb-plain eggb-rating-breakdown eggb-rating-breakdown--plain';
        if ($theme_class !== '')
        {
            $classes .= ' ' . $theme_class;
        }

        $score        = $overall !== null ? (string) ($overall['score'] ?? '') : '';
        $denom        = $overall !== null ? (string) ($overall['denom'] ?? '') : '';
        $stars_markup = $overall !== null ? (string) ($overall['stars_markup'] ?? '') : '';
        $stars_aria   = $overall !== null ? (string) ($overall['stars_aria'] ?? '') : '';

        $has_rail = $score !== '' || $denom !== '' || $stars_markup !== '';
        if (!$has_rail)
        {
            $classes .= ' eggb-plain--norail';
        }
        ?>
        <div class="<?php echo esc_attr($classes); ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ($has_rail) : ?>
            <div class="eggb-plain-rail">
                <?php if ($score !== '') : ?>
                    <span class="eggb-rail-score"><?php echo esc_html($score); ?></span>
                <?php endif; ?>

                <?php if ($denom !== '') : ?>
                    <span class="eggb-rail-denom eggb-rail-denom--block"><?php echo esc_html($denom); ?></span>
                <?php endif; ?>

                <?php if ($stars_markup !== '') : ?>
                    <span class="eggb-rail-stars" role="img" aria-label="<?php echo esc_attr($stars_aria); ?>">
                        <?php echo $stars_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="eggb-plain-col">
                <?php if (!empty($categories)) : ?>
                    <ul class="eggb-leaders">
                        <?php foreach ($categories as $cat) : ?>
                            <li>
                                <span><?php echo esc_html((string) ($cat['label'] ?? '')); ?></span>
                                <span class="eggb-leader-fill"></span>
                                <span class="eggb-leader-val"><?php echo esc_html((string) ($cat['score'] ?? '')); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
