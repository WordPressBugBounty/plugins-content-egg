<?php

namespace ContentEgg\application\EggBlocks\blocks\ratingbreakdown;

use ContentEgg\application\EggBlocks\blocks\ratingbreakdown\variants\CategoryGridVariant;
use ContentEgg\application\EggBlocks\blocks\ratingbreakdown\variants\CompactVariant;
use ContentEgg\application\EggBlocks\blocks\ratingbreakdown\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\blocks\ratingbreakdown\variants\GridVariant;
use ContentEgg\application\EggBlocks\blocks\ratingbreakdown\variants\PlainVariant;
use ContentEgg\application\EggBlocks\shared\EggbIcons;
use ContentEgg\application\EggBlocks\shared\EggbSchemaCollector;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;

defined('ABSPATH') || exit;

class RatingBreakdownRenderer
{
    use RendersWithTheme;

    public static function render(array $attributes): string
    {
        $variant = self::normalizeVariant($attributes['variant'] ?? 'default');
        $categories = self::normalizeCategories($attributes['categories'] ?? [], $variant === 'grid' ? 6 : 0);
        $overall = self::normalizeOverall($attributes, $variant);

        if (empty($categories) && $overall === null)
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
            case 'compact':
                CompactVariant::render($overall, $categories, $theme_class, $data_theme);
                break;
            case 'grid':
                GridVariant::render($overall, $categories, $theme_class, $data_theme);
                break;
            case 'category-grid':
                CategoryGridVariant::render($categories, $theme_class, $data_theme);
                break;
            case 'plain':
                PlainVariant::render($overall, $categories, $theme_class, $data_theme);
                break;
            default:
                DefaultVariant::render($overall, $categories, $theme_class, $data_theme);
                break;
        }

        $html = (string) ob_get_clean();

        $overallScore = trim((string) ($attributes['overall_score'] ?? ''));
        if ($overallScore !== '')
        {
            EggbSchemaCollector::enrichRating($overallScore, '10');
        }

        return $html;
    }

    private static function normalizeVariant($variant): string
    {
        $variant = (string) $variant;
        if (!in_array($variant, ['default', 'compact', 'grid', 'category-grid', 'plain'], true))
        {
            return 'default';
        }

        return $variant;
    }

    private static function normalizeCategories($items, int $limit = 0): array
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

            $label = trim((string) ($item['label'] ?? ''));
            $score_text = trim((string) ($item['score'] ?? ''));
            $score_value = self::parseScoreValue($score_text);

            if ($label === '' && $score_text === '')
            {
                continue;
            }

            if ($score_text === '' || $score_value === null)
            {
                continue;
            }

            $normalized[] = [
                'label' => $label !== '' ? $label : __('Category', 'content-egg-tpl'),
                'score' => self::formatScoreText($score_value),
                'score_value' => $score_value,
                'bar_percent' => self::scoreToPercent($score_value),
            ];

            if ($limit > 0 && count($normalized) >= $limit)
            {
                break;
            }
        }

        return $normalized;
    }

    private static function normalizeOverall(array $attributes, string $variant): ?array
    {
        if ($variant === 'category-grid')
        {
            return null;
        }

        $score_text = trim((string) ($attributes['overall_score'] ?? ''));
        $score_value = self::parseScoreValue($score_text);
        if ($score_text === '' || $score_value === null)
        {
            return null;
        }

        $denom = trim((string) ($attributes['overall_denom'] ?? ''));
        if ($denom === '')
        {
            $denom = __('out of 10', 'content-egg-tpl');
        }

        $stars_attr = $attributes['stars'] ?? 0;
        $stars_value = is_numeric($stars_attr) ? (float) $stars_attr : 0.0;
        if ($stars_value <= 0)
        {
            $stars_value = $score_value / 2;
        }
        $stars_value = max(0.0, min(5.0, round($stars_value * 2) / 2));

        return [
            'score' => self::formatScoreText($score_value),
            'score_value' => $score_value,
            'denom' => $denom,
            'stars_value' => $stars_value,
            'stars_markup' => self::renderStars($stars_value),
            'stars_aria' => self::formatStarsAria($stars_value),
        ];
    }

    private static function parseScoreValue(string $score): ?float
    {
        $score = str_replace(',', '.', trim($score));
        if ($score === '' || !is_numeric($score))
        {
            return null;
        }

        return max(0.0, min(10.0, (float) $score));
    }

    private static function scoreToPercent(float $score): int
    {
        return (int) round(max(0.0, min(10.0, $score)) * 10);
    }

    private static function formatScoreText(float $score): string
    {
        return number_format($score, 1, '.', '');
    }

    private static function renderStars(float $stars_value): string
    {
        if ($stars_value <= 0)
        {
            return '';
        }

        $parts = [];
        for ($index = 1; $index <= 5; $index++)
        {
            if ($stars_value >= $index)
            {
                $parts[] = EggbIcons::get('star-fill', 'eggb-rb-star');
            }
            elseif ($stars_value >= ($index - 0.5))
            {
                $parts[] = EggbIcons::get('star-half', 'eggb-rb-star');
            }
            else
            {
                $parts[] = EggbIcons::get('star', 'eggb-rb-star eggb-rb-star--empty');
            }
        }

        return implode('', $parts);
    }

    private static function formatStarsAria(float $stars_value): string
    {
        $formatted = floor($stars_value) === $stars_value
            ? (string) (int) $stars_value
            : number_format($stars_value, 1, '.', '');

        return sprintf(
            /* translators: %s is the numeric star rating out of 5 */
            __('%s out of 5 stars', 'content-egg-tpl'),
            $formatted
        );
    }
}
