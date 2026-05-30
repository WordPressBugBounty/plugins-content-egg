<?php

namespace ContentEgg\application\EggBlocks\blocks\testimonial\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;

defined('ABSPATH') || exit;

class DefaultVariant
{
    public static function render(array $data, string $theme_class = '', string $data_theme = ''): void
    {
        $heading_tag = $data['heading_tag'];
        $title       = $data['title'];
        $aggregate   = $data['aggregate'];
        $items       = $data['items'];
        ?>
        <?php if ($title !== ''): ?>
            <<?php echo tag_escape($heading_tag); ?> class="eggb-testimonial-title"><?php echo esc_html($title); ?></<?php echo tag_escape($heading_tag); ?>>
        <?php endif; ?>

        <div class="eggb-block eggb-block--panel eggb-testimonial eggb-testimonial--default<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

            <?php if ($aggregate !== null): ?>
                <div class="eggb-testimonial-aggregate">
                    <?php echo self::renderStars($aggregate['stars'], $aggregate['rating'], $aggregate['rating_max']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <span class="eggb-testimonial-aggregate-score">
                        <?php echo esc_html($aggregate['rating'] . ' / ' . $aggregate['rating_max']); ?>
                    </span>
                    <span class="eggb-testimonial-aggregate-meta">
                        <?php
                        if ($aggregate['count'] !== null)
                        {
                            /* translators: 1: review count, 2: platform name */
                            printf(esc_html__('based on %1$s reviews · %2$s', 'content-egg-tpl'),
                                number_format_i18n($aggregate['count']),
                                $aggregate['source']
                            );
                        }
                        else
                        {
                            echo '· ' . esc_html($aggregate['source']);
                        }
                        ?>
                    </span>
                </div>
            <?php endif; ?>

            <div class="eggb-testimonial-body">

                <?php foreach ($items as $item): ?>
                    <div class="eggb-testimonial-entry">
                        <div class="eggb-testimonial-quote"><?php echo esc_html($item['quote']); ?></div>

                        <?php if ($item['stars'] !== null): ?>
                            <?php echo self::renderStars($item['stars'], $item['rating'], 5); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endif; ?>

                        <div class="eggb-testimonial-author-row">
                            <div class="eggb-testimonial-author"><?php echo esc_html($item['author']); ?></div>
                            <?php if ($item['attribution'] !== ''): ?>
                                <div class="eggb-testimonial-attribution"><?php echo esc_html($item['attribution']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>

        </div>
        <?php
    }

    private static function renderStars(array $stars, float $rating, float $max): string
    {
        $label = esc_attr(sprintf('%s out of %s stars', $rating, $max));
        $html  = '<div class="eggb-testimonial-stars" aria-label="' . $label . '">';
        foreach ($stars as $icon)
        {
            $html .= EggbIcons::get($icon);
        }
        $html .= '</div>';

        return $html;
    }
}
