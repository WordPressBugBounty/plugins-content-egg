<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

use ContentEgg\application\helpers\TextHelper;
use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\helpers\TemplateHelper;
use ContentEgg\application\Plugin;

/**
 * TemplateManager class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
abstract class TemplateManager
{

    private $templates = null;
    private $last_render_data;
    private static $product_style_enqueued = false;
    private static $product_style5_enqueued = false;
    private static $product_style5_enqueued_full = false;

    protected $items = array();
    protected $params = array();
    protected $item;
    protected $current_i;
    protected $coupons = array();
    protected $coupon_strip_done = false;

    /** item key => built card rows, so item_row can ask more than once. */
    protected $card_coupons = array();

    /** coupon id => true, for coupons already said inside a row. */
    protected $coupons_in_row = array();

    abstract public function getTempatePrefix();

    abstract public function getTempateDir();

    abstract public function getCustomTempateDirs();

    public function getTemplatesList($short_mode = false, $exclude_custom = false)
    {
        $prefix = $this->getTempatePrefix();
        $this->templates = null;
        if ($this->templates === null)
        {
            $templates = array();
            foreach ($this->getCustomTempateDirs() as $custom_name => $dir)
            {
                $templates = array_merge($templates, $this->scanTemplates($dir, $prefix, $custom_name));
            }
            $templates = array_merge($this->scanTemplates($this->getTempateDir(), $prefix, false), $templates);
            $this->templates = $templates;
        }

        $all = $this->templates;

        if ($exclude_custom)
        {
            $all = array_filter($all, function ($key)
            {
                return !self::isCustomTemplate($key);
            }, ARRAY_FILTER_USE_KEY);
        }
        else
        {
            // Sort to move custom templates to the top
            uasort($all, function ($a, $b)
            {
                $isCustomA = strpos($a, '[custom]') !== false;
                $isCustomB = strpos($b, '[custom]') !== false;

                if ($isCustomA === $isCustomB)
                    return 0;

                return $isCustomA ? -1 : 1;
            });
        }

        if ($short_mode)
        {
            $list = array();
            foreach ($all as $id => $name)
            {
                $custom = '';
                if (self::isCustomTemplate($id))
                {
                    $parts = explode('/', $id);
                    $custom = 'custom/';
                    $id = $parts[1];
                }

                // del prefix
                $list[$custom . substr($id, strlen($prefix))] = $name;
            }

            return $list;
        }

        return $all;
    }

    private function scanTemplates($path, $prefix, $custom_name = false)
    {
        if ($custom_name && !is_dir($path))
        {
            return array();
        }

        $tpl_files = glob($path . '/' . $prefix . '*.php');
        if (!$tpl_files)
        {
            return array();
        }

        $templates = array();
        foreach ($tpl_files as $file)
        {
            $template_id = basename($file, '.php');
            if ($custom_name)
            {
                $template_id = 'custom/' . $template_id;
            }

            $data = \get_file_data($file, array('name' => 'Name'));
            if ($data && !empty($data['name']))
            {
                $templates[$template_id] = sanitize_text_field($data['name']);
            }
            else
            {
                $templates[$template_id] = $template_id;
            }
            if ($custom_name)
            {
                $templates[$template_id] .= ' [' . esc_attr(__($custom_name, 'content-egg')) . ']';
            }
        }

        asort($templates, SORT_STRING);

        return $templates;
    }

    public function render($view_name, array $_data = array())
    {
        $file = $this->getViewPath($view_name);
        if (!$file)
            return '';

        $this->last_render_data = $_data;
        extract($_data, EXTR_PREFIX_SAME, 'data');

        ob_start();
        ob_implicit_flush(false);

        include $file;
        $content = ob_get_clean();
        $content = trim($content);

        if (!self::isCustomTemplate($view_name))
        {
            $this->enqueueCeggStyle();

            if ($view_name != 'block_customizable')
            {

                $class = self::generateContainerClassName($view_name);
                $content = '<div class="cegg5-container ' . esc_attr($class) . '">' . $content . '</div>';
            }
        }

        return $content;
    }

    public function renderPartial($view_name, array $_data = array())
    {
        $file = $this->getPartialViewPath($view_name, false);

        if (!$file)
            return '';

        $this->renderPath($file, $_data);
    }

    public function renderBlock($view_name, array $data = array())
    {
        if (!isset($data['item']))
            $data['item'] = $this->item;

        if (!isset($data['items']))
            $data['items'] = $this->items;

        if (!isset($data['params']))
            $data['params'] = $this->params;

        if (!isset($data['i']))
            $data['i'] = $this->current_i;

        // Before the disclaimer, not after the whole block - see
        // renderCouponStrip(). Runs even when the disclaimer itself renders
        // nothing, because the strip does not depend on it.
        if ($view_name === 'disclaimer')
            $this->renderCouponStrip();

        $file = $this->getPartialViewPath($view_name, true);

        if (!$file)
            return '';

        $this->renderPath($file, $data);
    }

    protected function renderPath($view_path, $_data = array())
    {
        if (!is_file($view_path) || !is_readable($view_path))
        {
            throw new \Exception(
                sprintf(
                    esc_html__('View file "%s" does not exist.', 'content-egg'),
                    esc_html($view_path)
                )
            );
        }

        $_data = array_merge($this->last_render_data, $_data);
        extract($_data, EXTR_PREFIX_SAME, 'data');
        include $view_path;
    }

    public function getPartialViewPath($view_name, $block = false)
    {
        $view_name = str_replace('.', '', $view_name);
        $file = \ContentEgg\PLUGIN_PATH . 'application/templates/';
        if ($block)
        {
            $file .= 'blocks/';
        }
        else
        {
            $file .= $this->getTempatePrefix();
        }
        $file .= TextHelper::clear($view_name) . '.php';
        if (is_file($file) && is_readable($file))
        {
            return $file;
        }
        else
        {
            return false;
        }
    }

    public function getViewPath($view_name)
    {
        $view_name = str_replace('.', '', $view_name);
        if (self::isCustomTemplate($view_name))
        {
            $view_name = substr($view_name, 7);
            foreach ($this->getCustomTempateDirs() as $custom_prefix => $custom_dir)
            {
                $tpl_path = $custom_dir;
                $file = trailingslashit($tpl_path) . TextHelper::clear($view_name) . '.php';
                if (is_file($file) && is_readable($file))
                {
                    return $file;
                }
            }

            return false;
        }
        else
        {
            $tpl_path = $this->getTempateDir();
            $file = trailingslashit($tpl_path) . TextHelper::clear($view_name) . '.php';
            if (is_file($file) && is_readable($file))
            {
                return $file;
            }
            else
            {
                return false;
            }
        }
    }

    public function getFullTemplateId($short_id)
    {
        $prefix = $this->getTempatePrefix();
        $custom = '';
        if (self::isCustomTemplate($short_id))
        {
            $parts = explode('/', $short_id);
            $custom = 'custom/';
            $id = $parts[1];
        }
        else
        {
            $id = $short_id;
        }

        // check _data prefix
        if (substr($id, 0, strlen($prefix)) != $prefix)
        {
            $id = $prefix . $id;
        }

        return $custom . $id;
    }

    public static function isCustomTemplate($template_id)
    {
        if (substr($template_id, 0, 7) == 'custom/')
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    public function isTemplateExists($tpl)
    {
        return array_key_exists($tpl, $this->getTemplatesList());
    }

    public function prepareShortcodeTempate($template)
    {
        if (self::isCustomTemplate($template))
        {
            $is_custom = true;
            // del 'custom/' prefix
            $template = substr($template, 7);
        }
        else
        {
            $is_custom = false;
        }

        $template = TextHelper::clear($template);
        if ($is_custom)
        {
            $template = 'custom/' . $template;
        }
        if ($template)
        {
            $template = $this->getFullTemplateId($template);
        }

        return $template;
    }

    public function enqueueCeggStyle($full = false)
    {
        if (!is_admin() && self::$product_style5_enqueued_full)
        {
            return;
        }
        elseif (!is_admin() && !$full && self::$product_style5_enqueued)
        {
            return;
        }

        if ($full)
        {
            \wp_enqueue_style('cegg-bootstrap5-full');
            self::$product_style5_enqueued_full = true;
        }
        else
        {
            \wp_enqueue_style('cegg-bootstrap5');
            self::$product_style5_enqueued = true;
        }

        \wp_enqueue_style('cegg-products');

        if ($css = self::getVariantCss())
        {
            \wp_add_inline_style('cegg-products', $css);
        }
    }

    private static function getPrimaryColorBackwardCompatibility()
    {
        $activation_date = \get_option(Plugin::slug . '_first_activation_date', false);
        if ($activation_date && $activation_date < strtotime('09/15/2024'))
        {
            $color = GeneralConfig::getInstance()->option('button_color');
            if ($color !== '#d9534f')
                return $color;
        }

        return false;
    }

    public static function getVariantCss()
    {
        $color_mode = GeneralConfig::getInstance()->option('color_mode');
        $st = new StyleVariant($color_mode);

        $css = '';
        foreach (self::getColorVariants() as $variant)
        {
            if (!$backround = GeneralConfig::getInstance()->option($variant . '_color'))
            {
                if ($variant != 'primary' || !$backround = self::getPrimaryColorBackwardCompatibility())
                    continue;
            }

            $st->setVariant($variant, $backround);
            $css .= esc_html($st->generateVariantCss());
        }

        return $css;
    }

    public static function getColorVariants()
    {
        return array('primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark');
    }

    /**
     * Deprecated
     */
    public function enqueueProductsStyle()
    {
        if (self::$product_style_enqueued)
            return;

        \wp_enqueue_style('egg-bootstrap');
        \wp_enqueue_style('egg-products');

        if (GeneralConfig::isShopInfoAvailable())
            \wp_enqueue_script('bootstrap-popover');

        if (!$background = \wp_strip_all_tags(GeneralConfig::getInstance()->option('button_color')))
            $background = '#dc3545';

        if (!$price_color = \wp_strip_all_tags(GeneralConfig::getInstance()->option('price_color')))
            $price_color = '#dc3545';

        $border = TemplateHelper::adjustBrightness($background, -0.05);
        $background_hover = TemplateHelper::adjustBrightness($background, -0.15);
        $border_hover = TemplateHelper::adjustBrightness($background_hover, -0.05);

        $custom_css = '.cegg-price-color {color: ' . $price_color . ';} .egg-container .btn-danger {background-color: ' . $background . ' !important;border-color: ' . $border . ' !important;} .egg-container .btn-danger:hover,.egg-container .btn-danger:focus,.egg-container .btn-danger:active {background-color: ' . $background_hover . ' !important;border-color: ' . $border_hover . ' !important;}';

        \wp_add_inline_style('egg-products', $custom_css);
        self::$product_style_enqueued = true;
    }

    public function setItems(array $items)
    {
        $this->items = $items;
    }

    public function setItem(array $item, $i = null)
    {
        $this->item = $item;
        $this->current_i = $i;
    }

    /**
     * The coupons ShopCoupons::resolve() found for this block, keyed by domain.
     *
     * Also mirrored onto TemplateHelper because isVisible() is static and has
     * no manager to ask - without it the coupons slot short-circuits on the
     * legacy HTML check and a structured coupon never renders.
     */
    public function setCoupons(array $coupons)
    {
        $this->coupons = $coupons;
        $this->coupon_strip_done = false;
        $this->card_coupons = array();
        $this->coupons_in_row = array();
        TemplateHelper::$block_coupons = $coupons;
    }

    /**
     * The attached strip, emitted just before the disclaimer.
     *
     * Appending it to the finished block output put it after the disclaimer,
     * after the price-update line and outside the block's bottom margin, so it
     * read as belonging to the NEXT block. Every template ends by calling
     * renderBlock('disclaimer') - including any a site copied into
     * content-egg-templates/ - which makes that one call the only place this
     * needs to hook.
     */
    public function renderCouponStrip()
    {
        if ($this->coupon_strip_done)
            return;

        $this->coupon_strip_done = true;

        // Below-the-block only. The above-the-block placement is prepended to
        // the finished output in ModuleViewer, where "before the block" is
        // exactly what prepending means - no partial to hook at the top.
        if (ShopCoupons::displayMode($this->params) !== 'attached')
            return;

        echo ShopCoupons::strip($this->coupons, $this->items); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in strip()
    }

    public function couponStripRendered()
    {
        return $this->coupon_strip_done;
    }

    /**
     * The coupons resolved for this item's shop, already gated and ranked.
     */
    public function coupons($item = null)
    {
        if ($item === null)
            $item = $this->item;

        if (!$item || empty($item['domain']))
            return array();

        $d = ShopStore::normalizeDomain($item['domain']);

        if (empty($this->coupons[$d]))
            return array();

        // The resolved map is the union over the block's items, so it can hold a
        // coupon bound to a different product. Re-filter and re-rank for THIS
        // row, then apply the per-item limit.
        $out = array();
        foreach ($this->coupons[$d] as $c)
        {
            if (ShopCoupon::matchesProduct($c, $item))
                $out[] = $c;
        }

        if (!$out)
            return array();

        // postTerms() memoizes, so this costs one query per request, not one per
        // row.
        $out = ShopCoupon::sort($out, ShopCoupons::postTerms(), $item);

        $limit = ShopCoupons::limitFor($this->params);

        return $limit > 0 ? array_slice($out, 0, $limit) : $out;
    }

    /**
     * The one coupon a row should show, or null.
     */
    public function coupon($item = null)
    {
        $coupons = $this->coupons($item);

        return $coupons ? $coupons[0] : null;
    }

    /**
     * The coupons this row renders as cards INSIDE itself.
     *
     * Two placements end up here - a coupon bound to this product in the
     * inline placement, and the lone coupon of a single-product block in the
     * cards placement. See ShopCoupons::inRowCoupons() for why those two and
     * nothing else.
     *
     * Whatever this returns is recorded as spoken for, and ModuleViewer drops
     * it from the cards below the block. Rendering here without that said the
     * same coupon twice on one page.
     */
    public function cardCoupons($item = null)
    {
        if ($item === null)
            $item = $this->item;

        if (!$item || empty($item['domain']))
            return array();

        // item_row asks twice - once to decide whether a chip belongs, once to
        // render - and building the rows twice would also mark them consumed
        // twice.
        $key = (isset($item['module_id']) ? (string) $item['module_id'] : '') . ':'
            . (isset($item['unique_id']) ? (string) $item['unique_id'] : '');

        if (isset($this->card_coupons[$key]))
            return $this->card_coupons[$key];

        $picked = ShopCoupons::inRowCoupons(
            $this->coupons($item),
            ShopCoupons::displayMode($this->params),
            count($this->items)
        );

        if (!$picked)
            return $this->card_coupons[$key] = array();

        $domain = ShopStore::normalizeDomain($item['domain']);

        // Named after the shop, not the product: the product's title is the
        // heading immediately above this card.
        $rows = ShopCoupons::cardRows(array($domain => $picked), array($item), $this->params, false);

        // The card sits in a column beside the product image, not across the
        // page. At full size its minimum width is wider than that column can
        // give, and the row wraps - the product image ends up above the text
        // instead of beside it.
        $own_button = $this->isVisible('button');

        foreach ($rows as $i => $row)
        {
            $rows[$i]['compact'] = true;

            $c = $row['coupon'];

            // A code-less deal with no Link of its own resolves to this
            // product's URL - the same place the row's own button already
            // points, a few pixels above it. Two buttons, one destination.
            //
            // Both exceptions keep their button: a coupon with a CODE needs
            // one, because Show Code reveals and copies (which the product
            // button cannot do), and a coupon with an explicit Link goes
            // somewhere the product button does not. So does a row that hides
            // its own button, or dropping this one would leave nothing to
            // click at all.
            $rows[$i]['hide_button'] = $own_button
                && trim((string) $c['code']) === ''
                && trim((string) $c['link']) === '';
        }

        foreach ($picked as $c)
        {
            if (!empty($c['id']))
                $this->coupons_in_row[(string) $c['id']] = true;
        }

        return $this->card_coupons[$key] = $rows;
    }

    /**
     * Coupon ids already rendered inside a row, so the cards below the block
     * can leave them out.
     */
    public function couponsRenderedInRow()
    {
        return array_keys($this->coupons_in_row);
    }

    /**
     * Kept for templates copied into content-egg-templates/ before cardCoupons
     * existed. Those keep the behaviour they were written against.
     */
    public function boundCoupons($item = null)
    {
        if (ShopCoupons::displayMode($this->params) !== 'inline')
            return array();

        return $this->cardCoupons($item);
    }

    /**
     * The coupon this row should render as a chip, or null. Combines the
     * visibility rules with the pick so a template is one call, not three.
     */
    public function couponChip($item = null)
    {
        if ($item === null)
            $item = $this->item;

        if (!$item || !TemplateHelper::isCouponChipVisible($item, $this->params))
            return null;

        return $this->coupon($item);
    }

    public function setParams(array $params)
    {
        $this->params = $params;
    }

    public function getParams()
    {
        return $this->params;
    }

    static function generateContainerClassName($view_name)
    {
        $class = 'cegg-';
        $class .= str_replace('block_', '', $view_name);
        return $class;
    }

    public function isVisible($field, $default = true)
    {
        if (!$this->item)
            return false;

        return TemplateHelper::isVisible($this->item, $field, $this->params, $this->items, $default);
    }

    public function isHide($field, $default = false)
    {
        return !$this->isHide($this->item, $field, $this->params, !$default);
    }

    public function isVisibleDisclaimerOrPriceUpdate()
    {
        return TemplateHelper::isVisibleDisclaimerOrPriceUpdate($this->items, $this->params);
    }

    public function colorMode()
    {
        TemplateHelper::colorMode($this->params);
    }
}
