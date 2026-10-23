<?php

namespace ContentEgg\application\components\families;

use ContentEgg\application\components\matching\UnitAttributes;

defined('\ABSPATH') || exit;

/**
 * VariantSwitcher class file
 *
 * The view model of the visitor-facing variant switcher. It turns a family
 * definition and its published members into what the two styles print:
 * chips() gives one row of chips per dimension (Memory: 128 GB / 256 GB ...),
 * rows() gives one row per sibling page. The HTML emitters only format these
 * arrays; every decision (which sibling a chip leads to, what is disabled,
 * the order of the values) is made here.
 *
 * A chip of dimension "d" with value "v" leads to the member that best fits
 * the current page's values with "d" replaced by "v": bestMatch(). Pure: no
 * WordPress calls, no storage, no price computing (members carry the stored
 * price summary).
 *
 * $family is what VariantFamily::normalize() returns. $members is
 * [{post_id, url, title, values, summary}] of the published members, where
 * values come from VariantFamily::memberValues() and summary is a price
 * summary or null. A member that carries a `status` other than "publish" is
 * ignored, so a caller cannot make a trashed page a target by mistake.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 *
 */
class VariantSwitcher
{

	const ANY = VariantFamily::ANY;

	// Between the values of a row label: "256 GB · Blue".
	const LABEL_SEPARATOR = ' · ';

	/**
	 * Chips per dimension, or [] when the current page is not one of the
	 * members or there is no other member to switch to.
	 *
	 * @return array {dimensions: [{name, selected, chips: [{value, post_id, url, current, all, summary}]}]}
	 */
	public static function chips(array $family, array $members, int $current): array
	{
		$members = self::published($members);
		$page = self::find($members, $current);
		if ($page === null || count($members) < 2)
		{
			return array();
		}

		$page_values = self::valueMap($page);
		$dimensions = array();

		foreach (self::dimensionNames($family) as $name)
		{
			$key = self::lower($name);
			$mine = isset($page_values[$key]) ? $page_values[$key] : self::ANY;
			$mine_is_any = self::isAny($mine);

			$chips = array();
			if ($mine_is_any)
			{
				// "Any" is the current page's own state: a first chip that is the page itself.
				$chips[] = self::chip(self::ANY, $page, true, true);
			}

			foreach (self::orderValues($family, $name, self::concreteValues($members, $key)) as $value)
			{
				if (!$mine_is_any && self::same($value, $mine))
				{
					$chips[] = self::chip($value, $page, true, false);
					continue;
				}

				$target = $page_values;
				$target[$key] = $value;
				$best = self::find($members, self::bestMatch($members, $target));
				$chips[] = $best === null ? self::disabledChip($value) : self::chip($value, $best, false, false);
			}

			$dimensions[] = array('name' => $name, 'selected' => $mine_is_any ? self::ANY : $mine, 'chips' => $chips);
		}

		return array('dimensions' => $dimensions);
	}

	/**
	 * One row per member, ordered by its values (dimension by dimension in
	 * family order, each by orderValues(); "any" after the concrete values),
	 * then by post id. [] under the same conditions as chips().
	 *
	 * @return array [{post_id, url, label, current, summary}]
	 */
	public static function rows(array $family, array $members, int $current): array
	{
		$members = self::published($members);
		if (self::find($members, $current) === null || count($members) < 2)
		{
			return array();
		}

		$names = self::dimensionNames($family);

		// Position of every concrete value in its dimension's order.
		$positions = array();
		foreach ($names as $name)
		{
			$key = self::lower($name);
			$positions[$key] = array();
			foreach (self::orderValues($family, $name, self::concreteValues($members, $key)) as $i => $value)
			{
				$positions[$key][self::lower($value)] = $i;
			}
		}

		$sort = array();
		foreach ($members as $member)
		{
			$values = self::valueMap($member);
			$ranks = array();
			foreach ($names as $name)
			{
				$key = self::lower($name);
				$value = isset($values[$key]) ? $values[$key] : self::ANY;
				$ranks[] = (!self::isAny($value) && isset($positions[$key][self::lower($value)])) ? $positions[$key][self::lower($value)] : PHP_INT_MAX;
			}
			$sort[] = array('ranks' => $ranks, 'member' => $member);
		}

		usort($sort, function ($a, $b) {
			foreach ($a['ranks'] as $i => $rank)
			{
				if ($rank !== $b['ranks'][$i])
				{
					return $rank < $b['ranks'][$i] ? -1 : 1;
				}
			}

			return (int) $a['member']['post_id'] <=> (int) $b['member']['post_id'];
		});

		$rows = array();
		foreach ($sort as $item)
		{
			$member = $item['member'];
			$rows[] = array(
				'post_id' => (int) $member['post_id'],
				'url' => self::url($member),
				'label' => self::label($names, $member),
				'current' => (int) $member['post_id'] === $current,
				'summary' => self::summary($member),
			);
		}

		return $rows;
	}

