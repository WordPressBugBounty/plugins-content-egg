<?php

namespace ContentEgg\application\EggBlocks\blocks\variantswitcher;

use ContentEgg\application\components\families\FamilyStore;
use ContentEgg\application\components\families\PriceSummary;
use ContentEgg\application\components\families\VariantFamily;
use ContentEgg\application\components\families\VariantSwitcher;
use ContentEgg\application\EggBlocks\blocks\variantswitcher\variants\ChipsVariant;
use ContentEgg\application\EggBlocks\blocks\variantswitcher\variants\ListVariant;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;
use ContentEgg\application\helpers\TemplateHelper;

defined('ABSPATH') || exit;

class VariantSwitcherRenderer
{
    use RendersWithTheme;

    const BLOCK = 'eggb/variant-switcher';
    const SHORTCODE = 'content-egg-variants';

    private const STYLES = ['chips', 'list'];

    // Statuses an editor previewing a family page also sees in its switcher.
    const PREVIEW_STATUSES = ['draft', 'pending', 'future', 'private'];

    public static function initShortcode(): void
    {
        add_shortcode(self::SHORTCODE, [self::class, 'shortcode']);
    }

    /**
     * [content-egg-variants style="chips|list"]
     *
     * A shortcode runs inside the content (or a WooCommerce summary), after
     * wp_head, so the block's stylesheets cannot be printed in the head. They
     * are enqueued here and WordPress prints them in the footer. That is a
     * late stylesheet (a brief unstyled flash is possible on a slow connection),
     * the price of a shortcode; the block itself gets its styles in time.
     */
    public static function shortcode($atts = []): string
    {
        $atts = shortcode_atts(['style' => 'chips'], is_array($atts) ? $atts : [], self::SHORTCODE);
        $html = self::render(['variant' => $atts['style']]);
        if ($html === '') {
            return '';
        }

        self::enqueueStyles();

        return self::wrap($html, $atts['style']);
    }

    /**
     * The wrapper the shortcode and the WooCommerce placement print around the
     * switcher (the block builds its own from get_block_wrapper_attributes()):
     * `cegg5-container` scopes the EggBlocks base rules. '' stays ''.
     */
    public static function wrap(string $html, $variant): string
    {
        if ($html === '') {
            return '';
        }

        return '<div class="cegg5-container eggb-variant-switcher-wrap eggb-variant-switcher-wrap--' . esc_attr(self::normalizeVariant($variant)) . '">' . $html . '</div>';
    }

    /**
     * The switcher of a page, or '' when it has nothing to show: the page is
     * not in a family or not published, the family has fewer than two
     * published pages, or no chip row offers a choice. The editor preview
     * prints a short notice instead of nothing (emptyReason()).
     *
     * @param int|null $post_id the current post when null
     */
    public static function render(array $attributes, ?int $post_id = null): string
    {
        $variant = self::normalizeVariant($attributes['variant'] ?? 'chips');
        $is_editor_preview = self::isEditorPreview();

        if ($post_id === null) {
            $post_id = self::currentPostId();
        }

        $family_id = FamilyStore::familyIdOf($post_id);
        $family = $family_id > 0 ? FamilyStore::get($family_id) : null;
        if ($family === null || empty($family['dimensions'])) {
            return $is_editor_preview ? self::notice(self::emptyReason(false, false, true, '')) : '';
        }

        $preview = self::isFrontPreview($post_id);
        $members = self::members($family, $family_id, $preview);
        $model = $variant === 'list'
            ? VariantSwitcher::rows($family, $members, $post_id)
            : VariantSwitcher::chips($family, $members, $post_id);

        if (!$model) {
            return $is_editor_preview ? self::notice(self::emptyReason(true, false, get_post_status($post_id) === 'publish', '', (string) get_post_field('post_password', $post_id) !== '')) : '';
        }

        $theme_class = self::resolveThemeClass('auto');
        $color_scheme = self::resolveColorScheme((string) ($attributes['color_scheme'] ?? 'auto'), $theme_class);
        $data_theme = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';

        self::loadVariants();

        ob_start();
        if ($variant === 'list') {
            ListVariant::render($model, $theme_class, $data_theme);
        } else {
            ChipsVariant::render($model, $theme_class, $data_theme);
        }
        $html = (string) ob_get_clean();

        // The chips emitter hides a row that offers no choice (R5), which can leave nothing.
        if ($is_editor_preview && trim($html) === '') {
            return self::notice(self::emptyReason(true, true, true, $html));
        }

        if ($preview && trim($html) !== '' && self::hasUnpublished($members)) {
            $html = self::notice('preview_unpublished') . $html;
        }

        return $html;
    }

