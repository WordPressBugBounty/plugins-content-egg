<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ModuleManager;

/**
 * ActivateModuleAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class ActivateModuleAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/activate-module';
    }

    public function label(): string
    {
        return __('Activate Module', 'content-egg');
    }

    public function description(): string
    {
        return 'Activates a Content Egg module so it can search and serve products. '
            . 'Idempotent: activating an already-active module reports changed=false. '
            . 'Note: many modules need API credentials before they work — check '
            . 'content-egg/get-module-settings and set credentials with '
            . 'content-egg/update-module-settings.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => array('object', 'null'),
            'properties' => array(
                'module_id' => array('type' => 'string'),
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
                'active' => array('type' => 'boolean'),
                'changed' => array('type' => 'boolean'),
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
        $manager = ModuleManager::getInstance();

        if (!$manager->moduleExists($module_id))
        {
            throw new AbilityInputException(
                "Unknown module '{$module_id}'. Call content-egg/list-modules for available module ids."
            );
        }

        if ($manager->isModuleActive($module_id))
        {
            return array('module_id' => $module_id, 'active' => true, 'changed' => false);
        }

        // Refuse activation when required configuration is missing or invalid,
        // exactly as the admin settings form does (it snaps is_active back to 0).
        $config = ModuleManager::configFactory($module_id);
        $blockers = $config->getActivationBlockers();
        if ($blockers)
        {
            $fields = implode(', ', array_keys($blockers));
            throw new AbilityInputException(
                "Cannot activate '{$module_id}' yet — required configuration is missing or invalid: {$fields}. "
                    . 'Set it with content-egg/update-module-settings (see content-egg/get-module-settings '
                    . 'for valid keys and values), then activate.'
            );
        }

        $manager->activateModule($module_id);

        return array('module_id' => $module_id, 'active' => true, 'changed' => true);
    }
}
