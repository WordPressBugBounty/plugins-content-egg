<?php

namespace ContentEgg\application\components\matching;

use ContentEgg\application\helpers\Gtin;

defined('\ABSPATH') || exit;

/**
 * OfferMatcher class file
 *
 * The deterministic part of "is this offer the passport's product?": compares
 * one candidate offer (LeanProduct::matchItem() shape) with a product passport
 * and returns the signals it found and a band.
 *
 *  - conflict:     the offer cannot be the passport's product (a unit value
 *                  differs, the condition is not accepted, the brand is another).
 *  - strong_match: identity is established (same GTIN, or same brand and MPN),
 *                  nothing contradicts it and the price is within the usual
 *                  range of the anchor. Never without a price anchor.
 *  - unclear:      everything else; the agent decides, or asks.
 *
 * The anti-goal is that a wrong product never gets strong_match, so every
 * rule errs towards unclear or conflict.
 *
 * Pure: depends on Gtin and UnitAttributes only. No WordPress calls, no
 * currency lookups of its own (the caller passes a cached-rate callable).
 * Reasons are plain English: they are stored with the decision and must not
 * change with the admin's locale.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 *
 */
class OfferMatcher
{

	const BAND_STRONG = 'strong_match';
	const BAND_CONFLICT = 'conflict';
	const BAND_UNCLEAR = 'unclear';

	/**
	 * An offer cheaper than 60 % or dearer than 167 % of the anchor is an
	 * outlier (167 % is the mirror image of 60 %).
	 */
	const PRICE_LOW_RATIO = 0.6;
	const PRICE_HIGH_RATIO = 1.67;

	/**
	 * Accepted offers needed before their median replaces the reference price.
	 */
	const MIN_MEDIAN_OFFERS = 2;

	// A passport MPN shorter than this is too likely to occur inside a title by chance.
	const MIN_MPN_IN_TITLE = 5;

	// A shorter MPN or model is not used to find where the product's name ends in a title.
	const MIN_CODE_ANCHOR = 3;

	// Longest value quoted inside a reason.
	const CLIP = 60;

	/**
	 * Accented letters folded to ASCII, by lowercase target.
	 */
	private static $fold_letters = array(
		'a' => 'àáâãäåāăąǎ',
		'c' => 'çćĉċč',
		'd' => 'ďđð',
		'e' => 'èéêëēĕėęěə',
		'g' => 'ĝğġģ',
		'h' => 'ĥħ',
		'i' => 'ìíîïĩīĭįıǐ',
		'j' => 'ĵ',
		'k' => 'ķ',
		'l' => 'ĺļľŀł',
		'n' => 'ñńņňŉ',
		'o' => 'òóôõöøōŏőǒ',
		'r' => 'ŕŗř',
		's' => 'śŝşš',
		't' => 'ţťŧ',
		'u' => 'ùúûüũūŭůűųǔ',
		'w' => 'ŵ',
		'y' => 'ýÿŷ',
		'z' => 'źżž',
	);

	private static $fold_map = null;

	/**
	 * Compare one candidate with the passport.
	 *
	 * @param array         $passport  Passport shape (gtins[{code}], brand, mpn, variant, conditions),
	 *                                 plus `name`: the page's title, when there is a page.
	 * @param array         $candidate LeanProduct::matchItem() shape.
	 * @param array|null    $anchor    From anchor(); null = no price anchor.
	 * @param callable|null $rate      function (string $from, string $to): float, 0 = unknown;
	 *                                 null = same currency only.
	 *
	 * @return array ['band' => string, 'signals' => array, 'reasons' => string[]]
	 */
	public static function evaluate(array $passport, array $candidate, ?array $anchor, ?callable $rate = null): array
	{
		$title = self::str($candidate, 'title');

		$shared_gtin = '';
		$gtin = self::gtinSignal($passport, $candidate, $shared_gtin);
		$brand = self::brandSignal($passport, $candidate, $title);
		$mpn = self::mpnSignal($passport, $candidate, $title);
		$units = self::unitRows($passport, $candidate, $title);
		$condition = self::conditionSignal($passport, $candidate);
		$price = self::priceSignal($candidate, $anchor, $rate);
		$bundle = self::bundleSignal($passport, $title);

		$signals = array(
			'gtin' => $gtin,
			'brand' => $brand,
			'mpn' => $mpn,
			'units' => $units,
			'condition' => $condition,
			'price' => $price,
			'bundle' => $bundle,
		);

		$unit_conflict = false;
		foreach ($units as $row)
		{
			if ($row['status'] === 'conflict')
			{
				$unit_conflict = true;
				break;
			}
		}
		$conflict = $unit_conflict || !$condition['allowed'] || $brand['status'] === 'different';
		$identity = self::hasIdentity($gtin['status'], $brand['status'], $mpn['status']);

		if ($conflict)
		{
			$band = self::BAND_CONFLICT;
		}
		elseif ($identity && $price['outlier'] === false && $bundle['marker'] === '')
		{
			$band = self::BAND_STRONG;
		}
		else
		{
			$band = self::BAND_UNCLEAR;
		}

		return array(
			'band' => $band,
			'signals' => $signals,
			'reasons' => self::reasons($signals, $band, $identity, $shared_gtin),
		);
	}

