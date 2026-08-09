<?php

namespace ContentEgg\application\BlockKit;

defined('\ABSPATH') || exit;

/**
 * TreeParser class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class TreeParser
{
    public static function toTree(array $parsed_blocks): array
    {
        $tree = array();

        foreach ($parsed_blocks as $block)
        {
            $name = $block['blockName'] ?? null;
            $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : array();
            $inner = (string) ($block['innerHTML'] ?? '');

            if ($name === null)
            {
                if (trim($inner) === '')
                {
                    continue;
                }
                // Trim: parse_blocks hands a freeform island the blank lines that
                // separated it from its neighbours, and Serializer re-joins nodes
                // with "\n\n". Storing the separators made every no-op edit cycle
                // add 4 bytes to the post, without bound.
                $tree[] = array('type' => 'core/freeform', 'opaque' => true, 'raw_markup' => trim($inner));
                continue;
            }

            if (strpos($name, 'eggb/') === 0 || strpos($name, 'content-egg/') === 0)
            {
                $node = array('type' => $name, 'attrs' => $attrs);
                if (strpos($name, 'eggb/') === 0 && isset($attrs['product_ref']) && is_array($attrs['product_ref']))
                {
                    $ref = $attrs['product_ref'];
                    unset($node['attrs']['product_ref']);
                    $node['product_ref'] = array(
                        'module_id' => (string) ($ref['module_id'] ?? ''),
                        'unique_id' => (string) ($ref['unique_id'] ?? ''),
                    );
                }
                $tree[] = $node;
                continue;
            }

            // A block with children can never round-trip as {type, attrs, html}:
            // parse_blocks() strips innerBlocks out of innerHTML, so a modern
            // core/list yields only its bare <ul> wrapper and Serializer would
            // write the list back empty, destroying every item. Attributes alone
            // cannot decide this -- such a block must stay opaque.
            $has_children = !empty($block['innerBlocks']);

            if (!$has_children
                && in_array($name, array('core/paragraph', 'core/heading', 'core/list'), true)
                && self::coreProseRoundTrips($name, $attrs))
            {
                $tree[] = array(
                    'type' => $name,
                    'attrs' => $attrs,
                    'html' => self::stripWrapper(trim($inner)),
                );
                continue;
            }

            $raw = function_exists('serialize_block') ? \serialize_block($block) : $inner;
            $tree[] = array('type' => $name, 'opaque' => true, 'raw_markup' => trim((string) $raw));
        }

        return $tree;
    }

    /**
     * A core prose block is editable only when its attributes are within the
     * exact set the Serializer can reproduce; otherwise it must round-trip as
     * an opaque node so attributes (align, className, anchor, ...) are not
     * silently lost.
     */
    private static function coreProseRoundTrips(string $name, array $attrs): bool
    {
        $serializable = array(
            'core/paragraph' => array(),
            'core/heading' => array('level'),
            'core/list' => array('ordered'),
        );
        $permitted = $serializable[$name] ?? array();

        foreach (array_keys($attrs) as $key)
        {
            if (!in_array($key, $permitted, true))
            {
                return false;
            }
        }

        return true;
    }

    private static function stripWrapper(string $html): string
    {
        if (preg_match('#^<([a-z][a-z0-9]*)\b[^>]*>(.*)</\1>$#is', $html, $m))
        {
            return trim($m[2]);
        }
        return trim($html);
    }
}
