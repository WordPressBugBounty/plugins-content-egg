<?php

namespace ContentEgg\application\EggBlocks\blocks\pricing;

use ContentEgg\application\EggBlocks\blocks\pricing\variants\PricingListVariant;
use ContentEgg\application\EggBlocks\blocks\pricing\variants\PricingCardsVariant;
use ContentEgg\application\EggBlocks\blocks\pricing\variants\PricingHighlightVariant;
use ContentEgg\application\EggBlocks\shared\HydrationHelper;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;

defined('ABSPATH') || exit;

class PricingRenderer
{
    use RendersWithTheme;

    public static function render(array $attributes): string
    {
        $post_id = (int) get_the_ID();

        $resolved_items      = HydrationHelper::resolveItems($attributes,      'items',      $post_id);
        $resolved_promotions = HydrationHelper::resolvePromotions($attributes, 'promotions', $post_id);
        $resolved_cta_url    = HydrationHelper::resolveUrl($attributes,        'cta_url_source', 'cta_url', $post_id);

        $items = array_values(array_filter($resolved_items, static function ($item) {
            return !empty($item['name']);
        }));

        if (empty($items))
        {
            return '';
        }

        $variant     = $attributes['variant'] ?? 'pricing-list';
        $heading_tag = in_array($attributes['heading_tag'] ?? 'h2', ['h1', 'h2', 'h3', 'h4', 'div'], true)
            ? $attributes['heading_tag']
            : 'h2';

        $items_limit = max(1, (int) ($attributes['items_limit'] ?? 6));
        $items       = array_slice($items, 0, $items_limit);

        $promotions = array_values(array_filter($resolved_promotions, static function ($p) {
            return !empty($p['title']);
        }));

        $featured_label = (string) ($attributes['featured_label'] ?? '');

        $data = [
            'variant'            => $variant,
            'heading_tag'        => $heading_tag,
            'featured_label'     => $featured_label ?: __('Most popular', 'content-egg-tpl'),
            'title'              => (string) ($attributes['title']              ?? ''),
            'intro_text'         => (string) ($attributes['intro_text']         ?? ''),
            'general_price_note' => (string) ($attributes['general_price_note'] ?? ''),
            'cta_label'          => (string) ($attributes['cta_label']          ?? ''),
            'cta_url'            => (string) $resolved_cta_url,
            'promotions'         => self::preparePromotions($promotions),
            'items'              => self::prepareItems($items),
        ];

        $theme_class  = self::resolveThemeClass($attributes['theme'] ?? 'auto');
        $color_scheme = self::resolveColorScheme($attributes['color_scheme'] ?? 'auto', $theme_class);
        $data_theme   = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';

        ob_start();

        switch ($variant)
        {
            case 'pricing-cards':
                PricingCardsVariant::render($data, $theme_class, $data_theme);
                break;
            case 'pricing-highlight':
                PricingHighlightVariant::render($data, $theme_class, $data_theme);
                break;
            default:
                PricingListVariant::render($data, $theme_class, $data_theme);
                break;
        }

        return (string) ob_get_clean();
    }

    /**
     * Normalize item attribute names and compute derived fields.
     * Output is raw (unescaped) — variants must escape at output point.
     */
    private static function prepareItems(array $items): array
    {
        $out = [];
        foreach ($items as $item)
        {
            $price      = trim((string) ($item['price']      ?? ''));
            $price_from = trim((string) ($item['priceFrom']  ?? ''));
            $price_to   = trim((string) ($item['priceTo']    ?? ''));

            if ($price !== '')
            {
                $price_display = $price;
            }
            elseif ($price_from !== '' && $price_to === '')
            {
                /* translators: %s: price lower bound */
                $price_display = sprintf(__('From %s', 'content-egg-tpl'), $price_from);
            }
            elseif ($price_from !== '' && $price_to !== '')
            {
                $price_display = $price_from . ' – ' . $price_to;
            }
            else
            {
                $price_display = '';
            }

            $features = [];
            foreach ((array) ($item['featuresSummary'] ?? []) as $f)
            {
                $f = trim((string) $f);
                if ($f !== '')
                {
                    $features[] = $f;
                }
            }

            $out[] = [
                'name'           => (string) ($item['name']          ?? ''),
                'description'    => (string) ($item['description']   ?? ''),
                'price_display'  => $price_display,
                'price_from'     => $price_from,
                'price_to'       => $price_to,
                'billing_period' => (string) ($item['billingPeriod'] ?? ''),
                'features'       => $features,
                'is_featured'    => rest_sanitize_boolean($item['isFeatured'] ?? false),
                'cta_label'      => (string) ($item['ctaLabel']      ?? ''),
                'cta_url'        => (string) ($item['ctaUrl']        ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Normalize promotion attribute names.
     * Output is raw (unescaped) — variants must escape at output point.
     */
    private static function preparePromotions(array $promotions): array
    {
        $out = [];
        foreach ($promotions as $p)
        {
            $out[] = [
                'title'       => (string) ($p['title']       ?? ''),
                'description' => (string) ($p['description'] ?? ''),
            ];
        }

        return $out;
    }
}
