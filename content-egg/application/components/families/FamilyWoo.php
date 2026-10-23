<?php

namespace ContentEgg\application\components\families;

use ContentEgg\application\admin\GeneralConfig;

defined('\ABSPATH') || exit;

/**
 * FamilyWoo class file
 *
 * WooCommerce side of variant families: the switcher between the price and the
 * Buy button on a product page, and the option to show one product per family
 * in the shop and category lists.
 *
 * appliesTo(), excludedIds() and includedIds() are pure and unit-tested; the rest needs
 * WordPress and is covered by the WP smoke test.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 *
 */
class FamilyWoo
{

	const RENDERER = '\ContentEgg\application\EggBlocks\blocks\variantswitcher\VariantSwitcherRenderer';

	public static function initAction(): void
	{
		if (!class_exists('\WooCommerce'))
			return;

		$config = GeneralConfig::getInstance();

		if ($config->option('woocommerce_variant_switcher') !== '')
		{
			\add_action('woocommerce_single_product_summary', array(__CLASS__, 'echoSwitcher'), 26);
			\add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueueStyles'));
		}

		if ($config->option('woocommerce_family_archive') === 'one')
			\add_action('woocommerce_product_query', array(__CLASS__, 'filterCatalog'));
	}

	/**
	 * Load the switcher stylesheet in the head of a family product page. The
	 * block loader only looks in post_content, which a product page does not use.
	 */
	public static function enqueueStyles(): void
	{
		if (!\function_exists('is_product') || !\is_product())
			return;

		$post_id = (int) \get_queried_object_id();
		if ($post_id <= 0 || FamilyStore::familyIdOf($post_id) <= 0)
			return;

		$renderer = self::RENDERER;
		if (class_exists($renderer))
			$renderer::enqueueStyles();
	}

	/**
	 * Print the switcher for the current product, in the style the setting picks.
	 * Products outside a family print nothing.
	 */
	public static function echoSwitcher(): void
	{
		$style = GeneralConfig::getInstance()->option('woocommerce_variant_switcher');
		if ($style !== 'chips' && $style !== 'list')
			return;

		$post_id = (int) \get_the_ID();
		if ($post_id <= 0 || FamilyStore::familyIdOf($post_id) <= 0)
			return;

		$renderer = self::RENDERER;
		if (!class_exists($renderer))
			return;

		// The same cegg5-container wrapper as the block and the shortcode, so the EggBlocks base rules apply.
		echo $renderer::wrap($renderer::render(array('variant' => $style), $post_id), $style); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by the renderer
	}

	/**
	 * Hide every family member that is not its family's primary from a shop or
	 * category list. Search, wp-admin and REST queries keep every variant.
	 */
	public static function filterCatalog($query): void
	{
		if (!$query instanceof \WP_Query)
			return;

		if (!self::appliesTo(\is_admin(), (bool) $query->is_search(), defined('REST_REQUEST') && REST_REQUEST))
			return;

		$hidden = FamilyStore::catalogHiddenIds();
		if (!$hidden)
			return;

		// WP_Query ignores post__not_in when post__in is set (another plugin narrowed the list): filter post__in then.
		$in = $query->get('post__in');
		if (is_array($in) && $in)
		{
			$query->set('post__in', self::includedIds($in, $hidden));
			return;
		}

		$not_in = $query->get('post__not_in');
		$query->set('post__not_in', self::excludedIds($hidden, is_array($not_in) ? $not_in : array()));
	}

	/**
	 * The catalog filter runs on the front-end shop and category lists only.
	 */
	public static function appliesTo(bool $is_admin, bool $is_search, bool $is_rest): bool
	{
		return !$is_admin && !$is_search && !$is_rest;
	}

	/**
	 * The hidden ids merged with what the query already excludes.
	 *
	 * @return int[] unique, positive, ascending
	 */
	public static function excludedIds(array $hidden, array $not_in): array
	{
		$ids = array_map('intval', array_merge($hidden, $not_in));
		$ids = array_values(array_unique(array_filter($ids, function ($id) {
			return $id > 0;
		})));
		sort($ids, SORT_NUMERIC);

		return $ids;
	}

	/**
	 * A query's non-empty post__in without the hidden ids, in its own order
	 * (orderby post__in keeps working). When nothing is left it is [0], so the
	 * list stays empty instead of WP_Query dropping the restriction.
	 *
	 * @return int[]
	 */
	public static function includedIds(array $in, array $hidden): array
	{
		// Filter plugins put whole result sets (thousands of ids) here on every
		// shop view: lookups go through maps, never in_array().
		$hidden = array_flip(array_map('intval', $hidden));
		$seen = array();
		$ids = array();
		foreach ($in as $id)
		{
			$id = (int) $id;
			if ($id > 0 && !isset($hidden[$id]) && !isset($seen[$id]))
			{
				$seen[$id] = true;
				$ids[] = $id;
			}
		}

		return $ids ? $ids : array(0);
	}
}