    /**
     * Pure: why the switcher shows nothing, '' when it shows something.
     * no_family: the page is not in a family (or the family is unusable);
     * unpublished: the page itself is not published, so it is no member yet;
     * password: the page is published with a password, so it is no member;
     * too_few: no other published page to switch to;
     * nothing_to_switch: a model was built but no row of it was printed.
     */
    public static function emptyReason(bool $has_family, bool $has_model, bool $page_published, string $html, bool $password = false): string
    {
        if (!$has_family) {
            return 'no_family';
        }
        if ($has_model) {
            return trim($html) === '' ? 'nothing_to_switch' : '';
        }

        if (!$page_published) {
            return 'unpublished';
        }

        return $password ? 'password' : 'too_few';
    }

    /**
     * The editor notice for an emptyReason(), '' for none.
     */
    public static function notice(string $reason): string
    {
        switch ($reason) {
            case 'no_family':
                $text = __('This page is not in a variant family yet.', 'content-egg');
                break;
            case 'unpublished':
                $text = __('This page is not published yet. The variant switcher appears once this page is published.', 'content-egg');
                break;
            case 'password':
                $text = __('This page is password-protected; the switcher shows only on public pages.', 'content-egg');
                break;
            case 'too_few':
                $text = __('This family has fewer than two published pages, so no switcher is shown yet.', 'content-egg');
                break;
            case 'preview_unpublished':
                $text = __('Preview: this switcher includes unpublished pages of the family. Visitors see only published pages, and no switcher until two of them are published.', 'content-egg');
                break;
            case 'nothing_to_switch':
                $text = __('No other variant can be reached from this page with chips, so no switcher is shown. The list style links every published page of the family.', 'content-egg');
                break;
            default:
                return '';
        }

        return '<div class="eggb-block eggb-variant-switcher-notice">' . esc_html($text) . '</div>';
    }

    /**
     * The price line of a chip or row: "from 729 EUR" for a sibling, the
     * plain price for the current page, "Out of stock", or '' when there is
     * nothing to say. Shared by both emitters; returns HTML (a span).
     */
    public static function priceLine(?array $summary, bool $current): string
    {
        if ($summary === null) {
            return '';
        }

        if (isset($summary['in_stock']) && !$summary['in_stock']) {
            return '<span class="eggb-vs-price eggb-vs-price--out">' . esc_html(__('Out of stock', 'content-egg-tpl')) . '</span>';
        }

        if (!isset($summary['price']) || !is_numeric($summary['price'])) {
            return '';
        }

        $price = (string) TemplateHelper::formatPriceCurrency((float) $summary['price'], (string) ($summary['currency'] ?? ''));
        if ($price === '') {
            return '';
        }

        /* translators: %s: the lowest price of a product variant, with its currency, such as "$729.00". */
        $text = $current ? $price : sprintf(__('from %s', 'content-egg-tpl'), $price);

        return '<span class="eggb-vs-price">' . esc_html($text) . '</span>';
    }

    public static function normalizeVariant($variant): string
    {
        $variant = (string) $variant;

        return in_array($variant, self::STYLES, true) ? $variant : 'chips';
    }

    private static function isEditorPreview(): bool
    {
        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return isset($_GET['context']) && $_GET['context'] === 'edit';
    }

    private static function currentPostId(): int
    {
        $id = (int) get_the_ID();

        return $id > 0 ? $id : (int) get_queried_object_id();
    }

    /**
     * A frontend preview (?preview=true) by someone who may edit the page:
     * the switcher then also shows the family's unpublished pages.
     */
    private static function isFrontPreview(int $post_id): bool
    {
        return function_exists('is_preview') && is_preview() && $post_id > 0 && current_user_can('edit_post', $post_id);
    }

