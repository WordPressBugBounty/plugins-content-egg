<?php

namespace ContentEgg\application\modules\AE;

defined('\ABSPATH') || exit;

/**
 * Pure helpers for the optional custom-domain "Search URL": keyword-token
 * substitution and validation. No WordPress or Affiliate Egg dependency, so it
 * is unit-testable standalone (see tests/ae/).
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class AeSearchUrl
{
    /**
     * Recognized keyword query-parameter names, most-specific/most-common first
     * (priority order). Derived from real Affiliate Egg search URLs (config.php +
     * ~1900 theme parsers): q, s, query, search, keyword, ... When a URL lacks a
     * %KEYWORD% token, normalize() replaces the value of the highest-priority
     * matching parameter. Matching is case-insensitive.
     */
    const KEYWORD_PARAMS = array(
        'q', 'query', 'keyword', 'keywords', 'search', 'search_query',
        'searchtext', 'searchterm', 'searchparam', 'searchstring', 'ssearch',
        'text', 'term', 'phrase', 'kw', 'key', '_nkw', 'ntt', 'tn_q',
        's', 'k', 'qs', 'sq', 'w', 'name',
    );

    public static function hasKeywordToken($search_uri)
    {
        $search_uri = (string) $search_uri;
        return strpos($search_uri, '%KEYWORD%') !== false
            || strpos($search_uri, '%KEY+WORD%') !== false
            || strpos($search_uri, '%KEY-WORD%') !== false;
    }

    /**
     * Substitute the keyword into a search-URL template. Mirrors the common
     * tokens Affiliate Egg's parseSearchCatalog() supports for registered shops.
     */
    public static function substitute($search_uri, $keyword)
    {
        $search_uri = (string) $search_uri;
        $keyword = (string) $keyword;
        // %KEYWORD% encodes spaces as '+' (application/x-www-form-urlencoded), same as
        // %KEY+WORD%. In real search URLs the '+' family is ~64% vs ~32% for '%20', and
        // '+' is the standard encoding for a query-string value. %KEY-WORD% joins with '-'.
        $url = str_replace('%KEYWORD%', urlencode($keyword), $search_uri);
        $url = str_replace('%KEY+WORD%', urlencode($keyword), $url);
        $url = str_replace('%KEY-WORD%', urlencode(str_replace(' ', '-', $keyword)), $url);
        return $url;
    }

    /**
     * Valid when empty (optional) OR it carries a keyword token and, once a
     * sample keyword is substituted, is a syntactically valid URL.
     */
    public static function isValidSearchUri($search_uri)
    {
        $search_uri = (string) $search_uri;
        if ($search_uri === '')
            return true;
        if (!self::hasKeywordToken($search_uri))
            return false;
        $probe = self::substitute($search_uri, 'test');
        return (bool) filter_var($probe, FILTER_VALIDATE_URL);
    }

    /**
     * If the URL has no keyword token, try to insert one by replacing the value
     * of a recognized search parameter (?q=, ?search=, ?keyword=, ...) with
     * %KEYWORD%. Users often paste a real search URL with an actual term instead
     * of the placeholder; this fixes the common case. Returns the URL unchanged
     * when it already has a token or has no recognizable parameter (then
     * validation rejects it). Only query parameters are handled — path-based
     * search URLs must use %KEYWORD% manually.
     */
    public static function normalize($search_uri)
    {
        $search_uri = trim((string) $search_uri);
        if ($search_uri === '' || self::hasKeywordToken($search_uri))
            return $search_uri;

        // Peel off the fragment, then the query string.
        $fragment = '';
        if (($h = strpos($search_uri, '#')) !== false)
        {
            $fragment = substr($search_uri, $h);
            $search_uri = substr($search_uri, 0, $h);
        }
        $qpos = strpos($search_uri, '?');
        if ($qpos === false)
            return $search_uri . $fragment;

        $base = substr($search_uri, 0, $qpos);
        $pairs = explode('&', substr($search_uri, $qpos + 1));

        // Pick the highest-priority known search parameter present.
        $best = null;
        $best_priority = PHP_INT_MAX;
        foreach ($pairs as $i => $pair)
        {
            $name = strtolower(explode('=', $pair, 2)[0]);
            $priority = array_search($name, self::KEYWORD_PARAMS, true);
            if ($priority !== false && $priority < $best_priority)
            {
                $best_priority = $priority;
                $best = $i;
            }
        }
        if ($best === null)
            return $base . '?' . implode('&', $pairs) . $fragment;

        // Preserve the original parameter name casing; replace only its value.
        $name = explode('=', $pairs[$best], 2)[0];
        $pairs[$best] = $name . '=%KEYWORD%';
        return $base . '?' . implode('&', $pairs) . $fragment;
    }
}
