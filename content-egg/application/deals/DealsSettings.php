<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;

/**
 * DealsSettings class file
 *
 * Typed reads of the deals_* options, so no caller repeats the
 * "dropdown means enabled unless it says disabled" rule.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealsSettings
{
    public static function enabled(): bool
    {
        return (bool) self::opt('deals_enabled');
    }

    public static function singleSlug(): string
    {
        return self::slug('deals_slug', 'deal');
    }

    public static function archiveSlug(): string
    {
        return self::slug('deals_archive_slug', 'deals');
    }

    public static function threshold(): float
    {
        $value = self::opt('deals_price_threshold');

        return ($value === '' || $value === null) ? 5.0 : max(0.0, (float) $value);
    }

    public static function autoReactivate(): bool
    {
        return self::opt('deals_auto_reactivate') !== 'disabled';
    }

    public static function hideExpired(): bool
    {
        return self::opt('deals_hide_expired') !== 'disabled';
    }

    public static function noindexExpired(): bool
    {
        return (bool) self::opt('deals_noindex_expired');
    }

    public static function votingEnabled(): bool
    {
        return self::opt('deals_voting') !== 'disabled';
    }

    public static function votingLoggedInOnly(): bool
    {
        return (bool) self::opt('deals_voting_logged_in');
    }

    /**
     * Settings → Deals → Hot deal score; the cegg_deal_hot_score filter can
     * change it from code (0 turns the badge off).
     */
    public static function hotScore(): int
    {
        return max(0, (int) \apply_filters('cegg_deal_hot_score', (int) self::opt('deals_hot_score')));
    }

    public static function perPage(): int
    {
        $n = (int) self::opt('deals_per_page');

        return $n > 0 ? min($n, 100) : 24;
    }

    private static function opt($name)
    {
        return GeneralConfig::getInstance()->option($name);
    }

    private static function slug(string $name, string $default): string
    {
        $slug = \sanitize_title((string) self::opt($name));

        return $slug !== '' ? $slug : $default;
    }
}
