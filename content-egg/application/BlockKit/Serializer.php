<?php

namespace ContentEgg\application\BlockKit;

defined('\ABSPATH') || exit;

/**
 * Serializer class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class Serializer
{
    public static function serialize(array $tree): string
    {
        $out = array();

        foreach ($tree as $node)
        {
            if (!empty($node['raw_markup']))
            {
                $out[] = (string) $node['raw_markup'];
                continue;
            }

            $type = (string) ($node['type'] ?? '');
            $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : array();

            if (strpos($type, 'eggb/') === 0 || strpos($type, 'content-egg/') === 0)
            {
                if (!empty($node['product_ref']) && is_array($node['product_ref']))
                {
                    $attrs['product_ref'] = $node['product_ref'];
                }
                $out[] = '<!-- wp:' . $type . ' ' . self::encodeAttrs($attrs) . ' /-->';
                continue;
            }

            $html = (string) ($node['html'] ?? '');

            if ($type === 'core/heading')
            {
                $level = max(1, min(6, (int) ($attrs['level'] ?? 2)));
                $json = ' ' . self::encodeAttrs(array('level' => $level));
                $out[] = '<!-- wp:heading' . $json . ' --><h' . $level . ' class="wp-block-heading">' . $html . '</h' . $level . '><!-- /wp:heading -->';
            }
            elseif ($type === 'core/list')
            {
                $ordered = !empty($attrs['ordered']);
                $tag = $ordered ? 'ol' : 'ul';
                $json = $ordered ? ' ' . self::encodeAttrs(array('ordered' => true)) : '';
                $out[] = '<!-- wp:list' . $json . ' --><' . $tag . ' class="wp-block-list">' . $html . '</' . $tag . '><!-- /wp:list -->';
            }
            elseif ($type === 'core/paragraph')
            {
                $out[] = '<!-- wp:paragraph --><p>' . $html . '</p><!-- /wp:paragraph -->';
            }
        }

        return implode("\n\n", $out);
    }

    private static function encodeAttrs(array $attrs): string
    {
        foreach (array_keys($attrs) as $key)
        {
            if (is_string($key) && strpos($key, '_') === 0)
            {
                unset($attrs[$key]);
            }
        }

        if (!$attrs)
        {
            return '{}';
        }

        // Block attrs live inside an HTML comment: core's encoder escapes
        // the sequences that would terminate it (--, <, >, &, quotes).
        if (function_exists('serialize_block_attributes'))
        {
            return (string) \serialize_block_attributes($attrs);
        }

        $json = (string) json_encode(
            $attrs,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        );

        return str_replace('--', '\\u002d\\u002d', $json);
    }
}
