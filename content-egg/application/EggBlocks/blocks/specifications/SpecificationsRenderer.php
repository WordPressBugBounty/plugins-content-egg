<?php

namespace ContentEgg\application\EggBlocks\blocks\specifications;

use ContentEgg\application\EggBlocks\blocks\specifications\variants\CompactVariant;
use ContentEgg\application\EggBlocks\blocks\specifications\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\blocks\specifications\variants\HighlightVariant;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;

defined('ABSPATH') || exit;

class SpecificationsRenderer
{
    use RendersWithTheme;

    private const ALLOWED_HEADING_TAGS = ['h1', 'h2', 'h3', 'h4', 'div'];

    public static function render(array $attributes): string
    {
        $variant = $attributes['variant'] ?? 'default';
        $specs = self::normalizeSpecs($attributes['specs'] ?? []);

        $section_label = trim((string) ($attributes['section_label'] ?? ''));
        $title = trim((string) ($attributes['title'] ?? ''));
        $heading_tag_raw = (string) ($attributes['heading_tag'] ?? 'h2');
        $heading_tag = in_array($heading_tag_raw, self::ALLOWED_HEADING_TAGS, true) ? $heading_tag_raw : 'h2';

        // Legacy fallback for default variant only: label_heading → section_label → "Key specs"
        $default_section_label = $section_label;
        if ($variant === 'default' && $default_section_label === '')
        {
            $legacy = trim((string) ($attributes['label_heading'] ?? ''));
            $default_section_label = $legacy !== '' ? $legacy : __('Key specs', 'content-egg-tpl');
        }

        $theme_class = self::resolveThemeClass('auto');
        $color_scheme = self::resolveColorScheme($attributes['color_scheme'] ?? 'auto', $theme_class);
        $data_theme = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';

        if ($variant === 'default' && empty($specs) && $default_section_label === '' && $title === '')
        {
            return '';
        }

        $specs_data = [
            'section_label' => $variant === 'default' ? $default_section_label : $section_label,
            'title'         => $title,
            'heading_tag'   => $heading_tag,
        ];

        ob_start();

        switch ($variant)
        {
            case 'compact':
                CompactVariant::render($specs, $specs_data, $theme_class, $data_theme);
                break;
            case 'highlight':
                HighlightVariant::render($specs, $specs_data, $theme_class, $data_theme);
                break;
            default:
                DefaultVariant::render($specs, $specs_data, $theme_class, $data_theme);
                break;
        }

        return (string) ob_get_clean();
    }

    private static function normalizeSpecs($specs): array
    {
        if (!is_array($specs))
        {
            return [];
        }

        $normalized = [];

        foreach ($specs as $spec)
        {
            if (!is_array($spec))
            {
                continue;
            }

            $value = isset($spec['value']) ? trim((string) $spec['value']) : '';
            if ($value === '')
            {
                continue;
            }

            $normalized[] = [
                'icon' => isset($spec['icon']) ? trim((string) $spec['icon']) : '',
                'value' => $value,
                'label' => isset($spec['label']) ? trim((string) $spec['label']) : '',
            ];
        }

        return $normalized;
    }

}
