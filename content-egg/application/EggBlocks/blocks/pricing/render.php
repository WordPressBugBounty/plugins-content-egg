<?php

/**
 * Server-side render for eggb/pricing block.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks content (unused).
 * @var WP_Block $block      Block instance.
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/variants/PricingListVariant.php';
require_once __DIR__ . '/variants/PricingCardsVariant.php';
require_once __DIR__ . '/variants/PricingHighlightVariant.php';
require_once __DIR__ . '/PricingRenderer.php';

$wrapper_attributes = get_block_wrapper_attributes(['class' => 'cegg5-container']);
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '<div ' . $wrapper_attributes . '>' . \ContentEgg\application\EggBlocks\blocks\pricing\PricingRenderer::render($attributes) . '</div>';
