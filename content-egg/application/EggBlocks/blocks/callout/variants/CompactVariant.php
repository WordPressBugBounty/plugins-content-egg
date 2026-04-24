<?php

namespace ContentEgg\application\EggBlocks\blocks\callout\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;

defined('ABSPATH') || exit;

class CompactVariant
{
    public static function render(array $callout, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <div class="eggb-block eggb-callout eggb-callout--compact <?php echo esc_attr($callout['type_class']); ?><?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php echo EggbIcons::get($callout['icon'], 'eggb-callout-icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <div class="eggb-callout-body">
                <span class="eggb-callout-type"><?php echo esc_html($callout['label']); ?></span>
                <?php echo $callout['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </div>
        <?php
    }
}
