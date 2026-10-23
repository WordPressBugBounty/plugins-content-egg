<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

/**
 * DealStatus class file
 *
 * The deal lifecycle. Pure - no WordPress calls - so every transition is
 * pinned by tests/deals rather than found in production.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealStatus
{
    const ACTIVE = 'active';
    const EXPIRED = 'expired';

    const REASON_MANUAL = 'manual';
    const REASON_ENDED = 'ended';
    const REASON_UNAVAILABLE = 'unavailable';
    const REASON_OUT_OF_STOCK = 'out_of_stock';
    const REASON_PRICE_UP = 'price_up';

    // ContentProduct::STOCK_STATUS_OUT_OF_STOCK, repeated so this stays pure.
    const STOCK_OUT = -1;

    public static function evaluate(array $s): array
    {
        $status = (($s['status'] ?? '') === self::EXPIRED) ? self::EXPIRED : self::ACTIVE;
        $reason = (string) ($s['reason'] ?? '');

        // An editor's decision, or a date that has passed, is not undone by a
        // price update.
        if ($status === self::EXPIRED && self::isSticky($reason))
            return self::result(self::EXPIRED, $reason);

        if ($expire = self::expireReason($s))
            return self::result(self::EXPIRED, $expire);

        if ($status === self::EXPIRED && empty($s['auto_reactivate']))
            return self::result(self::EXPIRED, $reason);

        return self::result(self::ACTIVE, '');
    }

    public static function isSticky(string $reason): bool
    {
        return $reason === self::REASON_MANUAL || $reason === self::REASON_ENDED;
    }

    public static function isPriceUp(float $price, float $base, float $threshold): bool
    {
        if ($price <= 0 || $base <= 0)
            return false;

        return round($price, 2) > round($base * (1 + max(0.0, $threshold) / 100), 2);
    }

    private static function expireReason(array $s): string
    {
        $ends = (int) ($s['ends'] ?? 0);
        if ($ends > 0 && $ends <= (int) ($s['now'] ?? 0))
            return self::REASON_ENDED;

        $item = $s['item'] ?? null;
        if (!is_array($item))
            return self::REASON_UNAVAILABLE;

        if (isset($item['stock_status']) && (int) $item['stock_status'] === self::STOCK_OUT)
            return self::REASON_OUT_OF_STOCK;

        if (self::isPriceUp((float) ($item['price'] ?? 0), (float) ($s['base_price'] ?? 0), (float) ($s['threshold'] ?? 0)))
            return self::REASON_PRICE_UP;

        return '';
    }

    private static function result(string $status, string $reason): array
    {
        return array('status' => $status, 'reason' => $reason);
    }
}
