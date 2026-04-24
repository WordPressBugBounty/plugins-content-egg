<?php

namespace ContentEgg\application\EggBlocks\shared;

defined('ABSPATH') || exit;

abstract class EggBlocksRenderer
{
    abstract public static function render(array $attributes): string;

    protected static function getVariant(array $attributes): string
    {
        return $attributes['variant'] ?? 'default';
    }

    protected static function getTheme(array $attributes): string
    {
        return $attributes['theme'] ?? 'inherit';
    }

    /**
     * Wrap block content in the standard cegg5-container + block root structure.
     */
    protected static function wrap(string $blockClass, string $theme, string $inner): string
    {
        return sprintf(
            '<div class="cegg5-container"><div class="%s eggb-block eggb-theme-%s">%s</div></div>',
            esc_attr($blockClass),
            esc_attr($theme),
            $inner
        );
    }
}
