<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

/**
 * OfferDedup class file
 *
 * Picks which offers of a duplicate group to drop. Two offers are duplicates
 * when a field (usually the EAN) holds the same non-empty value; the offer of
 * the module the owner ranked highest stays.
 *
 * Priority is a rank, not a score: 0 is the highest priority (see
 * ParserModuleConfig), so the LOWEST number wins. Offers are told apart by
 * module AND unique_id, because two modules can hand out the same unique_id.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class OfferDedup
{
    /**
     * Keys of every offer that loses to a duplicate with a better module.
     *
     * @param array  $items      Flat list of offers; each has module_id and unique_id.
     * @param string $field      Field that identifies duplicates, e.g. 'ean'.
     * @param array  $priorities module_id => priority. A module that is not
     *                           listed (uninstalled) ranks last. On a tie the
     *                           offer that comes first in $items stays.
     * @return string[] "module_id|unique_id" keys, in list order.
     */
    public static function losers(array $items, $field, array $priorities)
    {
        $groups = array();
        foreach ($items as $i => $item)
        {
            if (!isset($item[$field]) || !is_scalar($item[$field]))
                continue;

            $value = trim((string) $item[$field]);
            if ($value === '')
                continue;

            $groups[$value][] = $i;
        }

        $rank = function ($i) use ($items, $priorities)
        {
            $module_id = $items[$i]['module_id'] ?? '';

            return isset($priorities[$module_id]) ? (int) $priorities[$module_id] : PHP_INT_MAX;
        };

        $loser_indexes = array();
        foreach ($groups as $indexes)
        {
            if (count($indexes) < 2)
                continue;

            // usort() is not stable before PHP 8, so pick the winner by hand:
            // a strict "<" keeps the earlier offer when ranks are equal.
            $winner = $indexes[0];
            foreach ($indexes as $i)
            {
                if ($rank($i) < $rank($winner))
                    $winner = $i;
            }

            foreach ($indexes as $i)
            {
                if ($i !== $winner)
                    $loser_indexes[$i] = true;
            }
        }

        ksort($loser_indexes);

        $keys = array();
        foreach (array_keys($loser_indexes) as $i)
        {
            $keys[] = ($items[$i]['module_id'] ?? '') . '|' . ($items[$i]['unique_id'] ?? '');
        }

        return $keys;
    }
}
