<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/variants/DefaultVariant.php';
require_once __DIR__ . '/variants/CompactVariant.php';
require_once __DIR__ . '/variants/AlternativesVariant.php';
require_once __DIR__ . '/variants/ShelvesVariant.php';
require_once __DIR__ . '/variants/GridVariant.php';
require_once __DIR__ . '/variants/HighlightVariant.php';
require_once __DIR__ . '/QuickPicksRenderer.php';
require_once __DIR__ . '/../../shared/EggbTocSupport.php';

$anchor = \ContentEgg\application\EggBlocks\shared\EggbTocSupport::normalizeAnchor((string) ($attributes['anchor'] ?? ''));
$wrapper_args = ['class' => 'cegg5-container'];
if ($anchor !== '') {
    $wrapper_args['id'] = $anchor;
}

$wrapper_attributes = get_block_wrapper_attributes($wrapper_args);
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '<div ' . $wrapper_attributes . '>' . \ContentEgg\application\EggBlocks\blocks\quickpicks\QuickPicksRenderer::render($attributes) . '</div>';
