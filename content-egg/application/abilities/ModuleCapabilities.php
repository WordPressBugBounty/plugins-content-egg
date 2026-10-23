<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * ModuleCapabilities class file
 *
 * What a module can do, in the vocabulary agents need to pick a module for a
 * task (price comparison, GTIN lookup, refresh by id, cost of a search).
 * Derived from the module's own flags plus static maps keyed by the base
 * module id (the part before "__", so "Feed__3" -> "Feed").
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class ModuleCapabilities
{
    // Modules whose results are offers for the same product from many shops.
    const PRICE_COMPARISON = array(
        'Kelkoo', 'Billigerde', 'Geizhalsde', 'Kieskeurignl', 'Trovaprezzi',
        'Allkeyshop', 'Viglink', 'Clickwise',
    );

    // Modules whose results span several merchants (a superset of the above).
    const MULTI_MERCHANT = array(
        'Kelkoo', 'Billigerde', 'Geizhalsde', 'Kieskeurignl', 'Trovaprezzi',
        'Allkeyshop', 'Viglink', 'Clickwise',
        'Awin', 'Webgains', 'Daisycon', 'CjProducts', 'Impactradius', 'Linkshare',
        'AvantlinkProducts', 'CityadsProducts', 'LomadeeProducts', 'Linkwise',
        'Walmart', 'Ebay2',
    );

    // Marketplaces: one product comes back as many listings from different
    // sellers. A search there returns MARKETPLACE_DEFAULT_LIMIT results unless
    // the caller sets a limit.
    const MARKETPLACE = array('Ebay', 'Ebay2', 'Aliexpress', 'Aliexpress2', 'Shopee');
    const MARKETPLACE_DEFAULT_LIMIT = 5;

    // Modules that fill the item's GTIN field (every feed module does too).
    const RETURNS_GTIN = array(
        'Amazon', 'Awin', 'Webgains', 'Daisycon', 'Bolcom', 'Bestbuy', 'Walmart',
        'Kelkoo', 'Billigerde', 'Geizhalsde', 'Clickwise', 'CjProducts',
        'Impactradius', 'TradetrackerProducts', 'TradedoublerProducts', 'LomadeeProducts',
    );

    // Modules whose items do not expose the merchant's domain.
    const NO_MERCHANT_DOMAINS = array('Kieskeurignl', 'Trovaprezzi', 'Viglink', 'Allkeyshop');

    // What a "native id" search takes, per module.
    const NATIVE_ID_HINTS = array(
        'Amazon' => 'ASIN',
        'AmazonNoApi' => 'ASIN',
        'Ebay2' => 'eBay item ID or ePID',
        'Billigerde' => 'product ID',
        'Geizhalsde' => 'product ID',
        'Kieskeurignl' => 'product ID',
        'Bestbuy' => 'SKU',
        'Aliexpress2' => 'product ID',
        'Shopee' => 'item ID',
    );

    const FEED_NATIVE_ID_HINT = 'id:<feed product id>';
    const SCRAPE_REQUESTS = '1 + number of results';

    // Affiliate Egg shop modules are "AE__<shop>" (ModuleManager::AE_MODULES_PREFIX).
    const AE_BASE_ID = 'AE';
    const AMAZON_NO_API = 'AmazonNoApi';

    /**
     * @param object $module Module instance from ModuleManager::parserFactory()
     * @return array<string, mixed>
     */
    public static function forModule($module, string $module_id, bool $is_feed): array
    {
        $base = self::baseId($module_id);
        $searchable = self::supports($module, 'isSearchable');
        $hint = self::nativeIdHint($base, $is_feed);

        $search_by = array();
        if ($searchable)
        {
            $search_by[] = 'keyword';
            if (self::supports($module, 'isGtinSearchAllowed'))
            {
                $search_by[] = 'gtin';
            }
            if (self::supports($module, 'isUrlSearchAllowed'))
            {
                $search_by[] = 'url';
            }
            if ($hint !== '')
            {
                $search_by[] = 'native_id';
            }
        }

        $cost = self::cost($module_id, $is_feed);

        $filters = array();
        if (method_exists($module, 'getSearchFilters'))
        {
            $filters = array_values(array_map('strval', array_column((array) $module->getSearchFilters(), 'key')));
        }

        list($locales, $default_locale) = self::locales($module);

        return array(
            'searchable' => $searchable,
            'search_by' => $search_by,
            'native_id_hint' => $hint,
            'refresh_by_id' => self::supports($module, 'isItemsUpdateAvailable'),
            'multi_merchant' => in_array($base, self::MULTI_MERCHANT, true),
            'marketplace' => self::isMarketplace($module_id),
            'price_comparison' => in_array($base, self::PRICE_COMPARISON, true),
            'returns_gtin' => $is_feed || in_array($base, self::RETURNS_GTIN, true),
            'merchant_domains' => !in_array($base, self::NO_MERCHANT_DOMAINS, true),
            'cost' => $cost,
            'shop_domain' => self::shopDomain($module),
            'requests_per_search' => $cost === 'scrape' ? self::SCRAPE_REQUESTS : null,
            'filters' => $filters,
            'locales' => $locales,
            'default_locale' => $default_locale,
        );
    }

    /**
     * Modules that fetch each result with its own page request (Affiliate Egg
     * shops, Amazon without the API): they fetch exactly as many results as
     * asked for, not a padded page.
     */
    public static function isScraper(string $module_id): bool
    {
        $base = self::baseId($module_id);

        return $base === self::AE_BASE_ID || $module_id === self::AMAZON_NO_API;
    }

    public static function isMarketplace(string $module_id): bool
    {
        return in_array(self::baseId($module_id), self::MARKETPLACE, true);
    }

    /** "Feed__3" -> "Feed", "AE__bolcom" -> "AE", "Amazon" -> "Amazon". */
    public static function baseId(string $module_id): string
    {
        return explode('__', $module_id)[0];
    }

    private static function cost(string $module_id, bool $is_feed): string
    {
        if ($is_feed)
        {
            return 'local';
        }

        return self::isScraper($module_id) ? 'scrape' : 'api';
    }

    private static function nativeIdHint(string $base, bool $is_feed): string
    {
        if ($is_feed)
        {
            return self::FEED_NATIVE_ID_HINT;
        }

        return self::NATIVE_ID_HINTS[$base] ?? '';
    }

    /**
     * The one shop an Affiliate Egg module searches ("target.com"), whose
     * product URLs it takes; '' for every other module.
     */
    private static function shopDomain($module): string
    {
        if (!method_exists($module, 'getShopHost'))
        {
            return '';
        }

        try
        {
            return (string) $module->getShopHost();
        }
        catch (\Throwable $e)
        {
            // Capabilities are descriptive; never let one module break list-modules.
            return '';
        }
    }

    /** True when the module has the boolean capability method and it returns true. */
    public static function supports($module, string $method): bool
    {
        return method_exists($module, $method) && (bool) $module->$method();
    }

    /** @return array{0: string[], 1: string} locale codes and the default one */
    private static function locales($module): array
    {
        if (!method_exists($module, 'getConfigInstance'))
        {
            return array(array(), '');
        }

        try
        {
            $config = $module->getConfigInstance();
            if (!is_object($config) || !method_exists($config, 'getActiveLocalesList'))
            {
                return array(array(), '');
            }

            $locales = array_map('strval', array_keys((array) $config->getActiveLocalesList()));
            $default = (string) $config->option('locale');
        }
        catch (\Throwable $e)
        {
            // Capabilities are descriptive; never let one module break list-modules.
            return array(array(), '');
        }

        return array($locales, $default);
    }
}
