<?php

namespace ContentEgg\application\admin\import;

defined('ABSPATH') || exit;

/**
 * CategoryMapper - resolves a product's feed category to one of the store's
 * own categories, using the ordered rule list saved on an import preset.
 *
 * Deliberately free of WordPress calls: the caller checks that the term still
 * exists, which keeps the matching rules unit-testable on their own.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class CategoryMapper
{
    /** Canonical separator every path is reduced to before comparing. */
    const SEPARATOR = ' > ';

    /**
     * @param array  $map      Rows of ['from' => string, 'to_cat' => int, 'to_woo_cat' => int]
     * @param array  $product  Import product, read for 'categoryPath' and 'category'
     * @param string $taxonomy 'product_cat' or 'category'
     *
     * @return int Term ID, or 0 when no rule matches.
     */
    public static function resolve(array $map, array $product, string $taxonomy): int
    {
        $candidates = self::candidates($product);

        if (!$candidates)
        {
            return 0;
        }

        $target_key = ('product_cat' === $taxonomy) ? 'to_woo_cat' : 'to_cat';

        foreach ($map as $rule)
        {
            if (!is_array($rule))
            {
                continue;
            }

            $from = self::normalize((string) ($rule['from'] ?? ''));
            $to   = (int) ($rule[$target_key] ?? 0);

            // A row targeting only the other taxonomy is not a match here, it
            // is simply not a rule for this import.
            if ('' === $from || $to <= 0)
            {
                continue;
            }

            if (self::matches($from, $candidates))
            {
                return $to;
            }
        }

        return 0;
    }

    /**
     * Full path first, then the leaf, so a rule copied from the feed
     * ("home > bathroom > storage") and one typed by hand ("storage") both land.
     *
     * @return string[] Index 0 is always the full path when there is one.
     */
    private static function candidates(array $product): array
    {
        $path = [];

        if (!empty($product['categoryPath']) && is_array($product['categoryPath']))
        {
            $path = $product['categoryPath'];
        }
        elseif (!empty($product['category']))
        {
            $path = [$product['category']];
        }

        $path = array_map('trim', array_map('strval', $path));
        $path = array_values(array_filter($path, 'strlen'));

        if (!$path)
        {
            return [];
        }

        $full = self::normalize(join(self::SEPARATOR, $path));
        $leaf = self::normalize((string) end($path));

        return ($full === $leaf) ? [$full] : [$full, $leaf];
    }

    private static function matches(string $rule, array $candidates): bool
    {
        if ('*' === substr($rule, -1))
        {
            $prefix = rtrim(substr($rule, 0, -1));

            // A bare "*" would map every product in the feed by accident.
            if ('' === $prefix)
            {
                return false;
            }

            // A wildcard names a branch, so it is anchored to the full path.
            // Testing it against the leaf would make "Bathroom*" match
            // "Kitchen > Bathroom Scales".
            return 0 === strpos($candidates[0], $prefix);
        }

        return in_array($rule, $candidates, true);
    }

    /**
     * Lowercases and reduces "|" and ">" to one separator, so "Home>Bathroom",
     * "Home | Bathroom" and "home > bathroom" are all the same rule.
     *
     * The patterns are ASCII on purpose: the /u modifier would make
     * preg_replace return null on a feed row with broken UTF-8, silently
     * turning the value into an empty string.
     */
    private static function normalize(string $value): string
    {
        $value = str_replace('|', '>', $value);
        $value = (string) preg_replace('/\s*>\s*/', self::SEPARATOR, $value);
        $value = (string) preg_replace('/\s+/', ' ', $value);
        $value = trim($value);

        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}
