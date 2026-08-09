<?php

namespace ContentEgg\application\components\feed;

defined('\ABSPATH') || exit;

use ContentEgg\application\helpers\LegacyTransientHelper;
use ContentEgg\application\helpers\TextHelper;

/**
 * FeedImportState class file
 *
 * Durable "when did this feed last import, and what happened" state, stored as a
 * single non-autoloaded option. This used to live in transients; on sites with a
 * persistent object cache (Redis, LiteSpeed Cache 7.8+) core stores transients
 * only in the cache, where they are evicted long before the requested lifetime --
 * which made every import look overdue and re-ran it hourly. Options are always
 * DB-backed, so the schedule survives cache flushes.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class FeedImportState
{
    const OPTION_PREFIX = 'cegg_feed_import_state_';

    /** Legacy transient keys, read once during migration. */
    const LEGACY_DATE_PREFIX = 'cegg_products_last_import_';
    const LEGACY_ERROR_PREFIX = 'cegg_last_import_error_';
    const LEGACY_NOTICE_PREFIX = 'cegg_last_import_notice_';

    const MESSAGE_MAX_LENGTH = 500;

    private $module_id;

    /** Set once the legacy keys have been probed and found empty, so reads stop re-probing. */
    private $migration_probed = false;

    /** Last row-level parse failure and how many there were, held until flushRowErrors(). */
    private $row_error = null;
    private $row_error_count = 0;

    public function __construct(string $module_id)
    {
        $this->module_id = $module_id;
    }

    public function optionName(): string
    {
        return self::OPTION_PREFIX . strtolower($this->module_id);
    }

    public static function defaults(): array
    {
        return array(
            'date' => 0,
            'error' => '',
            'notice' => '',
        );
    }

    public function get(): array
    {
        $data = \get_option($this->optionName(), null);

        if (!is_array($data))
        {
            $data = $this->migration_probed ? array() : $this->migrate();
        }

        return array_merge(self::defaults(), $data);
    }

    public function getDate(): int
    {
        return (int) $this->get()['date'];
    }

    public function setDate($time = null): void
    {
        if ($time === null)
        {
            $time = time();
        }

        $this->set('date', (int) $time);
    }

    public function getError(): string
    {
        return (string) $this->get()['error'];
    }

    public function setError(string $error): void
    {
        $this->set('error', TextHelper::truncate($error, self::MESSAGE_MAX_LENGTH));
    }

    public function getNotice(): string
    {
        return (string) $this->get()['notice'];
    }

    public function setNotice(string $notice): void
    {
        $this->set('notice', TextHelper::truncate($notice, self::MESSAGE_MAX_LENGTH));
    }

    /**
     * Note a parse failure for a single feed row without touching the database.
     *
     * A malformed feed can produce one of these per row, and only the last one is
     * ever shown, so writing each would mean an option read-modify-write per bad
     * row for a message nobody reads. Call flushRowErrors() once the parse ends.
     */
    public function recordRowError(string $message): void
    {
        $this->row_error = $message;
        $this->row_error_count++;
    }

    /** Persist the buffered row failures as one error. No-op when none were recorded. */
    public function flushRowErrors(): void
    {
        if ($this->row_error === null)
        {
            return;
        }

        $message = $this->row_error;

        if ($this->row_error_count > 1)
        {
            $suffix = sprintf(' (+%d more)', $this->row_error_count - 1);
            $message = TextHelper::truncate($message, self::MESSAGE_MAX_LENGTH - mb_strlen($suffix)) . $suffix;
        }

        $this->row_error = null;
        $this->row_error_count = 0;

        $this->setError($message);
    }

    public function reset(): void
    {
        \delete_option($this->optionName());
        $this->purgeLegacy();
        $this->migration_probed = true;
    }

    private function set(string $field, $value): void
    {
        $data = $this->get();
        $data[$field] = $value;
        $this->save($data);
    }

    private function save(array $data): void
    {
        // autoload=false: needed on feed screens and during cron, never on the front end.
        if (false === \add_option($this->optionName(), $data, '', false))
        {
            \update_option($this->optionName(), $data, false);
        }
    }

    /**
     * One-time read-through migration from the legacy transients.
     * Returns the migrated state, or an empty array when there was nothing to
     * migrate (in which case nothing is written).
     */
    private function migrate(): array
    {
        $this->migration_probed = true;

        $keys = $this->legacyKeys();

        $legacy = array();
        foreach ($keys as $field => $key)
        {
            $legacy[$field] = LegacyTransientHelper::read($key);
        }

        if ($legacy['date'] === false && $legacy['error'] === false && $legacy['notice'] === false)
        {
            return array();
        }

        $data = array(
            'date' => (int) $legacy['date'],
            'error' => is_string($legacy['error']) ? $legacy['error'] : '',
            'notice' => is_string($legacy['notice']) ? $legacy['notice'] : '',
        );

        $this->save($data);
        $this->purgeLegacy();

        return $data;
    }

    /** @return array<string,string> field => legacy transient key */
    private function legacyKeys(): array
    {
        return array(
            'date' => self::LEGACY_DATE_PREFIX . $this->module_id,
            'error' => self::LEGACY_ERROR_PREFIX . $this->module_id,
            'notice' => self::LEGACY_NOTICE_PREFIX . $this->module_id,
        );
    }

    private function purgeLegacy(): void
    {
        foreach ($this->legacyKeys() as $key)
        {
            LegacyTransientHelper::purge($key);
        }
    }
}
