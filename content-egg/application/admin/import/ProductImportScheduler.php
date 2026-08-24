<?php

namespace ContentEgg\application\admin\import;

defined('\ABSPATH') || exit;

use ContentEgg\application\models\ImportQueueModel;
use ContentEgg\application\Plugin;

/**
 * ProductImportScheduler class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 *
 */

class ProductImportScheduler
{
    const CRON_TAG_HEARTBEAT = 'cegg_import_failsafe_heartbeat';
    const CRON_TAG_BATCH = 'cegg_run_import_batch';
    const BATCH_SIZE = 3;

    /**
     * Ceiling for a batch sized from measured history. Higher than BATCH_SIZE
     * on purpose: a bigger batch has to be earned by jobs that have actually
     * been observed to be fast, never assumed from the preset's shape.
     */
    const MAX_BATCH_SIZE = 5;

    const LOCK_KEY = 'cegg_product_import_batch_lock';

    /** Seconds the lock outlives the PHP time limit it was sized against. */
    const LOCK_MARGIN = 300;

    /**
     * Token of the lock held by THIS process, empty when it holds none.
     * The lock stores a per-run token rather than a flag so a process can
     * only ever refresh or release a lock it actually owns — releasing
     * blindly would let a run that overran its own lock delete the lock of
     * the run that legitimately took over.
     */
    private static $lockToken = '';

    /** TTL the current lock was acquired with, reused when refreshing. */
    private static $lockTtl = 0;

    public static function initAction()
    {
        self::initSchedule();

        add_action(self::CRON_TAG_HEARTBEAT, [__CLASS__, 'processImportBatch']);
        add_action(self::CRON_TAG_BATCH, [__CLASS__, 'processImportBatch']);
    }

    /**
     * Seconds to wait before the next batch runs.
     *
     * Batch sizing keeps a single run inside its time budget, but says nothing
     * about how hard the queue runs overall — back-to-back batches with only a
     * few seconds between them keep a box at a near-100% duty cycle. Raising
     * this trades import throughput for CPU headroom, which is the right trade
     * on shared hosting or when other background work (gallery downloads,
     * Action Scheduler) is competing for the same cores.
     */
    public static function getNextBatchDelay(): int
    {
        return max(1, (int) apply_filters('cegg_import_next_batch_delay', 5));
    }

    public static function addScheduleEvent($recurrence = 'ten_min')
    {
        if (!wp_next_scheduled(self::CRON_TAG_BATCH))
        {
            wp_schedule_single_event(time() + self::getNextBatchDelay(), self::CRON_TAG_BATCH);
        }

        if (!wp_next_scheduled(self::CRON_TAG_HEARTBEAT))
        {
            wp_schedule_event(time() + 60, $recurrence, self::CRON_TAG_HEARTBEAT);
        }
    }

    /**
     * Batch size for the run about to claim jobs, sized so the batch fits a
     * target wall-clock budget for one cron run.
     *
     * Cost per job is taken from what jobs of this preset have actually cost
     * on this site, because it cannot be predicted from the preset: the same
     * preset has been measured at ~2s per job on one host and ~21s on another,
     * and a preset with no AI at all is not necessarily cheap — most of a
     * WooCommerce import is the product save and whatever hooks the site's
     * other plugins attach to it.
     *
     * Only until that history exists does this fall back to estimating from
     * the preset's AI call count, which is a guess and is capped accordingly.
     */
    public static function getBatchSize(?int $presetId = null)
    {
        $size   = self::BATCH_SIZE;
        $preset = $presetId ? PresetRepository::get($presetId) : null;

        if ($preset)
        {
            $timeBudget = (float) apply_filters('cegg_import_batch_time_budget', 180);
            $measured   = ImportQueueModel::model()->medianProcessingTime(
                (int) $presetId,
                (int) apply_filters('cegg_import_batch_sample_size', 10)
            );

            if ($measured > 0)
            {
                $ceiling = (int) apply_filters('cegg_import_max_batch_size', self::MAX_BATCH_SIZE);
                $size    = max(1, min($ceiling, (int) floor($timeBudget / $measured)));
            }
            else
            {
                // No history yet. Estimate from AI calls — the only cost
                // visible in the preset itself — and never exceed the default.
                $aiCalls = PresetNormalizer::estimateAiCallCount($preset);

                if ($aiCalls > 0)
                {
                    $avgCallSeconds = (float) apply_filters('cegg_import_ai_avg_call_seconds', 16);
                    $size = max(1, min(self::BATCH_SIZE, (int) floor($timeBudget / ($aiCalls * $avgCallSeconds))));
                }
            }
        }

        return (int) apply_filters('cegg_product_import_batch_size', $size, $presetId, $preset);
    }

