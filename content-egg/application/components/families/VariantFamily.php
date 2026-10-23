<?php

namespace ContentEgg\application\components\families;

use ContentEgg\application\components\matching\ProductPassport;

defined('\ABSPATH') || exit;

/**
 * VariantFamily class file
 *
 * Pages that are variants of one product (iPhone 15 128 GB / 256 GB ...) are
 * linked by a term of the private `cegg_family` taxonomy. The family is
 * defined in the term meta `_cegg_family`: its variant dimensions (each with
 * an optional schema.org property), the display order of the values, the
 * primary page and the brand.
 *
 * This class is the pure part: normalize() validates and canonicalizes a
 * definition, memberValues() reads a page's passport variant in family terms,
 * sameValues() / duplicateOf() decide whether two pages would be the same
 * variant. No WordPress calls, no storage.
 *
 * Messages are plain English: they go to an AI agent, not into the admin UI.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 *
 */
class VariantFamily
{

	const TAXONOMY = 'cegg_family';
	const META = '_cegg_family';
	const VERSION = 1;

	const MAX_DIMENSIONS = 8;
	const MAX_ORDER_VALUES = 50;
	const MAX_NAME = 120;
	const MAX_BRAND = 100;

	const SCHEMA_PROPERTIES = array('color', 'size', 'material', 'pattern', 'suggestedAge', 'suggestedGender');

	// The passport value that means "every variant is fine".
	const ANY = 'any';

	/**
	 * Validate and canonicalize a family definition. Fields it does not know
	 * are ignored.
	 *
	 * @throws \InvalidArgumentException naming the field that is wrong.
	 */
	public static function normalize(array $input): array
	{
		$dimensions = self::normalizeDimensions(isset($input['dimensions']) ? $input['dimensions'] : null);

		return array(
			'v' => self::VERSION,
			'dimensions' => $dimensions,
			'order' => self::normalizeOrder(isset($input['order']) ? $input['order'] : array(), $dimensions),
			'primary' => self::normalizePrimary(isset($input['primary']) ? $input['primary'] : 0),
			'brand' => self::normalizeBrand(isset($input['brand']) ? $input['brand'] : ''),
		);
	}

	/**
	 * A page's variant values in family terms: [dimension name (family
	 * spelling) => value], in family order. Passport keys match
	 * case-insensitively; "any" stays "any".
	 *
	 * @throws \InvalidArgumentException when the variant lacks a dimension of the family, has a key the family lacks
	 *                                   or names one dimension twice (keys that differ only in case).
	 */
	public static function memberValues(array $family, array $variant): array
	{
		$dimensions = (isset($family['dimensions']) && is_array($family['dimensions'])) ? $family['dimensions'] : array();

		$by_key = array();
		foreach ($variant as $name => $value)
		{
			$key = self::lower(trim((string) $name));
			if (isset($by_key[$key]))
			{
				throw new \InvalidArgumentException(sprintf(
					'The variant has both "%s" and "%s": they name the same dimension (names are compared without case).',
					self::clip(trim($by_key[$key][0])),
					self::clip(trim((string) $name))
				));
			}
			$by_key[$key] = array((string) $name, $value);
		}

		$out = array();
		foreach ($dimensions as $dimension)
		{
			$name = (string) $dimension['name'];
			$key = self::lower($name);
			if (!isset($by_key[$key]))
			{
				throw new \InvalidArgumentException(sprintf('The variant has no value for the family dimension "%s" (use "any" when every value is fine).', self::clip($name)));
			}

			$value = $by_key[$key][1];
			if (!is_scalar($value) || is_bool($value) || trim((string) $value) === '')
			{
				throw new \InvalidArgumentException(sprintf('variant.%s must be a text value.', self::clip($name)));
			}
			$value = trim((string) $value);

			$out[$name] = self::lower($value) === self::ANY ? self::ANY : $value;
			unset($by_key[$key]);
		}

		if ($by_key)
		{
			$extra = reset($by_key);
			throw new \InvalidArgumentException(sprintf('The variant has "%s", which is not a dimension of this family.', self::clip(trim($extra[0]))));
		}

		return $out;
	}

