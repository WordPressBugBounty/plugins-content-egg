<?php

/**
 * Server-side render for eggb/verdict block.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks content (unused).
 * @var WP_Block $block      Block instance.
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/variants/DefaultVariant.php';
require_once __DIR__ . '/variants/CompactVariant.php';
require_once __DIR__ . '/variants/SummaryVariant.php';
require_once __DIR__ . '/variants/PlainVariant.php';
require_once __DIR__ . '/VerdictRenderer.php';

$wrapper_attributes = get_block_wrapper_attributes(['class' => 'cegg5-container']);
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '<div ' . $wrapper_attributes . '>' . \ContentEgg\application\EggBlocks\blocks\verdict\VerdictRenderer::render($attributes) . '</div>';
