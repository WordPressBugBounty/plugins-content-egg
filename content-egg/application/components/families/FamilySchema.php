<?php

namespace ContentEgg\application\components\families;

defined('\ABSPATH') || exit;

/**
 * FamilySchema class file
 *
 * schema.org ProductGroup markup for variant family pages. Each variant lives
 * on its own page, so (per Google's multi-page variants rules) the group is
 * repeated on every page: the page's Product points at it with `isVariantOf`,
 * and `hasVariant` lists that Product by `@id` (when it has one), then the
 * other published pages as `{url}` stubs. A
 * multi-page ProductGroup has no `url`. The group is nested in the Product,
 * so the existing Product filters keep receiving one Product object.
 *
 * Only dimensions with a schema.org property are written (`variesBy` and the
 * product's own property); a dimension without one, such as Memory, is not
 * invented. productGroup(), decorate() and forMembers() are pure and
 * unit-tested; the rest needs WordPress.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 *
 */
class FamilySchema
{

	const GROUP_ID_PREFIX = 'cegg-family-';

	public static function initAction(): void
	{
		\add_filter('cegg_structured_data_single', array(__CLASS__, 'filterPostMarkup'), 20, 2);
		\add_filter('woocommerce_structured_data_product', array(__CLASS__, 'filterWooMarkup'), 20, 2);
	}

	/**
	 * The ProductGroup of a family, as seen from one of its pages.
	 *
	 * @param array  $family       the family (`dimensions`, `brand`).
	 * @param array  $sibling_urls urls of the other published pages.
	 * @param string $product_id   the `@id` of this page's Product, '' when it has none.
	 */
	public static function productGroup(array $family, string $name, int $term_id, string $page_url, array $sibling_urls, string $product_id = ''): array
	{
		$group = array(
			'@type' => 'ProductGroup',
			'@id' => $page_url . '#product-group',
			'name' => $name,
			'productGroupID' => self::GROUP_ID_PREFIX . $term_id,
		);

		$varies_by = array();
		foreach (self::dimensions($family) as $dimension)
		{
			if (!empty($dimension['schema']))
			{
				$varies_by[] = 'https://schema.org/' . $dimension['schema'];
			}
		}
		if ($varies_by)
		{
			$group['variesBy'] = $varies_by;
		}

		$has_variant = array();
		if ($product_id !== '')
		{
			$has_variant[] = array('@id' => $product_id);
		}
		foreach ($sibling_urls as $url)
		{
			$has_variant[] = array('url' => (string) $url);
		}
		$group['hasVariant'] = $has_variant;

		if (!empty($family['brand']))
		{
			$group['brand'] = array('@type' => 'Brand', 'name' => (string) $family['brand']);
		}

		return $group;
	}

	/**
	 * Add the family to a page's Product: the group id, the value of every
	 * mapped dimension (unless "any") and the nested group.
	 *
	 * @param array $values [dimension name => value] from VariantFamily::memberValues().
	 */
	public static function decorate(array $product, array $family, string $name, int $term_id, array $values, string $page_url, array $sibling_urls): array
	{
		$product['inProductGroupWithID'] = self::GROUP_ID_PREFIX . $term_id;

		foreach (self::dimensions($family) as $dimension)
		{
			if (empty($dimension['schema']))
			{
				continue;
			}
			$dimension_name = (string) $dimension['name'];
			if (!isset($values[$dimension_name]) || $values[$dimension_name] === VariantFamily::ANY)
			{
				continue;
			}
			$product[$dimension['schema']] = (string) $values[$dimension_name];
		}

		$product_id = (isset($product['@id']) && is_string($product['@id'])) ? trim($product['@id']) : '';
		$product['isVariantOf'] = self::productGroup($family, $name, $term_id, $page_url, $sibling_urls, $product_id);

		return $product;
	}

	/**
	 * Decorate a Product for one family member. The markup comes back
	 * unchanged when the page is not a published member, has no published
	 * sibling, or its passport no longer fits the family.
	 *
	 * @param array $family  ['name'] + the definition.
	 * @param array $members published members [{post_id, url, variant, ...}].
	 */
	public static function forMembers(array $markup, array $family, int $term_id, int $post_id, array $members): array
	{
		$page_url = '';
		$variant = null;
		$sibling_urls = array();
		foreach ($members as $member)
		{
			if ((int) $member['post_id'] === $post_id)
			{
				$page_url = (string) $member['url'];
				$variant = isset($member['variant']) && is_array($member['variant']) ? $member['variant'] : array();
			}
			elseif ((string) $member['url'] !== '')
			{
				$sibling_urls[] = (string) $member['url'];
			}
		}

		if ($variant === null || $page_url === '' || !$sibling_urls)
		{
			return $markup;
		}

		try
		{
			$values = VariantFamily::memberValues($family, $variant);
		}
		catch (\InvalidArgumentException $e)
		{
			return $markup;
		}

		return self::decorate($markup, $family, isset($family['name']) ? (string) $family['name'] : '', $term_id, $values, $page_url, $sibling_urls);
	}

	public static function filterPostMarkup($markup, $post_id = 0)
	{
		if (!is_array($markup))
		{
			return $markup;
		}

		return self::decoratePost($markup, (int) $post_id);
	}

	public static function filterWooMarkup($markup, $product = null)
	{
		if (!is_array($markup) || !is_object($product) || !method_exists($product, 'get_id'))
		{
			return $markup;
		}

		return self::decoratePost($markup, (int) $product->get_id());
	}

	private static function decoratePost(array $markup, int $post_id): array
	{
		if ($post_id <= 0)
		{
			return $markup;
		}

		$term_id = FamilyStore::familyIdOf($post_id);
		if ($term_id <= 0)
		{
			return $markup;
		}

		$family = FamilyStore::get($term_id);
		if (!$family)
		{
			return $markup;
		}

		return self::forMembers($markup, $family, $term_id, $post_id, FamilyStore::members($term_id));
	}

	private static function dimensions(array $family): array
	{
		return (isset($family['dimensions']) && is_array($family['dimensions'])) ? $family['dimensions'] : array();
	}
}
