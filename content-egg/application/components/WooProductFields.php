<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\matching\ProductPassport;
use ContentEgg\application\helpers\Gtin;
use ContentEgg\application\helpers\WooHelper;

/**
 * WooProductFields class file
 *
 * The WooCommerce product fields an AI agent may set on a post that already is
 * a product: the type (external or simple), the product categories, the GTIN and
 * the catalog visibility. Price, stock and everything else stay with the offer
 * sync.
 *
 * validate() and resolveCategoryIds() are pure; apply(), setGlobalUniqueId()
 * and snapshot() touch WooCommerce. Messages are plain English: they go to an
 * AI agent, not into the admin UI.
 *
 * The GTIN is written through WC_Product::set_global_unique_id(), which checks
 * uniqueness. On a product loaded from the database (wc_get_product()) it
 * throws WC_Data_Exception when another product already holds the code
 * (verified against WooCommerce 11.2, abstract-wc-product.php: it asks
 * wc_product_has_global_unique_id() and calls $this->error(), which throws).
 * The setter only exists from WooCommerce 9.2.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 *
 */
class WooProductFields
{

	// A product type an agent may set. Variable and grouped products carry data an agent cannot see.
	const TYPES = array('external', 'simple');

	const VISIBILITY = array('visible', 'catalog', 'search', 'hidden');

	const MAX_CATEGORIES = 10;

	// How many existing category names an "unknown category" message lists.
	const LISTED_CATEGORIES = 20;

	const GTIN_SET = 'set';
	const GTIN_UNCHANGED = 'unchanged';
	const GTIN_DUPLICATE = 'duplicate';
	const GTIN_UNSUPPORTED = 'unsupported';

	/**
	 * Check the `woo` input of an agent. Only the fields that were given
	 * (not null) come back. categories stay references (ids or names): they
	 * are resolved against the site's terms by resolveCategoryIds().
	 *
	 * @throws \InvalidArgumentException naming the field that is wrong.
	 */
	public static function validate(array $woo): array
	{
		$out = array();

		if (isset($woo['type']))
		{
			if (!is_string($woo['type']) || !in_array($woo['type'], self::TYPES, true))
			{
				throw new \InvalidArgumentException('type must be one of: ' . implode(', ', self::TYPES) . '.');
			}
			$out['type'] = $woo['type'];
		}

		if (isset($woo['categories']))
		{
			$out['categories'] = self::validateCategories($woo['categories']);
		}

		if (isset($woo['gtin']))
		{
			$raw = is_scalar($woo['gtin']) ? trim((string) $woo['gtin']) : '';
			$gtin14 = Gtin::normalize($raw);
			if ($gtin14 === '')
			{
				throw new \InvalidArgumentException('gtin is not a valid GTIN (wrong length or check digit).');
			}
			// The same refusals as a passport: a code that is not one product's GTIN has no place in the product's GTIN field either.
			$issue = Gtin::issue($gtin14);
			if ($issue !== '')
			{
				throw new \InvalidArgumentException(sprintf(
					'gtin %s is %s, not a product GTIN.',
					$raw,
					isset(ProductPassport::GTIN_ISSUES[$issue]) ? ProductPassport::GTIN_ISSUES[$issue] : 'an unusable code (' . $issue . ')'
				));
			}
			$out['gtin'] = Gtin::display($gtin14);
		}

		if (isset($woo['catalog_visibility']))
		{
			if (!is_string($woo['catalog_visibility']) || !in_array($woo['catalog_visibility'], self::VISIBILITY, true))
			{
				throw new \InvalidArgumentException('catalog_visibility must be one of: ' . implode(', ', self::VISIBILITY) . '.');
			}
			$out['catalog_visibility'] = $woo['catalog_visibility'];
		}

		if (!$out)
		{
			throw new \InvalidArgumentException('woo needs at least one of: type, categories, gtin, catalog_visibility.');
		}

		return $out;
	}

