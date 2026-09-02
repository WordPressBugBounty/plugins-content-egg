<?php

namespace ContentEgg\application\EggBlocks\blocks\myth_fact;

use ContentEgg\application\EggBlocks\blocks\myth_fact\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\blocks\myth_fact\variants\InlineVariant;
use ContentEgg\application\EggBlocks\blocks\myth_fact\variants\PlainVariant;
use ContentEgg\application\EggBlocks\shared\EggbSanitizer;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;

defined('ABSPATH') || exit;

class MythFactRenderer
{
    use RendersWithTheme;

    private const ALLOWED_HEADING_TAGS = ['h1', 'h2', 'h3', 'h4', 'div'];

    public static function render(array $attributes): string
    {
        $variant = (string) ($attributes['variant'] ?? 'default');
        if (!in_array($variant, ['default', 'inline', 'plain'], true))
        {
            $variant = 'default';
        }
        $items = self::normalizeItems($attributes['items'] ?? [], $variant);

        if (empty($items))
        {
            return '';
        }

        $heading_tag_raw = (string) ($attributes['heading_tag'] ?? 'h2');
        $heading_tag = in_array($heading_tag_raw, self::ALLOWED_HEADING_TAGS, true) ? $heading_tag_raw : 'h2';

        $data = [
            'section_label' => self::resolveHeading($attributes['section_label'] ?? __('Myth vs Fact', 'content-egg-tpl')),
            'title' => trim((string) ($attributes['title'] ?? '')),
            'heading_tag' => $heading_tag,
            'label_myth' => self::resolveHeading($attributes['label_myth'] ?? __('Myth', 'content-egg-tpl')),
            'label_fact' => self::resolveHeading($attributes['label_fact'] ?? __('Fact', 'content-egg-tpl')),
            'label_why' => self::resolveHeading($attributes['label_why'] ?? __('Why it matters', 'content-egg-tpl')),
            'items' => $items,
        ];

        $theme_class = self::resolveThemeClass('auto');
        $color_scheme = self::resolveColorScheme($attributes['color_scheme'] ?? 'auto', $theme_class);
        $data_theme = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';

        ob_start();

        if ($variant === 'plain')
        {
            PlainVariant::render($data, $theme_class, $data_theme);
        }
        elseif ($variant === 'inline')
        {
            InlineVariant::render($data, $theme_class, $data_theme);
        }
        else
        {
            DefaultVariant::render($data, $theme_class, $data_theme);
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

            $myth_text = trim((string) ($item['myth_text'] ?? ''));
            if ($myth_text === '')
            {
                continue;
            }

            if ($variant === 'inline')
            {
                $verdict = trim((string) ($item['verdict'] ?? ''));
                if ($verdict === '')
                {
                    continue;
                }

                $normalized[] = [
                    'myth_text' => $myth_text,
                    'verdict' => $verdict,
                    'verdict_text' => trim((string) ($item['verdict_text'] ?? '')),
                    'verdict_class' => self::resolveVerdictClass(trim((string) ($item['verdict_tone'] ?? ''))),
                ];
                continue;
            }

            $fact_text = trim((string) ($item['fact_text'] ?? ''));
            if ($fact_text === '')
            {
                continue;
            }

            $normalized[] = [
                'myth_text' => $myth_text,
                'fact_text' => self::sanitizeOptionalRichText($fact_text),
                'why_text' => self::sanitizeOptionalRichText(trim((string) ($item['why_text'] ?? ''))),
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

    private static function resolveHeading(string $value): string
    {
        return trim($value);
    }

    private static function resolveVerdictClass(string $verdict_tone): string
    {
        $tone = strtolower(trim($verdict_tone));

        if ($tone === 'true')
        {
            return 'eggb-mf-verdict--true';
        }

        if ($tone === 'false')
        {
            return 'eggb-mf-verdict--false';
        }

        if ($tone === 'partial')
        {
            return 'eggb-mf-verdict--partial';
        }

        if ($tone === 'neutral')
        {
            return '';
        }

        return '';
    }
}
