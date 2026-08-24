<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\helpers\TemplateHelper;
use ContentEgg\application\helpers\TextHelper;

/**
 * ShopCoupons class file
 *
 * Resolves the coupons a block should show, once per block.
 *
 * ModuleViewer::viewBlockData() is the only place where the final $items and
 * the post id are both known, so this runs there and rides into the template on
 * the manager. Doing it per row would mean one store read and one source call
 * per merchant in the comparison.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ShopCoupons
{
    private static $post_terms = null;

    /**
     * Enabled, live, right type, right category - then ranked and capped.
     *
     * sort() before dedupe() is load-bearing: dedupe keeps the first
     * occurrence, so the native-wins rule only holds once ranking has run.
     * Pure.
     */
    public static function pick(array $coupons, array $post_terms, $now, $types, $limit, array $item = array())
    {
        $out = array();

        foreach ($coupons as $c)
        {
            if (!ShopCoupon::isLive($c, $now))
                continue;

            // Codes-only exists because a generic code-less deal beside a buy
            // button tells the reader nothing that clicking would not. A coupon
            // bound to THIS product does - "free bag with this camera" is the
            // offer - so it is exempt, the same way cards mode is. Without this
            // the per-product bonus the feature was built for is invisible on a
            // default install, because such promotions rarely carry a code.
            if ($types !== ShopCoupon::TYPES_ALL
                && ShopCoupon::typeOf($c) === ShopCoupon::TYPE_DEAL
                && !ShopCoupon::isBound($c))
                continue;

            if (!ShopCoupon::matchesTerms($c, $post_terms))
                continue;

            // Before the limit, never after: slicing first could discard a
            // bound coupon before its product was known. With no $item the
            // caller has not scoped to a row yet, so bound coupons pass through
            // for it to filter.
            if ($item && !ShopCoupon::matchesProduct($c, $item))
                continue;

            $out[] = $c;
        }

        $out = ShopCoupon::dedupe(ShopCoupon::sort($out, $post_terms, $item));

        $limit = (int) $limit;

        return $limit > 0 ? array_slice($out, 0, $limit) : $out;
    }

    /**
     * Coupons per shop for this block: the shortcode's coupons_limit when it set
     * one, otherwise the Shops setting.
     *
     * !empty, not isset: the shortcode attribute is always present now and
     * defaults to 0, so isset() was always true and every block silently ran
     * unlimited, ignoring the setting.
     */
    public static function limitFor(array $params = array())
    {
        return !empty($params['coupons_limit'])
            ? (int) $params['coupons_limit']
            : (int) GeneralConfig::getInstance()->option('coupons_limit');
    }

    /**
     * The distinct normalized domains in a block's items. Pure.
     */
    public static function domainsFrom(array $items)
    {
        $domains = array();

        foreach ($items as $item)
        {
            if (empty($item['domain']))
                continue;

            $d = ShopStore::normalizeDomain($item['domain']);

            if ($d !== '' && !in_array($d, $domains, true))
                $domains[] = $d;
        }

        return $domains;
    }

    /**
     * The terms of the post the READER is on, plus their ancestors.
     *
     * Not the block's post_id: those differ whenever post_id= or sources= pulls
     * another post's products, and it is the reader's category that reflects
     * intent.
     *
     * Expanding the post's terms UPWARD rather than the coupon's terms downward
     * means a coupon on "Gaming" covers "Gaming > Nintendo" without ever walking
     * an unbounded descendant tree.
     */
    public static function postTerms()
    {
        if (self::$post_terms !== null)
            return self::$post_terms;

        $terms = array();

        if (\is_singular() && ($post_id = (int) \get_queried_object_id()))
        {
            foreach (\get_object_taxonomies(\get_post_type($post_id)) as $tax)
            {
                $ids = \wp_get_post_terms($post_id, $tax, array('fields' => 'ids'));

                if (\is_wp_error($ids))
                    continue;

                foreach ($ids as $id)
                {
                    $terms[] = (int) $id;

                    foreach (\get_ancestors((int) $id, $tax, 'taxonomy') as $ancestor)
                        $terms[] = (int) $ancestor;
                }
            }
        }
        elseif (\is_category() || \is_tax())
        {
            // A category archive IS the category, so a targeted coupon belongs
            // here even though there is no post to read terms from.
            $term = \get_queried_object();

            if ($term && !empty($term->term_id))
            {
                $terms[] = (int) $term->term_id;

                foreach (\get_ancestors((int) $term->term_id, $term->taxonomy, 'taxonomy') as $ancestor)
                    $terms[] = (int) $ancestor;
            }
        }

        self::$post_terms = array_values(array_unique($terms));

        return self::$post_terms;
    }

    /**
     * domain => ordered coupons, for every shop in this block.
     */
    public static function resolve(array $items, array $params = array())
    {
        $config = GeneralConfig::getInstance();

        if (self::displayMode($params) === 'off')
            return array();

        if (!$domains = self::domainsFrom($items))
            return array();

        $post_terms = self::postTerms();
        $now = time();

        // Codes-only exists because a code-less deal beside a buy button adds a
        // line and nothing the reader could not get by clicking. On a card the
        // deal IS the offer, with its own title and button, so the reason does
        // not carry - and on a real imported corpus 89% of coupons have no
        // code, which the setting would otherwise hide by default.
        $types = self::displayMode($params) === 'cards'
            ? ShopCoupon::TYPES_ALL
            : (string) $config->option('coupon_types');

        $limit = self::limitFor($params);

        $by_domain = array();
        foreach ($domains as $d)
        {
            $shop = ShopStore::get($d);
            $by_domain[$d] = $shop && !empty($shop['coupons']) ? $shop['coupons'] : array();
        }

        foreach (self::externalCoupons($domains) as $d => $coupons)
        {
            if (!isset($by_domain[$d]))
                continue;

            foreach ($coupons as $c)
                $by_domain[$d][] = $c;
        }

        $out = array();
        foreach ($by_domain as $d => $coupons)
        {
            if (!$coupons)
                continue;

            // The union over this shop's items, not one pick for the shop: with
            // bindings, two rows of the same shop can legitimately resolve to
            // different coupons. The union can therefore exceed $limit, which is
            // per item - TemplateManager::coupons() re-applies it per row.
            $picked = array();
            $seen = array();

            foreach ($items as $item)
            {
                if (empty($item['domain']) || ShopStore::normalizeDomain($item['domain']) !== $d)
                    continue;

                foreach (self::pick($coupons, $post_terms, $now, $types, $limit, $item) as $c)
                {
                    $key = !empty($c['id']) ? (string) $c['id'] : md5(serialize($c));

                    if (isset($seen[$key]))
                        continue;

                    $seen[$key] = true;
                    $picked[] = $c;
                }
            }

            if ($picked)
                $out[$d] = $picked;
        }

        return $out;
    }

    /**
     * Coupons from any registered source, keyed by normalized domain.
     *
     * The filter takes the WHOLE domain list, not one domain: a ten-shop
     * comparison must cost a source one `WHERE domain IN (…)` query, not ten.
     *
     * NOT named cegg_shop_coupons - that filter already exists and returns an
     * HTML string for a single domain. Reusing the name would be a silent
     * collision.
     */
    private static function externalCoupons(array $domains)
    {
        if ((string) GeneralConfig::getInstance()->option('coupons_use_cashback_tracker') !== 'enabled')
            return array();

        $external = \apply_filters('cegg_coupon_sources', array(), $domains, (int) \get_queried_object_id());

        if (!is_array($external))
            return array();

        $out = array();

        foreach ($external as $domain => $coupons)
        {
            // A source that normalizes differently would otherwise silently
            // contribute nothing, so both sides are reduced here too.
            $domain = ShopStore::normalizeDomain($domain);

            if ($domain === '' || !is_array($coupons))
                continue;

            foreach ($coupons as $c)
            {
                if (!is_array($c))
                    continue;

                $c = ShopStore::sanitizeCoupon($c);
                $c['source'] = 'external';

                $out[$domain][] = $c;
            }
        }

        return $out;
    }

    /**
     * The resolved map without the coupons named, and without a shop left
     * holding nothing. Pure.
     */
    public static function without(array $by_domain, array $ids)
    {
        if (!$ids)
            return $by_domain;

        $drop = array_flip(array_map('strval', $ids));
        $out = array();

        foreach ($by_domain as $domain => $coupons)
        {
            $kept = array();

            foreach ($coupons as $c)
            {
                if (!empty($c['id']) && isset($drop[(string) $c['id']]))
                    continue;

                $kept[] = $c;
            }

            if ($kept)
                $out[$domain] = $kept;
        }

        return $out;
    }

    /**
     * Of a row's resolved coupons, the ones that belong INSIDE the row rather
     * than below the block.
     *
     * Two cases, and only two:
     *
     *   inline  a coupon bound to this product. A binding says which items a
     *           coupon may appear with, and the roomiest layout in the plugin
     *           can carry it as a card rather than a chip.
     *   cards   one coupon, one product. A single card below a single product
     *           is the same card in a worse place - it reads as belonging to
     *           the page rather than to the product directly above it.
     *
     * The item count is what keeps the second case honest. item_simple loops,
     * so a three-product block sharing one shop-wide coupon would otherwise
     * print the identical card three times, where below the block it is said
     * once. Judged on the RESOLVED count, not the limit setting: a shop with
     * one coupon qualifies whether or not the operator capped it. Pure.
     */
    public static function inRowCoupons(array $coupons, $mode, $item_count)
    {
        if (!$coupons)
            return array();

        if ($mode === 'inline')
        {
            $out = array();

            foreach ($coupons as $c)
            {
                if (ShopCoupon::isBound($c))
                    $out[] = $c;
            }

            return $out;
        }

        if ($mode === 'cards' && count($coupons) === 1 && (int) $item_count === 1)
            return $coupons;

        return array();
    }

    /**
     * A compact row of every resolved coupon, shop-labelled, appended below the
     * block.
     *
     * This is what reaches templates with no room for a chip and, more
     * importantly, any template a site copied into content-egg-templates/ -
     * those never receive the inline call, so without the strip a customized
     * site silently gets nothing.
     *
     * $items is not optional: a hand-typed coupon has no link of its own, and
     * without an offer URL to inherit the strip would send readers to the
     * merchant UNAFFILIATED - giving away exactly the click it exists to earn.
     */
    /**
     * A compact row of every resolved coupon, appended below the block or
     * prepended above it.
     *
     * This is what reaches templates with no room for a chip and, more
     * importantly, any template a site copied into content-egg-templates/ -
     * those never receive the inline call, so without the strip a customized
     * site silently gets nothing.
     *
     * $items is not optional: a hand-typed coupon has no link of its own, and
     * without an offer URL to inherit the strip would send readers to the
     * merchant UNAFFILIATED - giving away exactly the click it exists to earn.
     */
    /**
     * The label a bound coupon carries instead of its shop name.
     *
     * Truncated: a product title runs to seventy characters and more, and the
     * strip is a row of compact chips. The shop name it replaces is a word or
     * two, so an untruncated title does not just look wrong, it pushes the code
     * and the expiry out of the chip. Pure.
     */
    public static function boundLabel(array $item)
    {
        $title = isset($item['title']) ? trim((string) $item['title']) : '';

        if ($title === '')
            return '';

        return TextHelper::truncate($title, 38, '…');
    }

    /**
     * The item a bound coupon belongs to, or array().
     *
     * A card below the block that says only "Amazon" is detached from the one
     * product it describes; named by its product, it is not - which is what
     * makes the cards placement usable for a per-product bonus at all. Pure.
     */
    public static function boundItem(array $c, array $items)
    {
        if (!ShopCoupon::isBound($c))
            return array();

        foreach ($items as $item)
        {
            if (!is_array($item))
                continue;

            if (ShopCoupon::matchesProduct($c, $item))
                return $item;
        }

        return array();
    }

    public static function strip(array $by_domain, array $items)
    {
        if (!$by_domain)
            return '';

        $urls = array();
        foreach ($items as $item)
        {
            if (empty($item['domain']) || empty($item['url']))
                continue;

            $d = ShopStore::normalizeDomain($item['domain']);

            if ($d !== '' && !isset($urls[$d]))
                $urls[$d] = $item['url'];
        }

        // Whether the shop name earns its place. Judged on the shops in the
        // BLOCK, not the ones with coupons: ten rows from ten shops still need
        // to know which one the coupon belongs to, even if only one has any.
        // A single-shop block has no such ambiguity, so the room goes to the
        // discount and the expiry instead.
        $named = count(self::domainsFrom($items)) > 1;

        $out = '';

        foreach ($by_domain as $domain => $coupons)
        {
            $shop = ShopStore::get($domain);
            $label = $shop && $shop['name'] !== '' ? $shop['name'] : $domain;

            foreach ($coupons as $c)
            {
                $code = isset($c['code']) ? trim((string) $c['code']) : '';
                $title = isset($c['title']) ? trim((string) $c['title']) : '';

                if ($code === '' && $title === '')
                    continue;

                $bound = self::boundItem($c, $items);

                if (!empty($c['link']))
                    $url = $c['link'];
                elseif ($bound && !empty($bound['url']))
                    $url = $bound['url'];
                elseif (isset($urls[$domain]))
                    $url = $urls[$domain];
                else
                    continue;   // no earning link: render nothing rather than a giveaway

                $inner = '';

                if ($bound && self::boundLabel($bound) !== '')
                    $inner .= '<span class="cegg-coupon-shop">' . \esc_html(self::boundLabel($bound)) . '</span>';
                elseif ($named)
                    $inner .= '<span class="cegg-coupon-shop">' . \esc_html($label) . '</span>';
                elseif (!empty($c['discount']))
                    $inner .= '<span class="cegg-coupon-discount">' . \esc_html($c['discount']) . '</span>';

                $inner .= $code !== ''
                    ? '<span class="cegg-coupon-code">' . \esc_html($code) . '</span>'
                    : '<span class="cegg-coupon-title">' . \esc_html($title) . '</span>';

                $tip = TemplateHelper::couponTooltip($c);

                $chip = '<a class="cegg-coupon" href="' . \esc_url($url) . '"'
                    . ' target="_blank" rel="nofollow sponsored noopener"'
                    . ($tip !== '' ? ' title="' . \esc_attr($tip) . '"' : '')
                    . ($code !== '' ? ' data-cegg-coupon-code="' . \esc_attr($code) . '"' : '') . '>'
                    . $inner
                    . '</a>';

                $note = $named ? '' : TemplateHelper::couponExpiryNote($c);

                if ($note !== '')
                    $chip .= '<span class="cegg-coupon-ends">' . \esc_html($note) . '</span>';

                $out .= '<div class="cegg-coupon-wrap">' . $chip . '</div>';
            }
        }

        return $out === '' ? '' : '<div class="cegg-coupon-strip">' . $out . '</div>';
    }

    /**
     * Resolved coupons => rows for the shop-coupon card template.
     *
     * $name_by_product names a bound coupon after its product instead of its
     * shop. True where the card is detached from the offer it belongs to (the
     * cards placement, below the block); false inside the product's own row,
     * where the product name is already the heading directly above and
     * repeating it says nothing.
     *
     * Our own shape, not a COUPON module's. Feeding the coupon-module templates
     * meant synthesising a module_id and a handful of empty fields, and only
     * worked because every consumer happened to be guarded by moduleExists() -
     * a coupling that would break the moment that template was tuned for the
     * modules it was actually written for. Pure apart from the shop lookup.
     */
    public static function cardRows(array $by_domain, array $items, array $params = array(), $name_by_product = true)
    {
        $urls = array();
        $merchants = array();

        foreach ($items as $item)
        {
            if (empty($item['domain']))
                continue;

            $d = ShopStore::normalizeDomain($item['domain']);

            if ($d === '')
                continue;

            if (!isset($urls[$d]) && !empty($item['url']))
                $urls[$d] = $item['url'];

            if (!isset($merchants[$d]))
                $merchants[$d] = array(
                    'name' => TemplateHelper::getMerchantName($item),
                    'logo' => (string) TemplateHelper::getMerchantLogoUrl($item),
                );
        }

        $btn_variant = !empty($params['btn_variant'])
            ? $params['btn_variant']
            : (string) GeneralConfig::getInstance()->option('btn_variant');

        if ($btn_variant === '')
            $btn_variant = 'primary';

        $rows = array();

        foreach ($by_domain as $domain => $coupons)
        {
            $shop = ShopStore::get($domain);

            $name = $shop && $shop['name'] !== ''
                ? $shop['name']
                : (isset($merchants[$domain]['name']) ? $merchants[$domain]['name'] : $domain);

            $logo = $shop && $shop['logo'] !== ''
                ? $shop['logo']
                : (isset($merchants[$domain]['logo']) ? $merchants[$domain]['logo'] : '');

            foreach ($coupons as $c)
            {
                $code = isset($c['code']) ? trim((string) $c['code']) : '';
                $title = isset($c['title']) ? trim((string) $c['title']) : '';

                if ($code === '' && $title === '')
                    continue;

                $bound = self::boundItem($c, $items);

                if (!empty($c['link']))
                    $url = $c['link'];
                elseif ($bound && !empty($bound['url']))
                    $url = $bound['url'];
                elseif (isset($urls[$domain]))
                    $url = $urls[$domain];
                else
                    continue;   // no earning link: render nothing rather than a giveaway

                $rows[] = array(
                    'coupon' => $c,
                    'domain' => $domain,
                    'shop' => $name_by_product && $bound && self::boundLabel($bound) !== ''
                        ? self::boundLabel($bound)
                        : $name,
                    'logo' => $logo,
                    'url' => $url,
                    'btn_variant' => $btn_variant,
                    'btn_text' => TemplateHelper::__('Get deal'),
                );
            }
        }

        return $rows;
    }

    /**
     * The placement in force for this block: the shortcode's coupons_display
     * when it set one, otherwise the Shops setting.
     *
     * Every reader goes through here - resolve(), the inline chip guard and the
     * strip - so a block-level override cannot be honoured in one place and
     * ignored in another.
     */
    public static function displayMode(array $params = array())
    {
        // hide=coupons switches the whole feature off for this block, not just
        // the chip. It used to be checked only in the chip's own guard, so a
        // block asking to hide coupons still rendered a strip or a full set of
        // cards.
        if (!empty($params['hide']) && in_array('coupons', (array) $params['hide'], true))
            return 'off';

        if (!empty($params['coupons_display']))
            return (string) $params['coupons_display'];

        return (string) GeneralConfig::getInstance()->option('coupons_display');
    }

    /**
     * The coupon cards for this block.
     *
     * The view is located through BlockTemplateManager so a site can still
     * override it from content-egg-templates/, but included directly rather
     * than rendered through the manager: the manager is a singleton, and
     * render() reassigns its items and params, which would leave a block that
     * is still rendering pointing at coupons.
     */
    public static function cards(array $by_domain, array $items, array $params = array())
    {
        if (!$by_domain)
            return '';

        if (!$coupons = self::cardRows($by_domain, $items, $params))
            return '';

        // Enqueued here rather than in the template so a site that copied the
        // template into content-egg-templates/ still gets the reveal and copy
        // behaviour. Without it "Show Code" is an inert link.
        \wp_enqueue_script('cegg-products-view');

        $file = BlockTemplateManager::getInstance()->getViewPath('block_shop_coupons');

        if (!$file || !is_readable($file))
            return '';

        ob_start();
        include $file;

        return (string) ob_get_clean();
    }

    public static function resetCache()
    {
        self::$post_terms = null;
    }
}