    /**
     * PHP execution-time budget for the batch about to run. getBatchSize()
     * throttles the AVERAGE case, but each AI call still has its own
     * AiClient::TIMEOUT ceiling, so a single product's theoretical worst
     * case (batch size x AI calls per product x that ceiling) can still
     * exceed a flat limit — e.g. one product with 7 AI steps already
     * exceeds 1200s on its own (7 x 180s = 1260s) even at batch size 1.
     * Raised only when that worst case actually exceeds the floor, so an
     * AI-heavy preset can't hit PHP's own execution-time fatal — which,
     * unlike a thrown exception, isn't caught and leaves the job stuck.
     */
    private static function estimateTimeLimit(?int $presetId, int $batchSize): int
    {
        $floor = (int) apply_filters('cegg_import_batch_time_limit_floor', 1200);

        $preset = $presetId ? PresetRepository::get($presetId) : null;

        if (!$preset)
        {
            return $floor;
        }

        $worstCase = 0;
        $aiCalls   = PresetNormalizer::estimateAiCallCount($preset);

        if ($aiCalls > 0)
        {
            $maxCallSeconds = (float) apply_filters(
                'cegg_import_ai_max_call_seconds',
                \ContentEgg\application\components\ai\AiClient::TIMEOUT
            );

            $worstCase = (int) ceil($batchSize * $aiCalls * $maxCallSeconds);
        }

        // Measured cost covers what the AI estimate cannot see — image work,
        // the WooCommerce save, other plugins' hooks — so a preset with no AI
        // at all still gets a limit that reflects what its jobs really take.
        $measured = ImportQueueModel::model()->medianProcessingTime(
            (int) $presetId,
            (int) apply_filters('cegg_import_batch_sample_size', 10)
        );

        if ($measured > 0)
        {
            $safety    = (float) apply_filters('cegg_import_batch_time_safety_factor', 4);
            $worstCase = max($worstCase, (int) ceil($batchSize * $measured * $safety));
        }

        if ($worstCase <= 0)
        {
            return $floor;
        }

        $margin = (int) apply_filters('cegg_import_batch_time_margin', 120);

        return max($floor, $worstCase + $margin);
    }

    public static function initSchedule()
    {
        add_filter('cron_schedules', [__CLASS__, 'addSchedule']);
    }

    public static function addSchedule($schedules)
    {
        $schedules['ten_min'] = [
            'interval' => 600,
            'display'  => __('Every 10 minutes', 'content-egg'),
        ];
        return $schedules;
    }

    /**
     * Takes the batch lock, sized to outlive the run it is guarding.
     *
     * A fixed 15-minute lock used to expire while a slow AI batch was still
     * running, so the next cron tick started a second batch on top of the
     * first — each overlap slowing the others until the account ran out of
     * CPU and processes. The TTL now follows the same worst case the PHP
     * time limit is sized against, and the heartbeat extends it as the run
     * progresses.
     */
    private static function acquireLock(int $ttl): bool
    {
        if (get_transient(self::LOCK_KEY))
        {
            return false;
        }

        $token = uniqid('cegg_import_', true);

        set_transient(self::LOCK_KEY, $token, $ttl);

        self::$lockToken = $token;
        self::$lockTtl   = $ttl;

        return true;
    }

