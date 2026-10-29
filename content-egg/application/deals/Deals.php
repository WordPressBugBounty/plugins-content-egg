<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;

/**
 * Deals class file
 *
 * One switch for the whole feature. With deals off, nothing below registers -
 * an existing site upgrading to this version must see no difference at all.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class Deals
{
    const FLUSH_OPTION = 'cegg_deals_flush';

    public static function init(): void
    {
        \add_action('update_option_' . GeneralConfig::getInstance()->option_name(), array(__CLASS__, 'onSettingsSaved'), 10, 2);

        // Before the enabled check: with deals off the shortcode still has to
        // swallow itself rather than print as raw text.
        \ContentEgg\application\blocks\dealsblock\DealsBlock::registerShortcode();

        // Before the enabled check: deals turned off still get their keys.
        DealKeysBackfill::initAction();

        $enabled = DealsSettings::enabled();

        if ($enabled)
        {
            DealPostType::register();

            // A new or removed Cashback Tracker shop page changes store-page links.
            \add_action('save_post_cbtrkr_shop', array(DealNav::class, 'flushCashbackCache'));
            \add_action('delete_post_cbtrkr_shop', array(DealNav::class, 'flushCashbackCache'));
        }

        // After registering, so a flush on enable includes the new rules and a
        // flush on disable drops them. Only a settings save sets the flag, and
        // options.php redirects back to the settings screen, so the next admin
        // request flushes; front-end requests skip the option read.
        if (\is_admin() && \get_option(self::FLUSH_OPTION))
        {
            \delete_option(self::FLUSH_OPTION);
            \flush_rewrite_rules(false);
        }

        if (!$enabled)
        {
            DealsCron::unschedule();
            return;
        }

        DealService::initAction();
        DealsCron::initAction();
        DealVotesRestController::getInstance()->init();
        DealQuery::initAction();
        DealTemplates::initAction();
        \ContentEgg\application\blocks\dealsblock\DealsBlock::initAction();
        DealStateRestController::getInstance()->init();
    }

    public static function adminInit(): void
    {
        if (!DealsSettings::enabled())
            return;

        DealsAdmin::init();

        if (self::shouldEnsurePreset() && !DealPreset::exists(DealPreset::id()))
            DealPreset::ensure();
    }

    /**
     * The preset's author is the user imports run as, so only an
     * administrator's own page load may create it - not a subscriber opening
     * their profile or an anonymous admin-ajax call.
     */
    public static function shouldEnsurePreset(): bool
    {
        return \current_user_can('manage_options') && !\wp_doing_ajax();
    }

    public static function onSettingsSaved($old, $new): void
    {
        foreach (array('deals_enabled', 'deals_slug', 'deals_archive_slug') as $key)
        {
            if ((is_array($old) ? ($old[$key] ?? null) : null) !== (is_array($new) ? ($new[$key] ?? null) : null))
            {
                \update_option(self::FLUSH_OPTION, 1, false);
                return;
            }
        }
    }
}
