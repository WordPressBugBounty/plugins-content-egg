<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ModuleManager;

/**
 * UpdateModuleSettingsAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class UpdateModuleSettingsAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/update-module-settings';
    }

    public function label(): string
    {
        return __('Update Module Settings', 'content-egg');
    }

    public function description(): string
    {
        return 'Updates settings of one Content Egg module (partial patch: only the '
            . 'keys you send change). Get valid keys, current values and allowed '
            . 'choices from content-egg/get-module-settings. Never send a '
            . 'masked ••••… value back. '
            . 'Credential fields accept '
            . 'full values on write even though reads show them masked. '
            . 'Use activate-module/deactivate-module to change '
            . 'the active state, not this ability.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => 'object',
            'default' => array(),
            'properties' => array(
                'module_id' => array('type' => 'string'),
                'settings' => self::freeFormObject('Option key => new value map (partial).'),
            ),
            'required' => array('module_id', 'settings'),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'module_id' => array('type' => 'string'),
                'applied' => array('type' => 'array', 'items' => array('type' => 'string')),
                'settings' => array('type' => 'object', 'description' => 'Current values after the patch; secrets masked.'),
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
        $module_id = trim((string) ($input['module_id'] ?? ''));
        $settings = is_array($input['settings'] ?? null) ? $input['settings'] : array();

        $manager = ModuleManager::getInstance();
        if (!$manager->moduleExists($module_id))
        {
            throw new AbilityInputException(
                "Unknown module '{$module_id}'. Call content-egg/list-modules for available module ids."
            );
        }

        $config = ModuleManager::configFactory($module_id);
        $result = SettingsPatcher::apply($config, $settings, array('is_active'));
        $view = SettingsPatcher::maskedValues($config);

        return array(
            'module_id' => $module_id,
            'applied' => $result['applied'],
            'settings' => $view['settings'],
        );
    }
}
