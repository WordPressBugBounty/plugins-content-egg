<?php

/**
 * Server-side render for eggb/variant-switcher block.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks content (unused).
 * @var WP_Block $block      Block instance.
 */

defined('ABSPATH') || exit;

// The renderer loads the two style emitters itself (loadVariants()).
require_once __DIR__ . '/VariantSwitcherRenderer.php';

$renderer = '\ContentEgg\application\EggBlocks\blocks\variantswitcher\VariantSwitcherRenderer';
$html = $renderer::render($attributes);

// Outside a family (or with no sibling to switch to) the block prints nothing at all,
// not even its wrapper and the bottom margin that comes with it.
if ($html === '') {
    return;
}

$variant = $renderer::normalizeVariant($attributes['variant'] ?? 'chips');
$wrapper_attributes = get_block_wrapper_attributes(['class' => 'cegg5-container eggb-variant-switcher-wrap eggb-variant-switcher-wrap--' . $variant]);
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '<div ' . $wrapper_attributes . '>' . $html . '</div>';