	/**
	 * The price the offers of a page are compared with: the median of the
	 * accepted offers when there are enough of them, else the passport's
	 * reference price, else null (no strong_match is possible then).
	 *
	 * @param array         $passport
	 * @param array         $accepted List of ['price' => float, 'currency' => string].
	 * @param callable|null $rate     See evaluate().
	 *
	 * @return array|null ['price' => float, 'currency' => string, 'basis' => 'accepted_median'|'reference']
	 */
	public static function anchor(array $passport, array $accepted, ?callable $rate = null): ?array
	{
		$reference = (isset($passport['reference']) && is_array($passport['reference'])) ? $passport['reference'] : array();
		$reference_currency = self::currency(isset($reference['currency']) ? $reference['currency'] : '');
		$reference_price = (isset($reference['price']) && is_numeric($reference['price'])) ? (float) $reference['price'] : 0.0;

		$rows = array();
		foreach ($accepted as $row)
		{
			if (!is_array($row) || !isset($row['price']) || !is_numeric($row['price']) || (float) $row['price'] <= 0)
			{
				continue;
			}
			$rows[] = array(
				'price' => (float) $row['price'],
				'currency' => self::currency(isset($row['currency']) ? $row['currency'] : ''),
			);
		}

		$target = $reference_currency !== '' ? $reference_currency : self::mostCommonCurrency($rows);
		if ($target !== '')
		{
			$prices = array();
			foreach ($rows as $row)
			{
				$converted = self::convert($row['price'], $row['currency'], $target, $rate);
				if ($converted !== null)
				{
					$prices[] = $converted;
				}
			}
			if (count($prices) >= self::MIN_MEDIAN_OFFERS)
			{
				return array(
					'price' => round((float) self::median($prices), 2),
					'currency' => $target,
					'basis' => 'accepted_median',
				);
			}
		}

		if ($reference_price > 0)
		{
			return array('price' => $reference_price, 'currency' => $reference_currency, 'basis' => 'reference');
		}

		return null;
	}

	/**
	 * A bundle word in the offer title: "Bundle" / "Bündel", "Combo", "with …
	 * Set / Holder / Lid", "including … Set", "3 Piece", or "Pack" that is not a count ("4 Pack",
	 * "4-Pack", "2er-Pack", "Pack of 1", "(Pack 4)" are counts, which the pack
	 * dimension reads). Also a second product joined after the passport's MPN
	 * or model: "AI209 Kit + 3M KCL Solution", "… & AcuRite 00613 Hygrometer".
	 * Sellers list console and game bundles under the console's own GTIN at a
	 * price close to it, so such an offer is never a strong_match. A page whose
	 * passport model or own name (`name`, the post title the caller adds) has
	 * a bundle word is a bundle page ("pH Tester Combo Kit"): only a second
	 * product joined to it counts there.
	 *
	 * @return array{marker: string} '' when the title has no bundle word.
	 */
	public static function bundleSignal(array $passport, string $title): array
	{
		$marker = self::bundleMarker($title);
		if ($marker !== '' && (self::bundleMarker(self::str($passport, 'model')) !== '' || self::bundleMarker(self::str($passport, 'name')) !== ''))
		{
			$marker = '';
		}
		if ($marker === '')
		{
			$marker = self::joinedProduct($passport, $title);
		}

		return array('marker' => $marker);
	}

