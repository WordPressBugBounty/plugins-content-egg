<?php

namespace ContentEgg\application\components\matching;

defined('\ABSPATH') || exit;

/**
 * UnitAttributes class file
 *
 * Memory, volume, weight and pack count read from free text (an offer title,
 * a feature value) or from the values of a product passport variant, reduced
 * to base units: memory in GB, volume in ml, weight in g. Two offers of the
 * same GTIN family that differ in one of these ("128GB" vs "256GB", "150 ml"
 * vs "4 x 150 ml") are different products, so the offer matcher compares the
 * rows compare() returns.
 *
 * Pure: no WordPress calls.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 *
 */
class UnitAttributes
{

	/**
	 * Relative tolerance when comparing two values of one dimension
	 * (1 TB = 1000 GB must match a "1024 GB" title).
	 */
	const TOLERANCE = 0.03;

	const DIMENSIONS = array('memory', 'volume', 'weight', 'pack');

	// A number with "." or "," as the decimal separator.
	const NUM = '\d+(?:[.,]\d+)?';

	// Where a measured number may start: not inside a longer number or word
	// ("PS5", "1,5"), but right after the "x" of "4x150ml".
	const NUM_START = '(?:(?<![\p{L}\d.,])|(?<=[x×]))';

	// A pack count never starts inside a word ("PS5 Pack" is not a 5-pack).
	const COUNT_START = '(?<![\p{L}\d.,])';

	// Between a measured number and its unit: nothing, a space or a hyphen
	// ("512GB", "512 GB", "512-GB"). Pack patterns keep their own separators.
	// Used as is for "fl oz"; the other units are read after a hyphen only from
	// the *_HYPHEN lists below.
	const UNIT_GAP = '[\s-]?';

	// "fl oz" is read before "oz" so that a fluid ounce is not also a weight.
	const UNIT_FL_OZ = 'fl\.?\s?oz';
	const UNIT_VOLUME = 'ml|cl|dl|ltr|liter|litre|l';
	// "g" only in lowercase: "5G" in a phone title is a network.
	const UNIT_WEIGHT = 'kg|mg|(?-i:g)|lbs?|oz';
	// "Go" and "To" only in their French capitalization: "for 2 to 4 people" is not 2 TB.
	const UNIT_MEMORY = 'tb|gb|mb|(?-i:Go|To)';

	// The units read after a hyphen. "l" only in lowercase: "Size 2-L" is a
	// clothing size. "Go"/"To" not before another hyphen or a digit:
	// "Splitter 1-To-2" is a range, not 1 TB.
	const UNIT_VOLUME_HYPHEN = 'ml|cl|dl|ltr|liter|litre|(?-i:l)';
	const UNIT_WEIGHT_HYPHEN = self::UNIT_WEIGHT;
	const UNIT_MEMORY_HYPHEN = 'tb|gb|mb|(?-i:Go|To)(?![-\d])';

	const KEY_PACK = '/pack|count|quantity|stück|menge|anzahl/iu';

	/**
	 * Multipliers to the base unit of each dimension, by lowercase unit.
	 */
	private static $factors = array(
		'memory' => array('tb' => 1000, 'gb' => 1, 'mb' => 0.001, 'go' => 1, 'to' => 1000),
		'volume' => array('ml' => 1, 'cl' => 10, 'dl' => 100, 'l' => 1000, 'ltr' => 1000, 'liter' => 1000, 'litre' => 1000),
		'weight' => array('kg' => 1000, 'g' => 1, 'mg' => 0.001, 'lb' => 453.592, 'lbs' => 453.592, 'oz' => 28.3495),
	);

	const FL_OZ_ML = 29.5735;

	/**
	 * Units found in a text: ['memory' => float[], 'volume' => float[],
	 * 'weight' => float[], 'pack' => int[]], each list unique and ascending,
	 * values rounded to one decimal.
	 */
	public static function extract(string $text): array
	{
		$found = array('memory' => array(), 'volume' => array(), 'weight' => array(), 'pack' => array());

		$text = self::utf8($text);
		if ($text === '')
		{
			return self::tidy($found);
		}

		$found['pack'] = self::findPacks($text);

		$text = (string) preg_replace_callback(
			'/' . self::NUM_START . '(' . self::NUM . ')' . self::UNIT_GAP . self::UNIT_FL_OZ . '\b/iu',
			function ($m) use (&$found) {
				$found['volume'][] = self::toNumber($m[1]) * self::FL_OZ_ML;

				return ' ';
			},
			$text
		);

		$units = array(
			'memory' => array(self::UNIT_MEMORY, self::UNIT_MEMORY_HYPHEN),
			'volume' => array(self::UNIT_VOLUME, self::UNIT_VOLUME_HYPHEN),
			'weight' => array(self::UNIT_WEIGHT, self::UNIT_WEIGHT_HYPHEN),
		);
		foreach ($units as $dimension => list($alternatives, $after_hyphen))
		{
			$pattern = '/' . self::NUM_START . '(' . self::NUM . ')'
				. '(?:\s?(' . $alternatives . ')|-(' . $after_hyphen . '))\b/iu';
			if (!preg_match_all($pattern, $text, $matches, PREG_SET_ORDER))
			{
				continue;
			}
			foreach ($matches as $m)
			{
				$unit = ($m[2] ?? '') !== '' ? $m[2] : $m[3];
				$factor = self::$factors[$dimension][strtolower($unit)];
				$found[$dimension][] = self::toNumber($m[1]) * $factor;
			}
		}

		return self::tidy($found);
	}

