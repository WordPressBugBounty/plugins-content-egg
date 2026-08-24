<?php

namespace ContentEgg\application\EggBlocks\blocks\proscons;

use ContentEgg\application\EggBlocks\blocks\proscons\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\blocks\proscons\variants\CompactVariant;
use ContentEgg\application\EggBlocks\blocks\proscons\variants\HighlightVariant;
use ContentEgg\application\EggBlocks\blocks\proscons\variants\InlineVariant;
use ContentEgg\application\EggBlocks\shared\EggbSchemaCollector;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;

defined('ABSPATH') || exit;

class ProsConsRenderer
{
    use RendersWithTheme;

    public static function render(array $attributes): string
    {
        $pros       = array_values(array_filter($attributes['pros']     ?? [], 'strlen'));
        $cons       = array_values(array_filter($attributes['cons']     ?? [], 'strlen'));
        $best_for   = array_values(array_filter($attributes['best_for'] ?? [], 'strlen'));
        $not_for    = array_values(array_filter($attributes['not_for']  ?? [], 'strlen'));
        $variant    = $attributes['variant'] ?? 'default';
        $quick_take = $attributes['quick_take'] ?? '';

        if (empty($pros) && empty($cons) && empty($best_for) && empty($not_for))
        {
            return '';
        }

        $pros_col = !empty($cons) ? 'col-12 col-md-6' : 'col-12';
        $cons_col = !empty($pros) ? 'col-12 col-md-6' : 'col-12';

        $labels = [
            'pros'       => ($attributes['label_pros']       ?? '') ?: __('Pros',        'content-egg-tpl'),
            'cons'       => ($attributes['label_cons']       ?? '') ?: __('Cons',        'content-egg-tpl'),
            'best_for'   => ($attributes['label_best_for']   ?? '') ?: __('Best for',    'content-egg-tpl'),
            'not_for'    => ($attributes['label_not_for']    ?? '') ?: __('Not for',     'content-egg-tpl'),
            'quick_take' => ($attributes['label_quick_take'] ?? '') ?: __('Quick take:', 'content-egg-tpl'),
        ];

        $theme_class  = self::resolveThemeClass($attributes['theme'] ?? 'auto');
        $color_scheme = self::resolveColorScheme($attributes['color_scheme'] ?? 'auto', $theme_class);
        $data_theme = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';

        ob_start();

        switch ($variant)
        {
            case 'inline':
                InlineVariant::render($pros, $cons, $best_for, $not_for, $labels, $theme_class, $data_theme);
                break;
            case 'compact':
                CompactVariant::render($pros, $cons, $best_for, $not_for, $pros_col, $cons_col, $labels, $theme_class, $data_theme);
                break;
            case 'highlight':
                HighlightVariant::render($pros, $cons, $best_for, $not_for, $quick_take, $pros_col, $cons_col, $labels, $theme_class, $data_theme);
                break;
            default:
                DefaultVariant::render($pros, $cons, $best_for, $not_for, $pros_col, $cons_col, $labels, $theme_class, $data_theme);
                break;
        }

        $html = (string) ob_get_clean();

        EggbSchemaCollector::enrichProsCons($pros, $cons);

        return $html;
    }
}
