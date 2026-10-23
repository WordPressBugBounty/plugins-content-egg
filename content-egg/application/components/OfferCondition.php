<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\components\matching\ProductPassport;

/**
 * OfferCondition class file
 *
 * The condition of an offer - new, used or refurbished - as one canonical
 * value, whatever vocabulary the module it came from speaks. Modules keep it
 * in different places (Amazon extra.Condition, Ebay extra.conditionId, Bol.com
 * the item root...) and in different words; this class reads them all, falls
 * back to a few unambiguous title markers, and otherwise says 'unknown'.
 *
 * Reading the condition is pure. What it consults to decide whether non-new
 * offers are left out of the best price (see excludedFromBestPrice()) is the
 * `exclude_non_new_offers` setting and, for an item that carries a post id,
 * whether that page has a product passport (a post meta, via ProductPassport).
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class OfferCondition
{
    const NEW = 'new';
    const USED = 'used';
    const REFURBISHED = 'refurbished';
    const UNKNOWN = 'unknown';

    /**
     * What modules call each condition, lower case. 1500 (eBay "new other") is
     * deliberately absent: it is neither plainly new nor used, so it stays
     * unrecognized and the item falls back to the title / 'unknown'.
     * eBay ids: 2010/2020/2030 are the refurbished grades (Excellent, Very
     * Good, Good), 2750 is Like New, 4000/5000/6000 are the used grades.
     */
    const VOCABULARY = array(
        'new' => self::NEW,
        'neu' => self::NEW,
        'nieuw' => self::NEW,
        'nuovo' => self::NEW,
        'nuevo' => self::NEW,
        'neuf' => self::NEW,
        'newcondition' => self::NEW,
        '1000' => self::NEW,

        'used' => self::USED,
        'gebraucht' => self::USED,
        'gebruikt' => self::USED,
        'usato' => self::USED,
        'usado' => self::USED,
        'occasion' => self::USED,
        'collectible' => self::USED,
        'usedcondition' => self::USED,
        'damagedcondition' => self::USED,
        '2750' => self::USED,
        '3000' => self::USED,
        '4000' => self::USED,
        '5000' => self::USED,
        '6000' => self::USED,
        '7000' => self::USED,
        'for parts' => self::USED,

        'refurbished' => self::REFURBISHED,
        'renewed' => self::REFURBISHED,
        'generalüberholt' => self::REFURBISHED,
        'reconditionné' => self::REFURBISHED,
        'ricondizionato' => self::REFURBISHED,
        'seller refurbished' => self::REFURBISHED,
        'refurbishedcondition' => self::REFURBISHED,
        '2000' => self::REFURBISHED,
        '2010' => self::REFURBISHED,
        '2020' => self::REFURBISHED,
        '2030' => self::REFURBISHED,
        '2500' => self::REFURBISHED,
    );

    /**
     * Module-reported values only (never titles): a value that contains one of
     * these words is refurbished - "Certified - Refurbished", "Manufacturer
     * refurbished", "Excellent - Refurbished".
     */
    const VALUE_REFURBISHED_WORDS = array(
        'refurbished',
        'renewed',
        'generalüberholt',
        'reconditionné',
        'ricondizionato',
        'refurb',
    );

    /**
     * Module-reported values only: a value that is, or whose first segment
     * (before " - " or "(") is, one of these is used - "Pre-owned", "Like New",
     * "Very Good (minor wear)". "New other (see details)" and "Open box" are
     * not here on purpose: they stay unrecognized.
     */
    const VALUE_USED_GRADES = array(
        'pre-owned',
        'like new',
        'very good',
        'good',
        'acceptable',
    );

    /**
     * Words in a product title that reliably mean the offer is not new. Whole
     * words only; no other markers without evidence from real data.
     */
    const TITLE_MARKERS = array(
        'refurbished' => self::REFURBISHED,
        'generalüberholt' => self::REFURBISHED,
        'renewed' => self::REFURBISHED,
        'b-ware' => self::REFURBISHED,
        'reconditionné' => self::REFURBISHED,
        'ricondizionato' => self::REFURBISHED,
        'gebraucht' => self::USED,
    );

    /**
     * Canonical value for one module-supplied condition, or '' when the
     * value is empty, 'unknown' or not recognized.
     *
     * @param mixed $raw
     */
    public static function normalizeValue($raw): string
    {
        if (is_int($raw) || is_float($raw))
        {
            $raw = (string) $raw;
        }

        if (!is_string($raw))
        {
            return '';
        }

        $value = self::lower(trim($raw));
        if ($value === '')
        {
            return '';
        }

        // schema.org: https://schema.org/RefurbishedCondition.
        if (preg_match('#^https?://.*/([^/]+)/?$#', $value, $m))
        {
            $value = $m[1];
        }

        if (isset(self::VOCABULARY[$value]))
        {
            return self::VOCABULARY[$value];
        }

        // Amazon sub-conditions: "Used - Like New", "Used - Very Good"...
        if (preg_match('/^used(?![\p{L}\p{N}])/u', $value))
        {
            return self::USED;
        }

        // eBay / marketplace free text: "Certified - Refurbished", "Manufacturer refurbished".
        if (preg_match(self::valueRefurbishedPattern(), $value))
        {
            return self::REFURBISHED;
        }

        // "Pre-owned", "Like New", "Very Good (minor wear)".
        $segment = preg_split('/\s+-\s+|\(/u', $value, 2);
        if (is_array($segment) && in_array(trim($segment[0]), self::VALUE_USED_GRADES, true))
        {
            return self::USED;
        }

        return '';
    }

    /**
     * Condition a product title gives away ('refurbished' or 'used'), or ''.
     */
    public static function titleMarker(string $title): string
    {
        if (!preg_match(self::markerPattern(), $title, $m))
        {
            return '';
        }

        return self::TITLE_MARKERS[self::lower($m[1])] ?? '';
    }

    /**
     * The condition of an offer: the first value any of the module's own
     * fields gives, else a title marker, else 'unknown'. A module's 'new'
     * gives way to a title marker: marketplaces report refurbished programs
     * as new (Amazon Renewed listings come with Condition "New" from the
     * CertifiedPreOwned seller). A module's used or refurbished always wins.
     * Works on a saved item (arrays) and on a raw module item (ContentProduct
     * with an ExtraData object in extra) alike.
     *
     * @param array|object $item
     */
    public static function fromItem($item): string
    {
        $item = self::toArray($item);
        $extra = self::toArray($item['extra'] ?? null);
        $data = self::toArray($extra['data'] ?? null);

        $candidates = array(
            $item['condition'] ?? null,
            $extra['Condition'] ?? null,
            $extra['condition'] ?? null,
            $extra['conditionId'] ?? null,
            $extra['conditionDisplayName'] ?? null,
            $data['condition'] ?? null,
        );

        $module_condition = '';
        foreach ($candidates as $candidate)
        {
            $module_condition = self::normalizeValue($candidate);
            if ($module_condition !== '')
            {
                break;
            }
        }

        if ($module_condition !== '' && $module_condition !== self::NEW)
        {
            return $module_condition;
        }

        $marker = self::markerOf($item);
        if ($marker !== '')
        {
            return $marker;
        }

        return $module_condition !== '' ? $module_condition : self::UNKNOWN;
    }

    /**
     * The condition of an offer as the policy sees it: the saved `condition`
     * when it names one, else (an item saved before the field existed, a saved
     * 'unknown', or a raw module item) whatever fromItem() makes of it. A
     * saved 'new' still gives way to a title marker, as in fromItem(), so an
     * offer saved before that rule existed is read the same way.
     */
    public static function resolve(array $item): string
    {
        $condition = $item['condition'] ?? '';
        if ($condition === self::NEW)
        {
            $marker = self::markerOf($item);

            return $marker !== '' ? $marker : self::NEW;
        }
        if (!in_array($condition, array(self::USED, self::REFURBISHED), true))
        {
            $condition = self::fromItem($item);
        }

        return $condition;
    }

    /** The title marker of an item, or ''. */
    private static function markerOf(array $item): string
    {
        $title = $item['title'] ?? '';

        return is_string($title) ? self::titleMarker($title) : '';
    }

    /**
     * True for an offer that is used or refurbished (see resolve()).
     */
    public static function isNonNew(array $item): bool
    {
        $condition = self::resolve($item);

        return $condition === self::USED || $condition === self::REFURBISHED;
    }

    /**
     * True when non-new offers are to stay out of the best price, the
     * WooCommerce price, price history and alerts, and this offer is one.
     * That is so when the owner enabled `exclude_non_new_offers`, and always
     * on a page with a product passport, whatever the setting says (D6:
     * non-new offers never set a best price there). Otherwise off: a site that
     * never saved the setting and has no passport keeps today's behaviour.
     */
    public static function excludedFromBestPrice(array $item): bool
    {
        if (!self::exclusionOn() && !self::onPassportPage($item))
        {
            return false;
        }

        return self::isNonNew($item);
    }

    /**
     * The items that may set the best price: everything except the excluded
     * offers. Keys are kept so callers can still map back to the originals.
     */
    public static function withoutExcluded(array $items): array
    {
        $always = self::exclusionOn();

        return array_filter(
            $items,
            static function ($item) use ($always)
            {
                if (!is_array($item))
                {
                    return true;
                }

                return !(($always || self::onPassportPage($item)) && self::isNonNew($item));
            }
        );
    }

    /**
     * The `exclude_non_new_offers` setting is 'enabled'.
     */
    private static function exclusionOn(): bool
    {
        return GeneralConfig::getInstance()->option('exclude_non_new_offers') === 'enabled';
    }

    /**
     * The item belongs to a page that has a product passport. An item with no
     * post id (a preview of a module search, a raw module item) never does.
     */
    private static function onPassportPage(array $item): bool
    {
        $post_id = isset($item['post_id']) ? (int) $item['post_id'] : 0;

        return $post_id > 0 && ProductPassport::has($post_id);
    }

    /**
     * A marker only counts as a whole word: not inside a longer word, and not
     * glued to a hyphenated compound ("Web-Ware" is not "B-Ware").
     */
    private static function markerPattern(): string
    {
        static $pattern;

        if ($pattern === null)
        {
            $words = array_map(
                static function ($marker)
                {
                    return preg_quote($marker, '/');
                },
                array_keys(self::TITLE_MARKERS)
            );
            $pattern = '/(?<![\p{L}\p{N}-])(' . implode('|', $words) . ')(?![\p{L}\p{N}-])/iu';
        }

        return $pattern;
    }

    /**
     * The refurbished-family words as whole words inside a module-reported
     * value (not used for titles; those keep the narrow TITLE_MARKERS list).
     */
    private static function valueRefurbishedPattern(): string
    {
        static $pattern;

        if ($pattern === null)
        {
            $words = array_map(
                static function ($word)
                {
                    return preg_quote($word, '/');
                },
                self::VALUE_REFURBISHED_WORDS
            );
            $pattern = '/(?<![\p{L}\p{N}])(?:' . implode('|', $words) . ')(?![\p{L}\p{N}])/u';
        }

        return $pattern;
    }

    private static function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    /**
     * @param mixed $value
     */
    private static function toArray($value): array
    {
        if (is_array($value))
        {
            return $value;
        }

        if (is_object($value))
        {
            return get_object_vars($value);
        }

        return array();
    }
}
