<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ModuleManager;

/**
 * DeactivateModuleAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class DeactivateModuleAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/deactivate-module';
    }

    public function label(): string
    {
        return __('Deactivate Module', 'content-egg');
    }

    public function description(): string
    {
        return 'Deactivates a Content Egg module. Existing product data attached to '
            . 'posts is kept; the module stops searching and updating. Idempotent: '
            . 'deactivating an inactive module reports changed=false.';
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

        if (!$manager->isModuleActive($module_id))
        {
            return array('module_id' => $module_id, 'active' => false, 'changed' => false);
        }

        $manager->deactivateModule($module_id);

        return array('module_id' => $module_id, 'active' => false, 'changed' => true);
    }
}
