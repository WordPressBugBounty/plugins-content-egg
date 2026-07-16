<?php

namespace ContentEgg\application\components\feed;

defined('\ABSPATH') || exit;

/**
 * FeedImportStatus class file
 *
 * Progress heartbeat and crash-aware lock for feed imports. Stored as a
 * non-autoloaded option (survives object-cache flushes, unlike transients).
 * An import is considered running only while its heartbeat is fresh, so
 * crashed imports self-recover after STALE_AFTER seconds.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class FeedImportStatus
{
    const OPTION_PREFIX = 'cegg_feed_import_status_';
    const STALE_AFTER = 180;

    const STATE_IDLE = 'idle';
    const STATE_RUNNING = 'running';
    const STATE_COMPLETED = 'completed';
    const STATE_FAILED = 'failed';

    private $module_id;

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
            'state' => self::STATE_IDLE,
            'rows_read' => 0,
            'inserted' => 0,
            'skipped' => 0,
            'bytes_done' => 0,
            'bytes_total' => 0,
            'started_at' => 0,
            'heartbeat_at' => 0,
            'error' => '',
        );
    }

    public function get(): array
    {
        $data = \get_option($this->optionName(), array());
        if (!is_array($data))
        {
            $data = array();
        }

        return array_merge(self::defaults(), $data);
    }

    public function isRunning(): bool
    {
        $data = $this->get();

        return $data['state'] === self::STATE_RUNNING
            && (time() - (int) $data['heartbeat_at']) < self::STALE_AFTER;
    }

    /**
     * Claim the import lock. Returns false when another import is genuinely
     * running (fresh heartbeat); stale locks are taken over.
     */
    public function start(int $bytes_total = 0): bool
    {
        if ($this->isRunning())
        {
            return false;
        }

        $this->save(array_merge(self::defaults(), array(
            'state' => self::STATE_RUNNING,
            'bytes_total' => $bytes_total,
            'started_at' => time(),
            'heartbeat_at' => time(),
        )));

        return true;
    }

    public function tick(array $fields = array()): void
    {
        $data = $this->get();
        if ($data['state'] !== self::STATE_RUNNING)
        {
            return;
        }

        $fields['heartbeat_at'] = time();
        $this->save(array_merge($data, $fields));
    }

    public function finish(array $fields = array()): void
    {
        $fields['state'] = self::STATE_COMPLETED;
        $fields['heartbeat_at'] = time();
        $this->save(array_merge($this->get(), $fields));
    }

    public function fail(string $error, array $fields = array()): void
    {
        $fields['state'] = self::STATE_FAILED;
        $fields['error'] = $error;
        $fields['heartbeat_at'] = time();
        $this->save(array_merge($this->get(), $fields));
    }

    public function reset(): void
    {
        \delete_option($this->optionName());
    }

    private function save(array $data): void
    {
        // autoload=false: written frequently during imports, never needed
        // on general page loads.
        if (false === \add_option($this->optionName(), $data, '', false))
        {
            \update_option($this->optionName(), $data, false);
        }
    }
}
