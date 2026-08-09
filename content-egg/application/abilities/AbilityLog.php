<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * AbilityLog class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Ring-buffer audit log for ability executions (design spec §7): who ran
 * what, when, with which (secret-masked) input, and how it ended.
 */
final class AbilityLog
{
    const TABLE = 'cegg_ability_log';
    const OPT_DB_VERSION = 'cegg_ability_log_db';
    const DB_VERSION = '1';
    const MAX_ROWS = 1000;
    const PRUNE_EVERY = 50;

    public static function tableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    public static function ensureTable(): void
    {
        if (\get_option(self::OPT_DB_VERSION) === self::DB_VERSION)
        {
            return;
        }

        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::tableName();
        $charset_collate = $wpdb->get_charset_collate();

        \dbDelta("CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            ability VARCHAR(80) NOT NULL,
            status VARCHAR(10) NOT NULL,
            code VARCHAR(60) NOT NULL DEFAULT '',
            duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
            input_digest VARCHAR(255) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            KEY created_at (created_at)
        ) $charset_collate;");

        \update_option(self::OPT_DB_VERSION, self::DB_VERSION);
    }

    public static function record(string $ability, string $status, string $code, array $input, int $duration_ms): void
    {
        global $wpdb;

        self::ensureTable();

        $inserted = $wpdb->insert(self::tableName(), array(
            'created_at' => \current_time('mysql', true),
            'user_id' => (int) \get_current_user_id(),
            'ability' => $ability,
            'status' => $status,
            'code' => $code,
            'duration_ms' => $duration_ms,
            'input_digest' => self::digest($input),
        ));

        if ($inserted && $wpdb->insert_id && $wpdb->insert_id % self::PRUNE_EVERY === 0)
        {
            self::prune();
        }
    }

    public static function recent(int $limit = 50): array
    {
        global $wpdb;

        self::ensureTable();

        $sql = $wpdb->prepare('SELECT * FROM ' . self::tableName() . ' ORDER BY id DESC LIMIT %d', $limit);

        return (array) $wpdb->get_results($sql, ARRAY_A);
    }

    public static function digest(array $input): string
    {
        $masked = SecretMasker::maskArray($input);

        // Settings patches may carry credentials under keys the pattern
        // cannot know (module-specific names). Force-mask everything under
        // a 'settings' key — over-masking the digest is harmless.
        if (isset($masked['settings']) && is_array($masked['settings']))
        {
            $masked['settings'] = SecretMasker::maskArray($masked['settings'], true);
        }

        $json = function_exists('wp_json_encode')
            ? \wp_json_encode($masked)
            : json_encode($masked, JSON_UNESCAPED_UNICODE);

        return mb_substr((string) $json, 0, 255);
    }

    private static function prune(): void
    {
        global $wpdb;

        $max = (int) $wpdb->get_var('SELECT MAX(id) FROM ' . self::tableName());
        if ($max > self::MAX_ROWS)
        {
            $wpdb->query($wpdb->prepare('DELETE FROM ' . self::tableName() . ' WHERE id <= %d', $max - self::MAX_ROWS));
        }
    }
}
