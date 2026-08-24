<?php

namespace ContentEgg\application\EggBlocks\blocks\verdict;

use ContentEgg\application\EggBlocks\blocks\verdict\variants\CompactVariant;
use ContentEgg\application\EggBlocks\blocks\verdict\variants\DefaultVariant;
use ContentEgg\application\EggBlocks\blocks\verdict\variants\SummaryVariant;
use ContentEgg\application\EggBlocks\shared\CeProductResolver;
use ContentEgg\application\EggBlocks\shared\EggbSanitizer;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;
use ContentEgg\application\helpers\TemplateHelper;

use function ContentEgg\prnx;

defined('ABSPATH') || exit;

class VerdictRenderer
{
    use RendersWithTheme;

    public static function render(array $attributes): string
    {
        $variant = self::normalizeVariant($attributes['variant'] ?? 'default');
        $resolved = self::normalizeAttributes($attributes);

        if (
            $resolved['score'] === ''
            && $resolved['title'] === ''
            && $resolved['award_label'] === ''
            && empty($resolved['chips'])
            && $resolved['verdict_text'] === ''
            && !$resolved['has_cta_target']
        )
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
            case 'compact':
                CompactVariant::render($resolved, $theme_class, $data_theme);
                break;
            case 'summary':
                SummaryVariant::render($resolved, $theme_class, $data_theme);
                break;
            default:
                DefaultVariant::render($resolved, $theme_class, $data_theme);
                break;
        }

        return (string) ob_get_clean();
    }

    private static function normalizeVariant($variant): string
    {
        $variant = (string) $variant;
        if (!in_array($variant, ['default', 'compact', 'summary'], true))
        {
            return 'default';
        }

        return $variant;
    }

    private static function normalizeAttributes(array $attributes): array
    {
        $fallback_url = esc_url_raw((string) ($attributes['cta_url'] ?? ''));
        $resolved_product = CeProductResolver::resolve(
            is_array($attributes['product_ref'] ?? null) ? $attributes['product_ref'] : []
        );
        $has_cta_target = !empty($resolved_product) || $fallback_url !== '';
        $custom_cta_label = trim((string) ($attributes['cta_label'] ?? ''));
        $resolved_cta_label = $custom_cta_label;

        if ($resolved_cta_label === '' && !empty($resolved_product))
        {
            $resolved_cta_label = trim((string) TemplateHelper::buyNowBtnText(false, $resolved_product));
        }

        if ($resolved_cta_label === '')
        {
            $resolved_cta_label = 'Check price';
        }

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

        return [
            'score' => trim((string) ($attributes['score'] ?? '')),
            'score_denom' => trim((string) ($attributes['score_denom'] ?? '/ 10')),
            'score_label' => trim((string) ($attributes['score_label'] ?? 'Score')),
            // Blank title falls back to the bound product, as product-card and
            // quick-picks do — the block owns its copy, the product fills gaps.
            'title' => trim((string) ($attributes['title'] ?? '')) !== ''
                ? trim((string) $attributes['title'])
                : trim((string) ($resolved_product['title'] ?? '')),
            'award_label' => trim((string) ($attributes['award_label'] ?? '')),
            'chips' => $chips,
            'product_item' => $resolved_product,
            'verdict_text' => EggbSanitizer::basicRichText((string) ($attributes['verdict_text'] ?? '')),
            'cta_label' => $resolved_cta_label,
            'cta_url' => $fallback_url,
            'has_cta_target' => $has_cta_target,
            'band_label' => trim((string) ($attributes['band_label'] ?? 'Our Verdict')),
        ];
    }
}
