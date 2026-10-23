<?php

namespace ContentEgg\application\components\matching;

use ContentEgg\application\helpers\Gtin;

defined('\ABSPATH') || exit;

/**
 * OfferDecisions class file
 *
 * What was decided about each offer of a page: accepted ("this is the
 * passport's product") or rejected ("it is not, never attach it"), by which
 * method, why, and what the offer looked like at that moment (the
 * fingerprint: title and GTINs). One post meta, `_cegg_match`, keyed
 * "<module_id>:<unique_id>" (split on the FIRST colon: a unique id may hold
 * colons itself).
 *
 * Rejected offers live here so they are never re-proposed, and so every
 * automated save path can drop them (applyOnSave()). A person adding an offer
 * by hand overrides the agent: inside runManual() the save forgets the
 * rejection instead (unless the person's form was opened before the rejection
 * was recorded). Automated saves on pages without a passport are never
 * touched.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 *
 */
class OfferDecisions
{

	const META = '_cegg_match';

	const STATUS_ACCEPTED = 'accepted';
	const STATUS_REJECTED = 'rejected';
	const STATUSES = array('accepted', 'rejected');
	const METHODS = array('gtin', 'mpn', 'agent', 'user');

	const MAX_REASONS = 5;
	const MAX_REASON_LENGTH = 200;

	// Nesting depth of runManual(); above 0 a save is a person's.
	private static $manual_depth = 0;

	// One entry per open runManual() frame: the time before which a rejection
	// may be cleared (the person's form load time), or null for "any".
	private static $manual_cutoffs = array();

	// Offers manual saves dropped because their rejection is newer than the form (applyManual()); only grows.
	private static $unseen_dropped = 0;

	public static function key(string $module_id, string $unique_id): string
	{
		return $module_id . ':' . $unique_id;
	}

	/**
	 * [module_id, unique_id] of a key, split on the first colon. A key with no
	 * colon has no module: ['', $key].
	 */
	public static function splitKey(string $key): array
	{
		$pos = strpos($key, ':');
		if ($pos === false)
		{
			return array('', $key);
		}

		return array(substr($key, 0, $pos), substr($key, $pos + 1));
	}

	/**
	 * One decision, ready for put().
	 *
	 * @param string[] $reasons plain English; at most 5 are kept, 200 characters each.
	 * @param array    $item    the offer decided on (title and GTIN fields), for the fingerprint.
	 *
	 * @throws \InvalidArgumentException on an unknown status or method.
	 */
	public static function entry(string $status, string $method, array $reasons, array $item, int $user_id, int $now): array
	{
		if (!in_array($status, self::STATUSES, true))
		{
			throw new \InvalidArgumentException('Unknown decision status "' . $status . '"; expected accepted or rejected.');
		}
		if (!in_array($method, self::METHODS, true))
		{
			throw new \InvalidArgumentException('Unknown decision method "' . $method . '"; expected one of: ' . implode(', ', self::METHODS) . '.');
		}

		$kept = array();
		foreach ($reasons as $reason)
		{
			if (!is_scalar($reason) || is_bool($reason))
			{
				continue;
			}
			$reason = self::cut(trim((string) $reason), self::MAX_REASON_LENGTH);
			if ($reason === '')
			{
				continue;
			}
			$kept[] = $reason;
			if (count($kept) >= self::MAX_REASONS)
			{
				break;
			}
		}

		return array(
			'status' => $status,
			'method' => $method,
			'reasons' => $kept,
			'checked_at' => $now,
			'by' => $user_id,
			'fp' => self::fingerprint($item),
		);
	}

	/**
	 * What identifies the offer at decision time: its trimmed title and its
	 * sorted display GTINs.
	 */
	public static function fingerprint(array $item): array
	{
		$title = (isset($item['title']) && is_scalar($item['title'])) ? trim((string) $item['title']) : '';
		$gtins = Gtin::fromItem($item);
		sort($gtins, SORT_STRING);

		return array('title' => $title, 'gtins' => $gtins);
	}

	/**
	 * Whether the offer is no longer what was decided on: another title (case
	 * and runs of whitespace do not count) or another set of GTINs. An entry
	 * with no usable fingerprint cannot vouch for the offer, so it counts as
	 * changed.
	 */
	public static function fingerprintChanged(array $entry, array $item): bool
	{
		if (!isset($entry['fp']) || !is_array($entry['fp']) || !isset($entry['fp']['title']) || !isset($entry['fp']['gtins']) || !is_array($entry['fp']['gtins']))
		{
			return true;
		}

		$now = self::fingerprint($item);
		if (self::foldTitle((string) $entry['fp']['title']) !== self::foldTitle($now['title']))
		{
			return true;
		}

		$was = array_values(array_unique(array_map('strval', $entry['fp']['gtins'])));
		sort($was, SORT_STRING);

		return $was !== array_values(array_unique($now['gtins']));
	}

