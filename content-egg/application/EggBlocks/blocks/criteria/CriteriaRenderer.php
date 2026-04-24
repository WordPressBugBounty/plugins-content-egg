<?php

namespace ContentEgg\application\EggBlocks\blocks\criteria;

use ContentEgg\application\EggBlocks\blocks\criteria\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\blocks\criteria\variants\ListVariant;
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

        $variant = ($attributes['variant'] ?? 'default') === 'list' ? 'list' : 'default';

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

        $theme_class = self::resolveThemeClass('auto');
        $color_scheme = self::resolveColorScheme($attributes['color_scheme'] ?? 'auto', $theme_class);
        $data_theme = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';

        ob_start();

        if ($variant === 'list')
        {
            ListVariant::render($header, $items, $theme_class, $data_theme);
        }
        else
        {
            DefaultVariant::render($header, $items, $theme_class, $data_theme);
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
                'description' => $description,
                'importance' => $importance,
                'importance_label' => ucfirst($importance),
                'look_for' => $look_for,
                'avoid' => $avoid,
            ];
        }

        return $normalized;
    }
}
