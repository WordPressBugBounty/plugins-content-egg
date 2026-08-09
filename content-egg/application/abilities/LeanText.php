<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * LeanText class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Small shared text helper for the lean media/coupon envelopes: strips HTML
 * and truncates to a bounded excerpt (design spec §5: compact by default).
 */
final class LeanText
{
    const EXCERPT_LENGTH = 300;

    /**
     * @return array [string $text, bool $truncated]
     */
    public static function excerpt(string $html): array
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($html)));

        if (mb_strlen($text) > self::EXCERPT_LENGTH)
        {
            return array(mb_substr($text, 0, self::EXCERPT_LENGTH) . "\u{2026}", true);
        }

        return array($text, false);
    }
}