	/**
	 * Every decision of the page, keyed by "<module_id>:<unique_id>".
	 */
	public static function all(int $post_id): array
	{
		if ($post_id <= 0)
		{
			return array();
		}

		$all = \get_post_meta($post_id, self::META, true);
		if (!is_array($all))
		{
			return array();
		}

		return array_filter($all, 'is_array');
	}

	/**
	 * Merge decisions into the page's: a key already there is replaced.
	 *
	 * @throws \InvalidArgumentException when $post_id is below 1.
	 */
	public static function put(int $post_id, array $entries_by_key): void
	{
		if ($post_id <= 0)
		{
			throw new \InvalidArgumentException('post_id must be a positive integer.');
		}
		if (!$entries_by_key)
		{
			return;
		}

		$all = self::all($post_id);
		foreach ($entries_by_key as $key => $entry)
		{
			$all[(string) $key] = $entry;
		}

		\update_post_meta($post_id, self::META, \wp_slash($all));
	}

	/**
	 * Remove decisions; the meta goes when none is left. Writes nothing when
	 * none of the keys is on file.
	 */
	public static function forget(int $post_id, array $keys): void
	{
		if ($post_id <= 0 || !$keys)
		{
			return;
		}

		$all = self::all($post_id);
		$left = array_diff_key($all, array_flip(array_map('strval', $keys)));
		if (count($left) === count($all))
		{
			return;
		}

		if ($left)
		{
			\update_post_meta($post_id, self::META, \wp_slash($left));
		}
		else
		{
			\delete_post_meta($post_id, self::META);
		}
	}

	/**
	 * @return string[] keys of the rejected decisions
	 */
	public static function rejectedKeys(array $decisions): array
	{
		$keys = array();
		foreach ($decisions as $key => $entry)
		{
			if (is_array($entry) && isset($entry['status']) && $entry['status'] === self::STATUS_REJECTED)
			{
				$keys[] = (string) $key;
			}
		}

		return $keys;
	}

	/**
	 * The offers a decision vouches for: [key => 'accepted'].
	 */
	public static function verifiedMap(array $decisions): array
	{
		$map = array();
		foreach ($decisions as $key => $entry)
		{
			if (is_array($entry) && isset($entry['status']) && $entry['status'] === self::STATUS_ACCEPTED)
			{
				$map[(string) $key] = self::STATUS_ACCEPTED;
			}
		}

		return $map;
	}

	/**
	 * The `match` member of the Product Manager payload: null when the page has
	 * no passport (the UI shows no marker at all), else the offers to mark
	 * "Verified": [key => 'accepted'].
	 *
	 * An offer counts only while it is still what was accepted: the stored item's
	 * title and GTINs must match the decision's fingerprint (the same test
	 * audit-offers uses for fingerprint_changed). An offer edited since, one with
	 * no stored item, one added by hand and one rejected are all left out, so
	 * the UI shows them as "Not verified".
	 *
	 * @param array $decisions OfferDecisions::all().
	 * @param array $items     The attached items as stored, keyed by decision key.
	 */
	public static function matchPayload(bool $has_passport, array $decisions, array $items): ?array
	{
		if (!$has_passport)
		{
			return null;
		}

		$map = array();
		foreach (self::verifiedMap($decisions) as $key => $status)
		{
			if (isset($items[$key]) && is_array($items[$key]) && !self::fingerprintChanged($decisions[$key], $items[$key]))
			{
				$map[$key] = $status;
			}
		}

		return $map;
	}

	/**
	 * $data without the rejected offers. Keys are kept; items may be arrays or
	 * objects.
	 */
	public static function dropRejected(array $data, string $module_id, array $decisions): array
	{
		$rejected = array_flip(self::rejectedKeys($decisions));
		if (!$rejected)
		{
			return $data;
		}

		foreach ($data as $index => $item)
		{
			if (isset($rejected[self::key($module_id, self::uniqueId($index, $item))]))
			{
				unset($data[$index]);
			}
		}

		return $data;
	}

	/**
	 * The choke point of every save of a module's offers (ContentManager::
	 * saveData()). An automated save drops the rejected offers on a page with
	 * a passport; without one the data comes back as it came. A manual save
	 * (a person's, see runManual()) goes through applyManual(). $data is never
	 * re-keyed.
	 */
	public static function applyOnSave(array $data, string $module_id, int $post_id): array
	{
		if (self::isManual())
		{
			return self::applyManual($data, $module_id, $post_id);
		}

		if (!ProductPassport::has($post_id))
		{
			return $data;
		}

		$decisions = self::all($post_id);
		if (!$decisions)
		{
			return $data;
		}

		return self::dropRejected($data, $module_id, $decisions);
	}

