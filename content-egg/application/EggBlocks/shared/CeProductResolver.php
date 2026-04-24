<?php

namespace ContentEgg\application\EggBlocks\shared;

use ContentEgg\application\components\ContentManager;

defined('ABSPATH') || exit;

class CeProductResolver
{
    /**
     * Resolve a product reference array to a raw CE product item array.
     *
     * @param array $productRef  ['module_id' => string, 'unique_id' => string, 'post_id' => int]
     * @return array|null        CE product item array, or null if not found
     */
    public static function resolve(array $productRef): ?array
    {
        if (empty($productRef['module_id'])
            || empty($productRef['unique_id'])
        ) {
            return null;
        }

        $post_id = self::resolvePostId($productRef);

        if ($post_id <= 0) {
            return null;
        }

        $product = ContentManager::getProductByUniqueId(
            $productRef['unique_id'],
            $productRef['module_id'],
            $post_id
        );

        if (is_array($product)) {
            return $product;
        }

        if (is_object($product)) {
            return (array) $product;
        }

        return null;
    }

    /**
     * Resolve multiple product refs. Returns array with null for unresolved items.
     * Keys are preserved.
     *
     * @param array $refs  Array of product_ref arrays
     * @return array       Array of product item arrays (or null per item)
     */
    public static function resolveMany(array $refs): array
    {
        return array_map([self::class, 'resolve'], $refs);
    }

    private static function resolvePostId(array $productRef): int
    {
        $post_id = isset($productRef['post_id']) ? (int) $productRef['post_id'] : 0;

        if ($post_id > 0) {
            return $post_id;
        }

        $current_post_id = (int) get_the_ID();
        if ($current_post_id > 0) {
            return $current_post_id;
        }

        return (int) get_queried_object_id();
    }
}
