<?php

namespace ContentEgg\application\EggBlocks\blocks\productcard;

use ContentEgg\application\EggBlocks\blocks\productcard\variants\CompactVariant;
use ContentEgg\application\EggBlocks\blocks\productcard\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\blocks\productcard\variants\FeaturedVariant;
use ContentEgg\application\EggBlocks\shared\CeProductResolver;
use ContentEgg\application\EggBlocks\shared\EggbSchemaCollector;
use ContentEgg\application\EggBlocks\shared\EggbSanitizer;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;
use ContentEgg\application\helpers\TemplateHelper;

defined('ABSPATH') || exit;

class ProductCardRenderer
{
    use RendersWithTheme;

    private const ALLOWED_HEADING_TAGS = ['h1', 'h2', 'h3', 'h4', 'div'];

    public static function render(array $attributes): string
    {
        $variant = self::normalizeVariant($attributes['variant'] ?? 'default');
        $card = self::normalizeCard($attributes, $variant);

        if (empty($card['product_item']))
        {
            if (!self::isEditorPreview())
            {
                return '';
            }

            if (!self::hasAuthoredContent($card))
            {
                return '';
            }
        }

        $theme_class = self::resolveThemeClass('auto');
        $color_scheme = self::resolveColorScheme($attributes['color_scheme'] ?? 'auto', $theme_class);
        $data_theme = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';

        ob_start();

        switch ($variant)
        {
            case 'featured':
                FeaturedVariant::render($card, $theme_class, $data_theme);
                break;
            case 'compact':
                CompactVariant::render($card, $theme_class, $data_theme);
                break;
            default:
                DefaultVariant::render($card, $theme_class, $data_theme);
                break;
        }

        $html = (string) ob_get_clean();

        if (!empty($attributes['enable_schema']))
        {
            self::registerSchema($card);
        }

        return $html;
    }

    private static function normalizeVariant($variant): string
    {
        $variant = (string) $variant;

        if (!in_array($variant, ['default', 'featured', 'compact'], true))
        {
            return 'default';
        }

        return $variant;
    }

    private static function isEditorPreview(): bool
    {
        if (!defined('REST_REQUEST') || !REST_REQUEST)
        {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return isset($_GET['context']) && $_GET['context'] === 'edit';
    }

    private static function hasAuthoredContent(array $card): bool
    {
        if ($card['title'] !== '' || $card['subtitle'] !== '' || $card['description'] !== '')
        {
            return true;
        }

        if (!empty($card['chips']))
        {
            return true;
        }

        if ($card['badge'] !== '' || $card['score'] !== '')
        {
            return true;
        }

        return false;
    }

    private static function normalizeCard(array $attributes, string $variant): array
    {
        $chips = [];
        if (!empty($attributes['chips']) && is_array($attributes['chips']))
        {
            foreach ($attributes['chips'] as $chip)
            {
                $chip = trim((string) $chip);
                if ($chip !== '')
                {
                    $chips[] = $chip;
                }
            }
        }

        $rank = absint($attributes['rank'] ?? 0);
        $score = trim((string) ($attributes['score'] ?? ''));
        $score_value = '';
        if ($score !== '' && is_numeric($score) && (float) $score > 0)
        {
            $score_value = number_format((float) $score, 1);
        }

        $product_item = CeProductResolver::resolve(
            is_array($attributes['product_ref'] ?? null) ? $attributes['product_ref'] : []
        );

        $description = self::sanitizeRichText((string) ($attributes['description'] ?? ''));
        $featured_text = $description;

        $cta_label = trim((string) ($attributes['cta_label'] ?? ''));
        if ($cta_label === '' && !empty($product_item))
        {
            $cta_label = trim((string) TemplateHelper::buyNowBtnText(false, $product_item));
        }
        if ($cta_label === '')
        {
            $cta_label = 'Check price';
        }

        $title = trim((string) ($attributes['title'] ?? ''));
        if ($title === '' && !empty($product_item['title']))
        {
            $title = trim((string) $product_item['title']);
        }

        $heading_tag_raw = (string) ($attributes['heading_tag'] ?? 'h2');
        $heading_tag = in_array($heading_tag_raw, self::ALLOWED_HEADING_TAGS, true) ? $heading_tag_raw : 'h2';

        return [
            'variant' => $variant,
            'section_label' => trim((string) ($attributes['section_label'] ?? '')),
            'block_title' => trim((string) ($attributes['block_title'] ?? '')),
            'heading_tag' => $heading_tag,
            'product_ref' => is_array($attributes['product_ref'] ?? null) ? $attributes['product_ref'] : [],
            'product_item' => $product_item,
            'title' => $title,
            'rank' => $rank,
            'has_rank' => $rank > 0,
            'rank_display' => $rank > 0 ? str_pad((string) $rank, 2, '0', STR_PAD_LEFT) : '',
            'badge' => trim((string) ($attributes['badge'] ?? '')),
            'score' => $score_value,
            'score_denom' => '/ 10',
            'subtitle' => self::sanitizeRichText((string) ($attributes['subtitle'] ?? '')),
            'chips' => $chips,
            'description' => $description,
            'featured_text' => $featured_text,
            'merchant' => trim((string) ($attributes['merchant'] ?? '')),
            'cta_label' => $cta_label,
        ];
    }

    private static function sanitizeRichText(string $value): string
    {
        $value = trim($value);
        if ($value === '')
        {
            return '';
        }

        return EggbSanitizer::basicRichText($value);
    }

    private static function registerSchema(array $card): void
    {
        $productRef = $card['product_ref'] ?? [];
        if (empty($productRef['module_id']) || empty($productRef['unique_id']))
        {
            return;
        }

        $item = $card['product_item'];
        if (empty($item))
        {
            return;
        }

        $data = [
            'name'        => $card['title'],
            'description' => wp_strip_all_tags((string) ($card['description'] ?? '')),
            'image'       => (string) ($item['img'] ?? ''),
            'score'       => $card['score'],
            'url'         => (string) ($item['url'] ?? ''),
            'merchant'    => $card['merchant'],
            'price'       => (string) ($item['price'] ?? ''),
            'currency'    => (string) ($item['currencyCode'] ?? ''),
        ];

        EggbSchemaCollector::addProduct($productRef, $data);
    }
}