	public static function bundleMarker(string $text): string
	{
		$text = self::utf8($text);
		if ($text === '')
		{
			return '';
		}
		if (preg_match('/(?<![\p{L}\d])(bundle|b\x{00FC}ndel|combo)(?!\p{L})/iu', $text, $m))
		{
			return $m[1];
		}
		// "with Maintenance Set", "Including the Calibration Set", "inkl. Reinigungs-Set",
		// "with Hot Handle Holder", "with Lid".
		if (preg_match('/(?<![\p{L}\d])(?:with|w\/|includ(?:ing|es)|incl\.?|inkl\.?|inklusive|mit)\s+(?:\S+\s+){0,3}?(?:\S+-)?(?:set|holder|lid)(?!\p{L})/iu', $text, $m))
		{
			return trim($m[0]);
		}
		// "3 Piece Set", "5-pc Cookware Set": several products sold together.
		if (preg_match('/(?<![\p{L}\d])(?:[2-9]|\d{2})[\s-]?(?:piece|pc|pcs)\b/iu', $text, $m))
		{
			return $m[0];
		}
		// "Pack" not preceded by a count ("4 ", "4-", "2er-", "2er ") and not
		// followed by one ("of 1", " 4").
		if (preg_match_all('/(?<![\p{L}\d])pack(?!\p{L})/iu', $text, $all, PREG_OFFSET_CAPTURE))
		{
			foreach ($all[0] as $hit)
			{
				$before = substr($text, max(0, $hit[1] - 8), min(8, $hit[1]));
				$after = substr($text, $hit[1] + strlen($hit[0]), 12);
				if (preg_match('/\d+\s*(?:er|x)?[\s-]*$/iu', $before) || preg_match('/^\s*(?:of\s*)?\d/iu', $after))
				{
					continue;
				}

				return $hit[0];
			}
		}

		return '';
	}

	/**
	 * Median of the numeric values, null when there are none.
	 *
	 * @param array $values
	 */
	public static function median(array $values): ?float
	{
		$numbers = array();
		foreach ($values as $value)
		{
			if (is_numeric($value))
			{
				$numbers[] = (float) $value;
			}
		}
		if (!$numbers)
		{
			return null;
		}

		sort($numbers);
		$count = count($numbers);
		$middle = intdiv($count, 2);

		return $count % 2 ? $numbers[$middle] : ($numbers[$middle - 1] + $numbers[$middle]) / 2;
	}

	/**
	 * Is price / anchor price too far from 1 for the same product?
	 */
	public static function isPriceOutlier(float $ratio): bool
	{
		return $ratio < self::PRICE_LOW_RATIO || $ratio > self::PRICE_HIGH_RATIO;
	}

	// ---------------------------------------------------------------- signals

	/**
	 * @return array ['status' => 'match'|'different'|'none', 'offer' => string[], 'flagged' => [['code', 'issue']]]
	 */
	private static function gtinSignal(array $passport, array $candidate, string &$shared = ''): array
	{
		$passport_raw = array();
		if (isset($passport['gtins']) && is_array($passport['gtins']))
		{
			foreach ($passport['gtins'] as $row)
			{
				$passport_raw[] = is_array($row) ? (isset($row['code']) ? $row['code'] : '') : $row;
			}
		}
		$offer_raw = (isset($candidate['gtins']) && is_array($candidate['gtins'])) ? $candidate['gtins'] : array();

		$flagged = array();
		$passport_codes = self::usableGtins($passport_raw, $flagged);
		$offer_codes = self::usableGtins($offer_raw, $flagged);

		$common = array_values(array_intersect($offer_codes, $passport_codes));
		if ($common)
		{
			$status = 'match';
			$shared = Gtin::display($common[0]);
		}
		elseif ($passport_codes && $offer_codes)
		{
			$status = 'different';
		}
		else
		{
			$status = 'none';
		}

		return array(
			'status' => $status,
			'offer' => array_map(array(Gtin::class, 'display'), $offer_codes),
			'flagged' => array_values($flagged),
		);
	}

