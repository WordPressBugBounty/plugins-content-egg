<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\Plugin;
use ContentEgg\application\admin\AeIntegrationConfig;
use ContentEgg\application\modules\AE\AeModuleId;
use ContentEgg\application\modules\AE\AeSearchUrl;

/**
 * ConnectShopAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Connect (create + activate) an Affiliate Egg-backed product module from a shop
 * domain, the agent-facing equivalent of the "Connect new module" modal on the
 * Modules page. Mirrors AeConnectController::handleConnect() minus the nonce and
 * redirect. Requires the Affiliate Egg plugin.
 */
final class ConnectShopAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/connect-shop';
    }

    public function label(): string
    {
        return __('Connect Shop', 'content-egg');
    }

    public function description(): string
    {
        return 'Connects a new Affiliate Egg-backed product module from a shop domain '
            . '(e.g. "walmart.com"), so its products become searchable via '
            . 'content-egg/search-products. Shops Affiliate Egg already recognizes '
            . '("known") work immediately; a custom/unregistered domain needs Affiliate '
            . 'Egg 11.0+ and a search_url containing %KEYWORD% for keyword search to work. '
            . 'For a custom domain, determine that search_url yourself — from the shop\'s '
            . 'known search pattern (e.g. goodeggs.com -> '
            . 'https://www.goodeggs.com/search?q=%KEYWORD%) or by asking the user to paste '
            . 'a real search-results URL (a ?q=term URL is auto-converted) — and confirm it '
            . 'with the user before connecting; connecting a custom domain without one '
            . 'yields a non-searchable module. The module is activated on connect. Requires '
            . 'the Affiliate Egg plugin — the call returns a clear error when it is not '
            . 'installed. Returns the new module_id plus whether the module is searchable.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => array('object', 'null'),
            'properties' => array(
                'domain' => array(
                    'type' => 'string',
                    'description' => 'Shop domain to connect, e.g. walmart.com (scheme/www optional).',
                ),
                'search_url' => array(
                    'type' => 'string',
                    'description' => 'Custom (unregistered) domains only: the shop search URL with '
                        . '%KEYWORD% where the term goes (a real ?q=term URL is accepted and '
                        . 'converted). Ignored for known shops.',
                ),
            ),
            'required' => array('domain'),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'module_id' => array('type' => 'string'),
                'known' => array('type' => 'boolean'),
                'searchable' => array('type' => 'boolean'),
                'active' => array('type' => 'boolean'),
                'settings_url' => array('type' => 'string'),
                'notice' => array('type' => 'string'),
            ),
        );
    }

    public function annotations(): array
    {
        // Re-connecting the same domain is safe (config writes are set-keyed).
        return array('readonly' => false, 'destructive' => false, 'idempotent' => true);
    }

    public function checkPermission($input = null): bool
    {
        return \current_user_can('manage_options');
    }

    public function execute(array $input): array
    {
        if (!AeIntegrationConfig::isAEIntegrationPosible())
        {
            throw new AbilityInputException(
                'Affiliate Egg is not available. Install and license the Affiliate Egg plugin '
                    . '(version ' . AeIntegrationConfig::MIN_AE_VERSION . ' or newer) to connect '
                    . 'shop modules.'
            );
        }

        $domain = trim((string) ($input['domain'] ?? ''));
        if ($domain === '')
        {
            throw new AbilityInputException("'domain' is required, e.g. walmart.com.");
        }

        $search_url = trim((string) ($input['search_url'] ?? ''));
        if ($search_url !== '')
        {
            // Users often paste a real search URL with an actual term; insert %KEYWORD%.
            $search_url = AeSearchUrl::normalize($search_url);
        }

        $res = AeModuleId::resolve($domain);
        if (!$res['ok'])
        {
            throw new AbilityInputException($res['error']);
        }

        if (!$res['known'] && !AeIntegrationConfig::isCustomDomainSupported())
        {
            throw new AbilityInputException(sprintf(
                "'%s' is not a shop Affiliate Egg recognizes. Connecting a custom domain "
                    . 'requires Affiliate Egg %s or newer.',
                $domain,
                AeIntegrationConfig::MIN_AE_VERSION_CUSTOM_DOMAIN
            ));
        }

        if ($search_url !== '' && !AeSearchUrl::isValidSearchUri($search_url))
        {
            throw new AbilityInputException(
                "'search_url' must include %KEYWORD% where the search term goes (or a "
                    . 'recognizable parameter like ?q=). Keyword search will not work without it.'
            );
        }

        $config = AeIntegrationConfig::getInstance();
        if ($res['known'])
        {
            $config->addKnownShop($res['key']);
        }
        else
        {
            $config->addCustomDomain($res['key']);
        }

        $module_id = (string) $res['module_id'];

        // Activate the module and seed the optional Search URL (custom domains
        // only), in a single write — mirroring AeConnectController::handleConnect().
        $opt = Plugin::slug . '_' . $module_id;
        $cfg = \get_option($opt, array());
        if (!is_array($cfg))
        {
            $cfg = array();
        }
        $cfg['is_active'] = 1;
        if (!$res['known'] && $search_url !== '')
        {
            $cfg['search_uri'] = $search_url;
        }
        \update_option($opt, $cfg);

        // Known shops carry a built-in search catalog; a custom domain can only
        // keyword-search once it has a valid search URL.
        $searchable = (bool) $res['known'] || $search_url !== '';

        return array(
            'module_id' => $module_id,
            'known' => (bool) $res['known'],
            'searchable' => $searchable,
            'active' => true,
            'settings_url' => \admin_url('admin.php?page=content-egg-modules--' . \urlencode($module_id)),
            'notice' => $searchable
                ? 'Module connected and active. Search it with content-egg/search-products using '
                    . 'this module_id.'
                : 'Module connected and active, but keyword search is not set up: provide a '
                    . 'search_url containing %KEYWORD% to enable content-egg/search-products.',
        );
    }
}
