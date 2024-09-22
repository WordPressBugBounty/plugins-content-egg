<?php

namespace ContentEgg\application;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\components\BlockTemplateManager;
use ContentEgg\application\helpers\TextHelper;
use ContentEgg\application\helpers\TemplateHelper;

use function ContentEgg\prn;
use function ContentEgg\prnx;

/**
 * BlockShortcode class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2024 keywordrush.com
 */
class BlockShortcode
{

    const shortcode = 'content-egg-block';

    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new self;
        return self::$instance;
    }

    private function __construct()
    {
        \add_shortcode(self::shortcode, array($this, 'viewDataShortcode'));
    }

    private function prepareAttr($atts)
    {
        $allowed_atts = array(
            'modules' => null,
            'template' => '',
            'post_id' => 0,
            'limit' => 0,
            'offset' => 0,
            'next' => 0,
            'title' => '',
            'cols' => 0,
            'sort' => '',
            'order' => '',
            'currency' => '',
            'groups' => '',
            'group' => '',
            'products' => '',
            'product' => '',
            'hide' => '',
            'show' => '',
            'btn_text' => '',
            'btn_class' => '',
            'locale' => '',
            'ean' => '',
            'add_query_arg' => '',
            'remove_duplicates_by' => '',
        );

        $allowed_atts = \apply_filters('cegg_block_shortcode_atts', $allowed_atts);
        $a = \shortcode_atts($allowed_atts, $atts);

        $a['next'] = (int) $a['next'];
        $a['limit'] = (int) $a['limit'];
        $a['offset'] = (int) $a['offset'];
        $a['cols'] = (int) $a['cols'];
        $a['title'] = \sanitize_text_field($a['title']);
        $a['currency'] = strtoupper(TextHelper::clear($a['currency']));
        $a['groups'] = \sanitize_text_field($a['groups']);
        $a['group'] = \sanitize_text_field($a['group']);
        $a['hide'] = TemplateHelper::hideParamPrepare($a['hide']);
        $a['show'] = strtolower(sanitize_text_field($a['show']));
        $a['btn_text'] = \wp_strip_all_tags($a['btn_text'], true);
        $a['btn_class'] = \sanitize_text_field($a['btn_class']);
        $a['add_query_arg'] = \sanitize_text_field(\wp_strip_all_tags($a['add_query_arg'], true));
        $a['locale'] = TextHelper::clear($a['locale']);
        $a['ean'] = TemplateHelper::eanParamPrepare($a['ean']);
        $a['remove_duplicates_by'] = \sanitize_text_field(\wp_strip_all_tags($a['remove_duplicates_by'], true));

        if ($a['group'] && !$a['groups'])
            $a['groups'] = $a['group'];
        if ($a['groups'])
            $a['groups'] = TextHelper::getArrayFromCommaList($a['groups']);
        if ($a['product'] && !$a['products'])
            $a['products'] = $a['product'];
        if ($a['products'])
            $a['products'] = TextHelper::getArrayFromCommaList($a['products']);
        if ($a['add_query_arg'])
            parse_str($a['add_query_arg'], $a['add_query_arg']);

        $allowed_sort = array('price', 'discount', 'reverse', 'total_price');
        $allowed_order = array('asc', 'desc');
        $a['sort'] = strtolower($a['sort']);
        $a['order'] = strtolower($a['order']);
        if (!in_array($a['sort'], $allowed_sort))
            $a['sort'] = '';
        if (!in_array($a['order'], $allowed_order))
            $a['order'] = '';
        if ($a['sort'] == 'discount' && !$a['order'])
            $a['order'] = 'desc';

        if ($a['modules'])
        {
            $modules = explode(',', $a['modules']);
            $module_ids = array();
            foreach ($modules as $key => $module_id)
            {
                $module_id = trim($module_id);
                if (ModuleManager::getInstance()->isModuleActive($module_id))
                    $module_ids[] = $module_id;
            }
            $a['modules'] = $module_ids;
        }
        else
            $a['modules'] = array();

        if ($a['template'])
        {
            $a['template'] = BlockTemplateManager::getInstance()->prepareShortcodeTempate($a['template']);
        }
        $a['post_id'] = (int) $a['post_id'];
        return $a;
    }

    public function viewDataShortcode($atts, $content = '')
    {
        return $this->viewData($atts, $content);
    }

    public function viewData($atts, $content = '', $only_return_data = false)
    {
        $a = $this->prepareAttr($atts);

        if (empty($a['post_id']))
        {
            global $post;
            if (empty($post))
                return '';

            $post_id = $post->ID;
        }
        else
            $post_id = $a['post_id'];

        if (empty($a['template']))
            return;

        if ($a['template'] != 'block_greenshift')
        {
            $tpl_manager = BlockTemplateManager::getInstance();
            if (!$tpl_manager->isTemplateExists($a['template']))
                return;

            if (!$template_file = $tpl_manager->getViewPath($a['template']))
                return '';

            // Get supported modules for this tpl
            $headers = \get_file_data($template_file, array('module_ids' => 'Modules', 'module_types' => 'Module Types', 'shortcoded' => 'Shortcoded'));
            $supported_module_ids = array();
            if ($headers && !empty($headers['module_ids']))
            {
                $supported_module_ids = explode(',', $headers['module_ids']);
                $supported_module_ids = array_map('trim', $supported_module_ids);
            }
            elseif ($headers && !empty($headers['module_types']))
            {
                $module_types = explode(',', $headers['module_types']);
                $module_types = array_map('trim', $module_types);
                $supported_module_ids = ModuleManager::getInstance()->getParserModuleIdsByTypes($module_types, true);
            }

            if ($headers && !empty($headers['shortcoded']))
                $a['shortcoded'] = filter_var($headers['shortcoded'], FILTER_VALIDATE_BOOLEAN);
        }
        else
        {
            $a['shortcoded'] = true;
            $supported_module_ids = ModuleManager::getInstance()->getParserModuleIdsByTypes('PRODUCT', true);
        }

        if ($a['modules'])
            $module_ids = $a['modules'];
        else
            $module_ids = ModuleManager::getInstance()->getParserModulesIdList(true);

        if ($supported_module_ids)
            $module_ids = array_intersect($module_ids, $supported_module_ids);

        return ModuleViewer::getInstance()->viewBlockData($module_ids, $post_id, $a, $content, $only_return_data);
    }
}