	/**
	 * Whether two member value sets are the same variant: per dimension,
	 * trimmed and case-insensitive. "any" is compared as a value.
	 */
	public static function sameValues(array $a, array $b): bool
	{
		if (count($a) !== count($b))
		{
			return false;
		}

		$b_by_key = array();
		foreach ($b as $name => $value)
		{
			$b_by_key[self::lower(trim((string) $name))] = self::lower(trim((string) $value));
		}

		foreach ($a as $name => $value)
		{
			$key = self::lower(trim((string) $name));
			if (!isset($b_by_key[$key]) || $b_by_key[$key] !== self::lower(trim((string) $value)))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * The post id of another member that already holds this variant, else 0.
	 * $members is [{post_id, status, variant}]. Every member except one in the
	 * trash counts: a draft (or pending, scheduled, private) page would collide
	 * once it is published. A member whose stored variant no longer fits the
	 * family cannot collide.
	 *
	 * @throws \InvalidArgumentException when $variant does not fit the family.
	 */
	public static function duplicateOf(array $family, array $members, int $post_id, array $variant): int
	{
		$values = self::memberValues($family, $variant);

		foreach ($members as $member)
		{
			if (!is_array($member) || !isset($member['post_id']) || (int) $member['post_id'] === $post_id)
			{
				continue;
			}
			if (isset($member['status']) && $member['status'] === 'trash')
			{
				continue;
			}

			try
			{
				$theirs = self::memberValues($family, isset($member['variant']) && is_array($member['variant']) ? $member['variant'] : array());
			}
			catch (\InvalidArgumentException $e)
			{
				continue;
			}

			if (self::sameValues($values, $theirs))
			{
				return (int) $member['post_id'];
			}
		}

		return 0;
	}

	private static function normalizeDimensions($dimensions): array
	{
		if (!is_array($dimensions) || !$dimensions)
		{
			throw new \InvalidArgumentException('dimensions must be a non-empty list of {name, schema} objects.');
		}
		if (count($dimensions) > self::MAX_DIMENSIONS)
		{
			throw new \InvalidArgumentException(sprintf('dimensions can hold at most %d entries.', self::MAX_DIMENSIONS));
		}

		$out = array();
		$seen = array();
		// schema property => the dimension that maps to it: one property describes one dimension.
		$schema_owner = array();
		$i = -1;
		foreach (array_values($dimensions) as $row)
		{
			$i++;
			if (!is_array($row))
			{
				throw new \InvalidArgumentException(sprintf('dimensions[%d] must be an object with name and schema.', $i));
			}

			$name = (isset($row['name']) && is_scalar($row['name']) && !is_bool($row['name'])) ? trim((string) $row['name']) : '';
			if ($name === '' || self::length($name) > ProductPassport::MAX_DIMENSION_NAME)
			{
				throw new \InvalidArgumentException(sprintf('dimensions[%d].name must be 1 to %d characters.', $i, ProductPassport::MAX_DIMENSION_NAME));
			}

			$lower = self::lower($name);
			if (isset($seen[$lower]))
			{
				throw new \InvalidArgumentException(sprintf('dimensions[%d].name "%s" repeats an earlier dimension (names are compared without case).', $i, self::clip($name)));
			}
			$seen[$lower] = true;

			$schema = self::normalizeSchema(isset($row['schema']) ? $row['schema'] : null, $i);
			if ($schema !== null)
			{
				if (isset($schema_owner[$schema]))
				{
					throw new \InvalidArgumentException(sprintf(
						'dimensions "%s" and "%s" both map to the schema property "%s": a property can describe one dimension only, so give one of them another property or null.',
						self::clip($schema_owner[$schema]),
						self::clip($name),
						$schema
					));
				}
				$schema_owner[$schema] = $name;
			}

			$out[] = array('name' => $name, 'schema' => $schema);
		}

		return $out;
	}

	private static function normalizeSchema($schema, int $i): ?string
	{
		if ($schema === null || $schema === '')
		{
			return null;
		}

		$wanted = is_string($schema) ? self::lower(trim($schema)) : '';
		foreach (self::SCHEMA_PROPERTIES as $property)
		{
			if (self::lower($property) === $wanted)
			{
				return $property;
			}
		}

		throw new \InvalidArgumentException(sprintf(
			'dimensions[%d].schema must be null or one of: %s.',
			$i,
			implode(', ', self::SCHEMA_PROPERTIES)
		));
	}

	private static function normalizeOrder($order, array $dimensions): array
	{
		if (!is_array($order) || ($order && array_keys($order) === range(0, count($order) - 1)))
		{
			throw new \InvalidArgumentException('order must be an object of {dimension: [values]}.');
		}

		$names = array();
		foreach ($dimensions as $dimension)
		{
			$names[self::lower($dimension['name'])] = $dimension['name'];
		}

		$by_name = array();
		// family dimension name => the order key that named it.
		$keys = array();
		foreach ($order as $key => $values)
		{
			$lower = self::lower(trim((string) $key));
			if (!isset($names[$lower]))
			{
				throw new \InvalidArgumentException(sprintf('order names "%s", which is not a dimension of the family.', self::clip(trim((string) $key))));
			}
			$name = $names[$lower];
			if (isset($keys[$name]))
			{
				throw new \InvalidArgumentException(sprintf(
					'order has both "%s" and "%s": they name the same dimension (keys are compared without case).',
					self::clip(trim((string) $keys[$name])),
					self::clip(trim((string) $key))
				));
			}
			$keys[$name] = (string) $key;

			if (!is_array($values) || ($values && array_keys($values) !== range(0, count($values) - 1)))
			{
				throw new \InvalidArgumentException(sprintf('order.%s must be a list of values.', self::clip($name)));
			}
			if (count($values) > self::MAX_ORDER_VALUES)
			{
				throw new \InvalidArgumentException(sprintf('order.%s can hold at most %d values.', self::clip($name), self::MAX_ORDER_VALUES));
			}

			$list = array();
			$seen = array();
			foreach ($values as $value)
			{
				if (!is_scalar($value) || is_bool($value))
				{
					throw new \InvalidArgumentException(sprintf('order.%s values must be text.', self::clip($name)));
				}
				$value = trim((string) $value);
				if ($value === '' || self::length($value) > ProductPassport::MAX_VARIANT_VALUE)
				{
					throw new \InvalidArgumentException(sprintf('order.%s values must be 1 to %d characters.', self::clip($name), ProductPassport::MAX_VARIANT_VALUE));
				}
				if (self::lower($value) === self::ANY)
				{
					throw new \InvalidArgumentException(sprintf('order.%s cannot contain "any": it is not a value a page can be sorted by.', self::clip($name)));
				}
				if (!isset($seen[self::lower($value)]))
				{
					$seen[self::lower($value)] = true;
					$list[] = $value;
				}
			}

			if ($list)
			{
				$by_name[$name] = $list;
			}
		}

		// Family order of the keys, whatever order the input had.
		$out = array();
		foreach ($dimensions as $dimension)
		{
			if (isset($by_name[$dimension['name']]))
			{
				$out[$dimension['name']] = $by_name[$dimension['name']];
			}
		}

		return $out;
	}

	private static function normalizePrimary($primary): int
	{
		if ($primary === null || $primary === '')
		{
			return 0;
		}
		if (is_bool($primary) || (!is_int($primary) && !(is_string($primary) && ctype_digit(trim($primary)))) || (int) $primary < 0)
		{
			throw new \InvalidArgumentException('primary must be a post id, or 0 for none.');
		}

		return (int) $primary;
	}

	private static function normalizeBrand($brand): string
	{
		if (!is_scalar($brand) || is_bool($brand))
		{
			throw new \InvalidArgumentException('brand must be text.');
		}

		$brand = trim((string) $brand);
		$collapsed = preg_replace('/\s+/u', ' ', $brand);
		$brand = $collapsed !== null ? $collapsed : $brand;
		if (self::length($brand) > self::MAX_BRAND)
		{
			throw new \InvalidArgumentException(sprintf('brand can be at most %d characters.', self::MAX_BRAND));
		}

		return $brand;
	}

	private static function lower(string $text): string
	{
		return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
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
