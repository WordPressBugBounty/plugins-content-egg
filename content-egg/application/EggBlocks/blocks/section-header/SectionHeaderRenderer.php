<?php

namespace ContentEgg\application\EggBlocks\blocks\sectionheader;

use ContentEgg\application\EggBlocks\blocks\sectionheader\variants\AccentVariant;
use ContentEgg\application\EggBlocks\blocks\sectionheader\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\blocks\sectionheader\variants\EditorialVariant;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;

defined('ABSPATH') || exit;

class SectionHeaderRenderer
{
    use RendersWithTheme;

    public static function render(array $attributes): string
    {
        $variant = (string) ($attributes['variant'] ?? 'default');
        if (!in_array($variant, ['default', 'accent', 'editorial'], true))
        {
            $variant = 'default';
        }

        $section = self::normalizeSection($attributes);
        if (
            $section['title'] === '' &&
            $section['subtitle'] === '' &&
            $section['kicker'] === '' &&
            $section['meta'] === '' &&
            $section['step_label'] === '' &&
            empty($section['chips']) &&
            $section['step_badge'] === ''
        )
        {
            return '';
        }

        $theme_class = self::resolveThemeClass('auto');
        $color_scheme = self::resolveColorScheme($attributes['color_scheme'] ?? 'auto', $theme_class);
        $data_theme = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';

        ob_start();

        switch ($variant)
        {
            case 'accent':
                AccentVariant::render($section, $theme_class, $data_theme);
                break;
            case 'editorial':
                EditorialVariant::render($section, $theme_class, $data_theme);
                break;
            default:
                DefaultVariant::render($section, $theme_class, $data_theme);
                break;
        }

        return (string) ob_get_clean();
    }

    private static function normalizeSection(array $attributes): array
    {
        $raw_step_number = trim((string) ($attributes['step_number'] ?? ''));
        $step_number = is_numeric($raw_step_number) ? (int) $raw_step_number : null;
        $step_badge = self::formatStepNumber($step_number);
        $step_label_text = trim((string) ($attributes['step_label_text'] ?? __('Step', 'content-egg-tpl')));
        $step_label = ($step_badge !== '' && $step_label_text !== '')
            ? $step_label_text . ' ' . $step_badge
            : ($step_badge !== '' ? $step_badge : '');

        $chips = [];
        foreach (($attributes['chips'] ?? []) as $chip)
        {
            $chip = trim((string) $chip);
            if ($chip !== '')
            {
                $chips[] = $chip;
            }
        }

        return [
            'step_badge' => $step_badge,
            'step_label' => $step_label,
            'title' => trim((string) ($attributes['title'] ?? '')),
            'heading_tag' => self::resolveHeadingTag($attributes['heading_tag'] ?? 'h2'),
            'subtitle' => trim((string) ($attributes['subtitle'] ?? '')),
            'kicker' => trim((string) ($attributes['kicker'] ?? '')),
            'meta' => trim((string) ($attributes['meta'] ?? '')),
            'chips' => $chips,
        ];
    }

    private static function resolveHeadingTag($tag): string
    {
        $tag = (string) $tag;

        if (!in_array($tag, ['h1', 'h2', 'h3', 'h4', 'div'], true))
        {
            return 'h2';
        }

        return $tag;
    }

    private static function formatStepNumber(?int $step_number): string
    {
        if ($step_number === null)
        {
            return '';
        }

        if ($step_number >= 0 && $step_number <= 9)
        {
            return '0' . (string) $step_number;
        }

        return (string) $step_number;
    }
}
