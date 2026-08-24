<?php

namespace ContentEgg\application\admin;

defined('\ABSPATH') || exit;

use ContentEgg\application\Plugin;
use ContentEgg\application\components\ShopCoupon;
use ContentEgg\application\components\ShopScan;
use ContentEgg\application\components\ShopStore;
use ContentEgg\application\components\ShopProductRef;

/**
 * ShopsController class file
 *
 * A list page plus a hidden edit page, following AutoblogController - the shape
 * the rest of this admin already uses.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ShopsController
{
    const SLUG = 'content-egg-shops';
    const SLUG_EDIT = 'content-egg-shops-edit';

    private static $instance = null;

    const WARNINGS_TRANSIENT = 'cegg_shop_binding_warnings_';

    // Carry a rejected submission from handleSave() (load-, before the header)
    // to actionEdit() (the render callback), so the form comes back with the
    // operator's own values and the reason it was refused.
    private $save_error = '';
    private $save_shop = null;

    public static function getInstance()
    {
        if (self::$instance === null)
            self::$instance = new self;

        return self::$instance;
    }

    private function __construct()
    {
        \add_action('admin_menu', array($this, 'add_admin_menu'));

        // The edit screen is a submenu with no parent, so WordPress cannot work
        // out which menu entry it belongs to and highlights nothing. Naming the
        // parent and the submenu explicitly keeps Content Egg > Shops selected
        // while a shop is open.
        \add_filter('parent_file', array($this, 'highlightMenu'));
        \add_filter('submenu_file', array($this, 'highlightSubmenu'));
    }

    public function highlightMenu($parent_file)
    {
        return self::isEditScreen() ? Plugin::slug : $parent_file;
    }

    public function highlightSubmenu($submenu_file)
    {
        return self::isEditScreen() ? self::SLUG : $submenu_file;
    }

    private static function isEditScreen()
    {
        if (empty($GLOBALS['pagenow']) || $GLOBALS['pagenow'] !== 'admin.php')
            return false;

        return isset($_GET['page']) && \sanitize_key(\wp_unslash($_GET['page'])) === self::SLUG_EDIT;
    }

    public function add_admin_menu()
    {
        \add_submenu_page(
            Plugin::slug,
            __('Shops', 'content-egg') . ' &lsaquo; Content Egg',
            __('Shops', 'content-egg'),
            'manage_options',
            self::SLUG,
            array($this, 'actionIndex')
        );

        $hook = \add_submenu_page(
            '',
            __('Edit shop', 'content-egg') . ' &lsaquo; Content Egg',
            __('Edit shop', 'content-egg'),
            'manage_options',
            self::SLUG_EDIT,
            array($this, 'actionEdit')
        );

        // A submenu registered with no parent leaves get_admin_page_parent()
        // empty, so get_admin_page_title() searches $menu instead of $submenu,
        // never finds this page, and leaves $title null - which
        // admin-header.php then passes straight to strip_tags(). load- fires
        // before the header, so setting it here is both the fix and the only
        // way this page gets a browser-tab title at all.
        if ($hook)
        {
            \add_action('load-' . $hook, array($this, 'setEditTitle'));

            // Saving has to happen here too, not in actionEdit(): the page
            // callback runs after admin-header.php, so a redirect from there
            // cannot send Location once the header has outgrown PHP's output
            // buffer.
            \add_action('load-' . $hook, array($this, 'handleSave'));
        }
    }

    public static function tabUrl($tab = '')
    {
        $url = \admin_url('admin.php?page=' . self::SLUG);

        return $tab === '' ? $url : \add_query_arg('tab', $tab, $url);
    }

    public static function currentTab()
    {
        return (isset($_GET['tab']) && \sanitize_key(\wp_unslash($_GET['tab'])) === 'coupons') ? 'coupons' : 'shops';
    }

    public function setEditTitle()
    {
        $domain = isset($_GET['domain']) ? \sanitize_text_field(\wp_unslash($_GET['domain'])) : '';

        $GLOBALS['title'] = $domain !== ''
            ? __('Edit shop', 'content-egg')
            : __('Add shop', 'content-egg');
    }

    public function actionIndex()
    {
        if (self::currentTab() === 'coupons')
            return $this->actionCoupons();

        $message = '';

        if (!empty($_GET['action']) && $_GET['action'] === 'scan')
        {
            \check_admin_referer('cegg_shop_scan');

            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : ShopScan::DEFAULT_LIMIT;
            $scan = ShopScan::run($limit);

            $message = sprintf(
                /* translators: 1: number of shops found, 2: number of product rows read */
                __('Found %1$s shops in your %2$s most recent products.', 'content-egg'),
                \number_format_i18n(count($scan['domains'])),
                \number_format_i18n($scan['scanned'])
            );
        }

        if (!empty($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['domain']))
        {
            \check_admin_referer('cegg_shop_delete');
            ShopStore::delete(\sanitize_text_field(\wp_unslash($_GET['domain'])));
            $message = __('Shop deleted.', 'content-egg');
        }

        if (!empty($_GET['message']))
        {
            $messages = array(
                'saved' => __('Shop saved.', 'content-egg'),
                'deleted' => __('Shop deleted.', 'content-egg'),
            );
            $key = \sanitize_key(\wp_unslash($_GET['message']));
            if (isset($messages[$key]))
                $message = $messages[$key];
        }

        $table = new ShopsTable();
        $table->prepare_items();

        PluginAdmin::getInstance()->render('shops_index', array('table' => $table, 'message' => $message));
    }

    /**
     * Runs on load-, before admin-header.php, so the success path can redirect.
     * A refusal is carried into actionEdit() and rendered there.
     */
    public function handleSave()
    {
        if (empty($_POST['cegg_shop_nonce']) || !\wp_verify_nonce(\sanitize_key($_POST['cegg_shop_nonce']), 'cegg_shop_save'))
            return;

        $submitted = isset($_POST['shop']) && is_array($_POST['shop']) ? \wp_unslash($_POST['shop']) : array();
        $previous = isset($_POST['previous_domain']) ? \sanitize_text_field(\wp_unslash($_POST['previous_domain'])) : '';

        $prepared = self::prepareSubmission($submitted);

        if (!$prepared)
        {
            $this->save_error = \esc_html__('Enter a shop domain, for example thalia.de.', 'content-egg');
            $this->save_shop = self::rehydrate($submitted);

            return;
        }

        if (ShopStore::collides(ShopStore::all(), $prepared['domain'], $previous))
        {
            // Refuse rather than upsert. save() would replace the existing
            // record wholesale - its logo, its description and every coupon
            // on it - and the operator would have no way to know, because
            // "www.amazon.com" and "amazon.com" are the same shop here.
            $existing = \admin_url('admin.php?page=' . self::SLUG_EDIT . '&domain=' . rawurlencode($prepared['domain']));

            $this->save_error = sprintf(
                /* translators: 1: shop domain, 2: opening link tag, 3: closing link tag */
                \esc_html__('A shop for %1$s already exists. %2$sEdit that shop%3$s instead — nothing here has been saved.', 'content-egg'),
                '<strong>' . \esc_html($prepared['domain']) . '</strong>',
                '<a href="' . \esc_url($existing) . '">',
                '</a>'
            );

            $this->save_shop = self::rehydrate($submitted);

            return;
        }

        $saved = ShopStore::save($prepared, $previous);

        // Survives the redirect that turns the POST into a GET, so a
        // reload does not resubmit the form.
        if ($warnings = self::bindingWarnings($submitted, $prepared['domain']))
            \set_transient(self::WARNINGS_TRANSIENT . \get_current_user_id(), $warnings, MINUTE_IN_SECONDS);

        \wp_safe_redirect(\admin_url('admin.php?page=' . self::SLUG_EDIT . '&domain=' . rawurlencode($saved) . '&message=saved'));
        exit;
    }

    public function actionEdit()
    {
        $message = '';
        $error = '';

        $domain = isset($_GET['domain']) ? \sanitize_text_field(\wp_unslash($_GET['domain'])) : '';
        $shop = $domain !== '' ? ShopStore::get($domain) : null;

        if (!$shop)
        {
            // A domain that is not a shop yet still prefills the form - that is
            // how "Add" works on a shop the scan found.
            $shop = ShopStore::sanitizeShop(array('domain' => $domain !== '' ? $domain : 'example.com'));

            if (!$shop)
                $shop = ShopStore::sanitizeShop(array('domain' => 'example.com'));

            if ($domain === '')
                $shop['domain'] = '';
        }

        // handleSave() ran on load-, before any output. It only gets this far
        // when it refused the submission.
        if ($this->save_error !== '')
        {
            $error = $this->save_error;
            $shop = $this->save_shop;
        }

        if (!empty($_GET['message']) && \sanitize_key(\wp_unslash($_GET['message'])) === 'saved')
            $message = __('Shop saved.', 'content-egg');

        $warnings = \get_transient(self::WARNINGS_TRANSIENT . \get_current_user_id());

        if ($warnings)
            \delete_transient(self::WARNINGS_TRANSIENT . \get_current_user_id());
        else
            $warnings = array();

        \wp_enqueue_media();

        PluginAdmin::getInstance()->render('shops_edit', array(
            'shop' => $shop,
            'previous_domain' => $domain,
            'message' => $message,
            'error' => $error,
            'warnings' => $warnings,
        ));
    }

    public function actionCoupons()
    {
        $table = new CouponsTable();
        $table->prepare_items();

        PluginAdmin::getInstance()->render('shops_coupons', array('table' => $table, 'message' => ''));
    }

    /**
     * A submitted form into a shop array, or array() when its domain is
     * unusable.
     *
     * Dates arrive as Y-m-d from <input type="date">, meaning that date in the
     * operator's own timezone. get_gmt_from_date() moves it to UTC, and an end
     * date then runs through endOfDay() so "ends Aug 31" survives all of Aug 31.
     */
    /**
     * Warnings for a submission that is saved anyway.
     *
     * A malformed paste and a cross-shop product are the only two mistakes this
     * feature can make that produce NO output and NO error: resolution is keyed
     * on the shop's domain, so a coupon bound to another shop's product simply
     * never renders. Both are detectable here for free - the pasted payload
     * carries the product's own URL - so they are reported rather than
     * swallowed.
     *
     * Never blocks the save: the operator may be pasting a reference for a
     * product they are about to import.
     */
    public static function bindingWarnings(array $submitted, $domain)
    {
        $out = array();

        if (empty($submitted['coupons']) || !is_array($submitted['coupons']))
            return $out;

        foreach ($submitted['coupons'] as $row)
        {
            if (!is_array($row) || empty($row['products']))
                continue;

            $raw = (string) $row['products'];

            $label = !empty($row['code'])
                ? $row['code']
                : (isset($row['title']) ? $row['title'] : '');
            $label = \sanitize_text_field($label);

            if (!ShopProductRef::parse($raw))
            {
                $out[] = sprintf(
                    /* translators: %s: the coupon's code or title */
                    __('Could not read the product reference for "%s". Paste the text copied by "Copy product reference".', 'content-egg'),
                    $label
                );

                continue;
            }

            foreach (ShopProductRef::urls($raw) as $url)
            {
                $host = ShopStore::normalizeDomain($url);

                if ($host === '' || $host === $domain)
                    continue;

                $out[] = sprintf(
                    /* translators: 1: the coupon's code or title, 2: the product's domain, 3: this shop's domain */
                    __('A product bound to "%1$s" is from %2$s, but this shop is %3$s. The coupon will not render.', 'content-egg'),
                    $label,
                    $host,
                    $domain
                );
            }
        }

        return $out;
    }

    public static function prepareSubmission(array $submitted)
    {
        $offset = (int) round((float) \get_option('gmt_offset') * HOUR_IN_SECONDS);

        $coupons = array();

        if (!empty($submitted['coupons']) && is_array($submitted['coupons']))
        {
            foreach ($submitted['coupons'] as $row)
            {
                if (!is_array($row))
                    continue;

                $code = isset($row['code']) ? trim(\sanitize_text_field($row['code'])) : '';
                $title = isset($row['title']) ? trim(\sanitize_text_field($row['title'])) : '';

                // A row with neither a code nor a title is the blank template
                // row, or one the operator emptied to remove it.
                if ($code === '' && $title === '')
                    continue;

                $start = self::dateToTimestamp(isset($row['start']) ? $row['start'] : '');
                $end = self::dateToTimestamp(isset($row['end']) ? $row['end'] : '');

                if ($end)
                    $end = ShopCoupon::endOfDay($end, $offset);

                $coupons[] = array(
                    'id' => isset($row['id']) ? \sanitize_text_field($row['id']) : '',
                    'products' => ShopProductRef::parse(isset($row['products']) ? $row['products'] : ''),
                    'code' => $code,
                    'title' => $title,
                    'description' => isset($row['description']) ? trim(\sanitize_text_field($row['description'])) : '',
                    'discount' => isset($row['discount']) ? trim(\sanitize_text_field($row['discount'])) : '',
                    'link' => isset($row['link']) ? \esc_url_raw(trim($row['link'])) : '',
                    'image' => isset($row['image']) ? \esc_url_raw(trim($row['image'])) : '',
                    'start' => $start,
                    'end' => $end,
                    'terms' => isset($row['terms']) && is_array($row['terms']) ? $row['terms'] : array(),
                    'enabled' => !empty($row['enabled']),
                );
            }
        }

        return ShopStore::sanitizeShop(array(
            'domain' => isset($submitted['domain']) ? \sanitize_text_field($submitted['domain']) : '',
            'name' => isset($submitted['name']) ? \sanitize_text_field($submitted['name']) : '',
            'logo' => isset($submitted['logo']) ? \esc_url_raw(trim($submitted['logo'])) : '',
            'info' => isset($submitted['info']) ? \wp_kses_post($submitted['info']) : '',
            'coupons_html' => isset($submitted['coupons_html']) ? \wp_kses_post($submitted['coupons_html']) : '',
            'coupons' => $coupons,
        ));
    }

    /**
     * A rejected submission redisplayed, so a typo in the domain does not cost
     * the operator every coupon they just typed.
     */
    private static function rehydrate(array $submitted)
    {
        $shop = ShopStore::sanitizeShop(array('domain' => 'example.com'));
        $shop['domain'] = isset($submitted['domain']) ? \sanitize_text_field($submitted['domain']) : '';
        $shop['name'] = isset($submitted['name']) ? \sanitize_text_field($submitted['name']) : '';
        $shop['logo'] = isset($submitted['logo']) ? \esc_url_raw(trim($submitted['logo'])) : '';
        $shop['info'] = isset($submitted['info']) ? \wp_kses_post($submitted['info']) : '';
        $shop['coupons_html'] = isset($submitted['coupons_html']) ? \wp_kses_post($submitted['coupons_html']) : '';

        $shop['coupons'] = array();
        if (!empty($submitted['coupons']) && is_array($submitted['coupons']))
        {
            $offset = (int) round((float) \get_option('gmt_offset') * HOUR_IN_SECONDS);

            foreach ($submitted['coupons'] as $row)
            {
                if (!is_array($row))
                    continue;

                $end = self::dateToTimestamp(isset($row['end']) ? $row['end'] : '');

                $shop['coupons'][] = ShopStore::sanitizeCoupon(array(
                    'id' => isset($row['id']) ? $row['id'] : '',
                    'products' => ShopProductRef::parse(isset($row['products']) ? $row['products'] : ''),
                    'code' => isset($row['code']) ? $row['code'] : '',
                    'title' => isset($row['title']) ? $row['title'] : '',
                    'description' => isset($row['description']) ? $row['description'] : '',
                    'discount' => isset($row['discount']) ? $row['discount'] : '',
                    'link' => isset($row['link']) ? $row['link'] : '',
                    'image' => isset($row['image']) ? $row['image'] : '',
                    'start' => self::dateToTimestamp(isset($row['start']) ? $row['start'] : ''),
                    'end' => $end ? ShopCoupon::endOfDay($end, $offset) : 0,
                    'terms' => isset($row['terms']) && is_array($row['terms']) ? $row['terms'] : array(),
                    'enabled' => !empty($row['enabled']),
                ));
            }
        }

        return $shop;
    }

    public static function dateToTimestamp($value)
    {
        $value = trim((string) $value);

        if ($value === '')
            return 0;

        $ts = \get_gmt_from_date($value . ' 00:00:00', 'U');

        return $ts ? (int) $ts : 0;
    }

    /**
     * A stored UTC timestamp back into the Y-m-d the operator typed. Without
     * the site offset an end date reads back as the previous day, because it is
     * stored as 23:59:59 local expressed in UTC.
     */
    public static function timestampToDate($ts)
    {
        $ts = (int) $ts;

        if (!$ts)
            return '';

        return \get_date_from_gmt(\gmdate('Y-m-d H:i:s', $ts), 'Y-m-d');
    }

    /**
     * Every coupon on the site as a flat row carrying its shop.
     *
     * The recurring job is not editing shops - it is adding a code that dies in
     * three days and seeing what is live or expiring without opening twenty
     * shops one at a time.
     */
    public static function allCouponRows($now, $types)
    {
        $rows = array();

        foreach (ShopStore::all() as $domain => $shop)
        {
            if (empty($shop['coupons']))
                continue;

            foreach ($shop['coupons'] as $i => $c)
            {
                $c['_domain'] = $domain;
                $c['_shop_name'] = $shop['name'];
                $c['_index'] = $i;
                $c['_state'] = ShopCoupon::stateOf($c, $now, $types);
                $rows[] = $c;
            }
        }

        return $rows;
    }

    public static function stateLabels()
    {
        return array(
            'live' => __('Live', 'content-egg'),
            'scheduled' => __('Scheduled', 'content-egg'),
            'expired' => __('Expired', 'content-egg'),
            'disabled' => __('Disabled', 'content-egg'),
            'hidden_deal' => __('No code — hidden by the codes-only setting', 'content-egg'),
            'bound' => __('Only on bound products', 'content-egg'),
        );
    }
}
