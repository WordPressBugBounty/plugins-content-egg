<?php

namespace ContentEgg\application\EggBlocks\shared;

defined('ABSPATH') || exit;

class EggbProductNavCollector
{
    /**
     * Collect product-card blocks for the product nav section.
     *
     * Each returned item:
     *   'anchor' => string (auto-generated or explicit, without #)
     *   'label'  => string (badge → block_title → title)
     *   'img'    => string (product image URL)
     *
     * @return array<int, array{anchor: string, label: string, img: string}>
     */
    public static function collect(array $blocks): array
    {
        $items = [];
        $seen = [];
        self::walk($blocks, $items, $seen);
        return $items;
    }

    private static function walk(array $blocks, array &$items, array &$seen): void
    {
        foreach ($blocks as $block)
        {
            if (!is_array($block))
            {
                continue;
            }

            $name = (string) ($block['blockName'] ?? '');

            if ($name === 'eggb/product-card')
            {
                $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
                $ref = is_array($attrs['product_ref'] ?? null) ? $attrs['product_ref'] : [];
                $dedupKey = (!empty($ref['module_id']) && !empty($ref['unique_id']))
                    ? $ref['module_id'] . ':' . $ref['unique_id']
                    : '';

                $item = self::extractItem($block);
                if ($item !== null && ($dedupKey === '' || !isset($seen[$dedupKey])))
                {
                    if ($dedupKey !== '')
                    {
                        $seen[$dedupKey] = true;
                    }
                    $items[] = $item;
                }
            }

            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks']))
            {
                self::walk($block['innerBlocks'], $items, $seen);
            }
        }
    }

    private static function extractItem(array $block): ?array
    {
        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];

        // Label: badge → section_label → block_title → title
        $label = trim((string) ($attrs['badge'] ?? ''));
        if ($label === '')
        {
            $label = trim((string) ($attrs['section_label'] ?? ''));
        }
        if ($label === '')
        {
            $label = trim((string) ($attrs['block_title'] ?? ''));
        }
        if ($label === '')
        {
            $label = trim((string) ($attrs['title'] ?? ''));
        }
        if ($label === '')
        {
            return null;
        }

        // Anchor: use explicit if set, otherwise auto-generate from label
        $anchor = EggbTocSupport::normalizeAnchor((string) ($attrs['anchor'] ?? ''));
        if ($anchor === '')
        {
            $anchor = EggbTocSupport::normalizeAnchor($label);
        }
        if ($anchor === '')
        {
            return null;
        }

        // Image: resolve product ref
        $img = '';
        $product_ref = is_array($attrs['product_ref'] ?? null) ? $attrs['product_ref'] : [];
        if (!empty($product_ref['module_id']) && !empty($product_ref['unique_id']))
        {
            $product = CeProductResolver::resolve($product_ref);
            if ($product !== null && !empty($product['img']))
            {
                $img = (string) $product['img'];
            }
        }

        return [
            'anchor' => $anchor,
            'label' => EggbTocSupport::normalizeLabel($label),
            'img' => $img,
        ];
    }
}
