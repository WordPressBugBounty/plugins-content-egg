<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

/**
 * BlockTemplateManager class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class BlockTemplateManager extends TemplateManager
{
    const TEMPLATE_DIR = 'templates';
    const CUSTOM_TEMPLATE_DIR = 'content-egg-templates';
    const TEMPLATE_PREFIX = 'block_';

    private $module_id;
    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null)
        {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    public function getTempatePrefix()
    {
        return self::TEMPLATE_PREFIX;
    }

    public function getTempateDir()
    {
        return \ContentEgg\PLUGIN_PATH . self::TEMPLATE_DIR;
    }

    public function getCustomTempateDirs()
    {
        $paths = array(
            'child-theme' => \get_stylesheet_directory() . '/' . self::CUSTOM_TEMPLATE_DIR, //child theme
            'theme' => \get_template_directory() . '/' . self::CUSTOM_TEMPLATE_DIR, // theme
            'custom' => \WP_CONTENT_DIR . '/' . self::CUSTOM_TEMPLATE_DIR,
        );

        return \apply_filters('content_egg_block_template_dirs', $paths);
    }

    public function getModuleId()
    {
        return $this->module_id;
    }

    public function getTemplatesList($short_mode = false, $exclude_custom = false)
    {
        $templates = parent::getTemplatesList($short_mode, $exclude_custom);
        $templates = \apply_filters('content_egg_block_templates', $templates);

        return $templates;
    }

    public function getPartialViewPath($view_name, $block = false)
    {
        $file = parent::getPartialViewPath($view_name, $block);
        if ($file)
            return $file;

        // allow render general block templates as partial
        $file = $this->getViewPath($view_name);
        if ($file)
            return $file;
        else
            return false;
    }

    public static function isPreviewAvailable($template_id)
    {
        if (is_file(\ContentEgg\PLUGIN_PATH . 'templates/preview/' . $template_id . '.webp'))
            return true;
        else
            return false;
    }

    /**
     * Whether a template's parsed headers declare support for a module type.
     * Pure (headers in, bool out) so it unit-tests without WordPress. Mirrors
     * how BlockShortcode reads the "Module Types" header.
     */
    public static function templateSupportsType(array $headers, $type)
    {
        if (empty($headers['module_types']))
            return false;

        $types = array_map('trim', explode(',', (string) $headers['module_types']));

        return in_array($type, $types, true);
    }

    /**
     * The "Module Types" header of a single block template ([] when unknown).
     * getTemplatesList() returns SHORT ids ("coupons") but getViewPath() needs the
     * prefixed id ("block_coupons"), so resolve it via getFullTemplateId first.
     */
    public function getTemplateModuleTypesHeaders($template_id)
    {
        $path = $this->getViewPath($this->getFullTemplateId($template_id));
        if (!$path)
            return array();

        return \get_file_data($path, array('module_types' => 'Module Types'));
    }

    /**
     * Block templates ([id => label]) whose "Module Types" header includes
     * $type. Lets each family block list only its own templates — the coupon
     * block shows COUPON templates, the media blocks IMAGE/VIDEO. Family
     * templates always declare their type, so this allowlist is exact.
     */
    public function getTemplatesByModuleType($type)
    {
        $result = array();
        foreach ($this->getTemplatesList(true, true) as $id => $label)
        {
            if (self::templateSupportsType($this->getTemplateModuleTypesHeaders($id), $type))
                $result[$id] = $label;
        }

        return $result;
    }

    /**
     * Block templates ([id => label]) offered by the PRODUCT block. A template
     * belongs to a non-product family ONLY by declaring it in its "Module Types"
     * header (COUPON/IMAGE/VIDEO and any future family); product templates are
     * the historical default and declare no header. So the product picker is:
     * every template that either declares no type OR explicitly lists PRODUCT —
     * i.e. everything NOT scoped to another family.
     *
     * Allowlist by construction: a new media/coupon family that ships typed
     * templates is excluded automatically, with no edit here. (Replaces the old
     * per-family denylist, which leaked IMAGE/VIDEO templates into the picker.)
     *
     * $exclude_custom mirrors getTemplatesList(): the block editor drops custom
     * templates (it can't live-preview them), the shortcode builder keeps them.
     */
    public function getProductTemplates($exclude_custom = true)
    {
        $result = array();
        foreach ($this->getTemplatesList(true, $exclude_custom) as $id => $label)
        {
            $headers = $this->getTemplateModuleTypesHeaders($id);
            if (empty($headers['module_types']) || self::templateSupportsType($headers, 'PRODUCT'))
                $result[$id] = $label;
        }

        return $result;
    }
}
