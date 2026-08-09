<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

/**
 * Matches a product against a `products=` filter list.
 *
 * Supports two id formats in the same list, so the block's "Choose products"
 * selection mode and every legacy shortcode/block keep working together:
 *   - legacy bare `unique_id` (module-local), e.g. "B08XYZ"
 *   - composite `module_id:unique_id` (Choose-products mode), e.g. "Amazon:B08XYZ"
 *
 * A composite token matches a row ONLY when the token's prefix equals the row's
 * own module id, so a look-alike unique_id under a different module is never
 * matched (collision-free). A bare token matches by exact unique_id equality —
 * which also covers any legacy id that happens to contain a colon.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ProductBindingFilter
{
    public static function matches(string $unique_id, string $module_id, array $products): bool
    {
        foreach ($products as $token)
        {
            $token = (string) $token;

            // Composite ref "module_id:unique_id" (Choose-products mode).
            $pos = strpos($token, ':');
            if ($pos !== false)
            {
                $ref_module = substr($token, 0, $pos);
                $ref_uid    = substr($token, $pos + 1);
                if ($ref_module === $module_id && $ref_uid === $unique_id)
                    return true;
            }

            // Legacy bare unique_id (also covers colon-containing legacy ids).
            if ($token === $unique_id)
                return true;
        }

        return false;
    }
}
