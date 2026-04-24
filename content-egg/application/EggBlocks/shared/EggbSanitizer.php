<?php

namespace ContentEgg\application\EggBlocks\shared;

defined('ABSPATH') || exit;

class EggbSanitizer
{
    public static function basicRichText(string $html): string
    {
        // Auto-paragraph plain text that has no existing block-level HTML
        if (!preg_match('/<(?:p|br|ul|ol)\b/i', $html))
        {
            $html = wpautop($html);
        }

        return wp_kses($html, [
            'p' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'strong' => [],
            'b' => [],
            'em' => [],
            'i' => [],
            'a' => [
                'href' => true,
                'rel' => true,
                'target' => true,
            ],
            'code' => [],
            's' => [],
            'br' => [],
        ]);
    }
}
