<?php

namespace ContentEgg\application\EggBlocks\blocks\contextualcta;

use ContentEgg\application\EggBlocks\blocks\contextualcta\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\blocks\contextualcta\variants\HighlightVariant;
use ContentEgg\application\EggBlocks\blocks\contextualcta\variants\InlineVariant;
use ContentEgg\application\EggBlocks\blocks\contextualcta\variants\SplitVariant;
use ContentEgg\application\EggBlocks\shared\EggbSanitizer;
use ContentEgg\application\EggBlocks\shared\HydrationHelper;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;

defined('ABSPATH') || exit;

class ContextualCtaRenderer
{
    use RendersWithTheme;

    public static function render(array $attributes): string
    {
        $variant         = $attributes['variant']         ?? 'default';
        $heading_tag     = in_array($attributes['heading_tag'] ?? 'h3', ['h1', 'h2', 'h3', 'h4', 'div'], true)
            ? $attributes['heading_tag']
            : 'h3';
        $eyebrow         = (string) ($attributes['eyebrow']         ?? '');
        $headline        = (string) ($attributes['headline']        ?? '');
        $text            = (string) ($attributes['text']            ?? '');
        $primary_label   = (string) ($attributes['primary_label']   ?? '');
        $primary_meta    = (string) ($attributes['primary_meta']    ?? '');
        $secondary_label = (string) ($attributes['secondary_label'] ?? '');
        $context         = (string) ($attributes['context']         ?? '');

        $post_id       = (int) get_the_ID();
        $primary_url   = HydrationHelper::resolveUrl($attributes,  'primary_url_source',   'primary_url',   $post_id);
        $secondary_url = HydrationHelper::resolveUrl($attributes,  'secondary_url_source', 'secondary_url', $post_id);
        $logo          = HydrationHelper::resolveLogo($attributes, 'logo',                 $post_id);

        // Block renders nothing without a headline or a primary action.
        if ($headline === '' && $primary_label === '')
        {
            return '';
        }

        $data = [
            'heading_tag'     => $heading_tag,
            'eyebrow'         => $eyebrow,
            'headline'        => $headline,
            'text'            => $text !== ''    ? EggbSanitizer::basicRichText($text)    : '',
            'primary_label'   => $primary_label,
            'primary_url'     => $primary_url,
            'primary_meta'    => $primary_meta,
            'secondary_label' => $secondary_label,
            'secondary_url'   => $secondary_url,
            'context'         => esc_html($context),
            'logo'            => $logo,
        ];

        $theme_class  = self::resolveThemeClass($attributes['theme'] ?? 'auto');
        $color_scheme = self::resolveColorScheme($attributes['color_scheme'] ?? 'auto', $theme_class);
        $data_theme = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';

        ob_start();

        switch ($variant)
        {
            case 'highlight':
                HighlightVariant::render($data, $theme_class, $data_theme);
                break;
            case 'inline':
                InlineVariant::render($data, $theme_class, $data_theme);
                break;
            case 'split':
                SplitVariant::render($data, $theme_class, $data_theme);
                break;
            default:
                DefaultVariant::render($data, $theme_class, $data_theme);
                break;
        }

        return (string) ob_get_clean();
    }
}
