<?php

namespace ContentEgg\application\EggBlocks;

use ContentEgg\application\Plugin;

defined('ABSPATH') || exit;

class EggBlocksAssets
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        $baseUrl = plugins_url('', __FILE__);
        $version = Plugin::version();

        wp_register_style(
            'eggb-base',
            $baseUrl . '/shared/eggb-base-min.css',
            ['cegg-bootstrap5'],
            $version
        );

        wp_register_style(
            'eggb-theme-default',
            $baseUrl . '/themes/eggb-theme-default-min.css',
            ['eggb-base'],
            $version
        );

        wp_register_style(
            'eggb-theme-default-dark',
            $baseUrl . '/themes/eggb-theme-default-dark-min.css',
            ['eggb-theme-default'],
            $version
        );

        wp_register_style(
            'eggb-theme-editorial',
            $baseUrl . '/themes/eggb-theme-editorial-min.css',
            ['eggb-base'],
            $version
        );

        wp_register_style(
            'eggb-theme-editorial-dark',
            $baseUrl . '/themes/eggb-theme-editorial-dark-min.css',
            ['eggb-theme-editorial'],
            $version
        );

        wp_register_style(
            'eggb-theme-data',
            $baseUrl . '/themes/eggb-theme-data-min.css',
            ['eggb-base'],
            $version
        );

        wp_register_style(
            'eggb-theme-data-dark',
            $baseUrl . '/themes/eggb-theme-data-dark-min.css',
            ['eggb-theme-data'],
            $version
        );

        wp_register_style(
            'eggb-theme-conversion',
            $baseUrl . '/themes/eggb-theme-conversion-min.css',
            ['eggb-base'],
            $version
        );

        wp_register_style(
            'eggb-theme-conversion-dark',
            $baseUrl . '/themes/eggb-theme-conversion-dark-min.css',
            ['eggb-theme-conversion'],
            $version
        );

        wp_register_style(
            'eggb-theme-magazine',
            $baseUrl . '/themes/eggb-theme-magazine-min.css',
            ['eggb-base'],
            $version
        );

        wp_register_style(
            'eggb-theme-magazine-dark',
            $baseUrl . '/themes/eggb-theme-magazine-dark-min.css',
            ['eggb-theme-magazine'],
            $version
        );

        self::$registered = true;
    }
}
