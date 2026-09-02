<?php

/**
 * Server-side render for eggb/product-card block.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks content (unused).
 * @var WP_Block $block      Block instance.
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/variants/DefaultVariant.php';
require_once __DIR__ . '/variants/FeaturedVariant.php';
require_once __DIR__ . '/variants/CompactVariant.php';
require_once __DIR__ . '/variants/PlainVariant.php';
require_once __DIR__ . '/ProductCardRenderer.php';
require_once __DIR__ . '/../../shared/EggbTocSupport.php';

$anchor = \ContentEgg\application\EggBlocks\shared\EggbTocSupport::normalizeAnchor((string) ($attributes['anchor'] ?? ''));
if ($anchor === '') {
    // Auto-generate from badge → section_label → block_title → title
    $auto_label = trim((string) ($attributes['badge'] ?? ''));
    if ($auto_label === '') {
        $auto_label = trim((string) ($attributes['section_label'] ?? ''));
    }
    if ($auto_label === '') {
        $auto_label = trim((string) ($attributes['block_title'] ?? ''));
    }
    if ($auto_label === '') {
        $auto_label = trim((string) ($attributes['title'] ?? ''));
    }
    if ($auto_label !== '') {
        $anchor = \ContentEgg\application\EggBlocks\shared\EggbTocSupport::normalizeAnchor($auto_label);
    }
}
$variant = sanitize_html_class((string) ($attributes['variant'] ?? 'default'));
$wrapper_args = ['class' => 'cegg5-container eggb-product-card-wrap eggb-product-card-wrap--' . $variant];
if ($anchor !== '') {
    $wrapper_args['id'] = $anchor;
}

$wrapper_attributes = get_block_wrapper_attributes($wrapper_args);
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '<div ' . $wrapper_attributes . '>' . \ContentEgg\application\EggBlocks\blocks\productcard\ProductCardRenderer::render($attributes) . '</div>';
