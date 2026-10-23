<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\helpers\TextHelper;

/**
 * SearchMode class file
 *
 * Reports how a module will treat a query, so an agent can tell a GTIN lookup
 * from a keyword search that merely received digits. Mirrors the modules'
 * own branching: the module decides, this only names the outcome.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class SearchMode
{
    const NATIVE_ID = 'native_id';
    const URL = 'url';
    const GTIN = 'gtin';
    const KEYWORD = 'keyword';

    // Feed keyword prefix (AffiliateFeedParserModule::ID_KEYWORD_PREFIX).
    const FEED_ID_PREFIX = 'id:';

    // Native-id shapes per base module id, copied from each module's own
    // "is this an id?" test (anchored). Amazon/AmazonNoApi use the ASIN test instead.
    const NATIVE_ID_PATTERNS = array(
        'Kieskeurignl' => '~^\d{6,12}$~',  // KieskeurignlModule::isPid()
        'Geizhalsde' => '~^\d+$~',
        'Billigerde' => '~^\d+$~',
        'Bestbuy' => '~^\d{7,12}$~',
        'Ebay2' => '~^\d{8,13}$~',         // ePID 8-11 digits, legacy item id 12-13
        'Aliexpress2' => '~^\d{10,}$~',
        'Shopee' => '~^\d{10,}$~',
    );

    // Modules that test for an id before they test for a GTIN; the rest try
    // the GTIN first, so a code that is both is a GTIN lookup for them.
    const NATIVE_ID_BEFORE_GTIN = array('Kieskeurignl', 'Amazon', 'AmazonNoApi');

    /**
     * Mirrors the module's own branch order: feed id: prefix, then an id test
     * for modules that run it first, then URL, GTIN, and finally the id test
     * for modules that look a GTIN up before trying an id.
     *
     * @param object $module Module instance from ModuleManager::parserFactory()
     * @return string native_id|url|gtin|keyword
     */
    public static function detect(string $keyword, $module, string $module_id, bool $is_feed): string
    {
        $keyword = trim($keyword);
        $base = ModuleCapabilities::baseId($module_id);
        $id_first = in_array($base, self::NATIVE_ID_BEFORE_GTIN, true);

        if ($is_feed && stripos($keyword, self::FEED_ID_PREFIX) === 0)
        {
            return self::NATIVE_ID;
        }
        if ($id_first && self::isNativeId($base, $keyword))
        {
            return self::NATIVE_ID;
        }
        if (TextHelper::isUrl($keyword) && ModuleCapabilities::supports($module, 'isUrlSearchAllowed'))
        {
            return self::URL;
        }
        if (TextHelper::isEan($keyword) && ModuleCapabilities::supports($module, 'isGtinSearchAllowed'))
        {
            return self::GTIN;
        }
        if (!$id_first && self::isNativeId($base, $keyword))
        {
            return self::NATIVE_ID;
        }

        return self::KEYWORD;
    }

    /** Base module ids that have a native-id rule (kept in step with ModuleCapabilities::NATIVE_ID_HINTS). */
    public static function nativeIdBaseIds(): array
    {
        return array_merge(array('Amazon', ModuleCapabilities::AMAZON_NO_API), array_keys(self::NATIVE_ID_PATTERNS));
    }

    private static function isNativeId(string $base, string $keyword): bool
    {
        if ($base === 'Amazon')
        {
            // AmazonModule: a code that is also a valid EAN is not treated as an ASIN
            return TextHelper::isAsin($keyword) && !TextHelper::isEan($keyword);
        }
        if ($base === ModuleCapabilities::AMAZON_NO_API)
        {
            // AmazonNoApiModule tests isAsin() alone
            return TextHelper::isAsin($keyword);
        }

        return isset(self::NATIVE_ID_PATTERNS[$base]) && (bool) preg_match(self::NATIVE_ID_PATTERNS[$base], $keyword);
    }
}
