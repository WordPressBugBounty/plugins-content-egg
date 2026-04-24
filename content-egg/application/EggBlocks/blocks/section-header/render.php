<?php

/**
 * Server-side render for eggb/section-header block.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks content (unused).
 * @var WP_Block $block      Block instance.
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/variants/DefaultVariant.php';
require_once __DIR__ . '/variants/AccentVariant.php';
require_once __DIR__ . '/variants/EditorialVariant.php';
require_once __DIR__ . '/SectionHeaderRenderer.php';
require_once __DIR__ . '/../../shared/EggbTocSupport.php';

$anchor = \ContentEgg\application\EggBlocks\shared\EggbTocSupport::normalizeAnchor((string) ($attributes['anchor'] ?? ''));
$wrapper_args = ['class' => 'cegg5-container'];
if ($anchor !== '') {
    $wrapper_args['id'] = $anchor;
}

$wrapper_attributes = get_block_wrapper_attributes($wrapper_args);
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '<div ' . $wrapper_attributes . '>' . \ContentEgg\application\EggBlocks\blocks\sectionheader\SectionHeaderRenderer::render($attributes) . '</div>';
