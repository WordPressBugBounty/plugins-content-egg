<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\Plugin;
use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\components\WooPreflight;
use ContentEgg\application\licensing\LicenseGate;

/**
 * GetStatusAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class GetStatusAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/get-status';
    }

    public function label(): string
    {
        return __('Get Plugin Status', 'content-egg');
    }

    public function description(): string
    {
        $description = 'Returns Content Egg plugin status: plugin version, build (free/pro/envato), '
            . 'WordPress version, agent abilities layer version, number of active modules, '
            . 'whether the WordPress MCP Adapter is installed, and a license summary '
            . '(administrators only; never contains license keys). '
            . 'On a WooCommerce site it also reports the product sync setup (woocommerce), '
            . 'which is null when WooCommerce is not active. '
            . 'Call this first to learn what this site supports.';

        if (!Plugin::isPaidBuild())
        {
            $description .= ' On this build it also reports which abilities are unavailable '
                . 'and what they require, so plan within capabilities.can_write.';
        }

        return $description;
    }

    public function inputSchema(): array
    {
        return array(
            'type' => 'object',
            'default' => array(),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        $properties = array(
            'plugin_version' => array('type' => 'string'),
            'build' => array('type' => 'string', 'enum' => array('free', 'pro', 'envato')),
            'abilities_layer_version' => array('type' => 'string'),
            'wp_version' => array('type' => 'string'),
            'active_modules' => array('type' => 'integer'),
            'mcp_adapter_present' => array(
                'type' => 'boolean',
                'description' => 'True only when the MCP route is actually mounted on this site. '
                    . 'When false, do not use the MCP URL — use the REST routes.',
            ),
            'guide_url' => array('type' => 'string'),
            'guide_version' => array(
                'type' => 'string',
                'description' => 'Changes whenever the agent guide changes; a guide topic loaded earlier is current while this is unchanged.',
            ),
            'guide_topics' => array(
                'type' => 'array',
                'items' => array('type' => 'string'),
                'description' => 'Topics of the agent guide, for content-egg/get-guide {topic}.',
            ),
            'openapi_url' => array('type' => 'string'),
            'license' => array(
                'type' => array('object', 'null'),
                'description' => 'License summary. Null on free builds or when the user lacks manage_options.',
            ),
            'woocommerce' => array(
                'type' => array('object', 'null'),
                'description' => 'WooCommerce sync setup, read it before editing a product. Null when WooCommerce is not active. '
                    . 'auto_sync.off_reason says why prices are not synced by themselves: manual_only (criteria is "manually") '
                    . 'or no_modules (no module is in scope); null when the automatic sync is on.',
                'properties' => array(
                    'version' => array('type' => 'string', 'description' => 'WooCommerce version.'),
                    'products_enabled' => array(
                        'type' => 'boolean',
                        'description' => 'False when "product" is not ticked in the post_types setting: create-post and the product abilities reject WooCommerce products until it is. Check it before searching.',
                    ),
                    'gtin_field' => array(
                        'type' => 'boolean',
                        'description' => 'True when this WooCommerce has the native GTIN field (9.2 or later).',
                    ),
                    'auto_sync' => array(
                        'type' => 'object',
                        'properties' => array(
                            'criteria' => array('type' => 'string', 'enum' => WooPreflight::CRITERIA),
                            'scope' => array('type' => 'string', 'enum' => WooPreflight::SCOPES),
                            'module_ids' => array('type' => 'array', 'items' => array('type' => 'string')),
                            'off_reason' => array('type' => array('string', 'null'), 'description' => 'manual_only | no_modules | null.'),
                        ),
                    ),
                    'exclude_non_new' => array(
                        'type' => 'boolean',
                        'description' => 'Used and refurbished offers are kept out of the best price and the WooCommerce price.',
                    ),
                    'sync_gtin' => array(
                        'type' => 'boolean',
                        'description' => 'The sync writes the offer GTIN into the product (not on pages with a passport).',
                    ),
                    'out_of_stock' => array(
                        'type' => 'string',
                        'description' => 'What happens to an out-of-stock product: "" (nothing), hide_price, hide_product or move_to_trash.',
                    ),
                ),
            ),
        );

        return array(
            'type' => 'object',
            'properties' => array_merge(
                $properties,
                BuildCapabilities::statusSchemaProperties(Plugin::isPaidBuild())
            ),
        );
    }

    public function checkPermission($input = null): bool
    {
        return \current_user_can('edit_posts');
    }

    public function execute(array $input): array
    {
        if (Plugin::isPro())
        {
            $build = 'pro';
        }
        elseif (Plugin::isEnvato())
        {
            $build = 'envato';
        }
        else
        {
            $build = 'free';
        }

        $out = array(
            'plugin_version' => (string) Plugin::version(),
            'build' => $build,
            'abilities_layer_version' => AbilitiesRegistrar::VERSION,
            'wp_version' => (string) \get_bloginfo('version'),
            'active_modules' => count((array) ModuleManager::getInstance()->getModulesIdList(true)),
            // Report whether MCP is actually SERVED, not merely linkable. The
            // adapter is shipped as a library by other plugins (WooCommerce bundles
            // it), so class_exists() is true on sites where the adapter plugin is
            // not active -- McpBridge registers on 'mcp_adapter_init', which only
            // fires when that plugin boots, so the advertised /content-egg/mcp
            // route is never mounted and agents trusting the flag got a 404.
            'mcp_adapter_present' => McpBridge::isServing(),
            'guide_url' => \rest_url('content-egg/v1/agent-guide'),
            'guide_version' => AgentGuideRestController::guideVersion(),
            'guide_topics' => AgentGuideRestController::topicNames(),
            'openapi_url' => \rest_url('content-egg/v1/openapi'),
            'license' => null,
            'woocommerce' => WooPreflight::current(),
        );

        if ($build !== 'free' && \current_user_can('manage_options'))
        {
            $gate = new LicenseGate();
            $status = $gate->legacyStatusArray();

            $out['license'] = array(
                'status' => (string) ($status['status'] ?? ''),
                'expires' => !empty($status['expiry_date']) ? gmdate('Y-m-d', (int) $status['expiry_date']) : '',
                'is_lifetime' => !empty($status['is_lifetime']),
                'blocked' => Plugin::isBlocked(),
                'in_grace' => $gate->inGrace(),
            );
        }

        // Free builds append capabilities/unavailable_abilities/upgrade_url so an
        // agent can plan within reach; on a paid build this merges nothing.
        // Gated on isPaidBuild() (the registrar's own predicate), NOT on $build
        // above — the two are different tests and must not be allowed to disagree.
        return array_merge($out, BuildCapabilities::statusFields(Plugin::isPaidBuild()));
    }
}
