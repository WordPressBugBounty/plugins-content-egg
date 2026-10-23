<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\helpers\WooHelper;

/**
 * WooPreflight class file
 *
 * What an AI agent needs to know about the site's WooCommerce synchronization
 * before it touches a product: whether prices are synced by themselves and
 * from which modules, whether used offers are kept out, whether the sync
 * writes the GTIN, what happens to an out-of-stock product, and whether this
 * WooCommerce has a GTIN field at all. Reported by `content-egg/get-status`.
 *
 * summary() is pure so it can be tested without WordPress; current() reads the
 * settings and the module registry.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 *
 */
class WooPreflight
{

	// Why the automatic sync does nothing (auto_sync.off_reason).
	const OFF_MANUAL_ONLY = 'manual_only';
	const OFF_NO_MODULES = 'no_modules';

	// The setting defaults, for options that were never saved.
	const DEFAULT_CRITERIA = 'min_price';
	const DEFAULT_SCOPE = 'selected';

	// The values get-status declares (its output schema enums). A stored value outside them is
	// reported as the sync reads it: an unknown criteria sorts by the lowest price
	// (ContentManager::getMainProduct()), an unknown scope is the selected modules
	// (WooSyncScope::resolve()).
	const CRITERIA = array('min_price', 'max_price', 'random', 'manually');
	const SCOPES = array('selected', 'all');

	/**
	 * The sync setup as the agent reads it.
	 *
	 * @param array $options GeneralConfig values: woocommerce_product_sync,
	 *   woocommerce_sync_scope, exclude_non_new_offers, sync_gtin, outofstock_woo
	 * @param string[] $module_ids the modules the automatic sync reads (WooSyncScope::moduleIds())
	 * @param string $wc_version
	 * @param bool $gtin_field whether this WooCommerce has the native GTIN field
	 * @param bool $products_enabled whether "product" is a Content Egg post type (post_types)
	 * @return array {version, products_enabled, gtin_field, auto_sync: {criteria, scope, module_ids, off_reason},
	 *   exclude_non_new, sync_gtin, out_of_stock}
	 */
	public static function summary(array $options, array $module_ids, string $wc_version, bool $gtin_field, bool $products_enabled = true): array
	{
		$criteria = self::oneOf(self::text($options, 'woocommerce_product_sync', self::DEFAULT_CRITERIA), self::CRITERIA, self::DEFAULT_CRITERIA);
		$scope = self::oneOf(self::text($options, 'woocommerce_sync_scope', self::DEFAULT_SCOPE), self::SCOPES, self::DEFAULT_SCOPE);
		$module_ids = array_values(array_map('strval', $module_ids));

		// "Manually only" switches the whole automatic sync off, whatever modules are in scope.
		if ($criteria === 'manually')
		{
			$off_reason = self::OFF_MANUAL_ONLY;
		}
		elseif (!$module_ids)
		{
			$off_reason = self::OFF_NO_MODULES;
		}
		else
		{
			$off_reason = null;
		}

		return array(
			'version' => $wc_version,
			// False blocks create-post for products: check it before spending any searches.
			'products_enabled' => $products_enabled,
			'gtin_field' => $gtin_field,
			'auto_sync' => array(
				'criteria' => $criteria,
				'scope' => $scope,
				'module_ids' => $module_ids,
				'off_reason' => $off_reason,
			),
			'exclude_non_new' => self::text($options, 'exclude_non_new_offers', 'disabled') === 'enabled',
			'sync_gtin' => self::text($options, 'sync_gtin', 'disabled') === 'enabled',
			// '' = do nothing; hide_price | hide_product | move_to_trash.
			'out_of_stock' => self::text($options, 'outofstock_woo', ''),
		);
	}

	/**
	 * The summary of this site, or null when WooCommerce is not active.
	 */
	public static function current(): ?array
	{
		if (!WooHelper::isWooActive())
		{
			return null;
		}

		$config = GeneralConfig::getInstance();
		$options = array();
		foreach (array('woocommerce_product_sync', 'woocommerce_sync_scope', 'exclude_non_new_offers', 'sync_gtin', 'outofstock_woo') as $key)
		{
			$options[$key] = $config->option($key);
		}

		return self::summary(
			$options,
			WooSyncScope::moduleIds(),
			WooHelper::wcVersion(),
			WooHelper::supportsGlobalUniqueId(),
			in_array('product', (array) $config->option('post_types'), true)
		);
	}

	/**
	 * $value when it is one of $allowed (exactly), else $fallback.
	 */
	private static function oneOf(string $value, array $allowed, string $fallback): string
	{
		return in_array($value, $allowed, true) ? $value : $fallback;
	}

	/**
	 * A scalar option as a string; $default when it is missing or empty and a default is wanted.
	 */
	private static function text(array $options, string $key, string $default): string
	{
		if (!isset($options[$key]) || !is_scalar($options[$key]))
		{
			return $default;
		}

		$value = (string) $options[$key];

		return ($value === '' && $default !== '') ? $default : $value;
	}
}
