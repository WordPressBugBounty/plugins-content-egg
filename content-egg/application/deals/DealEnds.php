<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

/**
 * DealEnds class file
 *
 * Where a deal's end date comes from. "offer" follows the store's own deal
 * end time (DealSignals); "manual" is a date, or "no end", that a person or
 * an agent set, and is never overwritten. Pure; DealStatus still decides
 * whether the result is expired.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealEnds
{
    const SOURCE_OFFER = 'offer';
    const SOURCE_MANUAL = 'manual';

    /**
     * The source of a deal's end date. A deal with no recorded source predates
     * end dates that follow the store, so an end date it already has was set by
     * a person.
     */
    public static function sourceFor(string $stored, int $ends): string
    {
        if ($stored === self::SOURCE_MANUAL || $stored === self::SOURCE_OFFER)
            return $stored;

        return $ends > 0 ? self::SOURCE_MANUAL : self::SOURCE_OFFER;
    }

    /**
     * @param array $s {source, ends, signal_ends, status, reason, now}
     * @return array {ends: int, status: string, reason: string}; ends 0 = none
     */
    public static function resolve(array $s): array
    {
        $ends = (int) ($s['ends'] ?? 0);
        $status = (string) ($s['status'] ?? '');
        $reason = (string) ($s['reason'] ?? '');

        if (self::sourceFor((string) ($s['source'] ?? ''), $ends) === self::SOURCE_MANUAL)
            return array('ends' => $ends, 'status' => $status, 'reason' => $reason);

        $signal = (int) ($s['signal_ends'] ?? 0);

        // The store moved its end into the future: a deal that expired only
        // because the old end passed comes back, as setEnds() does by hand.
        if (
            $status === DealStatus::EXPIRED && $reason === DealStatus::REASON_ENDED
            && $signal > (int) ($s['now'] ?? 0)
        )
        {
            $status = DealStatus::ACTIVE;
            $reason = '';
        }

        return array('ends' => $signal, 'status' => $status, 'reason' => $reason);
    }
}
