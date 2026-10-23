<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;

/**
 * WooSyncScope class file
 *
 * Which modules the WooCommerce automatic synchronization reads.
 *
 * Two modes: 'selected' syncs only the modules ticked in
 * woocommerce_modules (how every site worked before the scope existed, and
 * the default); 'all' syncs every product module except the ones ticked in
 * woocommerce_modules_exclude (what a fresh install is seeded with).
 *
 * resolve() and normalizeList() are pure so the rules can be tested without
 * WordPress; moduleIds() is the one method that reads settings and the module
 * registry.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class WooSyncScope
{
    const SCOPE_ALL = 'all';
    const SCOPE_SELECTED = 'selected';

    /**
     * A stored checkbox list as a plain list of module ids.
     *
     * The settings form stores [id => id]; an agent writing JSON stores
     * [0 => id]. Both mean the same thing, so every reader goes through here.
     * Anything that is not an array (an unset option, '') is an empty list.
     *
     * @param mixed $value
     * @return string[]
     */
    public static function normalizeList($value): array
    {
        if (!is_array($value))
            return array();

        $ids = array();
        foreach ($value as $id)
        {
            if (!is_scalar($id))
                continue;

            $id = trim((string) $id);
            if ($id === '' || in_array($id, $ids, true))
                continue;

            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * The module ids to sync, in the order of $available_ids.
     *
     * 'all' = available minus excluded. Any other scope, including a missing
     * or unknown one, = available intersected with selected: an existing site
     * that has not been touched must keep syncing exactly what it had ticked.
     *
     * @param string $scope
     * @param mixed $selected woocommerce_modules as stored
     * @param mixed $excluded woocommerce_modules_exclude as stored
     * @param array $available_ids
     * @return array
     */
    public static function resolve(string $scope, $selected, $excluded, array $available_ids): array
    {
        $is_all = $scope === self::SCOPE_ALL;
        $listed_ids = self::normalizeList($is_all ? $excluded : $selected);

        $result = array();
        foreach ($available_ids as $id)
        {
            $is_listed = in_array((string) $id, $listed_ids, true);

            // 'all' keeps what is not listed as excluded; 'selected' keeps what is listed.
            if ($is_all ? !$is_listed : $is_listed)
                $result[] = $id;
        }

        return $result;
    }

    /**
     * The module ids the scope may pick from.
     *
     * 'all' picks from the product modules only: a coupon has no price to
     * put in a product. Any other scope picks from every active affiliate
     * module, which is what the ticked list was always matched against -
     * a site that ticked a coupon module keeps getting exactly that.
     *
     * @param string $scope
     * @param array $all_active_ids every active affiliate module
     * @param array $product_ids the active product modules
     * @return array
     */
    public static function availableFor(string $scope, array $all_active_ids, array $product_ids): array
    {
        return $scope === self::SCOPE_ALL ? $product_ids : $all_active_ids;
    }

    /**
     * The ids of the modules the automatic sync reads right now.
     *
     * @return string[]
     */
    public static function moduleIds(): array
    {
        $config = GeneralConfig::getInstance();
        $scope = (string) $config->option('woocommerce_sync_scope');

        // The same set WooIntegrator::wooHandler() builds its module data from.
        $all_active_ids = array_map('strval', array_keys(ModuleManager::getInstance()->getAffiliteModulesList(true)));
        $product_ids = array_map('strval', array_keys(self::productModules()));

        return self::resolve(
            $scope,
            $config->option('woocommerce_modules'),
            $config->option('woocommerce_modules_exclude'),
            self::availableFor($scope, $all_active_ids, $product_ids)
        );
    }

    /**
     * Active product modules, as [id => name]. Coupon modules have nothing to
     * put in a price field, and CjLinks is a link generator, not a product
     * source.
     *
     * @return array
     */
    public static function productModules(): array
    {
        $modules = array();
        foreach (ModuleManager::getInstance()->getAffiliateParsers(true, true) as $module_id => $module)
        {
            if (stripos($module_id, 'Coupons') !== false || stripos($module_id, 'CjLinks') !== false)
                continue;

            // The manual Coupon module has no 's' in its id but is a coupon module all the same.
            if ($module->getParserType() === ParserModule::PARSER_TYPE_COUPON)
                continue;

            $modules[$module_id] = $module->getName();
        }

        return $modules;
    }
}
