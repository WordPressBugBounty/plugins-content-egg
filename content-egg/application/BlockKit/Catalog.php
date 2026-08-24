<?php

namespace ContentEgg\application\BlockKit;

defined('\ABSPATH') || exit;

/**
 * Catalog class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

final class Catalog
{
    const EGGB_DIR = \ContentEgg\PLUGIN_PATH . 'application/EggBlocks/blocks/';

    const SINGLE_PRODUCT = array('product-card', 'verdict');
    const MULTI_PRODUCT = array('comparison-table', 'quick-picks', 'where-to-buy');

    /** The four "live display" blocks that render module data attached to a post. */
    const LIVE_DISPLAY_TYPES = array('content-egg/products', 'content-egg/coupons', 'content-egg/images', 'content-egg/videos');

    private static $cache = null;

    public static function all(?array $products_templates = null): array
    {
        if (self::$cache !== null && $products_templates === null)
        {
            return self::$cache;
        }

        $catalog = array();

        foreach (Hints::DATA as $slug => $meta)
        {
            $json = self::readBlockJson($slug);
            if (!$json)
            {
                continue;
            }

            $binding = 'none';
            if (in_array($slug, self::SINGLE_PRODUCT, true))
            {
                $binding = 'single';
            }
            elseif (in_array($slug, self::MULTI_PRODUCT, true))
            {
                $binding = 'items';
            }

            $catalog['eggb/' . $slug] = array(
                'type' => 'eggb/' . $slug,
                'family' => 'eggb',
                'title' => (string) ($json['title'] ?? $slug),
                'hint' => $meta['hint'],
                'attributes' => self::annotateArrayElements(
                    self::annotateProductRef((array) ($json['attributes'] ?? array())),
                    $slug
                ),
                'product_binding' => $binding,
                'rich_text' => $meta['rich_text'],
                'item_shape' => $meta['item_shape'],
                // Block-level repeatable shape distinct from items (only
                // comparison-table's `criteria` today) — the comparison matrix
                // lives here, so it must be documented alongside item_shape.
                'criteria_shape' => $meta['criteria_shape'] ?? null,
            );
        }

        $catalog['content-egg/products'] = self::liveDisplayDescriptor(
            'content-egg/products',
            'products',
            'PRODUCT',
            self::blockAttributes('\ContentEgg\application\blocks\productblock\ProductBlock'),
            $products_templates,
            'Products (live display)',
            Hints::PRODUCTS_HINT
        );

        $catalog['content-egg/coupons'] = self::liveDisplayDescriptor(
            'content-egg/coupons',
            'coupons',
            'COUPON',
            self::blockAttributes('\ContentEgg\application\blocks\couponblock\CouponBlock'),
            null,
            'Coupons (live display)',
            Hints::COUPONS_HINT
        );

        $catalog['content-egg/images'] = self::liveDisplayDescriptor(
            'content-egg/images',
            'images',
            'IMAGE',
            self::blockAttributes('\ContentEgg\application\blocks\mediablock\MediaBlock'),
            null,
            'Images (live display)',
            Hints::IMAGES_HINT
        );

        $catalog['content-egg/videos'] = self::liveDisplayDescriptor(
            'content-egg/videos',
            'videos',
            'VIDEO',
            self::blockAttributes('\ContentEgg\application\blocks\mediablock\MediaBlock'),
            null,
            'Videos (live display)',
            Hints::VIDEOS_HINT
        );

        $catalog['core/markdown'] = array(
            'type' => 'core/markdown',
            'family' => 'core',
            'title' => 'Prose (markdown)',
            'hint' => Hints::MARKDOWN_HINT,
            'attributes' => array('markdown' => array('type' => 'string')),
            'product_binding' => 'none',
            'rich_text' => array(),
            'item_shape' => null,
            'criteria_shape' => null,
        );

        if ($products_templates === null)
        {
            self::$cache = $catalog;
        }

        return $catalog;
    }

    /**
     * Compact, self-describing block menu for the agent guide: every block's
     * type + the first sentence of its hint, grouped the way the guide teaches
     * (product-bound authoring / editorial authoring / live display / prose).
     * Derived from all(), so it can never drift from the registry. Markdown.
     */
    public static function paletteMarkdown(): string
    {
        $product = array();
        $editorial = array();
        $live = array();
        $prose = array();

        foreach (self::all() as $type => $d)
        {
            $line = '- `' . $type . '` — ' . self::firstSentence((string) $d['hint']);

            if ($d['family'] === 'eggb')
            {
                if (($d['product_binding'] ?? 'none') !== 'none')
                {
                    $product[] = $line;
                }
                else
                {
                    $editorial[] = $line;
                }
            }
            elseif ($type === 'core/markdown')
            {
                $prose[] = $line;
            }
            else
            {
                $live[] = $line;
            }
        }

        $sections = array();
        if ($product)
        {
            $sections[] = "**Product blocks** — Egg Blocks that bind products; you author the per-product copy (title, verdict, pros/cons):\n" . implode("\n", $product);
        }
        if ($editorial)
        {
            $sections[] = "**Editorial blocks** — Egg Blocks for article structure; use the one whose shape matches the section you're writing:\n" . implode("\n", $editorial);
        }
        if ($live)
        {
            $sections[] = "**Live display blocks** — render module data attached to the post (attach first with the matching add-*-to-post ability):\n" . implode("\n", $live);
        }
        if ($prose)
        {
            $sections[] = "**Prose** — connective copy between blocks:\n" . implode("\n", $prose);
        }

        return implode("\n\n", $sections);
    }

    private static function firstSentence(string $text): string
    {
        $text = trim($text);
        $pos = strpos($text, '. ');
        return $pos !== false ? substr($text, 0, $pos + 1) : $text;
    }

    public static function get(string $type, ?array $products_templates = null): ?array
    {
        $all = self::all($products_templates);
        return $all[$type] ?? null;
    }

    /**
     * Describe the product_ref attribute the way the write path actually reads it.
     *
     * It is a registered block attribute, so block.json lists it here — but the
     * Validator accepts it at the node top level too, and normalizes to that.
     * Reporting only the attribute location made agents believe the two were in
     * conflict when both work; every agent that used the catalog asked about it.
     * Say so, and give the shape, which block.json's bare object never did.
     *
     * @param array<string,mixed> $attributes
     * @return array<string,mixed>
     */
    private static function annotateProductRef(array $attributes): array
    {
        if (!isset($attributes['product_ref']) || !is_array($attributes['product_ref']))
        {
            return $attributes;
        }

        $attributes['product_ref'] = array(
            'type' => 'object',
            'description' => 'Binds the block to one attached product: {module_id, unique_id}. '
                . 'It appears in two places because the two layers differ, not because one is '
                . 'legacy: the post STORES it as this block attribute, while the block tree the '
                . 'abilities speak carries it as a product_ref property at the node top level — '
                . 'which is what get-post-blocks returns, and the form to write. Sending it here '
                . 'also validates; the tree is normalized to the top-level form either way.',
            'properties' => array(
                'module_id' => array('type' => 'string'),
                'unique_id' => array('type' => 'string'),
            ),
        );

        return $attributes;
    }

    /**
     * Say what an array attribute's ELEMENTS are, on the attribute itself.
     *
     * block.json declares every one of them as {"type":"array","default":[]},
     * which is the same schema for eggb/intro's `points` (plain strings) and
     * eggb/key-takeaways' `items` ({text} objects) — so the catalog described
     * two incompatible payloads identically and an agent had to infer the
     * difference from item_shape, prose, or a blank block. A block-level
     * item_shape also cannot describe a block with two object arrays
     * (comparison-table, pricing, trust-signals). Attach the element schema to
     * each array instead; item_shape stays for compatibility.
     *
     * @param array<string,mixed> $attributes
     * @return array<string,mixed>
     */
    private static function annotateArrayElements(array $attributes, string $slug): array
    {
        $kinds = Hints::ARRAY_ELEMENTS[$slug] ?? array();
        $meta = Hints::DATA[$slug] ?? array();

        foreach ($kinds as $attr => $kind)
        {
            if (!isset($attributes[$attr]) || !is_array($attributes[$attr]))
            {
                continue;
            }

            if ($kind === 'string')
            {
                $attributes[$attr]['items'] = array('type' => 'string');
                $attributes[$attr]['description'] = 'A list of plain strings — NOT objects.';
                continue;
            }

            $shape = self::elementShape($slug, $attr, $kind, $meta);

            $items = array('type' => 'object');
            if (is_array($shape) && $shape)
            {
                $items['properties'] = $shape;
                $attributes[$attr]['description'] = 'A list of objects with these keys — NOT plain strings.';
            }
            else
            {
                $attributes[$attr]['description'] = 'A list of objects — NOT plain strings. See this block\'s hint for the keys.';
            }
            $attributes[$attr]['items'] = $items;
        }

        return $attributes;
    }

    /**
     * The element shape for one object array: the block-level item_shape /
     * criteria_shape, or the per-array entry for blocks that hold several
     * object arrays. Null when the block publishes none.
     *
     * @param array<string,mixed> $meta Hints::DATA entry for this slug.
     * @return array<string,mixed>|null
     */
    public static function elementShape(string $slug, string $attr, string $kind, array $meta): ?array
    {
        if ($kind === 'object:item')
        {
            $shape = $meta['item_shape'] ?? null;
        }
        elseif ($kind === 'object:criteria')
        {
            $shape = $meta['criteria_shape'] ?? null;
        }
        else
        {
            $shape = Hints::ARRAY_SHAPES[$slug][$attr] ?? null;
        }

        return is_array($shape) && $shape ? $shape : null;
    }

    /** Block attributes from a *Block class, or [] when the class isn't loaded (CLI/tests). */
    private static function blockAttributes(string $block_class): array
    {
        return class_exists($block_class) ? (array) $block_class::getAttributes() : array();
    }

    /**
     * Descriptor for a "live display" block (content-egg/products|coupons|images|
     * videos). All four are structurally identical: they render module data
     * attached to the post, filtered by template/modules/limit, and carry no
     * product_ref. $templates lets the caller (products only) inject a dynamic
     * template list; otherwise the enum is resolved from the block-template
     * manager by $module_type.
     */
    private static function liveDisplayDescriptor(string $block_type, string $family, string $module_type, array $attributes, ?array $templates, string $title, string $hint): array
    {
        if ($templates === null && class_exists('\ContentEgg\application\components\BlockTemplateManager'))
        {
            $manager = \ContentEgg\application\components\BlockTemplateManager::getInstance();
            // Products use getProductTemplates() (everything NOT scoped to another
            // family) so coupon/media templates no longer leak into the products
            // enum; the media/coupon families use their typed template list.
            $list = ($module_type === 'PRODUCT')
                ? $manager->getProductTemplates()
                : $manager->getTemplatesByModuleType($module_type);
            $templates = array_values(array_diff(array_keys((array) $list), array('customizable')));
        }

        if (is_array($templates) && isset($attributes['template']))
        {
            $attributes['template']['enum'] = array_values($templates);
        }

        // post_id defaults to 0 = "this post". The agent knows the current post
        // id and tends to fill it in, which needlessly hard-codes the source.
        // Tell it to leave it alone unless it deliberately wants another post.
        if (isset($attributes['post_id']))
        {
            $attributes['post_id']['description'] =
                'Leave unset to use the data attached to THIS post (the normal case). '
                . 'Only set it to another post\'s ID to display that post\'s items instead.';
        }

        return array(
            'type' => $block_type,
            'family' => $family,
            'title' => $title,
            'hint' => $hint,
            'attributes' => $attributes,
            'product_binding' => 'none',
            'rich_text' => array(),
            'item_shape' => null,
            'criteria_shape' => null,
        );
    }

    private static function readBlockJson(string $slug): ?array
    {
        $file = self::EGGB_DIR . $slug . '/block.json';
        if (!is_file($file))
        {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }
}
