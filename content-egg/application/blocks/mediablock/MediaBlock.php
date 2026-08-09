<?php

namespace ContentEgg\application\blocks\mediablock;

use ContentEgg\application\Plugin;
use ContentEgg\application\components\BlockTemplateManager;
use ContentEgg\application\components\ModuleManager;

defined('\ABSPATH') || exit;

/**
 * MediaBlock class file
 *
 * Registers the two media blocks — content-egg/images and content-egg/videos —
 * from a single editor bundle. Each serializes to the type-generic
 * [content-egg-block] shortcode (Module Types: IMAGE / VIDEO). Filter mode only:
 * media items are not in the editor product snapshot, so there is no per-item
 * Choose picker.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class MediaBlock
{
    const DEFAULT_IMAGE_TEMPLATE = 'images';
    const DEFAULT_VIDEO_TEMPLATE = 'videos_stacked';

    public static function initAction()
    {
        self::registerBlocks();
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
            'modules' => array('type' => 'array', 'default' => array()),
            'exclude_modules' => array('type' => 'array', 'default' => array()),
            'groups' => array('type' => 'array', 'default' => array()),
            // Item-ID binding: composite "module:uid,…" refs. When set, the block
            // renders exactly these items (set by sidebar drag-and-drop), not a filter.
            'products' => array('type' => 'string', 'default' => ''),
            'hide' => array('type' => 'array', 'default' => array()),
            'visible' => array('type' => 'array', 'default' => array()),
            'cols' => array('type' => 'integer'),
            'post_id' => array('type' => 'integer', 'default' => 0),
            'async' => array('type' => 'boolean', 'default' => false),
            'lazy' => array('type' => 'boolean', 'default' => false),
        );
    }

    public static function registerBlocks()
    {
        register_block_type('content-egg/images', array(
            'editor_script'   => 'content-egg-media-editor',
            'style'           => array('cegg-bootstrap5', 'cegg-products'),
            'render_callback' => array(__CLASS__, 'renderImages'),
            'attributes'      => self::getAttributes(),
        ));
        register_block_type('content-egg/videos', array(
            'editor_script'   => 'content-egg-media-editor',
            'style'           => array('cegg-bootstrap5', 'cegg-products'),
            'render_callback' => array(__CLASS__, 'renderVideos'),
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
            'content-egg-media-editor',
            plugins_url('block.js', __FILE__),
            $deps,
            $asset['version']
        );

        // Shared Inspector control styles (.cegg-components-label, …), same handle
        // as the metabox/products block so it de-dupes.
        \wp_enqueue_style('contentegg-admin', \ContentEgg\PLUGIN_RES . '/css/admin.css', array(), Plugin::version());

        $tpl_manager = BlockTemplateManager::getInstance();

        wp_localize_script(
            'content-egg-media-editor',
            'contentEggMediaBlockData',
            array(
                'imagesBaseUrl' => \ContentEgg\PLUGIN_DIR_URL . '/templates/preview/',
                'imageModules' => self::formatModules('IMAGE'),
                'videoModules' => self::formatModules('VIDEO'),
                'imageTemplates' => self::formatTemplates($tpl_manager->getTemplatesByModuleType('IMAGE')),
                'videoTemplates' => self::formatTemplates($tpl_manager->getTemplatesByModuleType('VIDEO')),
                'defaultImageTemplate' => self::DEFAULT_IMAGE_TEMPLATE,
                'defaultVideoTemplate' => self::DEFAULT_VIDEO_TEMPLATE,
            )
        );
    }

    private static function formatModules($type)
    {
        $ids = ModuleManager::getInstance()->getParserModuleIdsByTypes($type, true);
        $modules = array();
        foreach ($ids as $module_id)
        {
            $info = ModuleManager::factory($module_id)->info();
            $modules[] = array('value' => $module_id, 'label' => !empty($info['name']) ? $info['name'] : $module_id);
        }
        return $modules;
    }

    private static function formatTemplates(array $templates)
    {
        $formatted = array();
        foreach ($templates as $key => $value)
        {
            $formatted[] = array(
                'value' => $key,
                'label' => $value,
                'preview' => BlockTemplateManager::isPreviewAvailable($key) ? $key . '.webp' : '',
                'is_custom' => BlockTemplateManager::isCustomTemplate($key),
            );
        }
        return $formatted;
    }

    /**
     * Compose the [content-egg-block …] shortcode from block attributes.
     * Pure (no do_shortcode / WP template lookups) so it unit-tests in isolation.
     * An empty template defaults to $defaultTemplate (images/videos), never empty.
     */
    public static function buildShortcodeString(array $attributes, $defaultTemplate)
    {
        $template = isset($attributes['template']) ? (string) $attributes['template'] : '';
        // Module settings store file-prefixed names ("data_images"); block ids
        // are unprefixed. Normalize so a copied settings value still works.
        if (strpos($template, 'data_') === 0)
            $template = substr($template, strlen('data_'));
        if ($template === '')
            $template = $defaultTemplate;
        $attributes['template'] = $template;

        // Item-ID binding: when `products` (composite module:uid refs) is set the
        // block renders exactly those items via the generic ProductBindingFilter,
        // so the family filters would only narrow it — drop them. Empty → filter mode.
        $products = isset($attributes['products']) ? trim((string) $attributes['products']) : '';
        if ($products !== '')
            unset($attributes['modules'], $attributes['exclude_modules'], $attributes['groups']);
        else
            unset($attributes['products']);

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

    public static function renderImages($attributes)
    {
        return self::render((array) $attributes, 'IMAGE', self::DEFAULT_IMAGE_TEMPLATE);
    }

    public static function renderVideos($attributes)
    {
        return self::render((array) $attributes, 'VIDEO', self::DEFAULT_VIDEO_TEMPLATE);
    }

    private static function render(array $attributes, $type, $defaultTemplate)
    {
        // Safety net: coerce any non-matching template id to this block's default
        // so an images block can never render a video/product template.
        $template = isset($attributes['template']) ? (string) $attributes['template'] : '';
        if (strpos($template, 'data_') === 0)
            $template = substr($template, strlen('data_'));
        if ($template !== '')
        {
            $allowed = BlockTemplateManager::getInstance()->getTemplatesByModuleType($type);
            if (!isset($allowed[$template]))
                $template = $defaultTemplate;
        }
        $attributes['template'] = $template;

        $is_editor = defined('REST_REQUEST') && REST_REQUEST;
        if ($is_editor && $template && BlockTemplateManager::isCustomTemplate($template))
            return '<div><small>' . esc_html__('Preview is not available for custom/theme templates.', 'content-egg') . '</small></div>';

        $shortcode = self::buildShortcodeString($attributes, $defaultTemplate);

        return $shortcode === '' ? '' : do_shortcode($shortcode);
    }
}
