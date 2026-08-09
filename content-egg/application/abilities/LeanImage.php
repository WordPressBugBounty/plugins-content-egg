<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * LeanImage class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Compact envelope for image-module results (Pixabay, Unsplash, Pexels, ...).
 * The full raw item (dimensions, author, license) is available via fields=full.
 */
final class LeanImage
{
    /**
     * @param object|array $item
     */
    public static function map($item): array
    {
        $a = is_object($item) ? get_object_vars($item) : (array) $item;
        list($excerpt, $truncated) = LeanText::excerpt((string) ($a['description'] ?? ''));

        return array(
            'unique_id' => (string) ($a['unique_id'] ?? ''),
            'title' => (string) ($a['title'] ?? ''),
            'img' => (string) ($a['img'] ?? ''),
            'source_url' => (string) ($a['url'] ?? ''),
            'domain' => (string) ($a['domain'] ?? ''),
            'description_excerpt' => $excerpt,
            'description_truncated' => $truncated,
        );
    }

    public static function mapList(array $items): array
    {
        return array_values(array_map(array(self::class, 'map'), $items));
    }
}
