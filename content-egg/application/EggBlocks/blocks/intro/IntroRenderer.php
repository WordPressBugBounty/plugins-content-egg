<?php

namespace ContentEgg\application\EggBlocks\blocks\intro;

use ContentEgg\application\EggBlocks\blocks\intro\variants\CompactVariant;
use ContentEgg\application\EggBlocks\blocks\intro\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\shared\EggbSanitizer;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;

defined('ABSPATH') || exit;

class IntroRenderer
{
    use RendersWithTheme;

    private const ALLOWED_HEADING_TAGS = ['h1', 'h2', 'h3', 'h4', 'div'];

    public static function render(array $attributes): string
    {
        $title = trim((string) ($attributes['title'] ?? ''));
        $lead = trim((string) ($attributes['lead'] ?? ''));

        if ($title === '' && $lead === '')
        {
            return '';
        }

        $variant = ($attributes['variant'] ?? 'default') === 'compact' ? 'compact' : 'default';
        $section_label = trim((string) ($attributes['section_label'] ?? ''));
        $heading_tag = (string) ($attributes['heading_tag'] ?? 'h2');
        $body = trim((string) ($attributes['body'] ?? ''));
        $points_label = trim((string) ($attributes['points_label'] ?? ''));
        $points = array_values(array_filter(array_map('trim', $attributes['points'] ?? []), 'strlen'));
        $cta_label = trim((string) ($attributes['cta_label'] ?? ''));
        $cta_url = trim((string) ($attributes['cta_url'] ?? ''));

        $theme_class = self::resolveThemeClass('auto');
        $color_scheme = self::resolveColorScheme($attributes['color_scheme'] ?? 'auto', $theme_class);
        $data_theme = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';

        $intro = [
            'section_label' => $section_label,
            'title' => $title,
            'heading_tag' => in_array($heading_tag, self::ALLOWED_HEADING_TAGS, true) ? $heading_tag : 'h2',
            'lead' => $lead,
            'body' => $body !== '' ? EggbSanitizer::basicRichText($body) : '',
            'points_label' => $points_label,
            'points' => $points,
            'cta_label' => $cta_label,
            'cta_url' => $cta_url !== '' ? esc_url($cta_url) : '',
        ];

        ob_start();

        switch ($variant)
        {
            case 'compact':
                CompactVariant::render($intro, $theme_class, $data_theme);
                break;
            default:
                DefaultVariant::render($intro, $theme_class, $data_theme);
                break;
        }

        return (string) ob_get_clean();
    }
}