	/**
	 * Valid GTIN-14s without an issue. Codes that have an issue are added to $flagged
	 * (display form, once per code).
	 *
	 * @return string[]
	 */
	private static function usableGtins(array $raw, array &$flagged): array
	{
		$usable = array();
		foreach ($raw as $code)
		{
			if (!is_scalar($code))
			{
				continue;
			}
			$gtin14 = Gtin::normalize((string) $code);
			if ($gtin14 === '')
			{
				continue;
			}
			$issue = Gtin::issue($gtin14);
			if ($issue !== '')
			{
				$display = Gtin::display($gtin14);
				$flagged[$display] = array('code' => $display, 'issue' => $issue);
				continue;
			}
			if (!in_array($gtin14, $usable, true))
			{
				$usable[] = $gtin14;
			}
		}

		return $usable;
	}

	/**
	 * @return array ['status' => 'match'|'different'|'none', 'offer' => string, 'passport' => string]
	 */
	private static function brandSignal(array $passport, array $candidate, string $title): array
	{
		$passport_brand = self::str($passport, 'brand');
		$offer_brand = self::str($candidate, 'brand');

		$a = self::tokens($passport_brand);
		$b = self::tokens($offer_brand);

		if (!$a || !$b)
		{
			$status = 'none';
		}
		else
		{
			$n = min(count($a), count($b));
			if (array_slice($a, 0, $n) === array_slice($b, 0, $n))
			{
				$status = 'match';
			}
			else
			{
				// A different brand field is no proof when the title still names the passport's brand.
				$status = self::containsSequence(self::tokens($title), $a) ? 'none' : 'different';
			}
		}

		return array('status' => $status, 'offer' => $offer_brand, 'passport' => $passport_brand);
	}

	/**
	 * @return array ['status' => 'match'|'different'|'in_title'|'none', 'offer' => string, 'passport' => string]
	 */
	private static function mpnSignal(array $passport, array $candidate, string $title): array
	{
		$passport_mpn = self::str($passport, 'mpn');
		$offer_mpn = self::str($candidate, 'mpn');
		// Some shops put the EAN into the MPN field (seen on Billiger): that is
		// no MPN at all, and comparing it with a real one would read "different".
		if (self::isOwnGtin($offer_mpn, $candidate))
		{
			$offer_mpn = '';
		}

		$p = self::mpnKey($passport_mpn);
		$o = self::mpnKey($offer_mpn);
		// Walmart and some shops put the part number in the model field only.
		if ($o === '' && $p !== '' && self::mpnKey(self::str($candidate, 'model')) === $p)
		{
			$o = $p;
			$offer_mpn = self::str($candidate, 'model');
		}

		if ($p !== '' && $o !== '')
		{
			$status = ($p === $o || self::isPrefixedMpn($o, $p)) ? 'match' : 'different';
		}
		elseif ($o === '' && strlen($p) >= self::MIN_MPN_IN_TITLE && self::codeInText($p, $title) !== null)
		{
			$status = 'in_title';
		}
		else
		{
			$status = 'none';
		}

		return array('status' => $status, 'offer' => $offer_mpn, 'passport' => $passport_mpn);
	}

	/**
	 * UnitAttributes::compare() rows for the passport variant against the
	 * offer's units. The title is the primary source: a dimension the title
	 * gives values for is read from the title alone, and a feature row is
	 * consulted only for a dimension the title says nothing about. Otherwise a
	 * feature such as "microSD up to 256 GB" or "1 Stück" would add a value that
	 * hides a conflict the title shows ("128GB", "2er-Pack").
	 *
	 * Every text is extracted on its own, so the end of one feature value never
	 * joins the start of the next ("128" + "GB").
	 *
	 * @return array[]
	 */
	private static function unitRows(array $passport, array $candidate, string $title): array
	{
		$variant = (isset($passport['variant']) && is_array($passport['variant'])) ? $passport['variant'] : array();

		$offer_units = UnitAttributes::extract($title);

		$from_features = array();
		foreach (UnitAttributes::DIMENSIONS as $dimension)
		{
			$from_features[$dimension] = array();
		}
		if (isset($candidate['features']) && is_array($candidate['features']))
		{
			foreach ($candidate['features'] as $feature)
			{
				if (!is_array($feature) || !isset($feature['value']) || !is_scalar($feature['value']))
				{
					continue;
				}
				$units = UnitAttributes::extract((string) $feature['value']);
				foreach (UnitAttributes::DIMENSIONS as $dimension)
				{
					$from_features[$dimension] = array_merge($from_features[$dimension], $units[$dimension]);
				}
			}
		}
		foreach (UnitAttributes::DIMENSIONS as $dimension)
		{
			if (!$offer_units[$dimension] && $from_features[$dimension])
			{
				$values = array_values(array_unique($from_features[$dimension]));
				sort($values);
				$offer_units[$dimension] = $values;
			}
		}

		return UnitAttributes::compare(UnitAttributes::fromVariant($variant), $offer_units);
	}

