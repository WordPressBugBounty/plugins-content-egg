<?php

namespace ContentEgg\application\components\matching;

use ContentEgg\application\helpers\Gtin;

defined('\ABSPATH') || exit;

/**
 * ProductPassport class file
 *
 * What a page is about: the one product (and variant) whose prices the page
 * compares. Kept in one post meta, `_cegg_passport`: GTINs with the source of
 * each, brand, MPN, model, variant values ("any" for the dimensions that do
 * not matter), the offer conditions the page accepts and a reference price.
 *
 * normalize() validates and canonicalizes an input array and throws a
 * field-specific \InvalidArgumentException; save() adds the four stamps
 * (created_at / created_by / updated_at / updated_by) and writes it.
 *
 * A page "has a passport" when the meta holds a non-empty array. Every
 * behavior that is new on such pages sits behind has(), so a page without a
 * passport behaves exactly as it did before. has() is hot (rendering asks it
 * per offer), so it answers from a per-request cache and returns false for a
 * post id below 1 without reading anything.
 *
 * Messages are plain English: they go to an AI agent, not into the admin UI.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 *
 */
class ProductPassport
{

	const META = '_cegg_passport';
	const VERSION = 1;

	const MAX_GTINS = 20;
	const MAX_VARIANT_DIMS = 8;

	const CONDITIONS = array('new', 'used', 'refurbished');
	const CONDITIONS_DEFAULT = array('new');

	// Why Gtin::issue() refuses a code, as it reads inside "gtins[0].code X is ...".
	const GTIN_ISSUES = array(
		'restricted' => 'a restricted (in-store) code',
		'coupon' => 'a coupon code',
		'placeholder' => 'a placeholder code',
	);

	// The source that says a person gave the code.
	const SOURCE_USER = 'user';

	const MAX_TEXT = 200;
	const MAX_DIMENSION_NAME = 60;
	const MAX_VARIANT_VALUE = 100;
	const MAX_URL = 1000;

	// post_id => bool, filled by has(), dropped by save() and delete().
	private static $cache = array();

	/**
	 * Validate and canonicalize a passport. Fields it does not know (post_id,
	 * clear...) are ignored. The result carries no stamps; save() adds them.
	 *
	 * @throws \InvalidArgumentException naming the field that is wrong.
	 */
	public static function normalize(array $input): array
	{
		$passport = array(
			'v' => self::VERSION,
			'gtins' => self::normalizeGtins(isset($input['gtins']) ? $input['gtins'] : array()),
			'brand' => self::text($input, 'brand'),
			'mpn' => self::text($input, 'mpn'),
			'model' => self::text($input, 'model'),
			'variant' => self::normalizeVariant(isset($input['variant']) ? $input['variant'] : array()),
			'conditions' => self::normalizeConditions(array_key_exists('conditions', $input) ? $input['conditions'] : null),
			'reference' => self::normalizeReference(isset($input['reference']) ? $input['reference'] : array()),
		);

		// A passport names a product: without a GTIN, an MPN or a model there is nothing to match offers against.
		if (!$passport['gtins'] && $passport['mpn'] === '' && $passport['model'] === '')
		{
			throw new \InvalidArgumentException('The passport needs something that identifies the product: at least one GTIN, an MPN or a model name.');
		}

		return $passport;
	}

	/**
	 * The stored passport, or null when the page has none.
	 */
	public static function get(int $post_id): ?array
	{
		if ($post_id <= 0)
		{
			return null;
		}

		$passport = \get_post_meta($post_id, self::META, true);

		return (is_array($passport) && $passport) ? $passport : null;
	}

	/**
	 * Whether the page has a passport. Cached per request; save() and
	 * delete() drop the entry.
	 */
	public static function has(int $post_id): bool
	{
		if ($post_id <= 0)
		{
			return false;
		}

		if (!isset(self::$cache[$post_id]))
		{
			self::$cache[$post_id] = self::get($post_id) !== null;
		}

		return self::$cache[$post_id];
	}

	/**
	 * Write a normalized passport. A first save sets created_*; a later one
	 * keeps them and moves updated_*. Returns what was stored.
	 *
	 * @throws \InvalidArgumentException when $post_id is below 1.
	 */
	public static function save(int $post_id, array $normalized, int $user_id, int $now): array
	{
		if ($post_id <= 0)
		{
			throw new \InvalidArgumentException('A passport needs a post: post_id must be a positive integer.');
		}

		$existing = self::get($post_id);
		$created_at = ($existing && isset($existing['created_at'])) ? (int) $existing['created_at'] : $now;
		$created_by = ($existing && isset($existing['created_by'])) ? (int) $existing['created_by'] : $user_id;

		$passport = array_merge($normalized, array(
			'created_at' => $created_at,
			'created_by' => $created_by,
			'updated_at' => $now,
			'updated_by' => $user_id,
		));

		// update_post_meta() unslashes its value; slash it so a backslash in a brand survives.
		\update_post_meta($post_id, self::META, \wp_slash($passport));
		unset(self::$cache[$post_id]);

		return $passport;
	}

