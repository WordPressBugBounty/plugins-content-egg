<?php

namespace ContentEgg\application\admin;

defined('\ABSPATH') || exit;

use ContentEgg\application\models\ProductMapModel;
use ContentEgg\application\models\ProductModel;

/**
 * BridgeBackfillService class file
 *
 * Creates canonical Bridge Page mappings for pages the import tool already
 * created. All database access for the back-fill lives here; every decision
 * lives in BridgeBackfillPlanner.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class BridgeBackfillService
{
    /** Stamp the import tool writes on every page it creates. */
    const IMPORT_META = '_cegg_import_unique_id';

    /** Pages read per query while gathering. Matches ProductModel::scanProducts(). */
    const BATCH = 200;

    /** Option holding the last run's receipt, so it can be undone. */
    const RECEIPT_OPTION = 'cegg_bridge_backfill_last_run';

    /**
     * Examine every page the import tool created and decide what to map.
     *
     * @return array planner buckets plus 'stamped' => number of pages examined
     */
    public static function plan(): array
    {
        global $wpdb;

        $canonicalIndex = self::canonicalIndex();

        $rows    = array();
        $offset  = 0;
        $stamped = 0;

        while (true)
        {
            $batch = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT p.ID AS post_id, p.post_status, pm.meta_value AS unique_id
                       FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                      WHERE pm.meta_key = %s AND pm.meta_value <> ''
                   ORDER BY p.ID
                      LIMIT %d OFFSET %d",
                    self::IMPORT_META,
                    self::BATCH,
                    $offset
                ),
                ARRAY_A
            );

            if (!$batch)
            {
                break;
            }

            $stamped += count($batch);
            $rows     = array_merge($rows, self::resolveBatch($batch));
            $offset  += self::BATCH;
        }

        $plan = BridgeBackfillPlanner::plan($rows, $canonicalIndex);
        $plan['stamped'] = $stamped;

        // Reporting only: how many of the planned mappings redirect something
        // today, versus how many wait for the product to be used elsewhere.
        $plan['active_now'] = BridgeBackfillPlanner::countActive($plan['insert'], self::placements($plan['insert']));
        $plan['scanned_at'] = (int) ProductModel::model()->getLastSync();

        return $plan;
    }

    /**
     * Where each planned product is currently used, from the scanned product
     * index behind the All Products screen (unique_id and post_id are both
     * indexed there, so this stays cheap).
     *
     * That index is a cache refreshed by "Scan Products" and may lag reality,
     * which is why it informs the report and never the plan.
     *
     * @param array $insert rows from plan()['insert']
     * @return array<string,int[]> "module_id|unique_id" => post ids
     */
    private static function placements(array $insert): array
    {
        global $wpdb;

        if (!$insert)
        {
            return array();
        }

        $table = ProductModel::model()->tableName();

        $unique_ids = array_values(array_unique(array_map(
            function ($row)
            {
                return (string) $row['unique_id'];
            },
            $insert
        )));

        $out = array();

        foreach (array_chunk($unique_ids, 500) as $chunk)
        {
            $placeholders = implode(',', array_fill(0, count($chunk), '%s'));

            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT module_id, unique_id, post_id FROM {$table} WHERE unique_id IN ({$placeholders})",
                    $chunk
                ),
                ARRAY_A
            );

            foreach ((array) $rows as $row)
            {
                $out[$row['module_id'] . '|' . $row['unique_id']][] = (int) $row['post_id'];
            }
        }

        return $out;
    }

    /**
     * Create the canonical rows the plan proposes, and record what was written
     * so the run can be undone.
     *
     * @param array $plan from plan()
     * @return array{created:int,failed:int}
     */
    public static function apply(array $plan): array
    {
        $map     = ProductMapModel::model();
        $created = 0;
        $failed  = 0;
        $receipt = array();

        foreach ((array) $plan['insert'] as $row)
        {
            try
            {
                $id = $map->setCanonical(
                    (string) $row['module_id'],
                    (string) $row['unique_id'],
                    (int) $row['target_post_id']
                );

                if ($id > 0)
                {
                    $created++;
                    $receipt[] = array((int) $id, (int) $row['target_post_id']);
                }
                else
                {
                    $failed++;
                }
            }
            catch (\Throwable $e)
            {
                $failed++;
            }
        }

        if ($receipt)
        {
            \update_option(
                self::RECEIPT_OPTION,
                array('time' => time(), 'rows' => $receipt),
                false
            );
        }

        return array('created' => $created, 'failed' => $failed);
    }

    /**
     * The last run's receipt, or null when there is nothing to undo.
     */
    public static function receipt(): ?array
    {
        $receipt = \get_option(self::RECEIPT_OPTION);

        if (!is_array($receipt) || empty($receipt['rows']) || !is_array($receipt['rows']))
        {
            return null;
        }

        return $receipt;
    }

    /**
     * Remove the mappings the last run created.
     *
     * Deletes by row id AND target_post_id, so a mapping the user has since
     * re-pointed at a different page survives the undo.
     *
     * @return int mappings removed
     */
    public static function undo(): int
    {
        global $wpdb;

        $receipt = self::receipt();
        if (!$receipt)
        {
            return 0;
        }

        $table   = ProductMapModel::model()->tableName();
        $removed = 0;

        foreach ($receipt['rows'] as $row)
        {
            if (!is_array($row) || count($row) < 2)
            {
                continue;
            }

            $removed += (int) $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table}
                      WHERE id = %d AND target_post_id = %d AND source_post_id = %d",
                    (int) $row[0],
                    (int) $row[1],
                    ProductMapModel::CANONICAL_SOURCE
                )
            );
        }

        \delete_option(self::RECEIPT_OPTION);

        return $removed;
    }

    /**
     * Reduce one batch of stamped pages to light rows, reading each page's
     * module data and discarding it as soon as the module is resolved — the
     * item arrays are far too large to hold for a whole catalog.
     *
     * @param array $batch rows of post_id, post_status, unique_id
     * @return array rows shaped for BridgeBackfillPlanner::plan()
     */
    private static function resolveBatch(array $batch): array
    {
        global $wpdb;

        $post_ids = array_map('intval', \wp_list_pluck($batch, 'post_id'));
        $in       = implode(',', $post_ids);

        // Safe to interpolate: every value passed through intval above.
        $metas = $wpdb->get_results(
            "SELECT post_id, meta_key, meta_value
               FROM {$wpdb->postmeta}
              WHERE post_id IN ({$in})
                AND meta_key LIKE '\_cegg\_data\_%'",
            ARRAY_A
        );

        $byPost = array();
        foreach ((array) $metas as $meta)
        {
            $module_id = substr((string) $meta['meta_key'], strlen('_cegg_data_'));
            $data      = @unserialize((string) $meta['meta_value']);

            if ($module_id === '' || !is_array($data))
            {
                continue;
            }

            $byPost[(int) $meta['post_id']][$module_id] = $data;
        }

        $rows = array();
        foreach ($batch as $row)
        {
            $post_id = (int) $row['post_id'];

            $rows[] = array(
                'post_id'     => $post_id,
                'post_status' => (string) $row['post_status'],
                'unique_id'   => (string) $row['unique_id'],
                'modules'     => BridgeBackfillPlanner::matchModules(
                    (string) $row['unique_id'],
                    isset($byPost[$post_id]) ? $byPost[$post_id] : array()
                ),
            );
        }

        return $rows;
    }

    /**
     * Every canonical mapping that already exists, keyed "module_id|unique_id".
     *
     * @return array<string,int>
     */
    private static function canonicalIndex(): array
    {
        global $wpdb;

        $table = ProductMapModel::model()->tableName();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT module_id, unique_id, target_post_id FROM {$table} WHERE source_post_id = %d",
                ProductMapModel::CANONICAL_SOURCE
            ),
            ARRAY_A
        );

        $index = array();
        foreach ((array) $rows as $row)
        {
            $index[$row['module_id'] . '|' . $row['unique_id']] = (int) $row['target_post_id'];
        }

        return $index;
    }
}
