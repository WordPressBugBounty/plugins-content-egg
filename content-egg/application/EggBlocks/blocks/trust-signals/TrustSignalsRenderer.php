<?php

namespace ContentEgg\application\EggBlocks\blocks\trustsignals;

use ContentEgg\application\EggBlocks\blocks\trustsignals\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\blocks\trustsignals\variants\InlineVariant;
use ContentEgg\application\EggBlocks\shared\HydrationHelper;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;

defined('ABSPATH') || exit;

class TrustSignalsRenderer
{
    use RendersWithTheme;

    private const ALLOWED_ICONS = ['shield-check', 'patch-check', 'check-circle'];

    public static function render(array $attributes): string
    {
        $post_id   = (int) get_the_ID();
        $aggregate = self::resolveAggregate($attributes, $post_id);
        $metrics   = self::sanitizeMetrics($attributes['metrics'] ?? []);
        $badges    = self::sanitizeBadges($attributes['badges'] ?? []);

        if ($aggregate === null && empty($metrics) && empty($badges)) {
            return '';
        }

        $data = [
            'aggregate' => $aggregate,
            'metrics'   => $metrics,
            'badges'    => $badges,
        ];

        $theme_class  = self::resolveThemeClass('auto');
        $color_scheme = self::resolveColorScheme($attributes['color_scheme'] ?? 'auto', $theme_class);
        $data_theme   = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';

        ob_start();

        switch ($attributes['variant'] ?? 'default') {
            case 'inline':
                InlineVariant::render($data, $theme_class, $data_theme);
                break;
            default:
                DefaultVariant::render($data, $theme_class, $data_theme);
                break;
        }

        return (string) ob_get_clean();
    }

    private static function resolveAggregate(array $attributes, int $post_id): ?array
    {
        $resolved = HydrationHelper::resolveAggregate($attributes, $post_id);
        if ($resolved === null) {
            return null;
        }

        $max = max(1, (int) round($resolved['rating_max']));

        return [
            'rating' => $resolved['rating'],
            'max'    => $max,
            'count'  => $resolved['count'],
            'source' => esc_html($resolved['source']),
            'stars'  => self::resolveStars($resolved['rating'], $max),
        ];
    }

    private static function sanitizeMetrics(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $value = trim((string) ($item['value'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));
            if ($value !== '') {
                $out[] = [
                    'value' => $value,
                    'label' => $label,
                ];
            }
        }
        return $out;
    }

    private static function sanitizeBadges(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $label = trim((string) ($item['label'] ?? ''));
            $icon  = (string) ($item['icon'] ?? 'shield-check');
            if ($label !== '') {
                $out[] = [
                    'label' => $label,
                    'icon'  => in_array($icon, self::ALLOWED_ICONS, true) ? $icon : 'shield-check',
                ];
            }
        }
        return $out;
    }

    private static function resolveStars(float $rating, int $max = 5): array
    {
        $stars = [];
        for ($step = 1; $step <= $max; $step++) {
            if ($rating >= $step) {
                $stars[] = 'star-fill';
            } elseif ($rating >= $step - 0.5) {
                $stars[] = 'star-half';
            } else {
                $stars[] = 'star';
            }
        }
        return $stars;
    }
}
