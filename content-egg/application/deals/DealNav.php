<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;

/**
 * DealNav class file
 *
 * Store/category navigation for deal pages: breadcrumbs, their
 * BreadcrumbList markup, category chips and the Cashback Tracker shop link.
 * The static helpers at the top are pure and unit-tested; the WordPress
 * builders below feed them.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealNav
{
    const CT_CACHE_PREFIX = 'cegg_deal_ct_';
    const CT_CACHE_GEN = 'cegg_deal_ct_gen';

    /**
     * Home › Archive › ancestors… › leaf. The last crumb is never linked; with
     * no leaf the archive itself is the last crumb.
     */
    public static function trail(array $home, array $archive, array $ancestors, ?array $leaf): array
    {
        $trail = array_merge(array($home, $archive), array_values($ancestors));
        if ($leaf)
            $trail[] = $leaf;

        $trail[count($trail) - 1]['url'] = '';

        return $trail;
    }

    /**
     * The most specific term: most ancestors, ties to the lowest term_id so
     * the choice is stable across requests.
     */
    public static function deepest(array $terms, callable $depth_of)
    {
        $best = null;
        $best_depth = -1;
        foreach ($terms as $term)
        {
            $depth = (int) $depth_of($term);
            if ($depth > $best_depth || ($depth === $best_depth && (int) $term->term_id < (int) $best->term_id))
            {
                $best = $term;
                $best_depth = $depth;
            }
        }

        return $best;
    }

    /**
     * Direct children of $parent whose subtree holds deals. Core term counts
     * do not roll up, so a parent with deals only in its children reads 0.
     */
    public static function visibleChildren(array $terms, int $parent): array
    {
        $by_parent = array();
        foreach ($terms as $term)
            $by_parent[(int) $term->parent][] = $term;

        $subtree = function ($term) use (&$subtree, $by_parent)
        {
            $sum = (int) $term->count;
            foreach ($by_parent[(int) $term->term_id] ?? array() as $child)
                $sum += $subtree($child);

            return $sum;
        };

        $out = array();
        foreach ($by_parent[$parent] ?? array() as $term)
        {
            if ($subtree($term) > 0)
                $out[] = $term;
        }
        usort($out, function ($a, $b)
        {
            return strcasecmp($a->name, $b->name);
        });

        return $out;
    }

    public static function schema(array $trail): array
    {
        $items = array();
        foreach (array_values($trail) as $i => $crumb)
        {
            $el = array('@type' => 'ListItem', 'position' => $i + 1, 'name' => html_entity_decode((string) $crumb['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($crumb['url'] !== '')
                $el['item'] = (string) $crumb['url'];
            $items[] = $el;
        }

        return array('@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items);
    }

    /**
     * The markup for a <script type="application/ld+json">. Names are plain
     * text (store names come from imported feeds), so every < > & is escaped
     * as \u003C etc.: nothing in a name can close the tag or open a comment.
     */
    public static function schemaJson(array $trail): string
    {
        return (string) \wp_json_encode(self::schema($trail), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function breadcrumbs(string $context, int $object_id = 0): array
    {
        $home = array('name' => __('Home', 'content-egg-tpl'), 'url' => \home_url('/'));
        $archive_url = \get_post_type_archive_link(DealPostType::POST_TYPE);
        $archive = array('name' => __('Deals', 'content-egg-tpl'), 'url' => $archive_url ? $archive_url : '');
        $ancestors = array();
        $leaf = null;

        if ($context === 'single' && $object_id)
        {
            if ($cat = DealView::primaryCategory($object_id))
                $ancestors = self::termChain($cat);
            $leaf = array('name' => \get_the_title($object_id), 'url' => (string) \get_permalink($object_id));
        }
        elseif (($context === 'store' || $context === 'category') && $object_id)
        {
            $term = \get_term($object_id);
            if ($term instanceof \WP_Term)
            {
                $ancestors = self::termChain($term);
                $leaf = array_pop($ancestors);
            }
        }

        return (array) \apply_filters('cegg_deal_breadcrumbs', self::trail($home, $archive, $ancestors, $leaf), $context);
    }

    /** Root-first crumbs for a term and its ancestors. */
    private static function termChain(\WP_Term $term): array
    {
        $ids = array_reverse(\get_ancestors($term->term_id, $term->taxonomy, 'taxonomy'));
        $ids[] = $term->term_id;

        $chain = array();
        foreach ($ids as $id)
        {
            $t = \get_term($id, $term->taxonomy);
            if (!$t instanceof \WP_Term)
                continue;
            $link = \get_term_link($t);
            $chain[] = array('name' => $t->name, 'url' => \is_wp_error($link) ? '' : $link);
        }

        return $chain;
    }

    /**
     * SEO plugins print their own BreadcrumbList; two would compete.
     */
    public static function schemaEnabled(): bool
    {
        if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION'))
            return false;

        return GeneralConfig::getInstance()->option('add_schema_markup') !== 'disabled';
    }

    public static function categoryChips(int $parent): array
    {
        $terms = \get_terms(array('taxonomy' => DealPostType::TAX_CATEGORY, 'hide_empty' => false));

        return ($terms && !\is_wp_error($terms)) ? self::visibleChildren($terms, $parent) : array();
    }

    /**
     * The Cashback Tracker shop page for a store domain, or null. CT has no
     * public "page by domain" helper: the domain belongs to a CT advertiser,
     * and a cbtrkr_shop post points at the advertiser by module + id (its own
     * _cbtrkr_shop_domain is an optional override, checked as a fallback).
     * Cached 12 h, misses included, and the cegg_deal_store_cashback_url
     * filter's result with it. Saving or deleting a CT shop page starts a new
     * cache generation (see flushCashbackCache()).
     */
    public static function cashbackShopUrl(string $domain): ?string
    {
        $domain = strtolower(trim($domain));
        if ($domain === '' || !class_exists('\CashbackTracker\application\components\AdvertiserManager'))
            return null;

        $key = self::cashbackCacheKey($domain);
        $cached = \get_transient($key);
        if ($cached !== false)
            return $cached !== '' ? (string) $cached : null;

        $url = '';
        try
        {
            $queries = array();
            $adv = \CashbackTracker\application\components\AdvertiserManager::getInstance()->findAdvertiserByDomainOnly($domain);
            if (is_array($adv) && !empty($adv['module_id']) && isset($adv['id']) && $adv['id'] !== '')
            {
                $queries[] = array(
                    array('key' => '_cbtrkr_shop_module_id', 'value' => (string) $adv['module_id']),
                    array('key' => '_cbtrkr_shop_advertiser_id', 'value' => (string) $adv['id']),
                );
            }
            $queries[] = array(array('key' => '_cbtrkr_shop_domain', 'value' => $domain));

            foreach ($queries as $meta_query)
            {
                $ids = \get_posts(array('post_type' => 'cbtrkr_shop', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'meta_query' => $meta_query, 'no_found_rows' => true));
                if ($ids)
                {
                    $url = (string) \get_permalink((int) $ids[0]);
                    break;
                }
            }
        }
        catch (\Throwable $e)
        {
            $url = '';
        }

        $url = (string) \apply_filters('cegg_deal_store_cashback_url', $url, $domain);
        \set_transient($key, $url, 12 * HOUR_IN_SECONDS);

        return $url !== '' ? $url : null;
    }

    public static function cashbackCacheKey(string $domain): string
    {
        return self::CT_CACHE_PREFIX . (int) \get_option(self::CT_CACHE_GEN, 0) . '_' . md5(strtolower(trim($domain)));
    }

    /**
     * Transients cannot be listed by prefix on every object cache, so the key
     * carries a generation number; bumping it orphans the old entries, which
     * expire on their own.
     */
    public static function flushCashbackCache(): void
    {
        \update_option(self::CT_CACHE_GEN, (int) \get_option(self::CT_CACHE_GEN, 0) + 1, false);
    }
}
