<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

/**
 * DealVotes class file
 *
 * Votes are not money and nothing spends them, so the guards aim at making
 * abuse tedious rather than impossible - the same trade Cashback Tracker's
 * CouponVotes makes: one vote per deal per browser, a per-IP ceiling, and a
 * user record when voting is restricted to logged-in users.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealVotes
{
    const COOKIE_PREFIX = 'cegg_dv_';
    const COOKIE_DAYS = 180;
    const RATE_PREFIX = 'cegg_deal_vote_rl_';
    const RATE_LIMIT = 30;
    const USER_META = '_cegg_deal_votes';
    const VOTER_META = '_cegg_deal_voter';

    public static function score(int $up, int $down): int
    {
        return $up - $down;
    }

    public static function cookieName(int $id): string
    {
        return self::COOKIE_PREFIX . $id;
    }

    public static function hasVoted(int $id): bool
    {
        return isset($_COOKIE[self::cookieName($id)]);
    }

    public static function markVoted(int $id, string $dir): void
    {
        $name = self::cookieName($id);
        $_COOKIE[$name] = $dir;

        // Not httponly: deals.js reads it to grey out the buttons on a cached page.
        if (!headers_sent())
            setcookie($name, $dir, time() + self::COOKIE_DAYS * DAY_IN_SECONDS, '/');
    }

    public static function userHasVoted(int $user_id, int $id): bool
    {
        return in_array($id, array_map('intval', (array) \get_user_meta($user_id, self::USER_META, true)), true);
    }

    public static function markUserVoted(int $user_id, int $id): void
    {
        $ids = array_map('intval', (array) \get_user_meta($user_id, self::USER_META, true));
        $ids[] = $id;
        \update_user_meta($user_id, self::USER_META, array_values(array_unique($ids)));
    }

    /**
     * REMOTE_ADDR only: X-Forwarded-For is attacker-controlled. A site behind a
     * proxy or CDN, where REMOTE_ADDR is the proxy, says where the client
     * address is with the cegg_deal_vote_client_ip filter.
     */
    public static function clientIp(): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';

        return (string) \apply_filters('cegg_deal_vote_client_ip', $ip);
    }

    /**
     * One vote per deal per voter network, whatever the cookies say. The row
     * holds a keyed hash, never the address, and goes with the deal. A single
     * INSERT ... WHERE NOT EXISTS, so two requests racing cannot both win.
     */
    public static function claimVoter(int $post_id, string $ip): bool
    {
        $network = self::voterNetwork($ip);
        if ($network === '')
            return true;

        global $wpdb;
        $hash = \wp_hash('cegg_deal_voter|' . $network);
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
             SELECT %d, %s, %s FROM DUAL
             WHERE NOT EXISTS (SELECT 1 FROM (SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s AND meta_value = %s) AS seen)",
            $post_id, self::VOTER_META, $hash, $post_id, self::VOTER_META, $hash
        ));
        \wp_cache_delete($post_id, 'post_meta');

        return (int) $inserted === 1;
    }

    /**
     * What one voter is, network-wise: an IPv4 address, or an IPv6 /64 - one
     * ISP customer gets a whole /64, so the host half is free to rotate.
     */
    public static function voterNetwork(string $ip): string
    {
        $bin = @inet_pton($ip);
        if ($bin === false)
            return '';

        if (strlen($bin) === 16)
            return (string) inet_ntop(substr($bin, 0, 8) . str_repeat("\0", 8)) . '/64';

        return (string) inet_ntop($bin);
    }

    public static function rateLimited(string $ip): bool
    {
        if ($ip === '')
            return false;

        $key = self::RATE_PREFIX . md5($ip);
        $hits = (int) \get_transient($key);

        if ($hits >= self::RATE_LIMIT)
            return true;

        \set_transient($key, $hits + 1, HOUR_IN_SECONDS);

        return false;
    }

    /**
     * One UPDATE per counter, so two votes landing together both count - a
     * read-then-write would lose one.
     */
    public static function record(int $post_id, string $dir): array
    {
        $key = $dir === 'down' ? DealPostType::META_VOTES_DOWN : DealPostType::META_VOTES_UP;

        global $wpdb;
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET meta_value = CAST(meta_value AS SIGNED) + 1 WHERE post_id = %d AND meta_key = %s",
            $post_id,
            $key
        ));

        if (!$updated)
            \add_post_meta($post_id, $key, 1, true);

        \wp_cache_delete($post_id, 'post_meta');

        $up = (int) \get_post_meta($post_id, DealPostType::META_VOTES_UP, true);
        $down = (int) \get_post_meta($post_id, DealPostType::META_VOTES_DOWN, true);
        $score = self::score($up, $down);
        \update_post_meta($post_id, DealPostType::META_SCORE, $score);

        return array('up' => $up, 'down' => $down, 'score' => $score);
    }
}
