<?php

namespace ContentEgg\application\EggBlocks\blocks\wheretobuy;

use ContentEgg\application\Translator;
use ContentEgg\application\EggBlocks\blocks\wheretobuy\variants\CompactVariant;
use ContentEgg\application\EggBlocks\blocks\wheretobuy\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\blocks\wheretobuy\variants\TableCompactVariant;
use ContentEgg\application\EggBlocks\shared\CeProductResolver;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;
use ContentEgg\application\helpers\TemplateHelper;

defined('ABSPATH') || exit;

class WhereToBuyRenderer
{
    use RendersWithTheme;

    private const ALLOWED_HEADING_TAGS = ['h1', 'h2', 'h3', 'h4', 'div'];

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

        if (empty($payload['offers'])) {
            return '';
        }

        $theme_class = self::resolveThemeClass('auto');
        $color_scheme = self::resolveColorScheme($attributes['color_scheme'] ?? 'auto', $theme_class);
        $data_theme = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';
        $payload['logo_params'] = $color_scheme === 'dark' ? ['color_mode' => 'dark'] : [];

        ob_start();

        switch ($variant) {
            case 'compact':
                CompactVariant::render($payload, $theme_class, $data_theme);
                break;
            case 'table-compact':
                TableCompactVariant::render($payload, $theme_class, $data_theme);
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

        if (!in_array($variant, ['default', 'compact', 'table-compact'], true)) {
            return 'default';
        }

        return $variant;
    }

    private static function normalizePayload(array $attributes, string $variant, bool $is_editor_preview = false): array
    {
        $raw_items = is_array($attributes['items'] ?? null) ? $attributes['items'] : [];
        $refs = [];

        foreach ($raw_items as $raw_item) {
            $refs[] = is_array($raw_item['product_ref'] ?? null) ? $raw_item['product_ref'] : [];
        }

        $resolved_items = CeProductResolver::resolveMany($refs);
        $offers = [];
        $items_by_module = [];

        foreach ($raw_items as $index => $raw_item) {
            $product_item = $resolved_items[$index] ?? null;
            $is_bound = !empty($product_item) && is_array($product_item);

            if (!$is_bound) {
                if (!$is_editor_preview) {
                    continue;
                }
                $product_item = [];
            }

            $chips = [];
            foreach ((array) ($raw_item['chips'] ?? []) as $chip) {
                $chip = trim((string) $chip);
                if ($chip !== '') {
                    $chips[] = $chip;
                }
            }

            $current_price = self::normalizePriceValue($product_item['price'] ?? null);
            $old_price = self::normalizePriceValue($product_item['priceOld'] ?? null);
            if ($old_price !== null && $current_price !== null && $old_price <= $current_price) {
                $old_price = null;
            }

            $module_id = (string) ($product_item['module_id'] ?? $raw_item['product_ref']['module_id'] ?? '');
            if ($module_id !== '') {
                if (!isset($items_by_module[$module_id])) {
                    $items_by_module[$module_id] = [];
                }
                $items_by_module[$module_id][] = $product_item;
            }

            $offers[] = [
                'id' => (string) ($raw_item['id'] ?? ''),
                'product_ref' => is_array($raw_item['product_ref'] ?? null) ? $raw_item['product_ref'] : [],
                'product_item' => $product_item,
                'merchant' => trim((string) ($raw_item['merchant'] ?? '')),
                'merchant_display' => trim((string) ($raw_item['merchant'] ?? '')) !== ''
                    ? trim((string) ($raw_item['merchant'] ?? ''))
                    : self::resolveMerchantDisplay($product_item),
                'title' => trim((string) ($raw_item['title'] ?? '')) !== ''
                    ? trim((string) ($raw_item['title'] ?? ''))
                    : trim((string) ($product_item['title'] ?? '')),
                'chips' => $chips,
                'current_price' => $current_price,
                'old_price' => $old_price,
                'has_link' => trim((string) ($product_item['url'] ?? '')) !== '',
                'logo_url' => trim((string) ($product_item['logo'] ?? '')),
            ];
        }

        usort($offers, [self::class, 'compareOffersByPrice']);

        foreach ($offers as $index => &$offer) {
            $offer['rank'] = $index + 1;
            $offer['rank_display'] = str_pad((string) $offer['rank'], 2, '0', STR_PAD_LEFT);
            $offer['is_best'] = $index === 0;
        }
        unset($offer);

        $heading_tag_raw = (string) ($attributes['heading_tag'] ?? 'h2');
        $heading_tag = in_array($heading_tag_raw, self::ALLOWED_HEADING_TAGS, true) ? $heading_tag_raw : 'h2';

        return [
            'variant' => $variant,
            'section_label' => trim((string) ($attributes['section_label'] ?? 'Where to buy')),
            'title' => trim((string) ($attributes['title'] ?? '')),
            'heading_tag' => $heading_tag,
            'cta_label' => trim((string) ($attributes['cta_label'] ?? '')),
            'footer_note' => trim((string) ($attributes['footer_note'] ?? '')),
            'offers' => $offers,
            'offers_count_label' => self::buildOffersCountLabel(count($offers)),
            'amazon_update_html' => self::captureAmazonUpdate($items_by_module),
        ];
    }

    public static function resolveCtaLabel(array $payload, ?array $product_item = null): string
    {
        if ($payload['cta_label'] !== '') {
            return $payload['cta_label'];
        }

        if (!empty($product_item)) {
            $ce_label = trim((string) TemplateHelper::buyNowBtnText(false, $product_item));
            if ($ce_label !== '') {
                return $ce_label;
            }
        }

        return $payload['variant'] === 'table-compact' ? 'Buy' : 'View offer';
    }

    private static function resolveMerchantDisplay(array $product_item): string
    {
        $merchant = trim((string) ($product_item['merchant'] ?? ''));
        if ($merchant !== '') {
            return $merchant;
        }

        return trim((string) ($product_item['domain'] ?? $product_item['module_id'] ?? ''));
    }

    private static function normalizePriceValue($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $numeric = (float) $value;
        return $numeric > 0 ? $numeric : null;
    }

    private static function compareOffersByPrice(array $left, array $right): int
    {
        $left_price = $left['current_price'];
        $right_price = $right['current_price'];

        if ($left_price === null && $right_price === null) {
            return 0;
        }

        if ($left_price === null) {
            return 1;
        }

        if ($right_price === null) {
            return -1;
        }

        return $left_price <=> $right_price;
    }

    private static function buildOffersCountLabel(int $count): string
    {
        return Translator::__('Offers:') . ' ' . $count;
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
