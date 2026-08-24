<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\Plugin;
use ContentEgg\application\components\ModuleManager;
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
            'openapi_url' => array('type' => 'string'),
            'license' => array(
                'type' => array('object', 'null'),
                'description' => 'License summary. Null on free builds or when the user lacks manage_options.',
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
            'openapi_url' => \rest_url('content-egg/v1/openapi'),
            'license' => null,
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
