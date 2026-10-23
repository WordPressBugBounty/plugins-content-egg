<?php

namespace ContentEgg\application\EggBlocks\blocks\variantswitcher\variants;

use ContentEgg\application\EggBlocks\blocks\variantswitcher\VariantSwitcherRenderer;

defined('ABSPATH') || exit;

class ChipsVariant
{
    /**
     * One row of chips per dimension (VariantSwitcher::chips()). The current
     * chip is a span with aria-current and no price (the page shows it), a
     * combination without a page is a disabled span, every other chip is a
     * link with the sibling's lowest price. A dimension whose only chip
     * is the current (or "All") one offers no choice and is not printed.
     */
    public static function render(array $model, string $theme_class, string $data_theme): void
    {
        $dimensions = [];
        foreach ((array) ($model['dimensions'] ?? []) as $dimension) {
            if (!is_array($dimension) || count((array) ($dimension['chips'] ?? [])) < 2) {
                continue;
            }
            $dimensions[] = $dimension;
        }

        if (!$dimensions) {
            return;
        }

        $classes = 'eggb-block eggb-variant-switcher eggb-variant-switcher--chips';
        if ($theme_class !== '') {
            $classes .= ' ' . $theme_class;
        }
        ?>
        <nav class="<?php echo esc_attr($classes); ?>" aria-label="<?php echo esc_attr(__('Variants', 'content-egg-tpl')); ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php foreach ($dimensions as $dimension) :
                $selected = (string) ($dimension['selected'] ?? '');
                $selected_label = strtolower($selected) === 'any' ? __('All', 'content-egg-tpl') : $selected;
                ?>
                <div class="eggb-vs-dimension">
                    <div class="eggb-vs-label"><?php echo esc_html((string) $dimension['name']); ?>: <b><?php echo esc_html($selected_label); ?></b></div>
                    <div class="eggb-vs-chips">
                        <?php foreach ($dimension['chips'] as $chip) :
                            self::chip((array) $chip);
                        endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </nav>
        <?php
    }

    private static function chip(array $chip): void
    {
        $label = !empty($chip['all']) ? __('All', 'content-egg-tpl') : (string) ($chip['value'] ?? '');
        $current = !empty($chip['current']);
        $disabled = !$current && (int) ($chip['post_id'] ?? 0) <= 0;
        // The current page's price is already on the page, next to the
        // switcher; only the siblings show theirs.
        $price = ($disabled || $current) ? '' : VariantSwitcherRenderer::priceLine(isset($chip['summary']) && is_array($chip['summary']) ? $chip['summary'] : null, false);

        $inner = '<span class="eggb-vs-value">' . esc_html($label) . '</span>' . $price;

        if ($current) {
            echo '<span class="eggb-vs-chip is-current" aria-current="page">' . $inner . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } elseif ($disabled) {
            echo '<span class="eggb-vs-chip is-disabled" aria-disabled="true" title="' . esc_attr(__('Not available', 'content-egg-tpl')) . '">' . $inner . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            echo '<a class="eggb-vs-chip" href="' . esc_url((string) ($chip['url'] ?? '')) . '">' . $inner . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
