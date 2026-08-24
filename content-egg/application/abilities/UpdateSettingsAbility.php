<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;

/**
 * UpdateSettingsAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class UpdateSettingsAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/update-settings';
    }

    public function label(): string
    {
        return __('Update Global Settings', 'content-egg');
    }

    public function description(): string
    {
        return 'Updates Content Egg global settings (partial patch: only the keys you '
            . 'send change). Discover sections with content-egg/get-settings, then read '
            . 'one section for valid keys, current values and allowed choices. Never '
            . 'send a masked ••••… value back — credential fields accept full values '
            . 'on write.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => 'object',
            'default' => array(),
            'properties' => array(
                'settings' => self::freeFormObject('Option key => new value map (partial).'),
            ),
            'required' => array('settings'),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'applied' => array('type' => 'array', 'items' => array('type' => 'string')),
                'settings' => array('type' => 'object', 'description' => 'Current values of the patched keys; secrets masked.'),
            ),
        );
    }

    public function annotations(): array
    {
        return array('readonly' => false, 'destructive' => false, 'idempotent' => true);
    }

    public function checkPermission($input = null): bool
    {
        return \current_user_can('manage_options');
    }

    public function execute(array $input): array
    {
        $settings = is_array($input['settings'] ?? null) ? $input['settings'] : array();

        $config = GeneralConfig::getInstance();
        $result = SettingsPatcher::apply($config, $settings);
        $view = SettingsPatcher::maskedValues($config);

        $patched = array();
        foreach ($result['applied'] as $key)
        {
            $patched[$key] = $view['settings'][$key] ?? null;
        }

        return array(
            'applied' => $result['applied'],
            'settings' => $patched,
        );
    }
}