    /**
     * Pure: the members an editor's preview shows. Unpublished pages
     * (PREVIEW_STATUSES) the viewer may edit count as published and link to
     * their preview; the rest are left out, trash included.
     *
     * @param array    $members     FamilyStore::members($id, false) rows.
     * @param callable $can_edit    function (int $post_id): bool
     * @param callable $preview_url function (int $post_id): string
     */
    public static function previewMembers(array $members, callable $can_edit, callable $preview_url): array
    {
        $out = [];
        foreach ($members as $member) {
            $status = (string) ($member['status'] ?? '');
            if ($status === 'publish') {
                $out[] = $member;
                continue;
            }
            $post_id = (int) ($member['post_id'] ?? 0);
            if (!in_array($status, self::PREVIEW_STATUSES, true) || $post_id <= 0 || !$can_edit($post_id)) {
                continue;
            }
            $member['status'] = 'publish';
            $member['unpublished'] = true;
            $member['url'] = (string) $preview_url($post_id);
            $out[] = $member;
        }

        return $out;
    }

    private static function hasUnpublished(array $members): bool
    {
        foreach ($members as $member) {
            if (!empty($member['unpublished'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * FamilyStore::members() rows as the switcher takes them. A member whose
     * passport no longer fits the family is left out. In an editor's
     * preview the unpublished pages are in too (previewMembers()).
     */
    private static function members(array $family, int $family_id, bool $preview = false): array
    {
        $rows = FamilyStore::members($family_id, !$preview);
        if ($preview) {
            $rows = self::previewMembers(
                $rows,
                static function (int $id): bool {
                    return current_user_can('edit_post', $id);
                },
                static function (int $id): string {
                    return (string) get_preview_post_link($id);
                }
            );
        }

        $out = [];
        foreach ($rows as $member) {
            try {
                $values = VariantFamily::memberValues($family, (array) $member['variant']);
            } catch (\InvalidArgumentException $e) {
                continue;
            }

            $out[] = [
                'post_id' => (int) $member['post_id'],
                'url' => (string) $member['url'],
                'title' => (string) $member['title'],
                'status' => (string) $member['status'],
                'unpublished' => !empty($member['unpublished']),
                'values' => $values,
                'summary' => self::shownSummary((int) $member['post_id']),
            ];
        }

        return $out;
    }

    /**
     * The price summary a member shows. A WooCommerce product shows the price
     * its product page shows: the synced price, in the store currency.
     */
    private static function shownSummary(int $post_id): ?array
    {
        $summary = PriceSummary::get($post_id);
        if (get_post_type($post_id) !== 'product' || !function_exists('wc_get_product') || !function_exists('get_woocommerce_currency')) {
            return $summary;
        }

        $product = wc_get_product($post_id);

        return self::inStoreCurrency($summary, $product ? $product->get_price() : null, (string) get_woocommerce_currency());
    }

    /**
     * $summary with the WooCommerce price in place of its own price and
     * currency; stock and shop count stay. No usable price leaves it as is.
     */
    public static function inStoreCurrency(?array $summary, $woo_price, string $currency): ?array
    {
        if ($summary === null || !is_numeric($woo_price) || (float) $woo_price <= 0 || $currency === '') {
            return $summary;
        }

        $summary['price'] = (float) $woo_price;
        $summary['currency'] = $currency;

        return $summary;
    }

    /**
     * Enqueue the block's stylesheet (and the theme styles that go with it).
     */
    public static function enqueueStyles(): void
    {
        $type = \WP_Block_Type_Registry::get_instance()->get_registered(self::BLOCK);
        if (!$type) {
            return;
        }

        // EggBlocksLoader::scopeStyles() moves the block's styles from
        // style_handles to view_style_handles (+ the theme styles), so both
        // lists are read.
        $handles = array_merge((array) $type->style_handles, (array) $type->view_style_handles);
        foreach (array_unique($handles) as $handle) {
            wp_enqueue_style($handle);
        }
    }

    private static function loadVariants(): void
    {
        require_once __DIR__ . '/variants/ChipsVariant.php';
        require_once __DIR__ . '/variants/ListVariant.php';
    }
}
