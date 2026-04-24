<?php

namespace ContentEgg\application\EggBlocks\blocks\methodology;

use ContentEgg\application\EggBlocks\blocks\methodology\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\blocks\methodology\variants\GridVariant;
use ContentEgg\application\EggBlocks\blocks\methodology\variants\TimelineVariant;
use ContentEgg\application\EggBlocks\shared\EggbSanitizer;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;

defined('ABSPATH') || exit;

class MethodologyRenderer
{
    use RendersWithTheme;

    private const ALLOWED_HEADING_TAGS = ['h1', 'h2', 'h3', 'h4', 'div'];

    public static function render(array $attributes): string
    {
        $title = trim((string) ($attributes['title'] ?? ''));
        $variant = (string) ($attributes['variant'] ?? 'default');
        if (!in_array($variant, ['default', 'timeline', 'grid'], true))
        {
            $variant = 'default';
        }

        $items = self::normalizeItems($attributes['items'] ?? [], $variant);
        if (empty($items))
        {
            return '';
        }

        $heading_tag = (string) ($attributes['heading_tag'] ?? 'h3');
        if (!in_array($heading_tag, self::ALLOWED_HEADING_TAGS, true))
        {
            $heading_tag = 'h3';
        }

        $methodology = [
            'section_label' => trim((string) ($attributes['section_label'] ?? __('Methodology', 'content-egg-tpl'))),
            'title' => $title,
            'heading_tag' => $heading_tag,
            'description' => self::sanitizeOptionalRichText($attributes['description'] ?? ''),
            'note' => self::sanitizeOptionalRichText($attributes['note'] ?? ''),
            'items' => $items,
        ];

        $theme_class = self::resolveThemeClass('auto');
        $color_scheme = self::resolveColorScheme($attributes['color_scheme'] ?? 'auto', $theme_class);
        $data_theme = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';

        ob_start();

        switch ($variant)
        {
            case 'timeline':
                TimelineVariant::render($methodology, $theme_class, $data_theme);
                break;
            case 'grid':
                GridVariant::render($methodology, $theme_class, $data_theme);
                break;
            default:
                DefaultVariant::render($methodology, $theme_class, $data_theme);
                break;
        }

        return (string) ob_get_clean();
    }

    private static function normalizeItems($items, string $variant): array
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

            if ($variant === 'default')
            {
                $title = trim((string) ($item['title'] ?? ''));
                $description = trim((string) ($item['description'] ?? ''));
                if ($title === '' && $description === '')
                {
                    continue;
                }

                $normalized[] = [
                    'title' => $title,
                    'description' => self::sanitizeOptionalRichText($description),
                ];
                continue;
            }

            if ($variant === 'timeline')
            {
                $title = trim((string) ($item['title'] ?? ''));
                $description = trim((string) ($item['description'] ?? ''));
                if ($title === '' && $description === '')
                {
                    continue;
                }

                $normalized[] = [
                    'title' => $title,
                    'description' => self::sanitizeOptionalRichText($description),
                ];
                continue;
            }

            $title = trim((string) ($item['title'] ?? ''));
            $description = trim((string) ($item['description'] ?? ''));
            $icon = trim((string) ($item['icon'] ?? ''));
            if ($title === '' && $description === '' && $icon === '')
            {
                continue;
            }

            $normalized[] = [
                'icon' => $icon,
                'title' => $title,
                'description' => self::sanitizeOptionalRichText($description),
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
