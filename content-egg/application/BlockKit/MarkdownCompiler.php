<?php

namespace ContentEgg\application\BlockKit;

defined('\ABSPATH') || exit;

/**
 * MarkdownCompiler class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

final class MarkdownCompiler
{
    public static function compile(string $markdown): array
    {
        $nodes = array();
        $blocks = preg_split("/\n\s*\n/", trim($markdown));

        foreach ($blocks as $chunk)
        {
            $chunk = trim($chunk);
            if ($chunk === '')
            {
                continue;
            }

            if (preg_match('/^(#{1,4})\s+(.+)$/', $chunk, $m))
            {
                $nodes[] = array(
                    'type' => 'core/heading',
                    'attrs' => array('level' => strlen($m[1])),
                    'html' => self::inline(trim($m[2])),
                );
                continue;
            }

            $lines = preg_split('/\n/', $chunk);
            $is_ul = !array_filter($lines, function ($l) { return !preg_match('/^\s*[-*]\s+/', $l); });
            $is_ol = !$is_ul && !array_filter($lines, function ($l) { return !preg_match('/^\s*\d+[.)]\s+/', $l); });

            if ($is_ul || $is_ol)
            {
                $items = array_map(function ($l)
                {
                    return '<li>' . self::inline(trim(preg_replace('/^\s*(?:[-*]|\d+[.)])\s+/', '', $l))) . '</li>';
                }, $lines);

                // Deliberately the inline <li> form, not core/list-item inner
                // blocks. Inner blocks cannot round-trip through the {type, attrs,
                // html} node shape -- TreeParser keeps any block with children
                // opaque -- so emitting the modern form would make every list this
                // compiler generates uneditable through the agent API immediately
                // after writing it. The inline form renders identically and stays
                // editable. Do not "modernize" this without teaching Serializer and
                // TreeParser to carry inner blocks.
                $nodes[] = array(
                    'type' => 'core/list',
                    'attrs' => array('ordered' => $is_ol),
                    'html' => implode('', $items),
                );
                continue;
            }

            $nodes[] = array(
                'type' => 'core/paragraph',
                'attrs' => array(),
                'html' => self::inline(preg_replace('/\s*\n\s*/', ' ', $chunk)),
            );
        }

        return $nodes;
    }

    public static function inline(string $text): string
    {
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8', false);

        // Freeze emitted anchors behind placeholders so the marker passes
        // below cannot rewrite characters inside URLs.
        $links = array();
        // URL may contain one level of balanced parens (e.g. Wikipedia
        // ..._(disambiguation)) without the first ')' ending the match.
        $text = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/(?:[^\s()]|\([^\s()]*\))*)\)/', function ($m) use (&$links)
        {
            $token = "\x1A" . count($links) . "\x1A";
            $links[$token] = '<a href="' . $m[2] . '">' . $m[1] . '</a>';
            return $token;
        }, $text);

        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text);
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);

        return $links ? strtr($text, $links) : $text;
    }
}