	/**
	 * Category references to term ids.
	 *
	 * A reference is a term id (an int, or a digit-only string that is an id on
	 * this site) or a name (case-insensitive, exact). A repeated category is
	 * listed once, in the order first given.
	 *
	 * @param array $refs
	 * @param array $terms product_cat terms as [term_id => name]
	 * @return int[]
	 * @throws \InvalidArgumentException for a reference that is unknown or ambiguous.
	 */
	public static function resolveCategoryIds(array $refs, array $terms): array
	{
		$ids = array();

		foreach ($refs as $ref)
		{
			$id = self::resolveCategoryId($ref, $terms);
			if (!in_array($id, $ids, true))
			{
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * Write the validated fields to the product and save it.
	 *
	 * Order: the type first (a product_type term decides which class the
	 * product is), then the product is loaded, then categories, visibility and
	 * GTIN go on it and one save() writes them.
	 *
	 * When a type is given the product object is built from the class of that
	 * type, as WooCommerce's own product editor does, not by wc_get_product():
	 * WooCommerce caches a product's type (per request, and across requests in
	 * a persistent object cache), so wc_get_product() could still build the
	 * old class, and save() writes the object's type to the term, undoing the
	 * change that was just reported as applied.
	 *
	 * A GTIN that cannot be written (taken by another product, or this
	 * WooCommerce has no GTIN field) is a warning, not a failure; the other
	 * fields are still saved.
	 *
	 * The caller has checked that the post is a product (post_type "product");
	 * a post that wc_get_product() cannot load throws, but only after the type
	 * term was written.
	 *
	 * @param int $post_id
	 * @param array $validated the result of validate()
	 * @param int[] $category_ids the result of resolveCategoryIds() when categories were given
	 * @return array {applied: string[], warnings: string[]}
	 * @throws \InvalidArgumentException when the post is not a WooCommerce product.
	 */
	public static function apply(int $post_id, array $validated, array $category_ids): array
	{
		$applied = array();
		$warnings = array();

		// An empty id list would strip the product of every category.
		if (isset($validated['categories']) && !$category_ids)
		{
			throw new \InvalidArgumentException('categories were given but none resolved to a category id.');
		}

		if (isset($validated['type']))
		{
			\wp_set_object_terms($post_id, $validated['type'], 'product_type');
			$applied[] = 'type';
		}

		$product = self::loadProduct($post_id, isset($validated['type']) ? $validated['type'] : null);
		if (!$product)
		{
			throw new \InvalidArgumentException(sprintf('Post %d is not a WooCommerce product.', $post_id));
		}

		if (isset($validated['categories']))
		{
			\wp_set_object_terms($post_id, array_values(array_map('intval', $category_ids)), 'product_cat');
			$applied[] = 'categories';
		}

		if (isset($validated['catalog_visibility']))
		{
			$product->set_catalog_visibility($validated['catalog_visibility']);
			$applied[] = 'catalog_visibility';
		}

		if (isset($validated['gtin']))
		{
			$status = self::setGlobalUniqueId($product, $validated['gtin']);
			if ($status === self::GTIN_SET)
			{
				$applied[] = 'gtin';
			}
			elseif ($status === self::GTIN_DUPLICATE)
			{
				$warnings[] = sprintf('GTIN %s is already used by another WooCommerce product, so the product\'s GTIN was left unchanged.', $validated['gtin']);
			}
			elseif ($status === self::GTIN_UNSUPPORTED)
			{
				$warnings[] = sprintf(
					'This WooCommerce version (%s) has no GTIN field (it needs %s or later), so the GTIN was not written.',
					WooHelper::wcVersion() !== '' ? WooHelper::wcVersion() : 'unknown',
					WooHelper::GLOBAL_UNIQUE_ID_MIN_VERSION
				);
			}
		}

		$product->save();

		return array('applied' => $applied, 'warnings' => $warnings);
	}

	/**
	 * The product to write to: false when the post is not a product.
	 *
	 * @param string|null $type the type whose term was just written, or null when the type is not changing.
	 * @return \WC_Product|false
	 */
	private static function loadProduct(int $post_id, ?string $type)
	{
		if ($type === null)
		{
			return \wc_get_product($post_id);
		}

		$classname = \WC_Product_Factory::get_product_classname($post_id, $type);
		$product = new $classname($post_id);

		// WooCommerce leaves the id at 0 for a post it cannot read as a product.
		return $product->get_id() ? $product : false;
	}

	/**
	 * Put a GTIN on a product through WooCommerce's own setter, which refuses
	 * a code another product holds. Does not save.
	 *
	 * @return string set | unchanged | duplicate | unsupported
	 */
	public static function setGlobalUniqueId(\WC_Product $product, string $gtin): string
	{
		if (!WooHelper::supportsGlobalUniqueId())
		{
			return self::GTIN_UNSUPPORTED;
		}

		if ((string) $product->get_global_unique_id() === $gtin)
		{
			return self::GTIN_UNCHANGED;
		}

		try
		{
			$product->set_global_unique_id($gtin);
		}
		catch (\WC_Data_Exception $e)
		{
			// The input was validated to digits, so the only refusal left is "taken by another product".
			return self::GTIN_DUPLICATE;
		}

		return self::GTIN_SET;
	}

	/**
	 * The fields this class manages, as they are now. An agent compares it
	 * with the state it asked for.
	 *
	 * @return array {type, categories: [{id, name}], gtin, catalog_visibility}
	 */
	public static function snapshot(\WC_Product $product): array
	{
		$categories = array();
		foreach ((array) $product->get_category_ids() as $term_id)
		{
			$term = \get_term((int) $term_id, 'product_cat');
			if (!$term || \is_wp_error($term))
			{
				continue;
			}
			// WordPress keeps the name HTML-encoded ("Tools &amp; Accessories"); an agent reads and passes back "Tools & Accessories".
			$categories[] = array('id' => (int) $term_id, 'name' => html_entity_decode((string) $term->name, ENT_QUOTES, 'UTF-8'));
		}

		return array(
			'type' => (string) $product->get_type(),
			'categories' => $categories,
			'gtin' => WooHelper::supportsGlobalUniqueId() ? (string) $product->get_global_unique_id() : '',
			'catalog_visibility' => (string) $product->get_catalog_visibility(),
		);
	}

	/**
	 * @return array a non-empty list of ids and names, at most MAX_CATEGORIES.
	 */
	private static function validateCategories($categories): array
	{
		if (!is_array($categories) || !$categories)
		{
			throw new \InvalidArgumentException('categories must be a non-empty list of category ids or names.');
		}
		if (count($categories) > self::MAX_CATEGORIES)
		{
			throw new \InvalidArgumentException(sprintf('categories can hold at most %d entries.', self::MAX_CATEGORIES));
		}

		$refs = array();
		foreach ($categories as $i => $ref)
		{
			if (is_int($ref))
			{
				if ($ref < 1)
				{
					throw new \InvalidArgumentException(sprintf('categories[%s] must be a positive category id or a name.', $i));
				}
			}
			elseif (is_string($ref) && trim($ref) !== '')
			{
				$ref = trim($ref);
				// A digit-only string is an id, as in resolveCategoryIds().
				if (ctype_digit($ref) && (int) $ref < 1)
				{
					throw new \InvalidArgumentException(sprintf('categories[%s] must be a positive category id or a name.', $i));
				}
			}
			else
			{
				throw new \InvalidArgumentException(sprintf('categories[%s] must be a category id or a name.', $i));
			}

			// 12 and "12" are the same category; the first spelling stays.
			if (!in_array((string) $ref, array_map('strval', $refs), true))
			{
				$refs[] = $ref;
			}
		}

		return $refs;
	}

	/**
	 * @param int|string $ref
	 * @param array $terms [term_id => name]
	 */
	private static function resolveCategoryId($ref, array $terms): int
	{
		if (is_int($ref) || (is_string($ref) && ctype_digit(trim($ref))))
		{
			$id = (int) $ref;
			if (isset($terms[$id]))
			{
				return $id;
			}
			// A numeric name ("2024") that is not an id may still be a name.
			if (is_int($ref))
			{
				throw new \InvalidArgumentException(self::unknownCategory((string) $ref, $terms));
			}
		}

		$wanted = self::lower(trim((string) $ref));
		$matches = array();
		foreach ($terms as $term_id => $name)
		{
			if (self::lower(trim((string) $name)) === $wanted)
			{
				$matches[] = (int) $term_id;
			}
		}

		if (count($matches) === 1)
		{
			return $matches[0];
		}
		if (count($matches) > 1)
		{
			throw new \InvalidArgumentException(sprintf(
				'The category name "%s" is shared by several categories (ids %s). Use the id.',
				$ref,
				implode(', ', $matches)
			));
		}

		throw new \InvalidArgumentException(self::unknownCategory((string) $ref, $terms));
	}

	private static function unknownCategory(string $ref, array $terms): string
	{
		if (!$terms)
		{
			return sprintf('Unknown product category "%s": this site has no product categories.', $ref);
		}

		$names = array();
		foreach ($terms as $term_id => $name)
		{
			$names[] = $name . ' (' . $term_id . ')';
		}

		$listed = array_slice($names, 0, self::LISTED_CATEGORIES);
		$rest = count($names) - count($listed);

		return sprintf(
			'Unknown product category "%s". Existing categories: %s%s.',
			$ref,
			implode(', ', $listed),
			$rest > 0 ? sprintf(', and %d more', $rest) : ''
		);
	}

	private static function lower(string $text): string
	{
		return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
	}
}
