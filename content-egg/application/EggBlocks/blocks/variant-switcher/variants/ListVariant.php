<?php

namespace ContentEgg\application\EggBlocks\blocks\variantswitcher\variants;

use ContentEgg\application\EggBlocks\blocks\variantswitcher\VariantSwitcherRenderer;

defined('ABSPATH') || exit;

class ListVariant
{
    /**
     * One row per sibling page (VariantSwitcher::rows()), labels as given.
     * The current row is a span with aria-current, the others are links.
     */
    public static function render(array $rows, string $theme_class, string $data_theme): void
    {
        $rows = array_values(array_filter($rows, 'is_array'));
        if (!$rows) {
            return;
        }

        $classes = 'eggb-block eggb-variant-switcher eggb-variant-switcher--list';
        if ($theme_class !== '') {
            $classes .= ' ' . $theme_class;
        }
        ?>
        <nav class="<?php echo esc_attr($classes); ?>" aria-label="<?php echo esc_attr(__('Variants', 'content-egg-tpl')); ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="eggb-vs-rows">
                <?php foreach ($rows as $row) :
                    self::row($row);
                endforeach; ?>
            </div>
        </nav>
        <?php
    }

    private static function row(array $row): void
    {
        $current = !empty($row['current']);
        $summary = isset($row['summary']) && is_array($row['summary']) ? $row['summary'] : null;

        $meta = VariantSwitcherRenderer::priceLine($summary, $current);
        $offers = $summary !== null ? (int) ($summary['offers'] ?? 0) : 0;
        if ($offers > 0) {
            /* translators: %d: the number of shops that offer this product variant. */
            $meta .= '<span class="eggb-vs-shops">' . esc_html(sprintf(_n('%d shop', '%d shops', $offers, 'content-egg-tpl'), $offers)) . '</span>';
        }

        $inner = '<span class="eggb-vs-row-label">' . esc_html((string) ($row['label'] ?? '')) . '</span>'
            . '<span class="eggb-vs-row-meta">' . $meta . '</span>';

        if ($current) {
            echo '<span class="eggb-vs-row is-current" aria-current="page">' . $inner . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            echo '<a class="eggb-vs-row" href="' . esc_url((string) ($row['url'] ?? '')) . '">' . $inner . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