	/**
	 * Whether the passport accepts the candidate's condition (an offer that does not
	 * say counts as new). Public so the page audit applies the same rule.
	 *
	 * @return array ['value' => string, 'allowed' => bool]
	 */
	public static function conditionSignal(array $passport, array $candidate): array
	{
		$value = strtolower(self::str($candidate, 'condition'));
		if ($value === '')
		{
			$value = 'unknown';
		}

		$allowed_list = array();
		if (isset($passport['conditions']) && is_array($passport['conditions']))
		{
			foreach ($passport['conditions'] as $condition)
			{
				if (is_string($condition) && $condition !== '')
				{
					$allowed_list[] = strtolower($condition);
				}
			}
		}
		if (!$allowed_list)
		{
			$allowed_list = array('new');
		}

		// An offer that does not say counts as new.
		$effective = $value === 'unknown' ? 'new' : $value;

		return array('value' => $value, 'allowed' => in_array($effective, $allowed_list, true));
	}

	/**
	 * @return array ['value' => float|null, 'currency' => string, 'ratio' => float|null, 'outlier' => bool|null]
	 */
	private static function priceSignal(array $candidate, ?array $anchor, ?callable $rate): array
	{
		$value = (isset($candidate['price']) && is_numeric($candidate['price'])) ? (float) $candidate['price'] : null;
		$currency = self::currency(isset($candidate['currency']) ? $candidate['currency'] : '');

		$ratio = null;
		if ($value !== null && $value > 0 && $anchor
			&& isset($anchor['price']) && is_numeric($anchor['price']) && (float) $anchor['price'] > 0)
		{
			$anchor_currency = self::currency(isset($anchor['currency']) ? $anchor['currency'] : '');
			$converted = self::convert($value, $currency, $anchor_currency, $rate);
			if ($converted !== null)
			{
				$ratio = $converted / (float) $anchor['price'];
			}
		}

		return array(
			'value' => $value,
			'currency' => $currency,
			// Only the reported ratio is rounded: the outlier test is exact (a ratio of 1.6703 is an outlier although it is reported as 1.67).
			'ratio' => $ratio === null ? null : round($ratio, 3),
			'outlier' => $ratio === null ? null : self::isPriceOutlier($ratio),
		);
	}

	private static function hasIdentity(string $gtin, string $brand, string $mpn): bool
	{
		if ($gtin === 'match' && $mpn !== 'different')
		{
			return true;
		}

		return $brand === 'match' && $mpn === 'match' && $gtin !== 'different';
	}

	// ---------------------------------------------------------------- reasons