    /**
     * Extends this process's lock. Called from the import service's
     * heartbeat, so a run that legitimately takes longer than estimated
     * keeps its lock instead of having a second run start underneath it.
     */
    public static function refreshLock(): void
    {
        if (!self::$lockToken)
        {
            return;
        }

        // Lost to another process — do not stamp over the new owner's lock.
        if (get_transient(self::LOCK_KEY) !== self::$lockToken)
        {
            self::$lockToken = '';
            return;
        }

        set_transient(self::LOCK_KEY, self::$lockToken, self::$lockTtl);
    }

    private static function releaseLock(): void
    {
        if (self::$lockToken && get_transient(self::LOCK_KEY) === self::$lockToken)
        {
            delete_transient(self::LOCK_KEY);
        }

        self::$lockToken = '';
        self::$lockTtl   = 0;
    }

    /**
     * Whether this run finished the last job in the queue.
     *
     * The drain branch of processImportBatch() is also reached by a tick that
     * claimed nothing, so "the queue is empty" on its own does not mean an
     * import just ended — it is the normal state of an idle site. Only a run
     * that processed jobs and left nothing behind has actually drained.
     */
    public static function shouldSignalDrain(int $processed, bool $inProgress): bool
    {
        return $processed > 0 && !$inProgress;
    }

    public static function processImportBatch()
    {
        // prevent multiple instances
        if (get_transient(self::LOCK_KEY))
        {
            return;
        }

        $presetId  = ImportQueueModel::model()->peekNextPendingPresetId();
        $batchSize = self::getBatchSize($presetId);
        $timeLimit = self::estimateTimeLimit($presetId, $batchSize);

        if (!self::acquireLock($timeLimit + self::LOCK_MARGIN))
        {
            return;
        }

        $processed = 0;

        try
        {
            @set_time_limit($timeLimit);

            $service = new \ContentEgg\application\admin\import\ProductImportService();
            $processed = $service->processBatch($batchSize);
        }
        finally
        {
            self::releaseLock();
        }

        $inProgress = ImportQueueModel::model()->isInProgress();

        // If there are still posts to process, queue the next batch
        if ($inProgress)
        {
            if (!wp_next_scheduled(self::CRON_TAG_BATCH))
            {
                wp_schedule_single_event(time() + self::getNextBatchDelay(), self::CRON_TAG_BATCH);
            }
        }
        else
        {
            ProductImportScheduler::clearScheduleEvents();
        }

        if (self::shouldSignalDrain($processed, $inProgress))
        {
            /**
             * The import queue has just gone empty, in the same process that
             * finished the last job. This is where a purge belongs on a site
             * that suppressed purging for the length of the import — see the
             * recipe on 'cegg_import_batch_start' in ProductImportService.
             */
            do_action('cegg_import_queue_drained');
        }
    }

    public static function clearScheduleEvent()
    {
        self::clearScheduleEvents();
    }

    public static function clearScheduleEvents()
    {
        if (wp_next_scheduled(self::CRON_TAG_HEARTBEAT))
        {
            wp_clear_scheduled_hook(self::CRON_TAG_HEARTBEAT);
        }

        if (wp_next_scheduled(self::CRON_TAG_BATCH))
        {
            wp_clear_scheduled_hook(self::CRON_TAG_BATCH);
        }

        // Stopping the queue outright clears the lock whoever owns it.
        delete_transient(self::LOCK_KEY);
        self::$lockToken = '';
        self::$lockTtl   = 0;
    }

    public static function maybeAddScheduleEvent()
    {
        $queue = ImportQueueModel::model();

        if ($queue->isInProgress())
        {
            ProductImportScheduler::addScheduleEvent();
        }
    }
}
