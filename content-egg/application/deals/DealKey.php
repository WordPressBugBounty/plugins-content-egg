<?php

namespace ContentEgg\application\deals;

use ContentEgg\application\helpers\Gtin;

defined('\ABSPATH') || exit;

/**
 * DealKey class file
 *
 * The identity of the offer a deal is pinned to, as scalar strings a meta
 * query can match: the module item key, the normalized product URL (the only
 * stable identity of a manual Offer item) and a GTIN hint. Pure.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealKey
{
    /** Query parameters that track the visit, not the product. */
    const TRACKING_PARAMS = array(
        'gclid', 'fbclid', 'msclkid', 'tag', 'ref', 'ref_', 'linkcode', 'linkid', 'psc', 'th',
        '_skw', 'hash', 'amdata', 'mkcid', 'mkrid', 'campid', 'customid', 'toolid',
    );

    public static function key(string $module_id, string $unique_id): string
    {
        if ($module_id === '' || $unique_id === '')
            return '';

        return $module_id . '|' . $unique_id;
    }

    /**
     * Lowercase scheme and host without "www.", no fragment, no trailing
     * slash, tracking parameters dropped and the rest sorted. Empty for
     * anything that is not an http(s) URL.
     */
    public static function urlKey(string $url): string
    {
        $parts = parse_url(trim($url));
        if (!is_array($parts) || empty($parts['host']) || empty($parts['scheme']))
            return '';

        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https')
            return '';

        $host = strtolower($parts['host']);
        if (strpos($host, 'www.') === 0)
            $host = substr($host, 4);

        $path = rtrim($parts['path'] ?? '', '/');

        $query = array();
        if (!empty($parts['query']))
        {
            parse_str($parts['query'], $params);
            foreach ($params as $name => $value)
            {
                $lower = strtolower((string) $name);
                if (strpos($lower, 'utm_') === 0 || in_array($lower, self::TRACKING_PARAMS, true))
                    continue;
                $query[$name] = $value;
            }
            ksort($query);
        }

        return $scheme . '://' . $host . $path . ($query ? '?' . http_build_query($query) : '');
    }

    /**
     * @return array{key: string, url_key: string, gtin: string}
     */
    public static function fromItem(string $module_id, array $item): array
    {
        $url = (string) ($item['orig_url'] ?? '');
        if ($url === '')
            $url = (string) ($item['url'] ?? '');

        $gtins = Gtin::fromItem($item);

        return array(
            'key' => self::key($module_id, (string) ($item['unique_id'] ?? '')),
            'url_key' => self::urlKey($url),
            'gtin' => $gtins ? (string) $gtins[0] : '',
        );
    }
}
