<?php

namespace ContentEgg\application\components;

use function ContentEgg\prnx;

defined('\ABSPATH') || exit;

/**
 * ParserModuleConfig abstract class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2025 keywordrush.com
 */
abstract class ParserModuleConfig extends ModuleConfig
{
    public function options()
    {
        $tpl_manager = ModuleTemplateManager::getInstance($this->module_id);
        $options = array(
            'is_active' => array(
                'title' => __('Activate Module', 'content-egg') . ' **',
                'description' => __('Enable', 'content-egg'),
                'callback' => array($this, 'render_checkbox'),
                'default' => 0,
                'section' => 'default',
                'validator' => array(
                    array(
                        'call' => array($this, 'checkRequirements'),
                        'message' => __('Could not activate.', 'content-egg'),
                    ),
                ),
            ),
            'embed_at' => array(
                'title' => __('Auto-embedding', 'content-egg'),
                'callback' => array($this, 'render_dropdown'),
                'dropdown_options' => array(
                    'shortcode' => __('Use shortcodes only', 'content-egg'),
                    'post_top' => __('Embed at the beginning of the post', 'content-egg'),
                    'post_bottom' => __('Embed at the end of the post', 'content-egg'),
                ),
                'default' => 'shortcode',
                'section' => 'default',
            ),
            'priority' => array(
                'title' => __('Priority', 'content-egg'),
                'description' => __('Priority determines the order of modules for auto-embedding in a post, with 0 being the highest priority. This setting also affects price sorting.', 'content-egg'),
                'callback' => array($this, 'render_input'),
                'default' => 10,
                'validator' => array(
                    'trim',
                    'absint',
                ),
                'section' => 'default',
            ),
            'template' => array(
                'title' => __('Template', 'content-egg'),
                'description' => __('Select the module template to be used by default.', 'content-egg'),
                'callback' => array($this, 'render_dropdown'),
                'dropdown_options' => $tpl_manager->getTemplatesList(),
                'default' => $this->getModuleInstance()->defaultTemplateName(),
                'section' => 'default',
            ),
            'tpl_title' => array(
                'title' => __('Title', 'content-egg') . ' ' . __('(deprecated)', 'content-egg'),
                'description' => __('Templates can include the title when displaying data.', 'content-egg'),
                'callback' => array($this, 'render_input'),
                'default' => '',
                'validator' => array(
                    'trim',
                ),
                'section' => 'default',
            ),
            'featured_image' => array(
                'title' => __('Featured Image', 'content-egg'),
                'description' => __('Automatically set the featured image for the post using product images from this module.', 'content-egg'),
                'callback' => array($this, 'render_dropdown'),
                'dropdown_options' => array(
                    '' => __('Do not set', 'content-egg'),
                    'first' => __('First image', 'content-egg'),
                    'second' => __('Second image', 'content-egg'),
                    'rand' => __('Random image', 'content-egg'),
                    'last' => __('Last image', 'content-egg'),
                ),
                'default' => '',
                'section' => 'default',
            ),
            'set_local_redirect' => array(
                'title' => __('Link Cloaking', 'content-egg'),
                'description' => __('Enable local 301 redirect for links.', 'content-egg'),
                'callback' => array($this, 'render_checkbox'),
                'default' => 0,
                'section' => 'default',
            ),

        );

        if ($this->getModuleInstance()->isClone())
        {
            $options['feed_name'] = array(
                'title' => __('Clone Module Name', 'content-egg') . ' <span class="cegg_required">*</span>',
                'callback' => array($this, 'render_input'),
                'default' => '',
                'validator' => array(
                    'trim',
                    '\sanitize_text_field',
                    array(
                        'call' => array('\ContentEgg\application\helpers\FormValidator', 'required'),
                        'message' => sprintf(__('The field "%s" can not be empty.', 'content-egg'), __('Feed name', 'content-egg')),
                    ),
                    array(
                        'call' => array($this, 'saveModuleName'),
                        'type' => 'filter',
                    ),
                ),
            );
        }

        return array_merge(parent::options(), $options);
    }

    public function checkRequirements($value)
    {
        if ($this->getModuleInstance()->requirements())
            return false;
        else
            return true;
    }

    protected static function moveRequiredUp(array $options)
    {
        uasort($options, function ($a, $b)
        {
            if (strpos($a['title'], '*') !== false && strpos($b['title'], '*') === false)
                return -1;

            if (strpos($a['title'], '*') === false && strpos($b['title'], '*') !== false)
                return 1;

            if (strpos($a['title'], 'deprecated') !== false && strpos($b['title'], 'deprecated') === false)
                return 1;

            if (strpos($a['title'], 'deprecated') === false && strpos($b['title'], 'deprecated') !== false)
                return -1;

            return 0;
        });

        foreach ($options as $key => $option)
        {
            if (strpos($option['title'], '**') !== false)
                $options[$key]['title'] = str_replace('**', '', $option['title']);
        }

        return $options;
    }

    public function applayCustomOptions(array $settings)
    {
        foreach ($settings as $name => $value)
        {
            if (isset($this->option_values[$name]))
                $this->option_values[$name] = $value;
        }
    }

    public function saveModuleName($value)
    {
        ModuleName::getInstance()->saveName($this->getModuleId(), $value);
        return $value;
    }
}