	public static function delete(int $post_id): void
	{
		if ($post_id <= 0)
		{
			return;
		}

		\delete_post_meta($post_id, self::META);
		unset(self::$cache[$post_id]);
	}

	/**
	 * Forget what has() cached. For code that writes the meta some other way
	 * than save() and delete(), and for tests.
	 */
	public static function resetCache(): void
	{
		self::$cache = array();
	}

	/**
	 * The primary GTIN in display form (the flagged one, else the first), or ''.
	 */
	public static function primaryGtin(array $passport): string
	{
		if (!isset($passport['gtins']) || !is_array($passport['gtins']))
		{
			return '';
		}

		$first = '';
		foreach ($passport['gtins'] as $row)
		{
			$code = is_array($row) ? (isset($row['code']) ? (string) $row['code'] : '') : (is_scalar($row) ? (string) $row : '');
			if ($code === '')
			{
				continue;
			}
			if (is_array($row) && !empty($row['primary']))
			{
				return $code;
			}
			if ($first === '')
			{
				$first = $code;
			}
		}

		return $first;
	}

	private static function normalizeGtins($gtins): array
	{
		if (!is_array($gtins))
		{
			throw new \InvalidArgumentException('gtins must be a list of {code, source, primary} objects.');
		}
		if (count($gtins) > self::MAX_GTINS)
		{
			throw new \InvalidArgumentException(sprintf('gtins can hold at most %d codes.', self::MAX_GTINS));
		}

		$by_code = array();
		$i = -1;
		foreach ($gtins as $row)
		{
			$i++;
			if (!is_array($row))
			{
				throw new \InvalidArgumentException(sprintf('gtins[%d] must be an object with code and source.', $i));
			}

			$raw = (isset($row['code']) && is_scalar($row['code'])) ? trim((string) $row['code']) : '';
			if ($raw === '')
			{
				throw new \InvalidArgumentException(sprintf('gtins[%d].code is required.', $i));
			}
			$gtin14 = Gtin::normalize($raw);
			if ($gtin14 === '')
			{
				throw new \InvalidArgumentException(sprintf('gtins[%d].code "%s" is not a valid GTIN (wrong length or check digit).', $i, self::clip($raw)));
			}
			$issue = Gtin::issue($gtin14);
			if ($issue !== '')
			{
				throw new \InvalidArgumentException(sprintf(
					'gtins[%d].code %s is %s, not a product GTIN.',
					$i,
					self::clip($raw),
					isset(self::GTIN_ISSUES[$issue]) ? self::GTIN_ISSUES[$issue] : 'an unusable code (' . $issue . ')'
				));
			}
			$code = Gtin::display($gtin14);

			$source = self::source($row, $i);
			// A REST client may send the flag as the string "false".
			$primary = isset($row['primary']) && filter_var($row['primary'], FILTER_VALIDATE_BOOLEAN);

			if (isset($by_code[$code]))
			{
				// The same code twice: the first source stays, a primary flag on either counts.
				$by_code[$code]['primary'] = $by_code[$code]['primary'] || $primary;
				continue;
			}
			$by_code[$code] = array('code' => $code, 'source' => $source, 'primary' => $primary);
		}

		$out = array_values($by_code);
		$primaries = 0;
		foreach ($out as $row)
		{
			if ($row['primary'])
			{
				$primaries++;
			}
		}
		if ($primaries > 1)
		{
			throw new \InvalidArgumentException('gtins can have only one primary code.');
		}
		if ($out && $primaries === 0)
		{
			$out[0]['primary'] = true;
		}

		return $out;
	}

	private static function source(array $row, int $i): string
	{
		$source = (isset($row['source']) && is_scalar($row['source'])) ? trim((string) $row['source']) : '';

		if ($source === self::SOURCE_USER)
		{
			return $source;
		}
		if (strlen($source) <= self::MAX_URL
			&& (preg_match('~^https?://[^\s/?#]+\S*$~i', $source) || preg_match('~^offer:[^:\s]+:.+$~', $source)))
		{
			return $source;
		}

		throw new \InvalidArgumentException(sprintf(
			'gtins[%d].source must be the http(s) URL of the page the code was read from, "offer:<module_id>:<unique_id>" for an offer from your searches, or "user" when the user gave it. A GTIN is never taken from memory.',
			$i
		));
	}

