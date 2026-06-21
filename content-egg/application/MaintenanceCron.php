<?php

namespace ContentEgg\application;

defined('\ABSPATH') || exit;

/**
 * MaintenanceCron class file
 *
 * Periodic background maintenance. Uses WordPress cron as the baseline and
 * ActionScheduler (bundled with WooCommerce) as an extra, more reliable
 * trigger when available. Both fire the same throttled handler, so running
 * both is harmless.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class MaintenanceCron
{
    const HOOK = 'cegg_maintenance_cron';
    const GROUP = 'content-egg';

    public static function register()
    {
        \add_action(self::HOOK, array('\ContentEgg\application\licensing\LicenseGate', 'cronPoll'));
        \add_action(\ContentEgg\application\licensing\LicenseGate::NOTICE_HOOK, array('\ContentEgg\application\licensing\LicenseGate', 'cronNotify'));
    }

    public static function schedule()
    {
        if (!\wp_next_scheduled(self::HOOK))
            \wp_schedule_event(time() + 300, 'twicedaily', self::HOOK);

        if (function_exists('as_has_scheduled_action') && !\as_has_scheduled_action(self::HOOK, array(), self::GROUP))
            \as_schedule_recurring_action(time() + 300, 12 * HOUR_IN_SECONDS, self::HOOK, array(), self::GROUP);
    }

    public static function clear()
    {
        \wp_clear_scheduled_hook(self::HOOK);

        if (function_exists('as_unschedule_all_actions'))
            \as_unschedule_all_actions(self::HOOK, array(), self::GROUP);
    }
}
