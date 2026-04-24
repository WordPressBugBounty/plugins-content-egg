<?php

namespace ContentEgg\application;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ContentManager;
use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\components\ModuleTemplateManager;
use ContentEgg\application\components\ShortcodeAtts;
use ContentEgg\application\components\Shortcoded;
use ContentEgg\application\helpers\TextHelper;

/**
 * EggShortcode class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class EggShortcode
{
    const shortcode = 'content-egg';

    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new self;

        return self::$instance;
    }

    private function __construct()
    {
        \add_shortcode(self::shortcode, array($this, 'viewData'));
        \add_filter('term_description', 'shortcode_unautop');
        \add_filter('term_description', 'do_shortcode');
    }

    private function prepareAttr($atts)
    {
        $allowed_atts = array(
            'module' => null,
            'disable_features' => 0,
            'keyword' => '',
            'template' => '',
            'groups' => '',
        );

        $allowed_atts = \apply_filters('cegg_module_shortcode_atts', $allowed_atts);
        $a = \shortcode_atts($allowed_atts, $atts);

        $a['disable_features'] = filter_var($a['disable_features'], FILTER_VALIDATE_BOOLEAN);
        $a['keyword'] = \sanitize_text_field(html_entity_decode($a['keyword']));
        $a['module'] = TextHelper::clear($a['module']);

        if ($a['template'] && $a['module'])
            $a['template'] = ModuleTemplateManager::getInstance($a['module'])->prepareShortcodeTempate($a['template']);
        else
            $a['template'] = '';

        if ($a['keyword'] && !$a['groups'])
        {
            if (strstr($a['keyword'], '->'))
            {

                list($keywords, $groups) = ContentManager::prepareMultipleKeywords($a['keyword']);
                $a['groups'] = $groups;
            }
            else
                $a['groups'] = array($a['keyword']);
        }

        $general = ShortcodeAtts::prepare($atts);
        $a = array_merge($general, $a);

        return $a;
    }

    public function viewData($atts, $content)
    {
        $a = $this->prepareAttr($atts);

        if (empty($a['module']))
            return '';

        $post_id = null;
        if (empty($a['post_id']))
        {
            global $post;
            if (!empty($post))
                $post_id = $post->ID;
        }
        else
            $post_id = $a['post_id'];

        if (!$post_id)
            return '';

        $module_id = $a['module'];
        if (!ModuleManager::getInstance()->isModuleActive($module_id))
            return '';

        // Async placeholder (frontend only; never in editor REST)
        if (!$this->isEditorRenderRequest() && !empty($a['async']))
        {
            return $this->renderAsyncPlaceholder($post_id, $a, $content);
        }

        Shortcoded::getInstance($post_id)->setShortcodedModule($module_id);
        return ModuleViewer::getInstance()->viewModuleData($module_id, $post_id, $a, $content);
    }

    public static function arraySortByColumn(&$arr, $col, $dir = SORT_ASC)
    {
        $sort_col = array();
        foreach ($arr as $key => $row)
        {
            $sort_col[$key] = $row[$col];
        }

        array_multisort($sort_col, $dir, $arr);
    }

    protected function renderAsyncPlaceholder($post_id, array $a, $content = '')
    {
        $html = $this->buildAsyncPlaceholderHtml(
            'cegg-module-',
            'cegg-module',
            'module',
            $post_id,
            $a,
            $content
        );

        return $this->getAsyncInlineCssOnce() . $html;
    }

    protected function buildAsyncPlaceholderHtml($id_prefix, $base_class, $type, $post_id, array $a, $content = '')
    {
        \wp_enqueue_script('cegg-products-view');

        $container_id = \wp_unique_id($id_prefix);
        $endpoint = \rest_url('content-egg/v1/render-blocks');

        // Only include nonce when logged-in to avoid pointless values
        $nonce = \is_user_logged_in() ? \wp_create_nonce('wp_rest') : '';

        $payload = array(
            'type'    => $type,                 // 'block' or 'module'
            'post_id' => (int) $post_id,
            'atts'    => $a,
            'content' => (string) $content,
        );

        $payload_json = esc_attr(\wp_json_encode($payload));

        $html  = '<div'
            . ' id="' . esc_attr($container_id) . '"'
            . ' class="' . esc_attr($base_class) . ' cegg-async-placeholder"'
            . (!empty($a['lazy']) ? ' data-cegg-lazy="1"' : '')
            . ' data-cegg-endpoint="' . esc_url($endpoint) . '"'
            . ($nonce ? ' data-cegg-nonce="' . esc_attr($nonce) . '"' : '')
            . ' data-cegg-payload="' . $payload_json . '"'
            . '></div>';

        $html .= '<noscript>' . esc_html__('Please enable JavaScript to view this content.', 'content-egg') . '</noscript>';

        return $html;
    }

    protected function isEditorRenderRequest()
    {
        return (defined('REST_REQUEST') && REST_REQUEST);
    }

    protected function getAsyncInlineCssOnce()
    {
        static $printed = false;
        if ($printed)
        {
            return '';
        }
        $printed = true;

        $css = '
.cegg-async-placeholder{min-height:80px}
.cegg-async-loading{position:relative}
.cegg-async-skeleton{
  height:80px;border-radius:10px;
  background:linear-gradient(90deg,rgba(0,0,0,.06),rgba(0,0,0,.12),rgba(0,0,0,.06));
  background-size:200% 100%;
  animation:cegg-skeleton 1.2s ease-in-out infinite;
}
@keyframes cegg-skeleton{0%{background-position:200% 0}100%{background-position:-200% 0}}
@media (prefers-reduced-motion: reduce){.cegg-async-skeleton{animation:none}}
.cegg-async-loaded{animation:cegg-fadein 180ms ease-out}
@keyframes cegg-fadein{from{opacity:.01;transform:translateY(2px)}to{opacity:1;transform:translateY(0)}}
.cegg-async-error__inner{
  padding:10px 12px;border-radius:10px;
  border:1px solid rgba(220,53,69,.25);
  background:rgba(220,53,69,.06);
  font-size:14px;
}
.cegg-async-retry{
  margin-left:10px;padding:4px 10px;border-radius:8px;
  border:1px solid rgba(0,0,0,.2);background:#fff;cursor:pointer;
}';

        $css = apply_filters('cegg_async_inline_css', $css);

        return '<style id="cegg-async-inline-css">' . $css . '</style>';
    }
}
