<?php

namespace ContentEgg\application\EggBlocks\blocks\callout\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;

defined('ABSPATH') || exit;

class DefaultVariant
{
    public static function render(array $callout, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <div class="eggb-block eggb-callout eggb-callout--default <?php echo esc_attr($callout['type_class']); ?><?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php echo EggbIcons::get($callout['icon'], 'eggb-callout-icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <div class="eggb-callout-content">
                <span class="eggb-callout-type"><?php echo esc_html($callout['label']); ?></span>
                <?php if ($callout['title'] !== ''): ?>
                    <div class="eggb-callout-title"><?php echo esc_html($callout['title']); ?></div>
                <?php endif; ?>
                <?php if ($callout['body'] !== ''): ?>
                    <div class="eggb-callout-body"><?php echo $callout['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
