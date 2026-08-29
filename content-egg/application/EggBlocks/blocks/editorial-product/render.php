<?php

/**
 * Server-side render for eggb/editorial-product block.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks content (unused).
 * @var WP_Block $block      Block instance.
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/variants/FigureVariant.php';
require_once __DIR__ . '/variants/ClaimVariant.php';
require_once __DIR__ . '/variants/SpecimenVariant.php';
require_once __DIR__ . '/variants/MentionVariant.php';
require_once __DIR__ . '/EditorialProductRenderer.php';

// Normalized, not raw: a block saved with a variant that no longer exists
// renders as a figure, and its wrapper has to say so too or it misses the
// figure's own wrapper rules.
$variant = \ContentEgg\application\EggBlocks\blocks\editorialproduct\EditorialProductRenderer::normalizeVariant(
    $attributes['variant'] ?? 'figure'
);

$wrapper_args = ['class' => 'cegg5-container eggb-editorial-product-wrap eggb-editorial-product-wrap--' . $variant];

$anchor = sanitize_title((string) ($attributes['anchor'] ?? ''));
if ($anchor !== '')
{
    $wrapper_args['id'] = $anchor;
}

$wrapper_attributes = get_block_wrapper_attributes($wrapper_args);
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '<div ' . $wrapper_attributes . '>' . \ContentEgg\application\EggBlocks\blocks\editorialproduct\EditorialProductRenderer::render($attributes) . '</div>';