	/**
	 * Values of one dimension in display order: the family's `order` for that
	 * dimension first (in that order), the others by size when the text holds
	 * exactly one memory, volume or weight value (compared as the raw base
	 * number, so units of different kinds in one row sort by number only),
	 * the values without such a size after them, in natural order. Unique
	 * without case; the spelling of the first occurrence in $values is kept.
	 * "any" is never listed, and an ordered value nobody has is not invented.
	 *
	 * @param string[] $values
	 * @return string[]
	 */
	public static function orderValues(array $family, string $dimension, array $values): array
	{
		$unique = array();
		foreach ($values as $value)
		{
			$value = trim((string) $value);
			if ($value === '' || self::isAny($value) || isset($unique[self::lower($value)]))
			{
				continue;
			}
			$unique[self::lower($value)] = $value;
		}

		$out = array();
		foreach (self::familyOrder($family, $dimension) as $wanted)
		{
			$key = self::lower($wanted);
			if (isset($unique[$key]))
			{
				$out[] = $unique[$key];
				unset($unique[$key]);
			}
		}

		$rest = array();
		$i = 0;
		foreach ($unique as $value)
		{
			$rest[] = array('value' => $value, 'size' => self::size($value), 'i' => $i++);
		}

		usort($rest, function ($a, $b) {
			if ($a['size'] !== null && $b['size'] !== null && $a['size'] !== $b['size'])
			{
				return $a['size'] < $b['size'] ? -1 : 1;
			}
			if (($a['size'] === null) !== ($b['size'] === null))
			{
				return $a['size'] === null ? 1 : -1;
			}

			$natural = strnatcasecmp($a['value'], $b['value']);

			return $natural !== 0 ? $natural : $a['i'] <=> $b['i'];
		});

		foreach ($rest as $item)
		{
			$out[] = $item['value'];
		}

		return $out;
	}

	/**
	 * The post id of the member a chip leads to for $target (dimension =>
	 * value), or 0 for none. A member matches when, for every dimension of
	 * the target, its value equals the target's (trimmed, without case) or
	 * either side is "any". Among matches: the fewest "any" values, then the
	 * lowest summary price (none last), then the lowest post id.
	 *
	 * Prices of members in different currencies are compared as raw numbers,
	 * without conversion: the tie-break only has to be stable, and a family
	 * is normally sold in one currency.
	 */
	public static function bestMatch(array $members, array $target): int
	{
		$wanted = array();
		foreach ($target as $name => $value)
		{
			$wanted[self::lower(trim((string) $name))] = trim((string) $value);
		}

		$best = null;
		foreach (self::published($members) as $member)
		{
			$values = self::valueMap($member);
			$anys = 0;
			foreach ($values as $value)
			{
				if (self::isAny($value))
				{
					$anys++;
				}
			}

			$matches = true;
			foreach ($wanted as $key => $value)
			{
				if (!isset($values[$key]))
				{
					$matches = false;
					break;
				}
				if (!self::isAny($value) && !self::isAny($values[$key]) && !self::same($value, $values[$key]))
				{
					$matches = false;
					break;
				}
			}
			if (!$matches)
			{
				continue;
			}

			$summary = self::summary($member);
			$price = ($summary !== null && isset($summary['price']) && is_numeric($summary['price'])) ? (float) $summary['price'] : null;
			$candidate = array($anys, $price === null ? 1 : 0, $price === null ? 0.0 : $price, (int) $member['post_id']);

			if ($best === null || $candidate < $best[0])
			{
				$best = array($candidate, (int) $member['post_id']);
			}
		}

		return $best === null ? 0 : $best[1];
	}

