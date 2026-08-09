<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

use ContentEgg\application\helpers\TemplateHelper;
use ContentEgg\application\helpers\TextHelper;

/**
 * ProductSearchService class file
 *
 * Module product search shared by the metabox admin-ajax endpoint and the
 * REST API. Extracted from ModuleApi::addApiEntryModule() — the admin-ajax
 * behavior must stay byte-identical.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ProductSearchService
{

    /**
     * Keyword sanitization identical to the metabox endpoint: URLs and
     * "[..." listing prefixes go through FILTER_SANITIZE_URL, everything
     * else through sanitize_text_field(). Non-string scalars are cast to
     * string (matching the original sanitize_text_field behavior); arrays
     * and objects return ''.
     */
    public static function prepareKeyword($keyword)
    {
        if (is_array($keyword) || is_object($keyword))
            return '';

        $keyword = (string) $keyword;

        if ($keyword === '')
            return '';

        if ($keyword[0] == '[' || filter_var($keyword, FILTER_VALIDATE_URL))
        {
            $keyword = filter_var($keyword, FILTER_SANITIZE_URL);
            // FILTER_SANITIZE_URL strips the space; restore it for both listing prefixes.
            $keyword = str_replace(array('[importlimit', '[cataloglimit'), array('[import limit', '[catalog limit'), $keyword);
        }
        else
        {
            $keyword = \sanitize_text_field($keyword);
        }

        return (string) $keyword;
    }

    /**
     * Reduce a raw filters map to the keys the module actually declares in
     * getSearchFilters(). Keys are sanitized, non-scalar values dropped, and
     * anything outside the allowlist rejected. 'keyword' is added separately
     * by the caller and is never part of the filters allowlist.
     */
    public static function filterQueryKeys(array $filters, array $allowedKeys): array
    {
        $allowed = array_fill_keys($allowedKeys, true);

        $query = array();
        foreach ($filters as $key => $value)
        {
            if (!is_scalar($value))
                continue;

            $key = \sanitize_key($key);
            if (!isset($allowed[$key]))
                continue;

            $query[$key] = \sanitize_text_field((string) $value);
        }

        return $query;
    }

    /**
     * Map generic price/locale query params onto the parser's own param names.
     * The floatval() on locale is preserved as-is from ModuleApi (byte-identical
     * behavior) — do not "fix" it here.
     */
    public static function applyParamMaps($parser, array $query)
    {
        if (!$parser->isAffiliateParser())
            return $query;

        $cls = get_class($parser);

        // price range mapping.
        $map = $cls::getPriceParamMap();

        if (isset($map['min']) && isset($query['minimum_price']))
        {
            $query[$map['min']] = floatval($query['minimum_price']);
        }
        if (isset($map['max']) && isset($query['maximum_price']))
        {
            $query[$map['max']] = floatval($query['maximum_price']);
        }

        // locale map
        $localeMap = $cls::getLocaleParamMap();
        if (isset($localeMap['locale']) && isset($query['locale']))
        {
            $query[$localeMap['locale']] = floatval($query['locale']);
        }

        return $query;
    }

    /**
     * Post-search item formatting identical to the metabox endpoint.
     */
    public static function formatItems(array $data)
    {
        foreach ($data as $key => $item)
        {
            if (!$item->unique_id)
            {
                throw new \Exception('Item data "unique_id" must be specified.');
            }

            if ($item->description)
            {
                if (!TextHelper::isHtmlTagDetected($item->description))
                {
                    $item->description = TextHelper::br2nl($item->description);
                }

                $item->description = TextHelper::removeExtraBreaks($item->description);
            }

            if (property_exists($item, 'price'))
            {
                if (!(float) $item->price)
                {
                    $item->price = 0;
                    $item->priceOld = 0;
                }
                elseif (!(float) $item->priceOld)
                {
                    $item->priceOld = 0;
                }

                if ($item->price)
                    $item->_priceFormatted = TemplateHelper::formatPriceCurrency($item->price, $item->currencyCode);
                if ($item->priceOld)
                    $item->_priceOldFormatted = TemplateHelper::formatPriceCurrency($item->priceOld, $item->currencyCode);
                if ($item->description)
                    // Flatten the (sanitized) HTML to clean text — preserving bullet/line
                    // structure — and cap it so the search payload stays lean. The result
                    // list clamps it to ~2 lines in CSS on top of this.
                    $item->_descriptionText = TextHelper::truncate(
                        TextHelper::htmlToText($item->description),
                        300,
                        "\xe2\x80\xa6"
                    );
            }
        }

        return $data;
    }

    /**
     * Full search pipeline for REST consumers.
     *
     * @return array array('results' => object[], 'notice' => string)
     * @throws \InvalidArgumentException on unknown/inactive module or empty keyword
     * @throws \ContentEgg\application\components\feed\FeedImportPendingException while a feed import is pending
     * @throws \Exception on parser errors
     */
    public static function search($module_id, array $query)
    {
        $parser = ModuleManager::getInstance()->parserFactory($module_id);

        if (!$parser || !$parser->isActive())
            throw new \InvalidArgumentException('Parser module is inactive.');

        $keyword = self::prepareKeyword(isset($query['keyword']) ? $query['keyword'] : '');

        if (!$keyword)
            throw new \InvalidArgumentException("The 'keyword' parameter cannot be empty.");

        $query = self::applyParamMaps($parser, $query);

        $data = $parser->doMultipleRequests($keyword, $query);
        $data = self::formatItems($data);

        $notice = method_exists($parser, 'getSearchNotice') ? (string) $parser->getSearchNotice() : '';

        return array('results' => $data, 'notice' => $notice);
    }
}
