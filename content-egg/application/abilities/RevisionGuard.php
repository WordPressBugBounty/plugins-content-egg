<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ProductDataService;

/**
 * RevisionGuard class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Optimistic-concurrency check for product write abilities. An empty client
 * revision skips the check (same semantics as PostProductsRestController).
 */
final class RevisionGuard
{
    public static function check(int $post_id, string $module_id, string $revision): void
    {
        if ($revision === '')
        {
            return;
        }

        $current = (string) ProductDataService::revision($post_id, $module_id);
        if ($revision !== $current)
        {
            throw new AbilityConflictException($current);
        }
    }
}
