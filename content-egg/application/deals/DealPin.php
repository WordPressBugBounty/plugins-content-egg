<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

/**
 * DealPin class file
 *
 * Which Content Egg item a deal is about. Pure. Items arrive the way
 * ContentManager stores them: module_id => unique_id => item.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealPin
{
    /**
     * The imported product when there is one - an import saves comparison
     * modules too, and any of them can fire the first save - otherwise the
     * first item with a price.
     */
    public static function choose(array $itemsByModule, string $importUid): ?array
    {
        if ($importUid !== '')
        {
            foreach ($itemsByModule as $module_id => $items)
            {
                foreach ((array) $items as $key => $item)
                {
                    if (self::uid($item, $key) === $importUid)
                        return self::pin($module_id, $importUid);
                }
            }
        }

        foreach ($itemsByModule as $module_id => $items)
        {
            foreach ((array) $items as $key => $item)
            {
                if (is_array($item) && (float) ($item['price'] ?? 0) > 0)
                    return self::pin($module_id, self::uid($item, $key));
            }
        }

        return null;
    }

    public static function find(array $itemsByModule, array $pin): ?array
    {
        $module_id = (string) ($pin['module_id'] ?? '');
        $unique_id = (string) ($pin['unique_id'] ?? '');

        if (!isset($itemsByModule[$module_id]) || !is_array($itemsByModule[$module_id]))
            return null;

        foreach ($itemsByModule[$module_id] as $key => $item)
        {
            if (is_array($item) && self::uid($item, $key) === $unique_id)
                return $item;
        }

        return null;
    }

    private static function uid($item, $key): string
    {
        return is_array($item) && isset($item['unique_id']) ? (string) $item['unique_id'] : (string) $key;
    }

    private static function pin($module_id, string $unique_id): array
    {
        return array('module_id' => (string) $module_id, 'unique_id' => $unique_id);
    }

    /**
     * The pin goes into a [content-egg-block products="module:id"] string.
     * esc_attr() leaves [ and ] alone, so an id from a feed could close the
     * shortcode and open another; such a pin gets no embedded blocks.
     */
    public static function shortcodeSafe(array $pin): bool
    {
        $module_id = (string) ($pin['module_id'] ?? '');
        $unique_id = (string) ($pin['unique_id'] ?? '');

        return (bool) preg_match('/^[A-Za-z0-9_]+$/', $module_id)
            && $unique_id !== ''
            && strpbrk($unique_id, '[]"\'<>') === false;
    }
}
