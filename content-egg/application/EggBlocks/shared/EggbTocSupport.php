<?php

namespace ContentEgg\application\EggBlocks\shared;

defined('ABSPATH') || exit;

class EggbTocSupport
{
    public static function normalizeAnchor(string $anchor): string
    {
        $anchor = trim($anchor);
        if ($anchor === '') {
            return '';
        }

        if ($anchor[0] === '#') {
            $anchor = substr($anchor, 1);
        }

        return sanitize_title($anchor);
    }

    public static function normalizeLabel(string $label): string
    {
        return trim(wp_strip_all_tags($label));
    }

    public static function normalizeLevel($level, int $fallback = 2): int
    {
        $level = is_numeric($level) ? (int) $level : $fallback;
        if ($level < 1 || $level > 6) {
            $level = $fallback;
        }

        return $level;
    }
}
