<?php

namespace ContentEgg\application\EggBlocks\blocks\testimonial;

use ContentEgg\application\EggBlocks\blocks\testimonial\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\blocks\testimonial\variants\CardsVariant;
use ContentEgg\application\EggBlocks\blocks\testimonial\variants\InlineVariant;
use ContentEgg\application\EggBlocks\shared\HydrationHelper;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;

defined('ABSPATH') || exit;

class TestimonialRenderer
{
    use RendersWithTheme;

    public static function render(array $attributes): string
    {
        $post_id = (int) get_the_ID();

        $resolved_items = HydrationHelper::resolveTestimonials($attributes, 'items', $post_id);

        $items = array_values(array_filter($resolved_items, static function ($item) {
            return !empty($item['quote']) && !empty($item['author']);
        }));

        if (empty($items))
        {
            return '';
        }

        $variant     = $attributes['variant'] ?? 'default';
        $heading_tag = in_array($attributes['heading_tag'] ?? 'h3', ['h1', 'h2', 'h3', 'h4', 'div'], true)
            ? $attributes['heading_tag']
            : 'h3';

        if (($attributes['data_source'] ?? 'auto') !== 'manual') {
            $seed  = $post_id ^ crc32((string) ($attributes['title'] ?? ''));
            $items = self::seededShuffle($items, $seed);
        }

        $items_limit = max(1, (int) ($attributes['items_limit'] ?? 3));
        $items       = array_slice($items, 0, $items_limit);

        $aggregate = self::resolveAggregate($attributes, $post_id);

        $data = [
            'variant'     => $variant,
            'heading_tag' => $heading_tag,
            'title'       => (string) ($attributes['title'] ?? ''),
            'aggregate'   => $aggregate,
            'items'       => self::sanitizeItems($items),
        ];

        $theme_class  = self::resolveThemeClass($attributes['theme'] ?? 'auto');
        $color_scheme = self::resolveColorScheme($attributes['color_scheme'] ?? 'auto', $theme_class);
        $data_theme   = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';

        ob_start();

        switch ($variant)
        {
            case 'cards':
                CardsVariant::render($data, $theme_class, $data_theme);
                break;
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
        if ($resolved === null)
        {
            return null;
        }

        $count = $resolved['count'];

        return [
            'rating'     => $resolved['rating'],
            'rating_max' => $resolved['rating_max'],
            'count'      => $count > 0 ? $count : null,
            'source'     => esc_html($resolved['source']),
            'stars'      => self::resolveStars($resolved['rating']),
        ];
    }

    private static function sanitizeItems(array $items): array
    {
        $out = [];
        foreach ($items as $item)
        {
            $rating = (float) ($item['rating'] ?? 0);

            $out[] = [
                'quote'       => (string) ($item['quote']       ?? ''),
                'author'      => (string) ($item['author']      ?? ''),
                'attribution' => (string) ($item['attribution'] ?? ''),
                'rating'      => $rating > 0 ? $rating : null,
                'stars'       => $rating > 0 ? self::resolveStars($rating) : null,
            ];
        }

        return $out;
    }

    /**
     * Resolve a 1–5 star rating into an array of icon names.
     * Each element is 'star-fill', 'star-half', or 'star'.
     *
     * @return string[]
     */
    /**
     * Deterministic Fisher-Yates shuffle seeded with a custom value.
     * Does not affect PHP's global random state (no srand).
     */
    private static function seededShuffle(array $items, int $seed): array
    {
        $count = count($items);
        for ($i = $count - 1; $i > 0; $i--)
        {
            $seed     = ($seed * 1664525 + 1013904223) & 0x7fffffff;
            $j        = $seed % ($i + 1);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
        return $items;
    }

    public static function resolveStars(float $rating, int $max = 5): array
    {
        $stars = [];
        for ($i = 1; $i <= $max; $i++)
        {
            if ($rating >= $i)
            {
                $stars[] = 'star-fill';
            }
            elseif ($rating >= $i - 0.5)
            {
                $stars[] = 'star-half';
            }
            else
            {
                $stars[] = 'star';
            }
        }

        return $stars;
    }
}
