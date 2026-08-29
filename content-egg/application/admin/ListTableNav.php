<?php

namespace ContentEgg\application\admin;

defined('\ABSPATH') || exit;

/**
 * ListTableNav class file
 *
 * Pure paging and link-building decisions shared by the admin list tables.
 *
 * Deliberately free of WordPress calls: these are the parts worth testing, and
 * the repo's test harness loads this file with a bare require, no WP booted.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ListTableNav
{
    /**
     * Clamp a requested page number to the range that actually holds rows.
     *
     * The search box, the module dropdown and the table all share one GET form,
     * and WP's pagination() puts <input name="paged"> inside it. So narrowing a
     * result set re-submits the page number you were on, which without this
     * becomes an offset past the end and renders an empty screen with no
     * pagination control left to click.
     *
     * @param mixed $requested Raw $_REQUEST['paged'] — may be junk.
     * @return int 1-based page number.
     */
    public static function clampPage($requested, int $total_items, int $per_page): int
    {
        $per_page = max(1, $per_page);
        $total_pages = max(1, (int) ceil(max(0, $total_items) / $per_page));

        $page = is_scalar($requested) ? (int) $requested : 0;

        return min(max(1, $page), $total_pages);
    }

    /**
     * Clamp a rows-per-page value coming from a screen option.
     *
     * @param mixed $value Raw user input.
     */
    public static function clampPerPage($value, int $default, int $max = 999): int
    {
        if (!is_scalar($value) || !is_numeric($value))
        {
            return $default;
        }

        $value = (int) $value;

        if ($value < 1)
        {
            return $default;
        }

        return min($value, $max);
    }

    /**
     * Pick the request arguments worth forwarding into a link.
     *
     * Values are compared against '' rather than tested with empty(), because
     * stock_status legitimately carries '0' (unknown) and '-1' (out of stock).
     *
     * @param array $request Usually $_REQUEST.
     * @param array $keys    Keys to consider, in the order they should appear.
     * @return array<string,string>
     */
    public static function carryArgs(array $request, array $keys): array
    {
        $carry = array();

        foreach ($keys as $key)
        {
            if (!isset($request[$key]) || !is_scalar($request[$key]))
            {
                continue;
            }

            $value = trim((string) $request[$key]);

            if ($value === '')
            {
                continue;
            }

            $carry[$key] = $value;
        }

        return $carry;
    }
}
