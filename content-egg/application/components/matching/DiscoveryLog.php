<?php

namespace ContentEgg\application\components\matching;

defined('\ABSPATH') || exit;

/**
 * DiscoveryLog class file
 *
 * When, and how, each module was last searched for a page: one entry per
 * module id in the post meta `_cegg_discovery`, written by the actions that
 * consume a search (never by the search itself). The audit reads it to find
 * the modules whose last search is older than DUE_DAYS.
 *
 * Entry: {at, mode, query, results}. `mode` is how the module read the query
 * (the values of the abilities layer's SearchMode; listed here so this record
 * does not depend on that layer). Discovery counts searches by the passport's
 * identifiers (gtin, url, native_id): a keyword search never replaces one, and
 * a module last searched by keyword is still due (isIdentifierSearchDue()).
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 *
 */
class DiscoveryLog
{

	const META = '_cegg_discovery';
	const DUE_DAYS = 30;

	const MODES = array('native_id', 'url', 'gtin', 'keyword');
	// The modes that searched by an identifier of the product, not by its words.
	const IDENTIFIER_MODES = array('native_id', 'url', 'gtin');
	const MAX_QUERY = 200;

	/**
	 * @throws \InvalidArgumentException on an unknown mode.
	 */
	public static function entry(string $mode, string $query, int $results, int $now): array
	{
		if (!in_array($mode, self::MODES, true))
		{
			throw new \InvalidArgumentException('Unknown search mode "' . $mode . '"; expected one of: ' . implode(', ', self::MODES) . '.');
		}

		$query = trim($query);
		if (function_exists('mb_substr'))
		{
			$query = mb_substr($query, 0, self::MAX_QUERY, 'UTF-8');
		}
		else
		{
			$query = substr($query, 0, self::MAX_QUERY);
		}

		return array(
			'at' => $now,
			'mode' => $mode,
			'query' => $query,
			'results' => max(0, $results),
		);
	}

	/**
	 * Store the module's entry; the module's earlier one is replaced, except
	 * that a keyword entry never replaces an identifier one (the identifier
	 * search is what the audit counts, and it would be lost).
	 *
	 * @return bool whether the entry was written
	 * @throws \InvalidArgumentException on an empty module id or a post id below 1.
	 */
	public static function record(int $post_id, string $module_id, array $entry): bool
	{
		if ($post_id <= 0)
		{
			throw new \InvalidArgumentException('post_id must be a positive integer.');
		}
		if ($module_id === '')
		{
			throw new \InvalidArgumentException('A discovery entry needs a module id.');
		}

		$all = self::all($post_id);
		if (!self::isIdentifierMode($entry) && isset($all[$module_id]) && is_array($all[$module_id]) && self::isIdentifierMode($all[$module_id]))
		{
			return false;
		}
		$all[$module_id] = $entry;

		\update_post_meta($post_id, self::META, \wp_slash($all));

		return true;
	}

	/**
	 * Entries keyed by module id; [] when nothing was logged.
	 */
	public static function all(int $post_id): array
	{
		if ($post_id <= 0)
		{
			return array();
		}

		$all = \get_post_meta($post_id, self::META, true);

		return is_array($all) ? $all : array();
	}

	/**
	 * Whether the module should be searched again: never searched (null), or
	 * searched $due_days or more ago.
	 */
	public static function isDue(?array $entry, int $now, int $due_days): bool
	{
		if ($entry === null || !isset($entry['at']) || !is_numeric($entry['at']))
		{
			return true;
		}

		return (int) $entry['at'] <= $now - $due_days * 86400;
	}

	/**
	 * Whether a module should be searched again by the passport's
	 * identifiers: as isDue(), and also when its last search was not by an
	 * identifier (a keyword search says nothing about the GTIN).
	 */
	public static function isIdentifierSearchDue(?array $entry, int $now, int $due_days): bool
	{
		if ($entry === null || !self::isIdentifierMode($entry))
		{
			return true;
		}

		return self::isDue($entry, $now, $due_days);
	}

	private static function isIdentifierMode(array $entry): bool
	{
		return isset($entry['mode']) && in_array($entry['mode'], self::IDENTIFIER_MODES, true);
	}
}
