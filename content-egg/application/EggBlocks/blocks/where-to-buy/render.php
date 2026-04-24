<?php

/**
 * Server-side render for eggb/where-to-buy block.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks content (unused).
 * @var WP_Block $block      Block instance.
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/variants/DefaultVariant.php';
require_once __DIR__ . '/variants/CompactVariant.php';
require_once __DIR__ . '/variants/TableCompactVariant.php';
require_once __DIR__ . '/WhereToBuyRenderer.php';
require_once __DIR__ . '/../../shared/EggbTocSupport.php';

$anchor = \ContentEgg\application\EggBlocks\shared\EggbTocSupport::normalizeAnchor((string) ($attributes['anchor'] ?? ''));
$variant = sanitize_html_class((string) ($attributes['variant'] ?? 'default'));
$wrapper_args = ['class' => 'cegg5-container eggb-where-to-buy-wrap eggb-where-to-buy-wrap--' . $variant];
if ($anchor !== '') {
    $wrapper_args['id'] = $anchor;
}

$wrapper_attributes = get_block_wrapper_attributes($wrapper_args);
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '<div ' . $wrapper_attributes . '>' . \ContentEgg\application\EggBlocks\blocks\wheretobuy\WhereToBuyRenderer::render($attributes) . '</div>';
