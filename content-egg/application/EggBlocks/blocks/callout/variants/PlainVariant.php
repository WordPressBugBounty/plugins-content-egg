<?php

namespace ContentEgg\application\EggBlocks\blocks\callout\variants;

defined('ABSPATH') || exit;

class PlainVariant
{
    /**
     * A callout is an aside — its job is to interrupt the article, not to
     * join it. So unlike the other plain variants it deliberately does NOT
     * sit on the shared label rail: conforming to the rail is exactly what
     * makes a callout stop reading as an interruption.
     *
     * Instead the label straddles a hairline box, fieldset-legend style.
     * The rule is drawn as two flex segments either side of the label
     * rather than knocked out with a background, so it needs no knowledge
     * of the page colour and survives dark mode.
     */
    public static function render(array $callout, string $theme_class = '', string $data_theme = ''): void
    {
        $has_label = $callout['label'] !== '';

        $classes = 'eggb-block eggb-callout eggb-callout--plain ' . $callout['type_class'];
        if (!$has_label)
        {
            $classes .= ' eggb-callout--plain-nolabel';
        }
        if ($theme_class !== '')
        {
            $classes .= ' ' . $theme_class;
        }
        ?>
        <div class="<?php echo esc_attr($classes); ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ($has_label) : ?>
                <div class="eggb-co-head">
                    <span class="eggb-co-line"></span>
                    <span class="eggb-co-label"><?php echo esc_html($callout['label']); ?></span>
                    <span class="eggb-co-line"></span>
                </div>
            <?php endif; ?>

            <?php if ($callout['title'] !== '') : ?>
                <div class="eggb-co-title"><?php echo esc_html($callout['title']); ?></div>
            <?php endif; ?>

            <?php if ($callout['body'] !== '') : ?>
                <div class="eggb-co-text"><?php echo $callout['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php endif; ?>
        </div>
        <?php
    }
}
