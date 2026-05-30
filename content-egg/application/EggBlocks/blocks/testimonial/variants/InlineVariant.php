<?php

namespace ContentEgg\application\EggBlocks\blocks\testimonial\variants;

defined('ABSPATH') || exit;

class InlineVariant
{
    public static function render(array $data, string $theme_class = '', string $data_theme = ''): void
    {
        $heading_tag = $data['heading_tag'];
        $title       = $data['title'];
        $items       = $data['items'];
        ?>
        <?php if ($title !== ''): ?>
            <<?php echo tag_escape($heading_tag); ?> class="eggb-testimonial-title"><?php echo esc_html($title); ?></<?php echo tag_escape($heading_tag); ?>>
        <?php endif; ?>
        <aside class="eggb-block eggb-testimonial eggb-testimonial--inline<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php foreach ($items as $item):
                $attribution_parts = array_filter([$item['author'], $item['attribution']]);
                $attribution_line  = '— ' . implode(', ', $attribution_parts);
            ?>
                <div class="eggb-testimonial-quote">
                    <?php echo esc_html($item['quote']); ?>
                    <span class="eggb-testimonial-attribution-line"><?php echo esc_html($attribution_line); ?></span>
                </div>
            <?php endforeach; ?>
        </aside>
        <?php
    }
}
