<?php

namespace ContentEgg\application\EggBlocks\blocks\conclusion;

use ContentEgg\application\EggBlocks\blocks\conclusion\variants\CompactVariant;
use ContentEgg\application\EggBlocks\blocks\conclusion\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\shared\EggbSanitizer;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;

defined('ABSPATH') || exit;

class ConclusionRenderer
{
    use RendersWithTheme;

    private const ALLOWED_HEADING_TAGS = ['h1', 'h2', 'h3', 'h4', 'div'];

    public static function render(array $attributes): string
    {
        $summary = trim((string) ($attributes['summary'] ?? ''));
        if ($summary === '')
        {
            return '';
        }

        $variant = ($attributes['variant'] ?? 'default') === 'compact' ? 'compact' : 'default';
        $points = array_values(array_filter(array_map('trim', $attributes['points'] ?? []), 'strlen'));
        $next_steps = self::normalizeNextSteps($attributes['next_steps'] ?? []);

        $conclusion = [
            'section_label' => trim((string) ($attributes['section_label'] ?? '')),
            'title' => trim((string) ($attributes['title'] ?? '')),
            'heading_tag' => self::resolveHeadingTag($attributes['heading_tag'] ?? 'h2'),
            'points' => $points,
            'summary' => EggbSanitizer::basicRichText($summary),
            'next_steps_label' => trim((string) ($attributes['next_steps_label'] ?? '')),
            'next_steps' => $next_steps,
        ];

        $theme_class = self::resolveThemeClass('auto');
        $color_scheme = self::resolveColorScheme($attributes['color_scheme'] ?? 'auto', $theme_class);
        $data_theme = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';

        ob_start();

        switch ($variant)
        {
            case 'compact':
                CompactVariant::render($conclusion, $theme_class, $data_theme);
                break;
            default:
                DefaultVariant::render($conclusion, $theme_class, $data_theme);
                break;
        }

        return (string) ob_get_clean();
    }
    private static function resolveHeadingTag($tag): string
    {
        $tag = (string) $tag;
        return in_array($tag, self::ALLOWED_HEADING_TAGS, true) ? $tag : 'h2';
    }

    private static function normalizeNextSteps($items): array
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

            $text = trim((string) ($item['text'] ?? ''));
            if ($text === '')
            {
                continue;
            }

            $normalized[] = [
                'text' => $text,
                'url' => trim((string) ($item['url'] ?? '')),
            ];
        }

        return $normalized;
    }
}
