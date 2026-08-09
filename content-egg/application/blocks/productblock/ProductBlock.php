<?php

namespace ContentEgg\application\blocks\productblock;

use ContentEgg\application\Plugin;
use ContentEgg\application\components\BlockTemplateManager;
use ContentEgg\application\components\ModuleManager;;

defined('\ABSPATH') || exit;

/**
 * ProductBlock class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

class ProductBlock
{
    public static function initAction()
    {
        self::registerBlock();
        add_action('enqueue_block_editor_assets', array(__CLASS__, 'enqueueBlockAssets'));
    }

    public static function getAttributes()
    {
        return array(
            '_refresh' => array(
                'type' => 'integer',
                'default' => 0
            ),
            'template' => array(
                'type' => 'string',
                'default' => ''
            ),
            'color_mode' => array(
                'type' => 'string',
                'default' => ''
            ),
            'limit' => array(
                'type' => 'integer'
            ),
            'offset' => array(
                'type' => 'integer'
            ),
            'next' => array(
                'type' => 'integer'
            ),
            'products' => array(
                'type' => 'string',
                'default' => ''
            ),
            'selection_mode' => array(
                'type' => 'string',
                'default' => ''
            ),
            'chosen_products' => array(
                'type' => 'string',
                'default' => ''
            ),
            'border' => array(
                'type' => 'integer'
            ),
            'btn_variant' => array(
                'type' => 'string',
                'default' => ''
            ),
            'cols' => array(
                'type' => 'integer'
            ),
            'cols_xs' => array(
                'type' => 'integer'
            ),
            'modules' => array(
                'type' => 'array',
                'default' => array()
            ),
            'exclude_modules' => array(
                'type' => 'array',
                'default' => array()
            ),
            'groups' => array(
                'type' => 'array',
                'default' => array()
            ),
            'hide' => array(
                'type' => 'array',
                'default' => array()
            ),
            'visible' => array(
                'type' => 'array',
                'default' => array()
            ),
            'title_tag' => array(
                'type' => 'string',
                'default' => ''
            ),
            'currency' => array(
                'type' => 'string',
                'default' => ''
            ),
            'add_query_arg' => array(
                'type' => 'string',
                'default' => ''
            ),
            'btn_text' => array(
                'type' => 'string',
                'default' => ''
            ),
            'img_ratio' => array(
                'type' => 'string',
                'default' => ''
            ),
            'border_color' => array(
                'type' => 'string',
                'default' => ''
            ),
            'tabs_type' => array(
                'type' => 'string',
                'default' => ''
            ),
            'cols_order' => array(
                'type' => 'string',
                'default' => ''
            ),
            'start_number' => array(
                'type' => 'integer'
            ),
            'link_target' => [
                'type'    => 'string',
                'default' => 'auto',
                'enum'    => ['auto', 'affiliate', 'bridge', 'both'],
            ],
            'post_id' => array(
                'type' => 'integer',
                'default' => 0,
            ),
            'async' => array(
                'type' => 'boolean',
                'default' => false,
            ),
            'lazy' => array(
                'type' => 'boolean',
                'default' => false,
            ),
        );
    }

    public static function registerBlock()
    {
        register_block_type('content-egg/products', array(
            'editor_script'   => 'content-egg-products-editor',
            'style'           => array('cegg-bootstrap5', 'cegg-products'),
            'render_callback' => array(__CLASS__, 'renderShortcode'),
            'attributes'      => self::getAttributes(),
        ));
    }

    public static function enqueueBlockAssets()
    {
        // The block source accesses wp-blocks/wp-block-editor/etc. as `wp.*`
        // globals (not ES imports), so wp-scripts cannot list them in
        // block.asset.php — declare those explicitly. Merge with the deps
        // wp-scripts DID detect from ES imports (e.g. wp-data / wp-api-fetch
        // pulled in by the shared product picker) so the picker works at runtime.
        $asset_file = __DIR__ . '/block.asset.php';
        $asset = file_exists($asset_file) ? require $asset_file : array('dependencies' => array(), 'version' => false);
        $base_deps = array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-block-editor', 'wp-components');
        $deps = array_values(array_unique(array_merge($base_deps, (array) $asset['dependencies'])));

        wp_register_script(
            'content-egg-products-editor',
            plugins_url('block.js', __FILE__),
            $deps,
            $asset['version']
        );

        // The block's Inspector controls rely on .cegg-components-label /
        // .cegg-control-separator from admin.css. That stylesheet is otherwise only
        // enqueued by the legacy metabox, so subheaders (e.g. "Visible Elements")
        // render unstyled on editor screens where the metabox is absent (the new
        // sidebar/workspace presentation modes). Enqueue it here so the block is
        // self-styled everywhere; same handle as the metabox path, so it de-dupes.
        \wp_enqueue_style('contentegg-admin', \ContentEgg\PLUGIN_RES . '/css/admin.css', array(), Plugin::version());

        $modules = ModuleManager::getInstance()->getAffiliateParsersList(true);

        $tpl_manager = BlockTemplateManager::getInstance();
        // Only product templates — never coupon/image/video templates that ride
        // the same block pipeline but belong to their own family blocks. Allowlist
        // (untyped or PRODUCT-typed), so new media families can't leak in.
        $templates = $tpl_manager->getProductTemplates();
        $formatted_templates = array();

        foreach ($templates as $key => $value)
        {
            if ($key == 'customizable')
                continue;

            if (BlockTemplateManager::isPreviewAvailable($key))
                $preview = $key . '.webp';
            else
                $preview = '';

            $formatted_templates[] = array(
                'value' => __($key, 'content-egg'),
                'label' => $value,
                'preview' => $preview,
                'is_custom' => BlockTemplateManager::isCustomTemplate($key),
            );
        }

        wp_localize_script(
            'content-egg-products-editor',
            'contentEggProductsBlockData',
            array(
                'imagesBaseUrl' => \ContentEgg\PLUGIN_DIR_URL . '/templates/preview/',
                'modules' => $modules,
                'templates' => $formatted_templates,
                // Coupons share the editor product snapshot but must never appear
                // in the product picker — ProductRefsControl excludes these ids.
                'couponModuleIds' => array_values(ModuleManager::getInstance()->getParserModuleIdsByTypes('COUPON', true)),
            )
        );
    }

    public static function renderShortcode($attributes)
    {
        $is_editor = defined('REST_REQUEST') && REST_REQUEST;

        $template = isset($attributes['template']) ? $attributes['template'] : '';

        // Module settings store file-prefixed template names ("data_grid");
        // shortcode/block template ids are unprefixed ("grid"). AI agents and
        // users copying the module-settings value would otherwise get broken
        // output — normalize instead. No block template id starts with "data_".
        if (strpos($template, 'data_') === 0)
        {
            $template = substr($template, strlen('data_'));
            $attributes['template'] = $template;
        }

        if ($is_editor && BlockTemplateManager::isCustomTemplate($template))
        {
            return '<div><small>' . esc_html__('Preview is not available for custom/theme templates.', 'content-egg') . '</small></div>';
        }

        // "Choose products" mode renders exactly the chosen products, which live
        // in their own `chosen_products` attribute (kept separate from the manual
        // Filter-mode `products` list so toggling modes never mixes the two). Map
        // them into the `products=` filter the shortcode understands, and drop the
        // group/module filters so nothing else is ANDed in. In Filter mode the
        // manual `products` filter is emitted as-is and chosen_products is ignored.
        $selection_mode = isset($attributes['selection_mode']) ? $attributes['selection_mode'] : '';
        if ($selection_mode === 'products')
        {
            $chosen = isset($attributes['chosen_products']) ? trim($attributes['chosen_products']) : '';

            // Choose mode shows exactly the chosen products — and only those. With
            // none chosen, render nothing: an empty products filter would otherwise
            // be dropped from the shortcode and fall back to showing ALL products.
            if ($chosen === '')
                return '';

            $attributes['products'] = $chosen;
            unset($attributes['groups'], $attributes['modules'], $attributes['exclude_modules']);
        }

        // Editor-only flags; the shortcode/ModuleViewer layer does not consume them
        // (in Choose mode the chosen ids were just copied into `products`).
        unset($attributes['selection_mode'], $attributes['chosen_products']);

        foreach ($attributes as $key => $value)
        {
            if (is_array($value))
            {
                $attributes[$key] = array_map('sanitize_text_field', $value);
                $attributes[$key] = join(',', $attributes[$key]);
            }
            else
            {
                $attributes[$key] = sanitize_text_field($value);
            }
        }

        if (isset($attributes['async']))
        {
            $attributes['async'] = !empty($attributes['async']) ? '1' : '';
        }
        if (isset($attributes['lazy']))
        {
            $attributes['lazy'] = !empty($attributes['lazy']) ? '1' : '';
        }

        $shortcode = '[content-egg-block';

        foreach ($attributes as $key => $value)
        {
            if ($value || ($key == 'border' && $value == 0))
                $shortcode .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }

        $shortcode .= ']';

        return do_shortcode($shortcode);
    }
}
