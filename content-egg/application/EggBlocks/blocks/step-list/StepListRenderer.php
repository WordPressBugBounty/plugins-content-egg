<?php

namespace ContentEgg\application\EggBlocks\blocks\steplist;

use ContentEgg\application\EggBlocks\blocks\steplist\variants\CardGridVariant;
use ContentEgg\application\EggBlocks\blocks\steplist\variants\PlainVariant;
use ContentEgg\application\EggBlocks\blocks\steplist\variants\CardsVariant;
use ContentEgg\application\EggBlocks\blocks\steplist\variants\ChecklistVariant;
use ContentEgg\application\EggBlocks\blocks\steplist\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\shared\EggbSanitizer;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;

defined('ABSPATH') || exit;

class StepListRenderer
{
    use RendersWithTheme;

    private const ALLOWED_HEADING_TAGS = ['h1', 'h2', 'h3', 'h4', 'div'];

    public static function render(array $attributes): string
    {
        $variant = (string) ($attributes['variant'] ?? 'default');
        if (!in_array($variant, ['default', 'checklist', 'cards', 'card-grid', 'plain'], true))
        {
            $variant = 'default';
        }

        $steps = self::normalizeSteps($attributes['steps'] ?? []);
        if (empty($steps))
        {
            return '';
        }

        $heading_tag_raw = (string) ($attributes['heading_tag'] ?? 'h2');
        $heading_tag = in_array($heading_tag_raw, self::ALLOWED_HEADING_TAGS, true) ? $heading_tag_raw : 'h2';

        $step_list = [
            'section_label' => trim((string) ($attributes['section_label'] ?? __('Step List', 'content-egg-tpl'))),
            'title' => trim((string) ($attributes['title'] ?? '')),
            'heading_tag' => $heading_tag,
            'note' => self::sanitizeOptionalRichText($attributes['note'] ?? ''),
            'steps' => $steps,
        ];

        $theme_class = self::resolveThemeClass('auto');
        $color_scheme = self::resolveColorScheme($attributes['color_scheme'] ?? 'auto', $theme_class);
        $data_theme = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';

        ob_start();

        switch ($variant)
        {
            case 'checklist':
                ChecklistVariant::render($step_list, $theme_class, $data_theme);
                break;
            case 'cards':
                CardsVariant::render($step_list, $theme_class, $data_theme);
                break;
            case 'card-grid':
                CardGridVariant::render($step_list, $theme_class, $data_theme);
                break;
            case 'plain':
                PlainVariant::render($step_list, $theme_class, $data_theme);
                break;
            default:
                DefaultVariant::render($step_list, $theme_class, $data_theme);
                break;
        }

        return (string) ob_get_clean();
    }

    private static function normalizeSteps($steps): array
    {
        if (!is_array($steps))
        {
            return [];
        }

        $normalized = [];
        foreach ($steps as $step)
        {
            if (!is_array($step))
            {
                continue;
            }

            $title = trim((string) ($step['title'] ?? ''));
            $description = trim((string) ($step['description'] ?? ''));
            if ($title === '' && $description === '')
            {
                continue;
            }

            if ($title === '')
            {
                $title = $description;
                $description = '';
            }

            $normalized[] = [
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