	/**
	 * The same shape from passport variant values ("Memory" => "256 GB").
	 * "any" values are skipped. A dimension named like a pack count with a
	 * bare integer gives the pack. A passport that names no pack count means
	 * one unit, so pack is [1] when nothing gives one.
	 */
	public static function fromVariant(array $variant): array
	{
		$found = array('memory' => array(), 'volume' => array(), 'weight' => array(), 'pack' => array());

		foreach ($variant as $key => $value)
		{
			if (!is_scalar($value))
			{
				continue;
			}
			$value = trim((string) $value);
			if ($value === '' || strtolower($value) === 'any')
			{
				continue;
			}

			$units = self::extract($value);
			foreach (self::DIMENSIONS as $dimension)
			{
				$found[$dimension] = array_merge($found[$dimension], $units[$dimension]);
			}

			if (preg_match('/^\d{1,3}$/', $value) && preg_match(self::KEY_PACK, (string) $key))
			{
				$found['pack'][] = (int) $value;
			}
		}

		$found = self::tidy($found);
		if (!$found['pack'])
		{
			$found['pack'] = array(1);
		}

		return $found;
	}

	/**
	 * One row per passport value whose dimension the offer also has:
	 * ['dimension', 'passport' (the value), 'offer' (the offer's values of
	 * that dimension), 'status' ('match'|'conflict')]. A passport value
	 * matches when any offer value is within TOLERANCE (relative); pack
	 * counts must be equal. A dimension the offer lacks gives no row.
	 */
	public static function compare(array $passport_units, array $offer_units): array
	{
		$rows = array();

		foreach (self::DIMENSIONS as $dimension)
		{
			if (empty($passport_units[$dimension]) || empty($offer_units[$dimension]))
			{
				continue;
			}

			$offer_values = array_values($offer_units[$dimension]);
			foreach ($passport_units[$dimension] as $value)
			{
				$rows[] = array(
					'dimension' => $dimension,
					'passport' => $value,
					'offer' => $offer_values,
					'status' => self::anyClose($dimension, $value, $offer_values) ? 'match' : 'conflict',
				);
			}
		}

		return $rows;
	}

	private static function anyClose(string $dimension, $value, array $candidates): bool
	{
		foreach ($candidates as $candidate)
		{
			if ($dimension === 'pack')
			{
				if ((int) $candidate === (int) $value)
				{
					return true;
				}
				continue;
			}

			$largest = max(abs($value), abs($candidate));
			if (abs($value - $candidate) <= self::TOLERANCE * $largest)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Pack counts: "4 x 150 ml", "2er-Pack", "2-Pack", "Pack of 3", "Lot de 2"...
	 *
	 * @return int[]
	 */
	private static function findPacks(string $text): array
	{
		$measure = self::NUM . '\s?(?:' . self::UNIT_FL_OZ . '|' . self::UNIT_VOLUME . '|' . self::UNIT_WEIGHT . '|' . self::UNIT_MEMORY . ')\b';
		$patterns = array(
			'/' . self::COUNT_START . '(\d{1,3})\s*[x×]\s*' . $measure . '/iu',
			'/' . self::COUNT_START . '(\d{1,3})\s*er[\s-]?(?:packung|pack|set)\b/iu',
			'/' . self::COUNT_START . '(\d{1,3})[\s-]?(?:pack|pk|pcs|pieces|stück|stk)\b/iu',
			// Not a size: "Set of 12" & 10" Skillets" is a 12-inch pan.
			'/\b(?:pack of|set of|lot de|confezione da|paquete de)\s+(\d{1,3})\b(?!\s*(?:"|\x{201D}|\'\'|in\b|inch|cm\b|mm\b))/iu',
		);

		$packs = array();
		foreach ($patterns as $pattern)
		{
			if (!preg_match_all($pattern, $text, $matches))
			{
				continue;
			}
			foreach ($matches[1] as $count)
			{
				if ((int) $count > 0)
				{
					$packs[] = (int) $count;
				}
			}
		}

		return $packs;
	}

	/**
	 * Round, de-duplicate and sort every dimension.
	 */
	private static function tidy(array $found): array
	{
		foreach (self::DIMENSIONS as $dimension)
		{
			$values = isset($found[$dimension]) ? $found[$dimension] : array();
			if ($dimension === 'pack')
			{
				$values = array_map('intval', $values);
			}
			else
			{
				$values = array_map(function ($v) {
					return round((float) $v, 1);
				}, $values);
			}
			$values = array_values(array_unique($values));
			sort($values);
			$found[$dimension] = $values;
		}

		return $found;
	}

	private static function toNumber(string $raw): float
	{
		return (float) str_replace(',', '.', $raw);
	}

	/**
	 * The text as valid UTF-8 (the patterns run in /u mode), '' when it cannot be.
	 */
	private static function utf8(string $text): string
	{
		if ($text === '' || preg_match('//u', $text))
		{
			return $text;
		}

		return function_exists('mb_convert_encoding') ? (string) mb_convert_encoding($text, 'UTF-8', 'UTF-8') : '';
	}
}
