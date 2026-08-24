<?php

namespace ContentEgg\application\admin;

defined('\ABSPATH') || exit;

/**
 * BridgeBackfillPlanner class file
 *
 * Decides which canonical Bridge Page mappings a back-fill should create.
 * Pure: no database, no WordPress, no side effects — every input arrives as an
 * argument, so the whole decision surface is unit-testable.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class BridgeBackfillPlanner
{
    /**
     * Which modules on an imported page contain the product the page was
     * imported from?
     *
     * A page usually holds more than one module's data, because price
     * comparison saves competing offers onto it, so the module cannot be taken
     * from the meta key alone — it is the one whose items actually carry this
     * unique_id. Raw _cegg_data_<Module> unserializes to an array keyed by
     * unique_id whose elements also carry a unique_id field; both shapes match.
     *
     * @param string $unique_id
     * @param array<string,mixed> $modulesData module_id => unserialized module data
     * @return string[] matched module ids, in the order given
     */
    public static function matchModules(string $unique_id, array $modulesData): array
    {
        if ($unique_id === '')
        {
            return array();
        }

        $matched = array();

        foreach ($modulesData as $module_id => $items)
        {
            if (!is_array($items))
            {
                continue;
            }

            if (array_key_exists($unique_id, $items))
            {
                $matched[] = (string) $module_id;
                continue;
            }

            foreach ($items as $item)
            {
                if (is_array($item) && isset($item['unique_id']) && (string) $item['unique_id'] === $unique_id)
                {
                    $matched[] = (string) $module_id;
                    break;
                }
            }
        }

        return $matched;
    }

    /**
     * Bucket every candidate page into what the back-fill will and will not do.
     *
     * @param array<int,array{post_id:int,post_status:string,unique_id:string,modules:string[]}> $rows
     * @param array<string,int> $canonicalIndex "module_id|unique_id" => existing target_post_id
     * @return array{insert:array,already_mapped:array,conflicts:array,unresolved:array,skipped_status:array}
     */
    public static function plan(array $rows, array $canonicalIndex): array
    {
        $plan = array(
            'insert'         => array(),
            'already_mapped' => array(),
            'conflicts'      => array(),
            'unresolved'     => array(),
            'skipped_status' => array(),
        );

        // Group the usable rows by the pair they resolve to; a pair claimed by
        // more than one page is a conflict no rule can settle.
        $byPair = array();

        foreach ($rows as $row)
        {
            $post_id   = isset($row['post_id']) ? (int) $row['post_id'] : 0;
            $unique_id = isset($row['unique_id']) ? (string) $row['unique_id'] : '';
            $status    = isset($row['post_status']) ? (string) $row['post_status'] : '';
            $modules   = isset($row['modules']) && is_array($row['modules']) ? $row['modules'] : array();

            if ($status !== 'publish')
            {
                $plan['skipped_status'][] = array(
                    'post_id'     => $post_id,
                    'unique_id'   => $unique_id,
                    'post_status' => $status,
                );
                continue;
            }

            if (count($modules) !== 1)
            {
                $plan['unresolved'][] = array(
                    'post_id'   => $post_id,
                    'unique_id' => $unique_id,
                    'matched'   => array_values($modules),
                );
                continue;
            }

            $key = $modules[0] . '|' . $unique_id;
            $byPair[$key][] = $post_id;
        }

        foreach ($byPair as $key => $post_ids)
        {
            list($module_id, $unique_id) = explode('|', $key, 2);

            // An existing canonical row settles the pair, conflict or not — the
            // user's mapping is never overwritten.
            if (isset($canonicalIndex[$key]))
            {
                $plan['already_mapped'][] = array(
                    'module_id'          => $module_id,
                    'unique_id'          => $unique_id,
                    'target_post_id'     => (int) $canonicalIndex[$key],
                    'candidate_post_ids' => $post_ids,
                );
                continue;
            }

            if (count($post_ids) > 1)
            {
                $plan['conflicts'][] = array(
                    'module_id' => $module_id,
                    'unique_id' => $unique_id,
                    'post_ids'  => $post_ids,
                );
                continue;
            }

            $plan['insert'][] = array(
                'module_id'      => $module_id,
                'unique_id'      => $unique_id,
                'target_post_id' => $post_ids[0],
            );
        }

        return $plan;
    }

    /**
     * How many planned mappings would change a link straight away?
     *
     * A mapping only redirects something if the product is also used on some
     * other post — on its own page the self-link guard suppresses it. The rest
     * are not wasted: they apply the moment the product is added elsewhere,
     * which is the same state a fresh canonical import leaves behind. This
     * count exists to report that split, never to decide what gets created.
     *
     * @param array $insert     rows from plan()['insert']
     * @param array<string,int[]> $placements "module_id|unique_id" => post ids holding that product
     * @return int
     */
    public static function countActive(array $insert, array $placements): int
    {
        $active = 0;

        foreach ($insert as $row)
        {
            $key = $row['module_id'] . '|' . $row['unique_id'];

            if (empty($placements[$key]))
            {
                continue;
            }

            foreach ((array) $placements[$key] as $post_id)
            {
                if ((int) $post_id !== (int) $row['target_post_id'])
                {
                    $active++;
                    break;
                }
            }
        }

        return $active;
    }
}
