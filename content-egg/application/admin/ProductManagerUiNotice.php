<?php

namespace ContentEgg\application\admin;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\Config;

/**
 * One-time admin notice inviting an EXISTING install — pinned to the deprecated
 * classic metabox by Installer::upgrade_v93() — to switch to the new in-editor
 * product manager. Shown only while the prompt flag is set and the install is
 * still on the metabox; either choice (switch / keep) resolves it for good.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ProductManagerUiNotice
{
    const FLAG = 'cegg_pm_ui_prompt';
    const ACTION = 'cegg_pm_ui_choice';

    public static function init()
    {
        \add_action('admin_init', array(__CLASS__, 'handle'));
        \add_action('admin_notices', array(__CLASS__, 'render'));
    }

    private static function eligible(): bool
    {
        if (!\current_user_can('manage_options'))
            return false;
        if (!\get_option(self::FLAG))
            return false;
        // Defensive: only while still on the legacy metabox.
        return GeneralConfig::getInstance()->option('product_manager_ui') === 'metabox';
    }

    /**
     * Process a Yes/No click (nonce-checked), then redirect to strip the query
     * args so a refresh doesn't re-trigger it.
     */
    public static function handle()
    {
        if (empty($_GET[self::ACTION]) || !\current_user_can('manage_options'))
            return;

        \check_admin_referer(self::ACTION);

        $choice = \sanitize_key(\wp_unslash($_GET[self::ACTION]));
        if ($choice === 'switch')
        {
            Config::updateOption('product_manager_ui', 'sidebar', GeneralConfig::getInstance()->option_name());
        }
        // 'keep' leaves the metabox as-is.

        \delete_option(self::FLAG);

        \wp_safe_redirect(\remove_query_arg(array(self::ACTION, '_wpnonce')));
        exit;
    }

    public static function render()
    {
        if (!self::eligible())
            return;

        $switch = \wp_nonce_url(\add_query_arg(self::ACTION, 'switch'), self::ACTION);
        $keep   = \wp_nonce_url(\add_query_arg(self::ACTION, 'keep'), self::ACTION);

        echo '<div class="notice notice-info">';
        echo '<p><strong>' . \esc_html__('Content Egg: a new product manager is available', 'content-egg') . '</strong></p>';
        echo '<p>' . \esc_html__('You can now search and manage products right inside the editor instead of the classic metabox below it. Would you like to switch to the new interface? You can change this anytime under Content Egg → Settings → Egg Blocks → Product manager interface.', 'content-egg') . '</p>';
        echo '<p>';
        echo '<a href="' . \esc_url($switch) . '" class="button button-primary">' . \esc_html__('Yes, switch to the new interface', 'content-egg') . '</a> ';
        echo '<a href="' . \esc_url($keep) . '" class="button">' . \esc_html__('No, keep the classic metabox', 'content-egg') . '</a>';
        echo '</p>';
        echo '</div>';
    }
}