	private static function chip(string $value, array $member, bool $current, bool $all): array
	{
		return array(
			'value' => $value,
			'post_id' => (int) $member['post_id'],
			'url' => self::url($member),
			'current' => $current,
			'all' => $all,
			'summary' => self::summary($member),
		);
	}

	private static function disabledChip(string $value): array
	{
		return array('value' => $value, 'post_id' => 0, 'url' => '', 'current' => false, 'all' => false, 'summary' => null);
	}

	private static function label(array $names, array $member): string
	{
		$values = self::valueMap($member);
		$parts = array();
		foreach ($names as $name)
		{
			$key = self::lower($name);
			if (isset($values[$key]) && !self::isAny($values[$key]))
			{
				$parts[] = $values[$key];
			}
		}

		if ($parts)
		{
			return implode(self::LABEL_SEPARATOR, $parts);
		}

		// Every value is "any": the page title is the only thing that tells it apart.
		return isset($member['title']) && is_scalar($member['title']) ? trim((string) $member['title']) : '';
	}

	// Members that may be shown or targeted: arrays with a post id, published.
	private static function published(array $members): array
	{
		$out = array();
		foreach ($members as $member)
		{
			if (!is_array($member) || !isset($member['post_id']) || (int) $member['post_id'] <= 0)
			{
				continue;
			}
			if (isset($member['status']) && $member['status'] !== 'publish')
			{
				continue;
			}
			$out[] = $member;
		}

		return $out;
	}

	private static function find(array $members, int $post_id): ?array
	{
		if ($post_id <= 0)
		{
			return null;
		}
		foreach ($members as $member)
		{
			if ((int) $member['post_id'] === $post_id)
			{
				return $member;
			}
		}

		return null;
	}

	// [lowercase dimension name => trimmed value] ("any" normalized).
	private static function valueMap(array $member): array
	{
		$out = array();
		$values = (isset($member['values']) && is_array($member['values'])) ? $member['values'] : array();
		foreach ($values as $name => $value)
		{
			if (!is_scalar($value))
			{
				continue;
			}
			$value = trim((string) $value);
			$out[self::lower(trim((string) $name))] = self::isAny($value) ? self::ANY : $value;
		}

		return $out;
	}

	// The concrete values the members hold for one dimension (lowercase key).
	private static function concreteValues(array $members, string $key): array
	{
		$out = array();
		foreach ($members as $member)
		{
			$values = self::valueMap($member);
			if (isset($values[$key]) && $values[$key] !== '' && !self::isAny($values[$key]))
			{
				$out[] = $values[$key];
			}
		}

		return $out;
	}

	private static function dimensionNames(array $family): array
	{
		$out = array();
		$dimensions = (isset($family['dimensions']) && is_array($family['dimensions'])) ? $family['dimensions'] : array();
		foreach ($dimensions as $dimension)
		{
			if (is_array($dimension) && isset($dimension['name']) && trim((string) $dimension['name']) !== '')
			{
				$out[] = (string) $dimension['name'];
			}
		}

		return $out;
	}

	private static function familyOrder(array $family, string $dimension): array
	{
		$order = (isset($family['order']) && is_array($family['order'])) ? $family['order'] : array();
		foreach ($order as $name => $values)
		{
			if (self::lower(trim((string) $name)) === self::lower(trim($dimension)) && is_array($values))
			{
				return array_map('strval', array_values($values));
			}
		}

		return array();
	}

	/**
	 * The one memory (GB), volume (ml) or weight (g) number in a value, or
	 * null when it holds none or more than one.
	 */
	private static function size(string $value): ?float
	{
		$found = UnitAttributes::extract($value);
		$sizes = array();
		foreach (array('memory', 'volume', 'weight') as $dimension)
		{
			foreach ($found[$dimension] as $size)
			{
				$sizes[] = (float) $size;
			}
		}

		return count($sizes) === 1 ? $sizes[0] : null;
	}

	private static function url(array $member): string
	{
		return isset($member['url']) && is_scalar($member['url']) ? (string) $member['url'] : '';
	}

	private static function summary(array $member): ?array
	{
		return (isset($member['summary']) && is_array($member['summary'])) ? $member['summary'] : null;
	}

	private static function isAny(string $value): bool
	{
		return self::lower(trim($value)) === self::ANY;
	}

	private static function same(string $a, string $b): bool
	{
		return self::lower(trim($a)) === self::lower(trim($b));
	}

	private static function lower(string $text): string
	{
		return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
	}
}
