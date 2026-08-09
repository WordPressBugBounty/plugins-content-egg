<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ModuleManager;

/**
 * GetModuleSettingsAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class GetModuleSettingsAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/get-module-settings';
    }

    public function label(): string
    {
        return __('Get Module Settings', 'content-egg');
    }

    public function description(): string
    {
        return 'Returns the settings of one Content Egg module together with option metadata '
            . '(titles, defaults, allowed choices, sections). Credential-shaped values '
            . '(API keys, secrets, tokens) are masked and show only their last 4 characters; '
            . 'they can be updated but never read in full. '
            . 'Get module ids from content-egg/list-modules.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => array('object', 'null'),
            'properties' => array(
                'module_id' => array(
                    'type' => 'string',
                    'description' => 'Module id, e.g. "Amazon" or "Feed__1".',
                ),
            ),
            'required' => array('module_id'),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'module_id' => array('type' => 'string'),
                'is_active' => array('type' => 'boolean'),
                'settings' => array(
                    'type' => 'object',
                    'description' => 'Current option values; secret values are masked.',
                ),
                'options' => array(
                    'type' => 'object',
                    'description' => 'Per-option metadata: title, default, choices, secret flag.',
                ),
            ),
        );
    }

    public function checkPermission($input = null): bool
    {
        return \current_user_can('manage_options');
    }

    public function execute(array $input): array
    {
        $module_id = trim((string) ($input['module_id'] ?? ''));
        $manager = ModuleManager::getInstance();

        if (!$manager->moduleExists($module_id))
        {
            throw new AbilityInputException(
                "Unknown module '{$module_id}'. Call content-egg/list-modules for available module ids."
            );
        }

        $config = ModuleManager::configFactory($module_id);
        $view = SettingsPatcher::maskedValues($config);

        return array(
            'module_id' => $module_id,
            'is_active' => (bool) $manager->isModuleActive($module_id),
            'settings' => $view['settings'],
            'options' => $view['options'],
        );
    }
}
