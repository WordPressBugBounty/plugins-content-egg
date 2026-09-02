<?php

namespace ContentEgg\application\EggBlocks\blocks\criteria;

use ContentEgg\application\EggBlocks\blocks\criteria\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\blocks\criteria\variants\ListVariant;
use ContentEgg\application\EggBlocks\blocks\criteria\variants\PlainVariant;
use ContentEgg\application\EggBlocks\shared\EggbSanitizer;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;

defined('ABSPATH') || exit;

class CriteriaRenderer
{
    use RendersWithTheme;

    private const ALLOWED_HEADING_TAGS = ['h1', 'h2', 'h3', 'h4', 'div'];

    public static function render(array $attributes): string
    {
        $items = self::normalizeCriteria($attributes['criteria'] ?? []);
        if (empty($items))
        {
            return '';
        }

        $variant = (string) ($attributes['variant'] ?? 'default');
        if (!in_array($variant, ['default', 'list', 'plain'], true))
        {
            $variant = 'default';
        }

        $heading_tag = (string) ($attributes['heading_tag'] ?? 'h2');
        if (!in_array($heading_tag, self::ALLOWED_HEADING_TAGS, true))
        {
            $heading_tag = 'h2';
        }

        $header = [
            'section_label' => trim((string) ($attributes['section_label'] ?? __('What to look for', 'content-egg-tpl'))),
            'title'         => trim((string) ($attributes['title'] ?? '')),
            'heading_tag'   => $heading_tag,
        ];

        $labels = [
            'look_for' => trim((string) ($attributes['label_look_for'] ?? '')) ?: __('Look for', 'content-egg-tpl'),
            'avoid'    => trim((string) ($attributes['label_avoid'] ?? '')) ?: __('Avoid', 'content-egg-tpl'),
        ];

        $theme_class = self::resolveThemeClass('auto');
        $color_scheme = self::resolveColorScheme($attributes['color_scheme'] ?? 'auto', $theme_class);
        $data_theme = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';

        ob_start();

        if ($variant === 'list')
        {
            ListVariant::render($header, $items, $labels, $theme_class, $data_theme);
        }
        elseif ($variant === 'plain')
        {
            PlainVariant::render($header, $items, $labels, $theme_class, $data_theme);
        }
        else
        {
            DefaultVariant::render($header, $items, $labels, $theme_class, $data_theme);
        }

        return (string) ob_get_clean();
    }

    private static function normalizeCriteria($items): array
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

            $title = trim((string) ($item['title'] ?? ''));
            $description = trim((string) ($item['description'] ?? ''));
            $look_for = trim((string) ($item['look_for'] ?? ''));
            $avoid = trim((string) ($item['avoid'] ?? ''));

            if ($title === '' && $description === '' && $look_for === '' && $avoid === '')
            {
                continue;
            }

            $importance = strtolower(trim((string) ($item['importance'] ?? 'medium')));
            if (!in_array($importance, ['high', 'medium', 'low'], true))
            {
                $importance = 'medium';
            }

            $normalized[] = [
                'title' => $title,
                'description' => self::sanitizeOptionalRichText($description),
                'importance' => $importance,
                'importance_label' => match($importance) {
                    'high'  => __('High', 'content-egg-tpl'),
                    'low'   => __('Low', 'content-egg-tpl'),
                    default => __('Medium', 'content-egg-tpl'),
                },
                'importance_dots' => match($importance) {
                    'high'  => [true, true, true],
                    'low'   => [true, false, false],
                    default => [true, true, false],
                },
                'look_for' => $look_for,
                'avoid' => $avoid,
            ];
        }

        return $normalized;
    }

    private static function sanitizeOptionalRichText($text): string
    {
        $text = trim((string) $text);
        if ($text === '')
        {
            return '';
        }

        return EggbSanitizer::basicRichText($text);
    }
}
