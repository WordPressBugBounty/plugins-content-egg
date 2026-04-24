<?php

/**
 * Server-side render for eggb/related-posts block.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks content (unused).
 * @var WP_Block $block      Block instance.
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/variants/CompactVariant.php';
require_once __DIR__ . '/variants/MediaListVariant.php';
require_once __DIR__ . '/variants/CardsVariant.php';
require_once __DIR__ . '/variants/FeaturedVariant.php';
require_once __DIR__ . '/RelatedPostsRenderer.php';

$wrapper_attributes = get_block_wrapper_attributes(['class' => 'cegg5-container']);
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '<div ' . $wrapper_attributes . '>' . \ContentEgg\application\EggBlocks\blocks\relatedposts\RelatedPostsRenderer::render($attributes) . '</div>';
