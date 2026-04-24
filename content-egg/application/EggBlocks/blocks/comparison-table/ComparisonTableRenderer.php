<?php

namespace ContentEgg\application\EggBlocks\blocks\comparisontable;

use ContentEgg\application\EggBlocks\blocks\comparisontable\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\blocks\comparisontable\variants\ProductListVariant;
use ContentEgg\application\EggBlocks\blocks\comparisontable\variants\VersusVariant;
use ContentEgg\application\EggBlocks\shared\CeProductResolver;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;
use ContentEgg\application\helpers\TemplateHelper;

defined('ABSPATH') || exit;

class ComparisonTableRenderer
{
    use RendersWithTheme;

    private static function isEditorPreview(): bool
    {
        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return isset($_GET['context']) && $_GET['context'] === 'edit';
    }

    public static function render(array $attributes): string
    {
        $variant = self::normalizeVariant($attributes['variant'] ?? 'default');
        $is_editor_preview = self::isEditorPreview();
        $payload = self::normalizePayload($attributes, $variant, $is_editor_preview);

        if (count($payload['items']) < 2)
        {
            return '';
        }

        $theme_class = self::resolveThemeClass('auto');
        $color_scheme = self::resolveColorScheme($attributes['color_scheme'] ?? 'auto', $theme_class);
        $data_theme = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';

        ob_start();

        switch ($variant)
        {
            case 'versus':
                VersusVariant::render($payload, $theme_class, $data_theme);
                break;
            case 'product-list':
                ProductListVariant::render($payload, $theme_class, $data_theme);
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

        if (!in_array($variant, ['default', 'versus', 'product-list'], true))
        {
            return 'default';
        }

        return $variant;
    }

    private static function normalizePayload(array $attributes, string $variant, bool $is_editor_preview = false): array
    {
        $raw_items = is_array($attributes['items'] ?? null) ? $attributes['items'] : [];
        $raw_criteria = is_array($attributes['criteria'] ?? null) ? $attributes['criteria'] : [];

        $refs = [];
        $items_by_module = [];
        foreach ($raw_items as $raw_item)
        {
            $refs[] = is_array($raw_item['product_ref'] ?? null) ? $raw_item['product_ref'] : [];
        }

        $resolved_items = CeProductResolver::resolveMany($refs);
        $items = [];

        foreach ($raw_items as $index => $raw_item)
        {
            $product_item = $resolved_items[$index] ?? null;
            $is_bound = !empty($product_item) && is_array($product_item);

            if (!$is_bound)
            {
                if (!$is_editor_preview)
                {
                    continue;
                }
                $product_item = [];
            }

            $module_id = (string) ($product_item['module_id'] ?? $raw_item['product_ref']['module_id'] ?? '');
            if ($module_id !== '')
            {
                if (!isset($items_by_module[$module_id]))
                {
                    $items_by_module[$module_id] = [];
                }
                $items_by_module[$module_id][] = $product_item;
            }

            $title = trim((string) ($raw_item['title'] ?? ''));
            if ($title === '')
            {
                $title = trim((string) ($product_item['title'] ?? ''));
            }

            $items[] = [
                'id' => (string) ($raw_item['id'] ?? ''),
                'product_ref' => is_array($raw_item['product_ref'] ?? null) ? $raw_item['product_ref'] : [],
                'product_item' => $product_item,
                'title' => $title,
                'role_label' => trim((string) ($raw_item['role_label'] ?? '')),
                'is_winner' => self::normalizeBoolean($raw_item['is_winner'] ?? false),
                'rank' => $index + 1,
                'rank_display' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'has_link' => !empty($product_item['url']),
            ];
        }

        if ($variant === 'versus' && count($items) > 2)
        {
            $items = array_slice($items, 0, 2);
        }

        $criteria = [];
        foreach ($raw_criteria as $raw_criterion)
        {
            $type = (string) ($raw_criterion['type'] ?? 'text');
            if (!in_array($type, ['text', 'score', 'star', 'boolean', 'price'], true))
            {
                $type = 'text';
            }

            $values = [];
            foreach ((array) ($raw_criterion['values'] ?? []) as $value)
            {
                if (is_bool($value))
                {
                    $values[] = $value;
                    continue;
                }

                $values[] = trim((string) $value);
            }

            $criteria[] = [
                'key' => trim((string) ($raw_criterion['key'] ?? '')),
                'label' => trim((string) ($raw_criterion['label'] ?? '')),
                'type' => $type,
                'values' => $values,
            ];
        }

        $heading_tag = (string) ($attributes['heading_tag'] ?? 'div');
        if (!in_array($heading_tag, ['h1', 'h2', 'h3', 'h4', 'div'], true))
        {
            $heading_tag = 'div';
        }

        return [
            'variant' => $variant,
            'heading_label' => trim((string) ($attributes['heading_label'] ?? 'Side-by-side comparison')),
            'heading_title' => trim((string) ($attributes['heading_title'] ?? '')),
            'heading_tag' => $heading_tag,
            'cta_label' => trim((string) ($attributes['cta_label'] ?? '')),
            'footer_note' => trim((string) ($attributes['footer_note'] ?? '')),
            'items' => $items,
            'criteria' => $criteria,
            'amazon_update_html' => self::shouldShowAmazonUpdate($variant, $criteria)
                ? self::captureAmazonUpdate($items_by_module)
                : '',
        ];
    }

    public static function resolveCtaLabel(array $payload, ?array $product_item = null): string
    {
        $cta_label = trim((string) ($payload['cta_label'] ?? ''));
        if ($cta_label !== '')
        {
            return $cta_label;
        }

        if (!empty($product_item))
        {
            $ce_label = trim((string) TemplateHelper::buyNowBtnText(false, $product_item));
            if ($ce_label !== '')
            {
                return $ce_label;
            }
        }

        if (($payload['variant'] ?? '') === 'product-list')
        {
            return 'View offer';
        }

        return 'Check price';
    }

    private static function captureAmazonUpdate(array $items_by_module): string
    {
        if (empty($items_by_module))
        {
            return '';
        }

        ob_start();
        TemplateHelper::priceUpdateAmazon($items_by_module);
        return trim((string) ob_get_clean());
    }

    private static function shouldShowAmazonUpdate(string $variant, array $criteria): bool
    {
        foreach ($criteria as $criterion)
        {
            if (($criterion['type'] ?? '') === 'price')
            {
                return true;
            }
        }

        return false;
    }

    private static function normalizeBoolean($value): bool
    {
        if (is_bool($value))
        {
            return $value;
        }

        if (is_numeric($value))
        {
            return (int) $value === 1;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true);
    }
}