	/**
	 * What decided the band, most important first. Every band has at least one.
	 *
	 * @return string[]
	 */
	private static function reasons(array $signals, string $band, bool $identity, string $shared_gtin): array
	{
		$reasons = array();

		foreach ($signals['units'] as $row)
		{
			if ($row['status'] !== 'conflict')
			{
				continue;
			}
			$offer = array();
			foreach (array_slice($row['offer'], 0, 3) as $value)
			{
				$offer[] = self::formatUnit($row['dimension'], $value);
			}
			$reasons[] = sprintf(
				'%s differs: passport %s, offer %s',
				self::unitLabel($row['dimension']),
				self::formatUnit($row['dimension'], $row['passport']),
				implode(' / ', $offer)
			);
		}

		if (!$signals['condition']['allowed'])
		{
			$reasons[] = sprintf('Condition "%s" is not accepted for this product', self::clip($signals['condition']['value']));
		}

		$brand = $signals['brand'];
		if ($brand['status'] === 'different')
		{
			$reasons[] = sprintf('Brand differs: offer "%s", passport "%s"', self::clip($brand['offer']), self::clip($brand['passport']));
		}

		$gtin = $signals['gtin'];
		if ($gtin['status'] === 'match')
		{
			$reasons[] = 'GTIN ' . $shared_gtin . ' matches';
		}
		elseif ($gtin['status'] === 'different')
		{
			$reasons[] = 'GTIN differs: offer ' . implode(', ', array_slice($gtin['offer'], 0, 2)) . ' is not a passport GTIN';
		}
		foreach ($gtin['flagged'] as $flag)
		{
			$reasons[] = sprintf('GTIN %s ignored (%s code)', $flag['code'], $flag['issue']);
		}

		$mpn = $signals['mpn'];
		if ($mpn['status'] === 'match')
		{
			$reasons[] = sprintf('MPN %s matches', self::clip($mpn['passport']));
		}
		elseif ($mpn['status'] === 'different')
		{
			$reasons[] = sprintf('MPN differs: offer "%s", passport "%s"', self::clip($mpn['offer']), self::clip($mpn['passport']));
		}
		elseif ($mpn['status'] === 'in_title')
		{
			$reasons[] = sprintf('MPN %s appears in the title only', self::clip($mpn['passport']));
		}

		if ($brand['status'] === 'match')
		{
			$reasons[] = sprintf('Brand matches (%s)', self::clip($brand['passport']));
		}

		$price = $signals['price'];
		if ($price['outlier'] === true)
		{
			$reasons[] = sprintf('Price is %d%% of the anchor price, outside the usual range', (int) round($price['ratio'] * 100));
		}
		elseif ($price['outlier'] === null && $band !== self::BAND_CONFLICT && $identity)
		{
			$reasons[] = 'Price cannot be checked: no price anchor, or no known exchange rate';
		}

		if ($band === self::BAND_UNCLEAR && $signals['bundle']['marker'] !== '')
		{
			$reasons[] = sprintf('Title says "%s": the offer may include more than the product (a bundle); check what it contains', self::clip($signals['bundle']['marker']));
		}

		if ($band === self::BAND_UNCLEAR && !$identity)
		{
			$reasons[] = $gtin['status'] === 'match'
				? 'Identity is not established: the GTIN matches but the MPN differs; check which code is right'
				: 'Identity is not established: no shared GTIN, and no matching brand and MPN';
		}

		return $reasons;
	}

	private static function unitLabel(string $dimension): string
	{
		$labels = array('memory' => 'Memory', 'volume' => 'Volume', 'weight' => 'Weight', 'pack' => 'Pack count');

		return isset($labels[$dimension]) ? $labels[$dimension] : ucfirst($dimension);
	}

	/**
	 * @param float|int $value Base unit (GB, ml, g) or a pack count.
	 */
	private static function formatUnit(string $dimension, $value): string
	{
		if ($dimension === 'pack')
		{
			return (int) $value . '-pack';
		}
		$suffix = array('memory' => ' GB', 'volume' => ' ml', 'weight' => ' g');
		$number = rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.');

		return $number . (isset($suffix[$dimension]) ? $suffix[$dimension] : '');
	}

	// ---------------------------------------------------------------- helpers

	private static function str(array $source, string $key): string
	{
		return (isset($source[$key]) && is_scalar($source[$key])) ? trim((string) $source[$key]) : '';
	}

	private static function clip(string $text): string
	{
		if (function_exists('mb_substr') && function_exists('mb_strlen'))
		{
			return mb_strlen($text, 'UTF-8') > self::CLIP ? mb_substr($text, 0, self::CLIP, 'UTF-8') . '...' : $text;
		}

		return strlen($text) > self::CLIP ? substr($text, 0, self::CLIP) . '...' : $text;
	}

