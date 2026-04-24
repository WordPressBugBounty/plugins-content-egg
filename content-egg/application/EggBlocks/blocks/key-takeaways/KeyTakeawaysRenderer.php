<?php

namespace ContentEgg\application\EggBlocks\blocks\keytakeaways;

use ContentEgg\application\EggBlocks\blocks\keytakeaways\variants\CardsVariant;
use ContentEgg\application\EggBlocks\blocks\keytakeaways\variants\CompactVariant;
use ContentEgg\application\EggBlocks\blocks\keytakeaways\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\blocks\keytakeaways\variants\InlineVariant;
use ContentEgg\application\EggBlocks\shared\EggbSanitizer;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;

defined('ABSPATH') || exit;

class KeyTakeawaysRenderer
{
    use RendersWithTheme;

    private const ALLOWED_HEADING_TAGS = ['h1', 'h2', 'h3', 'h4', 'div'];

    public static function render(array $attributes): string
    {
        $variant = (string) ($attributes['variant'] ?? 'default');
        if (!in_array($variant, ['default', 'compact', 'inline', 'cards'], true))
        {
            $variant = 'default';
        }

        $items = self::normalizeItems($attributes['items'] ?? []);
        if (empty($items))
        {
            return '';
        }

        $heading_tag_raw = (string) ($attributes['heading_tag'] ?? 'h2');
        $heading_tag = in_array($heading_tag_raw, self::ALLOWED_HEADING_TAGS, true) ? $heading_tag_raw : 'h2';

        $takeaways = [
            'section_label' => trim((string) ($attributes['section_label'] ?? __('Key Takeaways', 'content-egg-tpl'))),
            'title' => trim((string) ($attributes['title'] ?? '')),
            'heading_tag' => $heading_tag,
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
            case 'compact':
                CompactVariant::render($takeaways, $theme_class, $data_theme);
                break;
            case 'inline':
                InlineVariant::render($takeaways, $theme_class, $data_theme);
                break;
            case 'cards':
                CardsVariant::render($takeaways, $theme_class, $data_theme);
                break;
            default:
                DefaultVariant::render($takeaways, $theme_class, $data_theme);
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

            $text_raw = trim((string) ($item['text'] ?? ''));
            $title = trim((string) ($item['title'] ?? ''));
            if ($text_raw === '' && $title === '')
            {
                continue;
            }

            $normalized[] = [
                'text' => self::sanitizeInlineItemText($text_raw),
                'title' => $title,
            ];
        }

        return $normalized;
    }

    private static function sanitizeInlineItemText(string $text): string
    {
        return wp_kses($text, [
            'strong' => [],
            'b' => [],
            'em' => [],
            'i' => [],
            'a' => [
                'href' => true,
                'rel' => true,
                'target' => true,
            ],
            'code' => [],
            's' => [],
            'br' => [],
        ]);
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
