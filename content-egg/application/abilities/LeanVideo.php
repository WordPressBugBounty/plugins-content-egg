<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * LeanVideo class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Compact envelope for video-module results (YouTube, Pexels Videos, ...).
 * Extra fields (duration, view count) are available via fields=full.
 */
final class LeanVideo
{
    /**
     * @param object|array $item
     */
    public static function map($item): array
    {
        $a = is_object($item) ? get_object_vars($item) : (array) $item;
        list($excerpt, $truncated) = LeanText::excerpt((string) ($a['description'] ?? ''));

        $extra = $a['extra'] ?? null;
        $extra = is_object($extra) ? get_object_vars($extra) : (is_array($extra) ? $extra : array());
        $channel = (string) ($extra['channel_title'] ?? $extra['author'] ?? '');

        return array(
            'unique_id' => (string) ($a['unique_id'] ?? ''),
            'title' => (string) ($a['title'] ?? ''),
            'url' => (string) ($a['url'] ?? ''),
            'thumb' => (string) ($a['img'] ?? ''),
            'channel' => $channel,
            'description_excerpt' => $excerpt,
            'description_truncated' => $truncated,
        );
    }

    public static function mapList(array $items): array
    {
        return array_values(array_map(array(self::class, 'map'), $items));
    }
}
