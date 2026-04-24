<?php

namespace ContentEgg\application\EggBlocks\blocks\toc;

use ContentEgg\application\EggBlocks\blocks\toc\variants\CompactVariant;
use ContentEgg\application\EggBlocks\blocks\toc\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\blocks\toc\variants\NumberedVariant;
use ContentEgg\application\EggBlocks\shared\EggbProductNavCollector;
use ContentEgg\application\EggBlocks\shared\EggbTocCollector;
use ContentEgg\application\EggBlocks\shared\EggbTocSupport;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;

use function ContentEgg\prn;
use function ContentEgg\prnx;

defined('ABSPATH') || exit;

class TocRenderer
{
    use RendersWithTheme;

    public static function render(array $attributes): string
    {
        $variant = self::normalizeVariant($attributes['variant'] ?? 'default');
        $source_mode = self::normalizeSourceMode($attributes['source_mode'] ?? 'auto');
        $items = $source_mode === 'auto'
            ? self::collectAutomaticItems($variant === 'default')
            : self::normalizeManualItems($attributes['items'] ?? [], $variant === 'default');
        if (empty($items))
        {
            return '';
        }

        $label = trim((string) ($attributes['label'] ?? __('In this article', 'content-egg-tpl')));
        $collapsible = !empty($attributes['collapsible']);
        $default_open = !array_key_exists('default_open', $attributes) || !empty($attributes['default_open']);

        $theme_class = self::resolveThemeClass('auto');
        $color_scheme = self::resolveColorScheme($attributes['color_scheme'] ?? 'auto', $theme_class);
        $data_theme = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';

        $products = [];
        if (!empty($attributes['show_products']))
        {
            $post_id = get_the_ID();
            if ($post_id)
            {
                $content = get_post_field('post_content', $post_id);
                if (is_string($content) && $content !== '')
                {
                    $products = EggbProductNavCollector::collect(parse_blocks($content));
                }
            }
        }

        ob_start();

        switch ($variant)
        {
            case 'compact':
                CompactVariant::render($label, $items, $collapsible, $default_open, $theme_class, $data_theme, $products);
                break;
            case 'numbered':
                NumberedVariant::render($label, $items, $collapsible, $default_open, $theme_class, $data_theme, $products);
                break;
            default:
                DefaultVariant::render($label, $items, $collapsible, $default_open, $theme_class, $data_theme, $products);
                break;
        }

        return (string) ob_get_clean();
    }

    private static function normalizeSourceMode($source_mode): string
    {
        return $source_mode === 'manual' ? 'manual' : 'auto';
    }

    private static function normalizeVariant($variant): string
    {
        $variant = (string) $variant;
        if (!in_array($variant, ['default', 'compact', 'numbered'], true))
        {
            return 'default';
        }

        return $variant;
    }

    private static function normalizeManualItems($items, bool $include_sub_items): array
    {
        if (!is_array($items))
        {
            return [];
        }

        $normalized = [];
        foreach ($items as $item)
        {
            if (!is_array($item))
            {
                continue;
            }

            $label = trim((string) ($item['text'] ?? ''));
            $anchor = EggbTocSupport::normalizeAnchor((string) ($item['anchor'] ?? ''));
            if ($label === '')
            {
                continue;
            }

            $sub_items = [];
            if ($include_sub_items && !empty($item['sub_items']) && is_array($item['sub_items']))
            {
                foreach ($item['sub_items'] as $sub_item)
                {
                    if (!is_array($sub_item))
                    {
                        continue;
                    }

                    $sub_label = trim((string) ($sub_item['text'] ?? ''));
                    if ($sub_label === '')
                    {
                        continue;
                    }

                    $sub_items[] = [
                        'label' => $sub_label,
                        'anchor' => self::formatHrefAnchor(
                            EggbTocSupport::normalizeAnchor((string) ($sub_item['anchor'] ?? ''))
                        ),
                        'level' => 3,
                    ];
                }
            }

            $normalized[] = [
                'label' => $label,
                'anchor' => self::formatHrefAnchor($anchor),
                'level' => 2,
                'sub_items' => $sub_items,
            ];
        }

        return $normalized;
    }

    private static function collectAutomaticItems(bool $include_sub_items): array
    {
        $post_id = get_the_ID();
        if (!$post_id)
        {
            return [];
        }

        $content = get_post_field('post_content', $post_id);
        if (!is_string($content) || $content === '')
        {
            return [];
        }

        $collected = EggbTocCollector::collect(parse_blocks($content));
        $items = array_map(static function (array $item): array
        {
            return [
                'label' => $item['label'],
                'anchor' => self::formatHrefAnchor($item['anchor']),
                'level' => (int) ($item['level'] ?? 2),
                'sub_items' => [],
            ];
        }, $collected);

        return $include_sub_items ? self::buildAutomaticNestedItems($items) : $items;
    }

    private static function buildAutomaticNestedItems(array $items): array
    {
        if (empty($items))
        {
            return [];
        }

        $base_level = min(array_map(static function (array $item): int
        {
            return (int) ($item['level'] ?? 2);
        }, $items));

        $nested_items = [];
        $current_parent_index = null;

        foreach ($items as $item)
        {
            $item['sub_items'] = [];
            $level = (int) ($item['level'] ?? $base_level);

            if ($level <= $base_level || $current_parent_index === null)
            {
                $nested_items[] = $item;
                $current_parent_index = array_key_last($nested_items);
                continue;
            }

            $nested_items[$current_parent_index]['sub_items'][] = $item;
        }

        return $nested_items;
    }

    private static function formatHrefAnchor(string $anchor): string
    {
        if ($anchor === '')
        {
            return '';
        }

        return '#' . $anchor;
    }
}
