<?php

namespace ContentEgg\application\EggBlocks\blocks\definitions;

use ContentEgg\application\EggBlocks\blocks\definitions\variants\CardsVariant;
use ContentEgg\application\EggBlocks\blocks\definitions\variants\CompactVariant;
use ContentEgg\application\EggBlocks\blocks\definitions\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\blocks\definitions\variants\InlineVariant;
use ContentEgg\application\EggBlocks\shared\EggbSanitizer;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;

defined('ABSPATH') || exit;

class DefinitionsRenderer
{
    use RendersWithTheme;

    private const ALLOWED_HEADING_TAGS = ['h1', 'h2', 'h3', 'h4', 'div'];

    public static function render(array $attributes): string
    {
        $items = self::normalizeItems($attributes['items'] ?? []);
        if (empty($items))
        {
            return '';
        }

        $variant = (string) ($attributes['variant'] ?? 'default');
        if (!in_array($variant, ['default', 'compact', 'inline', 'cards'], true))
        {
            $variant = 'default';
        }

        $heading_tag_raw = (string) ($attributes['heading_tag'] ?? 'h2');
        $heading_tag = in_array($heading_tag_raw, self::ALLOWED_HEADING_TAGS, true) ? $heading_tag_raw : 'h2';

        $definitions = [
            'section_label' => trim((string) ($attributes['section_label'] ?? '')),
            'title' => trim((string) ($attributes['title'] ?? '')),
            'heading_tag' => $heading_tag,
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
            case 'compact':
                CompactVariant::render($definitions, $theme_class, $data_theme);
                break;
            case 'inline':
                InlineVariant::render($definitions, $theme_class, $data_theme);
                break;
            case 'cards':
                CardsVariant::render($definitions, $theme_class, $data_theme);
                break;
            default:
                DefaultVariant::render($definitions, $theme_class, $data_theme);
                break;
        }

        return (string) ob_get_clean();
    }

    private static function normalizeItems($items): array
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

            $term = trim((string) ($item['term'] ?? ''));
            $definition = trim((string) ($item['definition'] ?? ''));
            if ($term === '' || $definition === '')
            {
                continue;
            }

            $normalized[] = [
                'term' => $term,
                'definition' => self::sanitizeOptionalRichText($definition),
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
