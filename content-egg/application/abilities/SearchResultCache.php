<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * SearchResultCache class file
 *
 * Short-lived server-side store for search-ability results (rehydrate-by-id
 * design spec 2026-07-30): each search stores its fullItem()-mapped results in
 * a transient under a random token, so add-* calls can name items by unique_id
 * and the server — not the agent — supplies the stored content. Tokens are
 * bound to the user who searched. Transients ride the persistent object cache
 * (Redis/Memcached) where one is configured, the options table otherwise.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class SearchResultCache
{
    const TTL = 1800; // 30 minutes
    const PREFIX = 'cegg_search_';

    /**
     * Store one search's items; returns the token for the search response, or
     * '' when the backing store rejects the write (transient wrappers return
     * false on failure, e.g. a full/unreachable persistent object cache).
     * $items must be fullItem()-mapped arrays; entries without a unique_id are
     * skipped (they could never be referenced).
     */
    public static function store(string $module_id, string $keyword, array $items): string
    {
        $token = 'st_' . bin2hex(random_bytes(16));

        $map = array();
        foreach ($items as $item)
        {
            $uid = (string) (is_array($item) ? ($item['unique_id'] ?? '') : '');
            if ($uid === '')
            {
                continue;
            }
            $map[$uid] = $item;
        }

        // Floor guards against a 0/negative filter return creating a never-expiring autoloaded transient.
        $ttl = max(60, (int) \apply_filters('content_egg_abilities_search_cache_ttl', self::TTL));
        $stored = \set_transient(self::PREFIX . $token, array(
            'user_id' => (int) \get_current_user_id(),
            'module_id' => $module_id,
            'keyword' => $keyword,
            'items' => $map,
        ), $ttl);

        if (!$stored)
        {
            return '';
        }

        return $token;
    }

    /**
     * Payload for a token ({user_id, module_id, keyword, items: uid => item}),
     * or null when the token is unknown/expired or belongs to another user.
     */
    public static function get(string $token): ?array
    {
        if (!preg_match('/^st_[0-9a-f]{32}$/', $token))
        {
            return null;
        }

        $payload = \get_transient(self::PREFIX . $token);
        if (!is_array($payload))
        {
            return null;
        }

        if ((int) ($payload['user_id'] ?? -1) !== (int) \get_current_user_id())
        {
            return null;
        }

        return $payload;
    }
}
