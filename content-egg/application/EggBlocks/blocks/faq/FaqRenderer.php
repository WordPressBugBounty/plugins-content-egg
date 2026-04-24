<?php

namespace ContentEgg\application\EggBlocks\blocks\faq;

use ContentEgg\application\EggBlocks\blocks\faq\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\blocks\faq\variants\FlatVariant;
use ContentEgg\application\EggBlocks\shared\EggbSanitizer;
use ContentEgg\application\EggBlocks\shared\EggbSchemaCollector;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;

defined('ABSPATH') || exit;

class FaqRenderer
{
    use RendersWithTheme;

    public static function render(array $attributes): string
    {
        $items = self::normalizeItems($attributes['items'] ?? []);
        if (empty($items))
        {
            return '';
        }

        $variant = ($attributes['variant'] ?? 'default') === 'flat' ? 'flat' : 'default';

        $heading_tag = (string) ($attributes['heading_tag'] ?? 'h2');
        if (!in_array($heading_tag, ['h1', 'h2', 'h3', 'h4', 'div'], true))
        {
            $heading_tag = 'h2';
        }

        $header = [
            'section_label' => self::resolveSectionLabel($attributes),
            'title'         => trim((string) ($attributes['title'] ?? '')),
            'heading_tag'   => $heading_tag,
        ];

        $theme_class = self::resolveThemeClass('auto');
        $color_scheme = self::resolveColorScheme($attributes['color_scheme'] ?? 'auto', $theme_class);
        $data_theme = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';

        ob_start();

        switch ($variant)
        {
            case 'flat':
                FlatVariant::render($header, $items, $theme_class, $data_theme);
                break;
            default:
                DefaultVariant::render($header, $items, !empty($attributes['collapsed_by_default']), $theme_class, $data_theme);
                break;
        }

        if (!empty($attributes['enable_schema']))
        {
            EggbSchemaCollector::addFaqItems($items);
        }

        return (string) ob_get_clean();
    }

    private static function resolveSectionLabel(array $attributes): string
    {
        if (array_key_exists('section_label', $attributes) && $attributes['section_label'] === '')
        {
            return '';
        }

        return trim((string) ($attributes['section_label'] ?? __('Frequently Asked Questions', 'content-egg-tpl')));
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

            $question = trim((string) ($item['question'] ?? ''));
            $answer_raw = trim((string) ($item['answer'] ?? ''));

            if ($question === '' || $answer_raw === '')
            {
                continue;
            }

            $answer_html = EggbSanitizer::basicRichText($answer_raw);
            $answer_text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($answer_html)));

            if ($answer_text === '')
            {
                continue;
            }

            $normalized[] = [
                'question' => $question,
                'answer_html' => $answer_html,
                'answer_text' => $answer_text,
            ];
        }

        return $normalized;
    }

}