	/**
	 * A person's save: keeping or re-adding an offer the agent rejected is
	 * their decision, so the rejections of the saved offers are forgotten and
	 * the data is returned whole. This holds whether or not the page still has
	 * a passport (a cleared passport leaves its decisions on file; a page that
	 * never had one has none, so nothing changes for it).
	 *
	 * A form opened before a rejection was recorded never showed it, so saving
	 * that form cannot override it: with a cutoff (runManual()'s second
	 * argument), a rejection recorded at or after it is kept, and on a page
	 * with a passport its offer is dropped from the save.
	 */
	private static function applyManual(array $data, string $module_id, int $post_id): array
	{
		$decisions = self::all($post_id);
		$rejected = array_flip(self::rejectedKeys($decisions));
		if (!$rejected)
		{
			return $data;
		}

		$cutoff = self::manualCutoff();
		$forget = array();
		$unseen = array();
		foreach ($data as $index => $item)
		{
			$key = self::key($module_id, self::uniqueId($index, $item));
			if (!isset($rejected[$key]))
			{
				continue;
			}

			if ($cutoff !== null && self::checkedAt($decisions[$key]) >= $cutoff)
			{
				$unseen[$index] = true;
				continue;
			}

			$forget[] = $key;
		}
		self::forget($post_id, $forget);

		if ($unseen && ProductPassport::has($post_id))
		{
			self::$unseen_dropped += count($unseen);
			$data = array_diff_key($data, $unseen);
		}

		return $data;
	}

	/**
	 * Run $fn as a person's action: saves inside it override rejections. The
	 * flag is a depth counter, released in `finally`, so a throwing $fn cannot
	 * leave later automated saves in manual mode.
	 *
	 * @param int|null $clear_rejections_before Only rejections recorded before
	 *        this Unix time may be cleared (the load time of the form being
	 *        saved); null or 0: any rejection of a saved offer is cleared.
	 *
	 * @return mixed whatever $fn returns
	 */
	public static function runManual(callable $fn, ?int $clear_rejections_before = null)
	{
		self::$manual_depth++;
		self::$manual_cutoffs[] = ($clear_rejections_before !== null && $clear_rejections_before > 0) ? $clear_rejections_before : null;
		try
		{
			return $fn();
		}
		finally
		{
			array_pop(self::$manual_cutoffs);
			self::$manual_depth--;
		}
	}

	/**
	 * The cutoff of the current manual save: the earliest one of the open
	 * runManual() frames (a nested frame cannot clear what an outer one keeps),
	 * or null when none set one.
	 */
	public static function manualCutoff(): ?int
	{
		$cutoff = null;
		foreach (self::$manual_cutoffs as $time)
		{
			if ($time !== null && ($cutoff === null || $time < $cutoff))
			{
				$cutoff = $time;
			}
		}

		return $cutoff;
	}

	/**
	 * How many offers manual saves of this request dropped because the agent
	 * rejected them after the form was loaded. Callers compare it before and
	 * after their runManual() to tell the person (UnseenRejectionNotice).
	 */
	public static function unseenDroppedCount(): int
	{
		return self::$unseen_dropped;
	}

	public static function isManual(): bool
	{
		return self::$manual_depth > 0;
	}

	/**
	 * The offer's unique id: its own field when it has one (ContentManager
	 * keys the list by it), else the list key.
	 *
	 * @param int|string   $index
	 * @param array|object $item
	 */
	private static function uniqueId($index, $item): string
	{
		$id = null;
		if (is_array($item))
		{
			$id = isset($item['unique_id']) ? $item['unique_id'] : null;
		}
		elseif (is_object($item))
		{
			$id = isset($item->unique_id) ? $item->unique_id : null;
		}

		if (is_scalar($id) && (string) $id !== '')
		{
			return (string) $id;
		}

		return (string) $index;
	}

	/**
	 * When the decision was recorded; 0 when the entry has no usable time.
	 */
	private static function checkedAt(array $entry): int
	{
		return (isset($entry['checked_at']) && is_numeric($entry['checked_at'])) ? (int) $entry['checked_at'] : 0;
	}

	private static function foldTitle(string $title): string
	{
		$collapsed = preg_replace('/\s+/u', ' ', $title);
		$title = trim($collapsed !== null ? $collapsed : $title);

		return function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
	}

	private static function cut(string $text, int $length): string
	{
		return function_exists('mb_substr') ? mb_substr($text, 0, $length, 'UTF-8') : substr($text, 0, $length);
	}
}
