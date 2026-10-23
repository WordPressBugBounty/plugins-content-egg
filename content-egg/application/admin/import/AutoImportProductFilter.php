<?php

namespace ContentEgg\application\admin\import;

defined('\ABSPATH') || exit;

use ContentEgg\application\deals\DealMeta;

/**
 * AutoImportProductFilter class file
 *
 * An auto-import rule's "real discounts only" conditions. Pure. A product the
 * source reports no saving for has a 0% discount, so a minimum drops it.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class AutoImportProductFilter
{
    public static function passes(array $product, ?int $minDiscount, bool $inStockOnly): bool
    {
        if ($inStockOnly && isset($product['stock_status']) && (int) $product['stock_status'] === -1)
            return false;

        if ($minDiscount)
        {
            $discount = DealMeta::discount((float) ($product['price'] ?? 0), (float) ($product['priceOld'] ?? 0), $product['percentageSaved'] ?? null);
            if ($discount < $minDiscount)
                return false;
        }

        return true;
    }
}
