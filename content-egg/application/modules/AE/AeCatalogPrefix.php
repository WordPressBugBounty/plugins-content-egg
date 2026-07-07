<?php

namespace ContentEgg\application\modules\AE;

defined('\ABSPATH') || exit;

/**
 * Parses the optional listing prefix a user can put in front of a URL to
 * bulk-import products from a category / search / archive page:
 *
 *   [import limit=3]https://shop.com/category    (primary)
 *   [catalog limit=3]https://shop.com/category   (backward-compatible alias)
 *
 * Both prefixes are equivalent; [import] is the current documented form and
 * [catalog] is kept working for existing content. FILTER_SANITIZE_URL applied
 * upstream (ModuleApi, ContentManager::sanitizeKeyword) can strip the space
 * ("[importlimit=3]"), so the regex tolerates a missing space too.
 *
 * Pure PHP with no WordPress or Affiliate Egg dependency, so it is unit-testable
 * standalone (see tests/ae/).
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class AeCatalogPrefix
{
    /**
     * @param string $keyword       the raw keyword the user entered
     * @param int    $default_limit  product limit to use when the prefix omits one
     * @return array|null  null when no listing prefix is present, otherwise
     *   array('url' => string, 'limit' => int)
     */
    public static function parse($keyword, $default_limit)
    {
        $keyword = (string) $keyword;
        if ($keyword === '' || $keyword[0] !== '[')
            return null;

        // [import] is the primary prefix; [catalog] is a backward-compatible alias.
        if (!\preg_match('/^\[(?:import|catalog)(.*?)\](.+)/', $keyword, $m))
            return null;

        $limit = (int) $default_limit;
        if ($m[1] !== '' && \preg_match('/limit\s*=\s*["\']?(\d+)/', $m[1], $lm))
            $limit = (int) $lm[1];

        return array(
            'url'   => \trim($m[2]),
            'limit' => $limit,
        );
    }
}
