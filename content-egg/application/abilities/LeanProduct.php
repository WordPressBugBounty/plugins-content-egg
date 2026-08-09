<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * LeanProduct class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Maps Content Egg product items to a compact, LLM-friendly envelope
 * (design spec §5: lean envelopes by default, `fields=full` opts out).
 */
final class LeanProduct
{
    const EXCERPT_LENGTH = 300;

    /**
     * @param object|array $item
     */
    public static function map($item): array
    {
        $a = is_object($item) ? get_object_vars($item) : (array) $item;

        $excerpt = (string) ($a['_descriptionText'] ?? '');
        $truncated = false;

        if ($excerpt === '' && !empty($a['description']))
        {
            list($excerpt, $truncated) = self::excerpt((string) $a['description']);
        }

        return array(
            'unique_id' => (string) ($a['unique_id'] ?? ''),
            'title' => (string) ($a['title'] ?? ''),
            // Emit null (not 0) when there is no usable price: some modules
            // return 0 / empty for in-stock items whose price is unavailable,
            // and a literal 0 reads as "free" to an agent.
            'price' => isset($a['price']) && $a['price'] !== '' && (float) $a['price'] > 0 ? (float) $a['price'] : null,
            'price_old' => !empty($a['priceOld']) ? (float) $a['priceOld'] : null,
            'currency' => (string) ($a['currencyCode'] ?? ''),
            'merchant' => (string) ($a['merchant'] ?? ''),
            'domain' => (string) ($a['domain'] ?? ''),
            'img' => (string) ($a['img'] ?? ''),
            'rating' => isset($a['rating']) && $a['rating'] !== '' ? (float) $a['rating'] : null,
            'description_excerpt' => $excerpt,
            'description_truncated' => $truncated,
        );
    }

    public static function mapList(array $items): array
    {
        return array_values(array_map(array(self::class, 'map'), $items));
    }

    /**
     * Raw item fields minus internal underscore-prefixed keys.
     *
     * @param object|array $item
     */
    public static function fullItem($item): array
    {
        $a = is_object($item) ? get_object_vars($item) : (array) $item;

        return array_filter($a, static function ($key)
        {
            return !is_string($key) || strpos($key, '_') !== 0;
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * @return array [string $text, bool $truncated]
     */
    private static function excerpt(string $html): array
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($html)));

        if (mb_strlen($text) > self::EXCERPT_LENGTH)
        {
            return array(mb_substr($text, 0, self::EXCERPT_LENGTH) . "\u{2026}", true);
        }

        return array($text, false);
    }
}
