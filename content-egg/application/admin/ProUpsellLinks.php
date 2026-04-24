<?php

namespace ContentEgg\application\admin;

use ContentEgg\application\Plugin;

defined('\ABSPATH') || exit;

/**
 * ProUpsellLinks class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ProUpsellLinks
{
    public static function init(): void
    {
        if (Plugin::isFree() && !Plugin::isEnvato())
        {
            add_action('admin_menu', [self::class, 'addSidebarLink']);
        }
    }

    public static function addSidebarLink(): void
    {
        global $submenu;

        $submenu[Plugin::slug][] = [
            '<span style="color: #75b798;" class="cegg-go-pro-link">'
                . esc_html__('Go PRO', 'content-egg')
                . ' <span class="dashicons dashicons-external"></span>'
                . '</span>',
            'manage_options',
            esc_url(Plugin::pluginPricingUrl('ce_sidebar', 'go_pro_link'))
        ];

        add_action('admin_print_footer_scripts', [self::class, 'printGoProScript']);
    }

    public static function printGoProScript(): void
    {
        ?>
        <script>
            document.querySelectorAll('a > .cegg-go-pro-link').forEach(function (el) {
                var a = el.parentElement;
                a.setAttribute('target', '_blank');
                a.setAttribute('rel', 'noopener noreferrer');
            });
        </script>
        <?php
    }
}
