<?php

namespace ContentEgg\application\EggBlocks\shared\traits;

use ContentEgg\application\admin\GeneralConfig;

defined('ABSPATH') || exit;

trait RendersWithTheme
{
    /**
     * Resolve the effective theme slug and return the CSS class.
     *
     * @return string e.g. 'eggb-theme-default', 'eggb-theme-editorial'
     */
    protected static function resolveThemeClass(string $theme): string
    {
        if ($theme === 'auto' || $theme === '') {
            $theme = (string) GeneralConfig::getInstance()->option('eggb_default_theme', 'default');
            if ($theme === '') {
                $theme = 'default';
            }
        }

        $theme_class = 'eggb-theme-' . sanitize_html_class($theme);
        wp_enqueue_style($theme_class);

        return $theme_class;
    }

    /**
     * Resolve the effective color scheme and enqueue dark CSS when needed.
     *
     * @param string $block_scheme 'auto', 'light', or 'dark'
     * @param string $theme_class  Resolved theme class from resolveThemeClass(), e.g. 'eggb-theme-editorial'
     * @return string 'light', 'dark', or 'auto'
     */
    protected static function resolveColorScheme(string $block_scheme, string $theme_class = 'eggb-theme-default'): string
    {
        $scheme = $block_scheme;
        if ($scheme === 'auto') {
            $scheme = (string) GeneralConfig::getInstance()->option('eggb_color_scheme', 'light');
        }

        if ($scheme !== 'light') {
            $dark_handle = $theme_class . '-dark';
            wp_enqueue_style($dark_handle);
        }

        return $scheme;
    }
}
