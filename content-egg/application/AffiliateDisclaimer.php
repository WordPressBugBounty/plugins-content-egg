<?php

namespace ContentEgg\application;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ContentManager;
use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\helpers\TemplateHelper;

/**
 * AffiliateDisclaimer class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class AffiliateDisclaimer
{
    private static $added = false;
    public static function initAction()
    {
        if (GeneralConfig::getInstance()->option('post_disclaimer_position') == 'disabled')
            return;

        add_filter('the_content', array(__CLASS__, 'addAffiliateDisclaimer'));
    }

    public static function addAffiliateDisclaimer($content)
    {
        global $post;

        if (!is_singular('post'))
            return $content;

        // Only act on the genuine, visible main-content render. Other plugins
        // (e.g. Spectra per-post CSS/JS generation, Rank Math schema) invoke
        // the_content out-of-loop; without this guard the one-shot $added flag
        // below is consumed there and the visible content never gets the disclaimer.
        if (!\in_the_loop() || !\is_main_query())
            return $content;

        if (!ContentManager::isProductDataExists($post->ID))
            return $content;

        if (self::$added)
            return $content;

        \wp_enqueue_style('cegg-bootstrap5');

        $position = GeneralConfig::getInstance()->option('post_disclaimer_position');
        $disclaimer_html = self::getDesclaimerHtml();

        if ($position == 'top')
            $content = $disclaimer_html . $content;
        else
            $content = $content . $disclaimer_html;

        self::$added = true;

        return $content;
    }

    public static function getDesclaimerHtml()
    {
        $color_mode = GeneralConfig::getInstance()->option('color_mode');

        $html = '<div class="cegg5-container cegg-post-disclimer"';
        if ($color_mode == 'dark')
            $html .= ' data-bs-theme="dark"';
        $html .= '>';
        $html .= '<div class="alert alert-light small mb-4 p-3 lh-sm" role="alert">';
        $html .= wp_kses_post(TemplateHelper::getPostDisclimerText());
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}
