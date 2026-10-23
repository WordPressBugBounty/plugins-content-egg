<?php

namespace ContentEgg\application\components\families;

use ContentEgg\application\components\ContentManager;
use ContentEgg\application\components\ContentProduct;
use ContentEgg\application\components\ModuleManager;

defined('\ABSPATH') || exit;

/**
 * PriceSummary class file
 *
 * The lowest price of a page, stored in the post meta `_cegg_price_summary`
 * when the page's offers are saved. The variant switcher reads this meta for
 * every sibling instead of computing a price per view.
 *
 * Shape: {price: float|null, currency: string, in_stock: bool, offers: int, updated_at: int};
 * `offers` is the number of distinct shops whose offer is not out of stock (countOffers()).
 *
 * fromMain() is pure and unit-tested; the rest needs WordPress.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 *
 */
class PriceSummary
{

	const META = '_cegg_price_summary';

	public static function initAction(): void
	{
		// After OfferCountService (20), which it reuses the count logic of.
		\add_action('content_egg_save_data', array(__CLASS__, 'onSaveData'), 21, 4);
	}

	/**
	 * Called per module on save; the summary is refreshed once, on the last
	 * iteration, and only for pages that belong to a family.
	 */
	public static function onSaveData($data, $module_id, $post_id, $is_last_iteration)
	{
		if (!$is_last_iteration)
		{
			return;
		}

		$post_id = (int) $post_id;
		if ($post_id <= 0 || FamilyStore::familyIdOf($post_id) <= 0)
		{
			return;
		}

		self::refresh($post_id);
	}

	/**
	 * Pure: the summary of a page from its main (lowest price) offer.
	 */
	public static function fromMain(?array $main, int $offers, int $now): array
	{
		$price = null;
		if ($main !== null && isset($main['price']) && is_numeric($main['price']) && (float) $main['price'] > 0)
		{
			$price = (float) $main['price'];
		}

		$currency = '';
		if ($main !== null)
		{
			// `currencyCode` is the ISO code; `currency` is a display symbol or label,
			// used only when it happens to be a 3-letter code.
			foreach (array('currencyCode', 'currency') as $key)
			{
				if (isset($main[$key]) && is_string($main[$key]) && preg_match('/^[A-Za-z]{3}$/', trim($main[$key])))
				{
					$currency = strtoupper(trim($main[$key]));
					break;
				}
			}
		}

		$in_stock = $main !== null
			&& (int) ($main['stock_status'] ?? 0) !== ContentProduct::STOCK_STATUS_OUT_OF_STOCK;

		return array(
			'price' => $price,
			'currency' => $currency,
			'in_stock' => $in_stock,
			'offers' => $offers,
			'updated_at' => $now,
		);
	}

	/**
	 * Pure: keep the product-module entries of a `module_id => items` map.
	 * Items may be arrays or objects; they come back as arrays.
	 */
	public static function productData(array $by_module, callable $is_product): array
	{
		$out = array();
		foreach ($by_module as $module_id => $items)
		{
			if (!is_array($items) || !$items || !$is_product($module_id))
			{
				continue;
			}

			$rows = array();
			foreach ($items as $item)
			{
				if (is_object($item))
				{
					$item = json_decode(json_encode($item), true);
				}
				if (is_array($item))
				{
					$rows[] = $item;
				}
			}
			if ($rows)
			{
				$out[$module_id] = $rows;
			}
		}

		return $out;
	}

	/**
	 * Pure: the number of shops a shopper can still buy from (the switcher
	 * prints "%d shops"). Offers that are out of stock do not count; an
	 * unknown stock status does, as in OfferCountService::calculateCount().
	 * A shop is its domain (lowercase), else its merchant name (lowercase),
	 * else the offer itself (module_id:unique_id), so two offers of one shop
	 * count once.
	 */
	public static function countOffers(array $product_data): int
	{
		$shops = array();
		foreach ($product_data as $module_id => $items)
		{
			if (!is_array($items))
			{
				continue;
			}

			$n = -1;
			foreach ($items as $item)
			{
				$n++;
				if (!is_array($item) || (isset($item['stock_status']) && (int) $item['stock_status'] === ContentProduct::STOCK_STATUS_OUT_OF_STOCK))
				{
					continue;
				}

				$shops[self::shopKey($item, (string) $module_id, $n)] = true;
			}
		}

		return count($shops);
	}

	private static function shopKey(array $item, string $module_id, int $n): string
	{
		foreach (array('domain' => 'd:', 'merchant' => 'm:') as $field => $prefix)
		{
			$value = (isset($item[$field]) && is_scalar($item[$field])) ? trim((string) $item[$field]) : '';
			if ($value !== '')
			{
				return $prefix . (function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value));
			}
		}

		$module = (isset($item['module_id']) && is_scalar($item['module_id']) && (string) $item['module_id'] !== '') ? (string) $item['module_id'] : $module_id;
		$unique = (isset($item['unique_id']) && is_scalar($item['unique_id'])) ? (string) $item['unique_id'] : '';

		// An offer without an id is a shop of its own.
		return 'o:' . $module . ':' . ($unique !== '' ? $unique : '#' . $n);
	}

	/**
	 * Pure: the summary of a `module_id => items` map. $pick_main receives the
	 * product-only map and returns the main offer or null (ContentManager::getMainProduct()).
	 */
	public static function summarize(array $by_module, callable $is_product, callable $pick_main, int $now): array
	{
		$data = self::productData($by_module, $is_product);
		$main = $data ? $pick_main($data) : null;

		return self::fromMain(is_array($main) ? $main : null, self::countOffers($data), $now);
	}

	/**
	 * Compute the summary from the page's current offers (product modules only).
	 */
	public static function compute(int $post_id): array
	{
		$modules = ModuleManager::getInstance();
		// Unmerged: data stays keyed by module id, which getMainProduct() expects.
		$all = ContentManager::getViewProductData($post_id, false);

		return self::summarize(
			is_array($all) ? $all : array(),
			function ($module_id) use ($modules)
			{
				return $modules->isProductParserModule($module_id);
			},
			function (array $data)
			{
				return ContentManager::getMainProduct($data, 'min_price');
			},
			time()
		);
	}

	/**
	 * Compute and store the summary.
	 */
	public static function refresh(int $post_id): array
	{
		$summary = self::compute($post_id);
		\update_post_meta($post_id, self::META, $summary);

		return $summary;
	}

	/**
	 * The stored summary, null when the page has none.
	 */
	public static function get(int $post_id): ?array
	{
		$summary = \get_post_meta($post_id, self::META, true);

		return is_array($summary) && array_key_exists('price', $summary) ? $summary : null;
	}
}
