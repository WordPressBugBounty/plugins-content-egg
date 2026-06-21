<?php

namespace ContentEgg\application\EggBlocks\blocks\callout;

use ContentEgg\application\EggBlocks\blocks\callout\variants\CompactVariant;
use ContentEgg\application\EggBlocks\blocks\callout\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\shared\EggbSanitizer;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;

defined('ABSPATH') || exit;

class CalloutRenderer
{
    use RendersWithTheme;

    private const TYPE_CONFIG = [
        'note' => [
            'class' => 'eggb--note',
            'icon' => 'info-circle-fill',
            'label' => 'Note',
        ],
        'tip' => [
            'class' => 'eggb--tip',
            'icon' => 'lightbulb-fill',
            'label' => 'Pro tip',
        ],
        'warning' => [
            'class' => 'eggb--warning',
            'icon' => 'exclamation-triangle-fill',
            'label' => 'Warning',
        ],
        'insight' => [
            'class' => 'eggb--insight',
            'icon' => 'graph-up-arrow',
            'label' => 'Insight',
        ],
    ];

    public static function render(array $attributes): string
    {
        $variant = ($attributes['variant'] ?? 'default') === 'compact' ? 'compact' : 'default';
        $body = trim((string) ($attributes['body'] ?? ''));
        $title = trim((string) ($attributes['title'] ?? ''));

        if ($body === '' && !($variant === 'default' && $title !== ''))
        {
            return '';
        }

        $config = self::TYPE_CONFIG[$attributes['callout_type'] ?? 'note'] ?? self::TYPE_CONFIG['note'];
        $label = trim((string) ($attributes['label_type'] ?? ''));

        $theme_class = self::resolveThemeClass('auto');
        $color_scheme = self::resolveColorScheme($attributes['color_scheme'] ?? 'auto', $theme_class);
        $data_theme = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';

        $resolved = [
            'type_class' => $config['class'],
            'icon' => $config['icon'],
            'label' => $label,
            'title' => $variant === 'default' ? $title : '',
            'body' => $body !== '' ? EggbSanitizer::basicRichText($body) : '',
        ];

        ob_start();

        switch ($variant)
        {
            case 'compact':
                CompactVariant::render($resolved, $theme_class, $data_theme);
                break;
            default:
                DefaultVariant::render($resolved, $theme_class, $data_theme);
                break;
        }

        return (string) ob_get_clean();
    }
}