	/** True when $mpn is one of the candidate's own GTINs (the same code, any spelling). */
	private static function isOwnGtin(string $mpn, array $candidate): bool
	{
		$code = Gtin::normalize($mpn);
		if ($code === '' || !isset($candidate['gtins']) || !is_array($candidate['gtins']))
		{
			return false;
		}
		foreach ($candidate['gtins'] as $gtin)
		{
			if (is_scalar($gtin) && Gtin::normalize((string) $gtin) === $code)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Uppercase [A-Z0-9] only: "WH-1000XM5" and "wh1000 xm5" compare equal.
	 * "+" is part of a model code ("Galaxy S24" is not "Galaxy S24+"), so it
	 * becomes "PLUS" first: "S24+", "S24 +" and "S24 Plus" compare equal.
	 */
	/**
	 * A seller's own number in front of the manufacturer's MPN: Amazon lists
	 * Lodge's L8SK3 as "17L8SK3". Only a short all-digit prefix to a code that
	 * starts with a letter, so "AI209" against "AI209T" stays different.
	 */
	private static function isPrefixedMpn(string $offer_key, string $passport_key): bool
	{
		$extra = strlen($offer_key) - strlen($passport_key);

		return $extra >= 1 && $extra <= 3
			&& substr($offer_key, $extra) === $passport_key
			&& preg_match('/^\d+$/', substr($offer_key, 0, $extra))
			&& preg_match('/^[A-Z]/', $passport_key);
	}

	private static function mpnKey(string $text): string
	{
		return (string) preg_replace('/[^A-Z0-9]/', '', str_replace('+', 'PLUS', strtoupper($text)));
	}

	/**
	 * Where the code $key (mpnKey() form) ends in $text, found as a whole code:
	 * the separators inside it may differ ("WH1000XM5" is in "WH-1000 XM5"),
	 * but it must not run into a longer code. "AI209" is not in "AI209-T",
	 * "AI2090" or "XAI209", and "S24" is not in "S24+" or "S24 Plus".
	 *
	 * @return int|null Byte offset just past the first such occurrence, null when there is none.
	 */
	private static function codeInText(string $key, string $text): ?int
	{
		if ($key === '')
		{
			return null;
		}

		// The text in mpnKey() form, with the byte offset in $text of every character kept.
		$upper = strtoupper($text);
		$flat = '';
		$offsets = array();
		$length = strlen($upper);
		for ($i = 0; $i < $length; $i++)
		{
			$char = $upper[$i];
			if ($char === '+')
			{
				$flat .= 'PLUS';
				array_push($offsets, $i, $i, $i, $i);
			}
			elseif (($char >= 'A' && $char <= 'Z') || ($char >= '0' && $char <= '9'))
			{
				$flat .= $char;
				$offsets[] = $i;
			}
		}

		$from = 0;
		while (($pos = strpos($flat, $key, $from)) !== false)
		{
			$first = $offsets[$pos];
			$last = $offsets[$pos + strlen($key) - 1];
			$before = $first > 0 ? $text[$first - 1] : ' ';
			$rest = (string) substr($text, $last + 1);
			if (!preg_match('/[A-Za-z0-9]/', $before)
				&& !preg_match('/^(?:[A-Za-z0-9]|[-\/_.][A-Za-z0-9]|\s*(?:\+|plus(?![A-Za-z])))/i', $rest))
			{
				return $last + 1;
			}
			$from = $pos + 1;
		}

		return null;
	}

	/**
	 * A second product joined to the passport's after its MPN or model in the
	 * title: "AI209 Kit + 3M KCL Solution", "AI209 … & AcuRite 00613". The sign
	 * needs a space on both sides and a word after it: "2 + 1" is a count and
	 * "S24+" a model. "&" also joins plain words ("Black & Silver"), so it
	 * needs a number within the next three words, as a second model has. A
	 * join the passport names itself (a variant "Wi-Fi + Cellular") is none.
	 *
	 * @return string The sign and the word after it, '' when there is none.
	 */
	private static function joinedProduct(array $passport, string $title): string
	{
		$title = self::utf8($title);

		$after = null;
		foreach (array('mpn', 'model') as $field)
		{
			$key = self::mpnKey(self::str($passport, $field));
			if (strlen($key) < self::MIN_CODE_ANCHOR)
			{
				continue;
			}
			$end = self::codeInText($key, $title);
			if ($end !== null && ($after === null || $end < $after))
			{
				$after = $end;
			}
		}
		if ($after === null
			|| !preg_match_all('/\s([+&])\s+([\p{L}\d][^\s,;]*)((?:\s+\S+){0,2})/u', (string) substr($title, $after), $joins, PREG_SET_ORDER))
		{
			return '';
		}

		$named = array(self::str($passport, 'model'), self::str($passport, 'name'));
		if (isset($passport['variant']) && is_array($passport['variant']))
		{
			foreach ($passport['variant'] as $value)
			{
				$named[] = is_scalar($value) ? (string) $value : '';
			}
		}

		foreach ($joins as $join)
		{
			list(, $sign, $word, $more) = $join;
			if (preg_match('/^\d+$/', $word) || ($sign === '&' && !preg_match('/\d/', $word . $more)))
			{
				continue;
			}
			$pattern = '/' . preg_quote($sign, '/') . '\s*' . preg_quote($word, '/') . '(?![\p{L}\d])/iu';
			foreach ($named as $text)
			{
				if ($text !== '' && preg_match($pattern, self::utf8($text)))
				{
					continue 2;
				}
			}

			return $sign . ' ' . $word;
		}

		return '';
	}

	private static function currency($raw): string
	{
		return is_scalar($raw) ? strtoupper(trim((string) $raw)) : '';
	}

	/**
	 * @param array[] $rows Each with 'currency'.
	 */
	private static function mostCommonCurrency(array $rows): string
	{
		$counts = array();
		foreach ($rows as $row)
		{
			if ($row['currency'] !== '')
			{
				$counts[$row['currency']] = (isset($counts[$row['currency']]) ? $counts[$row['currency']] : 0) + 1;
			}
		}
		if (!$counts)
		{
			return '';
		}

		// arsort() is not stable before PHP 8: the first currency seen wins a tie.
		$best = '';
		$best_count = 0;
		foreach ($counts as $currency => $count)
		{
			if ($count > $best_count)
			{
				$best = (string) $currency;
				$best_count = $count;
			}
		}

		return $best;
	}

	/**
	 * The amount in the target currency, null when it cannot be converted.
	 * An empty currency on either side is never comparable.
	 */
	private static function convert(float $amount, string $from, string $to, ?callable $rate): ?float
	{
		if ($from === '' || $to === '')
		{
			return null;
		}
		if ($from === $to)
		{
			return $amount;
		}
		if ($rate === null)
		{
			return null;
		}

		$factor = (float) call_user_func($rate, $from, $to);

		return ($factor > 0 && is_finite($factor)) ? $amount * $factor : null;
	}

	/**
	 * Lowercase words of a text without accents and apostrophes.
	 *
	 * @return string[]
	 */
	private static function tokens(string $text): array
	{
		$text = self::utf8($text);
		if ($text === '')
		{
			return array();
		}

		$text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
		$text = strtr($text, self::foldMap());
		// "Hill's", "L'Oréal": the apostrophe joins, it does not split.
		$text = str_replace(array("'", "\u{2019}", '`', "\u{00B4}"), '', $text);

		$tokens = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

		return $tokens ? $tokens : array();
	}

	/**
	 * Does $haystack hold $needle as a run of consecutive tokens?
	 */
	private static function containsSequence(array $haystack, array $needle): bool
	{
		$length = count($needle);
		if ($length === 0 || $length > count($haystack))
		{
			return false;
		}
		for ($i = 0; $i <= count($haystack) - $length; $i++)
		{
			if (array_slice($haystack, $i, $length) === $needle)
			{
				return true;
			}
		}

		return false;
	}

	private static function foldMap(): array
	{
		if (self::$fold_map === null)
		{
			$map = array('ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe', 'þ' => 'th');
			foreach (self::$fold_letters as $ascii => $letters)
			{
				foreach (preg_split('//u', $letters, -1, PREG_SPLIT_NO_EMPTY) as $letter)
				{
					$map[$letter] = $ascii;
				}
			}
			self::$fold_map = $map;
		}

		return self::$fold_map;
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
