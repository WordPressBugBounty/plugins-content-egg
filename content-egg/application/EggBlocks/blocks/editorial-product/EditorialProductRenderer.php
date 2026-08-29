<?php

namespace ContentEgg\application\EggBlocks\blocks\editorialproduct;

use ContentEgg\application\EggBlocks\blocks\editorialproduct\variants\ClaimVariant;
use ContentEgg\application\EggBlocks\blocks\editorialproduct\variants\FigureVariant;
use ContentEgg\application\EggBlocks\blocks\editorialproduct\variants\MentionVariant;
use ContentEgg\application\EggBlocks\blocks\editorialproduct\variants\SpecimenVariant;
use ContentEgg\application\EggBlocks\shared\CeProductResolver;
use ContentEgg\application\EggBlocks\shared\EggbSanitizer;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;

defined('ABSPATH') || exit;

class EditorialProductRenderer
{
    use RendersWithTheme;

    private const VARIANTS = ['figure', 'claim', 'specimen', 'mention'];

    public static function render(array $attributes): string
    {
        $block = self::normalize($attributes);

        // The editorial text is the content. Unlike product-card, this block
        // renders whenever body is present, with or without a resolved product,
        // so a removed product degrades to prose instead of a hole in the page.
        if ($block['body'] === '')
        {
            return '';
        }

        $theme_class = self::resolveThemeClass('auto');
        $color_scheme = self::resolveColorScheme($attributes['color_scheme'] ?? 'auto', $theme_class);
        $data_theme = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';

        ob_start();

        switch ($block['variant'])
        {
            case 'claim':
                ClaimVariant::render($block, $theme_class, $data_theme);
                break;
            case 'specimen':
                SpecimenVariant::render($block, $theme_class, $data_theme);
                break;
            case 'mention':
                MentionVariant::render($block, $theme_class, $data_theme);
                break;
            default:
                FigureVariant::render($block, $theme_class, $data_theme);
                break;
        }

        return (string) ob_get_clean();
    }

    /**
     * A variant this block still renders. Anything else -- an older variant that
     * has since been removed, or a typo -- becomes a figure.
     */
    public static function normalizeVariant($variant): string
    {
        $variant = (string) $variant;

        return in_array($variant, self::VARIANTS, true) ? $variant : 'figure';
    }

    public static function normalize(array $attributes): array
    {
        $variant = self::normalizeVariant($attributes['variant'] ?? 'figure');

        $product_ref = isset($attributes['product_ref']) && is_array($attributes['product_ref'])
            ? $attributes['product_ref']
            : [];
        $product_item = !empty($product_ref) ? CeProductResolver::resolve($product_ref) : null;

        // A gallery shot usually suits an editorial block better than the catalog
        // hero, which is nearly always the product alone on white. 0 keeps the
        // hero; 1..N pick from the gallery.
        //
        // An index past the end of the gallery steps down to the last shot
        // rather than jumping to the hero: asking for the third photo is asking
        // for a gallery photo, and the second is a far closer answer than the
        // catalog cut-out. This is what lets the generator name one preferred
        // position for every product without knowing how many images each has.
        // The hero is the last resort, for a product with no gallery at all.
        $image_index = max(0, (int) ($attributes['image_index'] ?? 0));

        if ($image_index > 0 && is_array($product_item))
        {
            $gallery = self::gallery($product_item);
            $position = min($image_index, count($gallery));

            if ($position > 0)
            {
                $product_item['img'] = $gallery[$position - 1];

                // primaryImages describes the hero, and TemplateHelper reaches
                // for it whenever a small render is requested -- which would
                // quietly hand back the very image we just replaced. It goes
                // with the image it describes.
                if (isset($product_item['extra']) && is_array($product_item['extra']))
                {
                    unset($product_item['extra']['primaryImages']);
                }
            }
        }

        // Authored values win. The editor seeds them from the product once per
        // binding (see seeded_for in Edit.js), so an author or the generator can
        // replace a raw catalog name with something that reads in prose; the
        // product record is only the fallback for a block that was never seeded.
        $title = trim((string) ($attributes['title'] ?? ''));
        $merchant = trim((string) ($attributes['merchant'] ?? ''));

        if (is_array($product_item))
        {
            if ($title === '')
            {
                $title = trim((string) ($product_item['title'] ?? ''));
            }

            if ($merchant === '')
            {
                $merchant = trim((string) ($product_item['merchant'] ?? $product_item['domain'] ?? ''));
            }
        }

        return [
            'variant'       => $variant,
            'product_item'  => is_array($product_item) ? $product_item : null,
            'section_label' => trim((string) ($attributes['section_label'] ?? '')),
            'body'          => EggbSanitizer::basicRichText((string) ($attributes['body'] ?? '')),
            'specs'         => self::normalizeSpecs($attributes['specs'] ?? []),
            'context'       => trim((string) ($attributes['context'] ?? '')),
            'title'         => $title,
            'merchant'      => $merchant,
        ];
    }

    /**
     * A product's gallery, hero excluded.
     *
     * Every module with more than one photo fills ContentProduct::$images, but
     * not identically: most list only the extra shots, while a few (Billiger.de,
     * Lomadee) repeat the hero as the first entry. Dropping anything equal to
     * img makes index 1 mean "the first photo that is not the hero" whatever
     * module the product came from.
     */
    public static function gallery(array $product_item): array
    {
        if (empty($product_item['images']) || !is_array($product_item['images']))
        {
            return [];
        }

        $hero = trim((string) ($product_item['img'] ?? ''));
        $out = [];

        foreach ($product_item['images'] as $url)
        {
            $url = trim((string) $url);

            if ($url === '' || $url === $hero || in_array($url, $out, true))
            {
                continue;
            }

            $out[] = $url;
        }

        return $out;
    }

    private static function normalizeSpecs($specs): array
    {
        if (!is_array($specs))
        {
            return [];
        }

        $out = [];
        foreach ($specs as $spec)
        {
            if (!is_array($spec))
            {
                continue;
            }

            $value = trim((string) ($spec['value'] ?? ''));
            if ($value === '')
            {
                continue;
            }

            $out[] = [
                'label' => trim((string) ($spec['label'] ?? '')),
                'value' => $value,
            ];
        }

        return $out;
    }
}
