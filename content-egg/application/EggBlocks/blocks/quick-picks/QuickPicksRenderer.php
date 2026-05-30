<?php

namespace ContentEgg\application\EggBlocks\blocks\quickpicks;

use ContentEgg\application\EggBlocks\blocks\quickpicks\variants\AlternativesVariant;
use ContentEgg\application\EggBlocks\blocks\quickpicks\variants\CompactVariant;
use ContentEgg\application\EggBlocks\blocks\quickpicks\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\blocks\quickpicks\variants\GridVariant;
use ContentEgg\application\EggBlocks\blocks\quickpicks\variants\HighlightVariant;
use ContentEgg\application\EggBlocks\blocks\quickpicks\variants\ShelvesVariant;
use ContentEgg\application\EggBlocks\shared\CeProductResolver;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;
use ContentEgg\application\helpers\TemplateHelper;

defined('ABSPATH') || exit;

class QuickPicksRenderer
{
    use RendersWithTheme;

    private const ALLOWED_HEADING_TAGS = ['h1', 'h2', 'h3', 'h4', 'div'];

    public static function render(array $attributes): string
    {
        $variant = self::normalizeVariant($attributes['variant'] ?? 'default');
        $is_editor_preview = self::isEditorPreview();
        $payload = self::normalizePayload($attributes, $variant, $is_editor_preview);

        if (empty($payload['items'])) {
            return '';
        }

        $theme_class = self::resolveThemeClass('auto');
        $color_scheme = self::resolveColorScheme($attributes['color_scheme'] ?? 'auto', $theme_class);
        $data_theme = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';

        ob_start();

        switch ($variant) {
            case 'compact':
                CompactVariant::render($payload, $theme_class, $data_theme);
                break;
            case 'alternatives':
                AlternativesVariant::render($payload, $theme_class, $data_theme);
                break;
            case 'shelves':
                ShelvesVariant::render($payload, $theme_class, $data_theme);
                break;
            case 'grid':
                GridVariant::render($payload, $theme_class, $data_theme);
                break;
            case 'highlight':
                HighlightVariant::render($payload, $theme_class, $data_theme);
                break;
            default:
                DefaultVariant::render($payload, $theme_class, $data_theme);
                break;
        }

        return (string) ob_get_clean();
    }

    private static function normalizeVariant($variant): string
    {
        $variant = (string) $variant;

        if (!in_array($variant, ['default', 'compact', 'alternatives', 'shelves', 'grid', 'highlight'], true)) {
            return 'default';
        }

        return $variant;
    }

    private static function isEditorPreview(): bool
    {
        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return isset($_GET['context']) && $_GET['context'] === 'edit';
    }

    private static function normalizePayload(array $attributes, string $variant, bool $is_editor_preview = false): array
    {
        $raw_items = is_array($attributes['items'] ?? null) ? $attributes['items'] : [];
        $refs = [];
        $items_by_module = [];

        foreach ($raw_items as $raw_item) {
            $refs[] = is_array($raw_item['product_ref'] ?? null) ? $raw_item['product_ref'] : [];
        }

        $resolved_items = CeProductResolver::resolveMany($refs);
        $items = [];

        foreach ($raw_items as $index => $raw_item) {
            $product_item = $resolved_items[$index] ?? null;
            $is_bound = !empty($product_item) && is_array($product_item);

            if (!$is_bound) {
                if (!$is_editor_preview) {
                    continue;
                }
                $product_item = [];
            }

            $module_id = (string) ($product_item['module_id'] ?? $raw_item['product_ref']['module_id'] ?? '');
            if ($module_id !== '') {
                if (!isset($items_by_module[$module_id])) {
                    $items_by_module[$module_id] = [];
                }
                $items_by_module[$module_id][] = $product_item;
            }

            $chips = [];
            foreach ((array) ($raw_item['chips'] ?? []) as $chip) {
                $chip = trim((string) $chip);
                if ($chip !== '') {
                    $chips[] = $chip;
                }
            }

            $title = trim((string) ($raw_item['title'] ?? ''));
            if ($title === '') {
                $title = trim((string) ($product_item['title'] ?? ''));
            }

            $subtitle = trim((string) ($raw_item['subtitle'] ?? ''));
            if ($subtitle === '') {
                $subtitle = trim((string) ($product_item['subtitle'] ?? ''));
            }

            $merchant = trim((string) ($raw_item['merchant'] ?? ''));
            if ($merchant === '') {
                $merchant = trim((string) ($product_item['merchant'] ?? $product_item['domain'] ?? ''));
            }

            $badge = trim((string) ($raw_item['badge'] ?? ''));
            if ($badge === '') {
                $badge = trim((string) ($product_item['badge'] ?? ''));
            }

            $description = trim((string) ($raw_item['description'] ?? ''));

            $score = trim((string) ($raw_item['score'] ?? ''));
            if ($score !== '' && is_numeric($score) && (float) $score > 0) {
                $score = number_format((float) $score, 1);
            } else {
                $score = '';
            }

            $items[] = [
                'id' => (string) ($raw_item['id'] ?? ''),
                'product_ref' => is_array($raw_item['product_ref'] ?? null) ? $raw_item['product_ref'] : [],
                'product_item' => $product_item,
                'has_image' => !empty($product_item['img']),
                'has_link' => !empty($product_item['url']),
                'title' => $title,
                'subtitle' => $subtitle,
                'merchant' => $merchant,
                'badge' => $badge,
                'description' => $description,
                'chips' => $chips,
                'score' => $score,
                'rank' => $index + 1,
                'rank_display' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'is_featured' => $index === 0,
            ];
        }

        $heading_tag_raw = (string) ($attributes['heading_tag'] ?? 'h2');
        $heading_tag = in_array($heading_tag_raw, self::ALLOWED_HEADING_TAGS, true) ? $heading_tag_raw : 'h2';

        return [
            'variant' => $variant,
            'section_label' => trim((string) ($attributes['section_label'] ?? 'Quick picks')),
            'title' => trim((string) ($attributes['title'] ?? '')),
            'heading_tag' => $heading_tag,
            'cta_label' => trim((string) ($attributes['cta_label'] ?? '')),
            'items' => $items,
            'has_any_image' => !empty(array_filter($items, fn($i) => $i['has_image'])),
            'amazon_update_html' => self::captureAmazonUpdate($items_by_module),
        ];
    }

    public static function resolveCtaLabel(array $payload, ?array $product_item = null): string
    {
        $cta_label = trim((string) ($payload['cta_label'] ?? ''));
        if ($cta_label !== '') {
            return $cta_label;
        }

        if (!empty($product_item)) {
            $ce_label = trim((string) TemplateHelper::buyNowBtnText(false, $product_item));
            if ($ce_label !== '') {
                return $ce_label;
            }
        }

        if (($payload['variant'] ?? '') === 'alternatives') {
            return 'View alternative';
        }

        if (($payload['variant'] ?? '') === 'shelves') {
            return 'View pick';
        }

        if (($payload['variant'] ?? '') === 'grid') {
            return 'View pick';
        }

        return 'Check price';
    }

    private static function captureAmazonUpdate(array $items_by_module): string
    {
        if (empty($items_by_module)) {
            return '';
        }

        ob_start();
        TemplateHelper::priceUpdateAmazon($items_by_module);
        return trim((string) ob_get_clean());
    }
}
