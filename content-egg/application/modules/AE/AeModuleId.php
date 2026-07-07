<?php

namespace ContentEgg\application\modules\AE;

defined('\ABSPATH') || exit;

use Keywordrush\AffiliateEgg\ParserManager;

/**
 * Resolve a user-typed domain/URL into a Content Egg module id, delegating to
 * Affiliate Egg so a KNOWN shop reuses its exact registry shop_id (backward
 * compatible with existing AE__<shop_id> modules) and an UNKNOWN domain uses its
 * normalized domain. Both become 'AE__' . key.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class AeModuleId
{
    const PREFIX = 'AE';

    /**
     * Encode an unregistered domain into a dot-free, slug-safe module key
     * ("bikesonline.com" => "bikesonline_com"). Content Egg treats module ids as
     * sanitize_key-safe slugs (used in admin page slugs, JS/Angular model keys,
     * etc.), and a dot breaks those paths. Domains never contain '_' and no
     * registered shop_id contains '_', so '.'<->'_' is bijective and collision-free.
     */
    public static function encodeDomain($domain)
    {
        return str_replace('.', '_', (string) $domain);
    }

    /** Reverse encodeDomain(): "bikesonline_com" => "bikesonline.com". */
    public static function decodeKey($key)
    {
        return str_replace('_', '.', (string) $key);
    }

    public static function resolve($input)
    {
        $fail = array('ok' => false, 'error' => '', 'key' => '', 'known' => false, 'module_id' => '');

        $pm = ParserManager::getInstance();
        $domain = $pm->domainKey($input); // lowercases, strips scheme/www; false if invalid
        if (!$domain)
        {
            $fail['error'] = __('Please enter a valid domain, e.g. example.com', 'content-egg');
            return $fail;
        }
        if (strpos($domain, '__') !== false)
        {
            $fail['error'] = __('This domain is not supported.', 'content-egg');
            return $fail;
        }

        $shop_id = $pm->getShopIdByUrl('http://' . $domain);
        if ($shop_id)
        {
            $key = $shop_id;
            $known = true;
        }
        else
        {
            // Unregistered domain: use a dot-free key so the module id stays a
            // valid Content Egg slug (see encodeDomain()).
            $key = self::encodeDomain($domain);
            $known = false;
        }

        return array(
            'ok'        => true,
            'error'     => '',
            'key'       => $key,
            'known'     => $known,
            'module_id' => self::PREFIX . '__' . $key,
        );
    }
}
