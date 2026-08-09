<?php

namespace ContentEgg\application\blocks\couponblock;

use ContentEgg\application\Plugin;
use ContentEgg\application\components\BlockTemplateManager;
use ContentEgg\application\components\ModuleManager;

defined('\ABSPATH') || exit;

/**
 * CouponBlock class file
 *
 * The coupon counterpart of ProductBlock: a lean Gutenberg block that serializes
 * to the type-generic [content-egg-block] shortcode. Coupons have no product-only
 * controls (price cols, currency, link target, …), so the attribute set is a
 * subset. Renders every active COUPON module aggregated, or a chosen subset.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class CouponBlock
{
    const DEFAULT_TEMPLATE = 'coupons_ticket';

    public static function initAction()
    {
        self::registerBlock();
        add_action('enqueue_block_editor_assets', array(__CLASS__, 'enqueueBlockAssets'));
    }

    public static function getAttributes()
    {
        return array(
            '_refresh' => array('type' => 'integer', 'default' => 0),
            'template' => array('type' => 'string', 'default' => ''),
            'color_mode' => array('type' => 'string', 'default' => ''),
            'limit' => array('type' => 'integer'),
            'offset' => array('type' => 'integer'),
            'next' => array('type' => 'integer'),
            'selection_mode' => array('type' => 'string', 'default' => ''),
            'chosen_coupons' => array('type' => 'string', 'default' => ''),
            'modules' => array('type' => 'array', 'default' => array()),
            'exclude_modules' => array('type' => 'array', 'default' => array()),
            'groups' => array('type' => 'array', 'default' => array()),
            'hide' => array('type' => 'array', 'default' => array()),
            'visible' => array('type' => 'array', 'default' => array()),
            'btn_variant' => array('type' => 'string', 'default' => ''),
            'start_number' => array('type' => 'integer'),
            'post_id' => array('type' => 'integer', 'default' => 0),
            'async' => array('type' => 'boolean', 'default' => false),
            'lazy' => array('type' => 'boolean', 'default' => false),
        );
    }

    public static function registerBlock()
    {
        register_block_type('content-egg/coupons', array(
            'editor_script'   => 'content-egg-coupons-editor',
            'style'           => array('cegg-bootstrap5', 'cegg-products'),
            'render_callback' => array(__CLASS__, 'renderShortcode'),
            'attributes'      => self::getAttributes(),
        ));
    }

    public static function enqueueBlockAssets()
    {
        // The block source accesses wp-blocks/wp-block-editor/etc. as `wp.*`
        // globals, so wp-scripts cannot list them in block.asset.php — declare
        // them explicitly, merged with the deps wp-scripts DID detect.
        $asset_file = __DIR__ . '/block.asset.php';
        $asset = file_exists($asset_file) ? require $asset_file : array('dependencies' => array(), 'version' => false);
        $base_deps = array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-block-editor', 'wp-components');
        $deps = array_values(array_unique(array_merge($base_deps, (array) $asset['dependencies'])));

        wp_register_script(
            'content-egg-coupons-editor',
            plugins_url('block.js', __FILE__),
            $deps,
            $asset['version']
        );

        // Shared Inspector control styles (.cegg-components-label, …), same handle
        // as the metabox/products block so it de-dupes.
        \wp_enqueue_style('contentegg-admin', \ContentEgg\PLUGIN_RES . '/css/admin.css', array(), Plugin::version());

        $tpl_manager = BlockTemplateManager::getInstance();
        $coupon_templates = $tpl_manager->getTemplatesByModuleType('COUPON');
        $formatted_templates = array();
        foreach ($coupon_templates as $key => $value)
        {
            $formatted_templates[] = array(
                'value' => $key,
                'label' => $value,
                'preview' => BlockTemplateManager::isPreviewAvailable($key) ? $key . '.webp' : '',
                'is_custom' => BlockTemplateManager::isCustomTemplate($key),
            );
        }

        // Coupon modules only (the block never shows product modules).
        $coupon_module_ids = ModuleManager::getInstance()->getParserModuleIdsByTypes('COUPON', true);
        $modules = array();
        foreach ($coupon_module_ids as $module_id)
        {
            $info = ModuleManager::factory($module_id)->info();
            $modules[] = array('value' => $module_id, 'label' => !empty($info['name']) ? $info['name'] : $module_id);
        }

        wp_localize_script(
            'content-egg-coupons-editor',
            'contentEggCouponsBlockData',
            array(
                'imagesBaseUrl' => \ContentEgg\PLUGIN_DIR_URL . '/templates/preview/',
                'modules' => $modules,
                'templates' => $formatted_templates,
                'defaultTemplate' => self::DEFAULT_TEMPLATE,
            )
        );
    }

    /**
     * Compose the [content-egg-block …] shortcode string from block attributes.
     * Pure (no do_shortcode / WP template lookups) so it unit-tests in isolation.
     *
     * - Choose mode ("coupons") maps `chosen_coupons` → the generic composite
     *   `products="Module:uid,…"` filter and drops the module/group filters; with
     *   nothing chosen it returns '' (an empty products filter would otherwise
     *   fall back to showing ALL coupons).
     * - Filter mode emits modules/groups/exclude_modules and ignores chosen.
     * - An empty template defaults to the coupon template (never empty).
     */
    public static function buildShortcodeString(array $attributes)
    {
        $template = isset($attributes['template']) ? (string) $attributes['template'] : '';
        // Module settings store file-prefixed names ("data_coupons"); block ids
        // are unprefixed. Normalize so a copied settings value still works.
        if (strpos($template, 'data_') === 0)
            $template = substr($template, strlen('data_'));
        if ($template === '')
            $template = self::DEFAULT_TEMPLATE;
        $attributes['template'] = $template;

        $selection_mode = isset($attributes['selection_mode']) ? (string) $attributes['selection_mode'] : '';
        if ($selection_mode === 'coupons')
        {
            $chosen = isset($attributes['chosen_coupons']) ? trim((string) $attributes['chosen_coupons']) : '';
            if ($chosen === '')
                return '';

            $attributes['products'] = $chosen;
            unset($attributes['groups'], $attributes['modules'], $attributes['exclude_modules']);
        }
        unset($attributes['selection_mode'], $attributes['chosen_coupons']);

        foreach ($attributes as $key => $value)
        {
            if (is_array($value))
            {
                $value = array_map('sanitize_text_field', $value);
                $attributes[$key] = join(',', $value);
            }
            else
            {
                $attributes[$key] = sanitize_text_field((string) $value);
            }
        }

        if (isset($attributes['async']))
            $attributes['async'] = !empty($attributes['async']) ? '1' : '';
        if (isset($attributes['lazy']))
            $attributes['lazy'] = !empty($attributes['lazy']) ? '1' : '';

        $shortcode = '[content-egg-block';
        foreach ($attributes as $key => $value)
        {
            if ($value !== '' && $value !== null)
                $shortcode .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }
        $shortcode .= ']';

        return $shortcode;
    }

    public static function renderShortcode($attributes)
    {
        $attributes = (array) $attributes;

        // Safety net: the editor only offers coupon templates, but coerce any
        // non-coupon template id to the default so this block can never render a
        // product template.
        $template = isset($attributes['template']) ? (string) $attributes['template'] : '';
        if (strpos($template, 'data_') === 0)
            $template = substr($template, strlen('data_'));
        if ($template !== '')
        {
            $coupon_templates = BlockTemplateManager::getInstance()->getTemplatesByModuleType('COUPON');
            if (!isset($coupon_templates[$template]))
                $template = self::DEFAULT_TEMPLATE;
        }
        $attributes['template'] = $template;

        $is_editor = defined('REST_REQUEST') && REST_REQUEST;
        if ($is_editor && $template && BlockTemplateManager::isCustomTemplate($template))
            return '<div><small>' . esc_html__('Preview is not available for custom/theme templates.', 'content-egg') . '</small></div>';

        $shortcode = self::buildShortcodeString($attributes);

        return $shortcode === '' ? '' : do_shortcode($shortcode);
    }
}
