<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;

/**
 * ShopMigration class file
 *
 * Folds the two legacy domain-keyed options - merchant_names and merchants -
 * into ShopStore.
 *
 * It COPIES rather than reading through, and the reason is not tidiness.
 * Config::validate() rebuilds the whole option from submitted keys that still
 * exist in options(); the moment those two field definitions are removed, the
 * next save of ANY settings tab wipes both from the database. A fallback that
 * read them live would be reading a value that may already be gone, so a
 * verbatim archive is written here and that is what the fallback reads.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ShopMigration
{
    /**
     * merchant_names[].name is a DISPLAY NAME.
     * merchants[].name is a DOMAIN - formatMerchantFields() ran getHostName()
     * on it.
     *
     * Same key, opposite meanings. Do not "simplify" this. Pure.
     */
    public static function merge(array $names, array $merchants, array $existing)
    {
        $out = $existing;

        foreach ($names as $row)
        {
            if (!is_array($row))
                continue;

            $domain = ShopStore::normalizeDomain(isset($row['domain']) ? $row['domain'] : '');
            if ($domain === '')
                continue;

            $out = self::fill($out, $domain, array(
                'name' => isset($row['name']) ? trim((string) $row['name']) : '',
            ));
        }

        foreach ($merchants as $row)
        {
            if (!is_array($row))
                continue;

            // NOT a display name. See the method comment.
            $domain = ShopStore::normalizeDomain(isset($row['name']) ? $row['name'] : '');
            if ($domain === '')
                continue;

            $out = self::fill($out, $domain, array(
                'info'         => isset($row['shop_info']) ? (string) $row['shop_info'] : '',
                'coupons_html' => isset($row['shop_coupons']) ? (string) $row['shop_coupons'] : '',
            ));
        }

        ksort($out);

        return $out;
    }

    /**
     * Writes only into empty fields, which is what makes re-running safe: a
     * settings import months later must not undo an edit made yesterday.
     *
     * A shop is created even when every field is empty - a merchant row that
     * exists only to be named is still a shop the operator configured. Pure.
     */
    private static function fill(array $all, $domain, array $fields)
    {
        if (!isset($all[$domain]))
        {
            $created = ShopStore::sanitizeShop(array('domain' => $domain));

            if (!$created)
                return $all;

            $all[$domain] = $created;
        }

        foreach ($fields as $key => $value)
        {
            if ($value === '')
                continue;

            if (isset($all[$domain][$key]) && $all[$domain][$key] !== '')
                continue;

            $all[$domain][$key] = $value;
        }

        return $all;
    }

    /**
     * The legacy values as they were at migration time. Read this, never the
     * live option - see the class comment.
     */
    public static function legacy()
    {
        $archive = \get_option(ShopStore::OPTION_LEGACY, array());

        return is_array($archive) ? $archive : array();
    }

    /**
     * Idempotent. Returns how many shops the store holds afterwards.
     */
    public static function run()
    {
        $config = GeneralConfig::getInstance();

        $names = $config->option('merchant_names');
        $merchants = $config->option('merchants');

        $names = is_array($names) ? $names : array();
        $merchants = is_array($merchants) ? $merchants : array();

        if ($names || $merchants)
        {
            $archive = self::legacy();

            // Never overwrite an existing archive with a thinner one: the first
            // run captured the pre-removal state, and a later settings import
            // must not replace that with whatever the import happened to carry.
            if (empty($archive['merchant_names']) && empty($archive['merchants']))
            {
                \update_option(ShopStore::OPTION_LEGACY, array(
                    'merchant_names' => $names,
                    'merchants' => $merchants,
                ), 'no');
            }
        }

        $merged = self::merge($names, $merchants, ShopStore::all());

        ShopStore::replaceAll($merged);

        // Autoloaded, unlike the store itself: maybeRun() is consulted on
        // every path that rewrites the settings option, and that check must not
        // cost a query.
        \update_option(ShopStore::OPTION_MIGRATED, 1, 'yes');

        return count($merged);
    }

    /**
     * Covers installs that never hit the upgrade ladder - a copied wp-content
     * tree, a restored backup - and settings imports, which re-introduce the
     * legacy keys long after the upgrade.
     */
    public static function maybeRun()
    {
        if (\get_option(ShopStore::OPTION_MIGRATED))
            return;

        self::run();
    }
}