	private static function normalizeVariant($variant): array
	{
		if (!is_array($variant) || ($variant && array_keys($variant) === range(0, count($variant) - 1)))
		{
			throw new \InvalidArgumentException('variant must be an object of {dimension: value}.');
		}
		if (count($variant) > self::MAX_VARIANT_DIMS)
		{
			throw new \InvalidArgumentException(sprintf('variant can name at most %d dimensions.', self::MAX_VARIANT_DIMS));
		}

		$out = array();
		$seen = array();
		foreach ($variant as $name => $value)
		{
			$name = trim((string) $name);
			if ($name === '' || self::length($name) > self::MAX_DIMENSION_NAME)
			{
				throw new \InvalidArgumentException(sprintf('variant dimension names must be 1 to %d characters.', self::MAX_DIMENSION_NAME));
			}
			if (!is_scalar($value) || is_bool($value))
			{
				throw new \InvalidArgumentException(sprintf('variant.%s must be a text value (use "any" when every variant is fine).', self::clip($name)));
			}
			$value = trim((string) $value);
			if ($value === '' || self::length($value) > self::MAX_VARIANT_VALUE)
			{
				throw new \InvalidArgumentException(sprintf('variant.%s must be 1 to %d characters (use "any" when every variant is fine).', self::clip($name), self::MAX_VARIANT_VALUE));
			}

			// The same folding as VariantFamily, so a pair like "Ä"/"ä" is one name in both.
			$lower = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
			if (isset($seen[$lower]))
			{
				throw new \InvalidArgumentException(sprintf('variant names the dimension "%s" twice.', self::clip($name)));
			}
			$seen[$lower] = true;

			$out[$name] = strtolower($value) === 'any' ? 'any' : $value;
		}

		return $out;
	}

	private static function normalizeConditions($conditions): array
	{
		if ($conditions === null)
		{
			return self::CONDITIONS_DEFAULT;
		}
		if (!is_array($conditions) || !$conditions)
		{
			throw new \InvalidArgumentException('conditions must be a non-empty list of: ' . implode(', ', self::CONDITIONS) . '.');
		}

		$picked = array();
		foreach ($conditions as $condition)
		{
			$condition = is_scalar($condition) ? strtolower(trim((string) $condition)) : '';
			if (!in_array($condition, self::CONDITIONS, true))
			{
				throw new \InvalidArgumentException('conditions can only hold: ' . implode(', ', self::CONDITIONS) . '.');
			}
			$picked[$condition] = true;
		}

		// Canonical order, no duplicates.
		return array_values(array_intersect(self::CONDITIONS, array_keys($picked)));
	}

	private static function normalizeReference($reference): array
	{
		if (!is_array($reference))
		{
			throw new \InvalidArgumentException('reference must be an object of {price, currency, img}.');
		}

		$price = null;
		if (isset($reference['price']) && $reference['price'] !== '')
		{
			$value = $reference['price'];
			if (is_bool($value) || !is_numeric($value) || !is_finite((float) $value) || (float) $value < 0)
			{
				throw new \InvalidArgumentException('reference.price must be a number of 0 or more, or null.');
			}
			$price = (float) $value;
		}

		$currency = '';
		if (isset($reference['currency']) && $reference['currency'] !== '')
		{
			$value = is_string($reference['currency']) ? strtoupper(trim($reference['currency'])) : '';
			if (!preg_match('/^[A-Z]{3}$/', $value))
			{
				throw new \InvalidArgumentException('reference.currency must be a three-letter currency code such as EUR, or empty.');
			}
			$currency = $value;
		}

		$img = '';
		if (isset($reference['img']) && $reference['img'] !== '')
		{
			$value = is_string($reference['img']) ? trim($reference['img']) : '';
			if (strlen($value) > self::MAX_URL || !preg_match('~^https?://[^\s/?#]+\S*$~i', $value))
			{
				throw new \InvalidArgumentException('reference.img must be an http(s) URL, or empty.');
			}
			$img = $value;
		}

		return array('price' => $price, 'currency' => $currency, 'img' => $img);
	}

	/**
	 * A short text field (brand, mpn, model): whitespace collapsed, at most
	 * MAX_TEXT characters. A number (an MPN of digits) is taken as text.
	 */
	private static function text(array $input, string $field): string
	{
		if (!isset($input[$field]))
		{
			return '';
		}
		$value = $input[$field];
		if (!is_scalar($value) || is_bool($value))
		{
			throw new \InvalidArgumentException(sprintf('%s must be text.', $field));
		}

		$value = trim((string) $value);
		$collapsed = preg_replace('/\s+/u', ' ', $value);
		$value = $collapsed !== null ? $collapsed : $value;

		if (self::length($value) > self::MAX_TEXT)
		{
			throw new \InvalidArgumentException(sprintf('%s can be at most %d characters.', $field, self::MAX_TEXT));
		}

		return $value;
	}

	private static function length(string $text): int
	{
		return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
	}

	// A value quoted inside a message.
	private static function clip(string $text): string
	{
		if (function_exists('mb_substr') && function_exists('mb_strlen'))
		{
			return mb_strlen($text, 'UTF-8') > 40 ? mb_substr($text, 0, 40, 'UTF-8') . '...' : $text;
		}

		return strlen($text) > 40 ? substr($text, 0, 40) . '...' : $text;
	}
}
