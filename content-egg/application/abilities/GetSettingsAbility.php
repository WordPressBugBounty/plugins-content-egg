<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;

/**
 * GetSettingsAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class GetSettingsAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/get-settings';
    }

    public function label(): string
    {
        return __('Get Global Settings', 'content-egg');
    }

    public function description(): string
    {
        return 'Reads Content Egg global settings. Call without arguments to list setting '
            . 'sections with option counts; pass "section" to get the option values and '
            . 'metadata of one section. Secret values are masked to their last 4 characters. '
            . 'The full settings set is large — always work section by section.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => 'object',
            'default' => array(),
            'properties' => array(
                'section' => array(
                    'type' => 'string',
                    'description' => 'Section name as returned by the section list.',
                ),
            ),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'sections' => array(
                    'type' => 'array',
                    'description' => 'Present when called without "section".',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'name' => array('type' => 'string'),
                            'option_count' => array('type' => 'integer'),
                        ),
                    ),
                ),
                'section' => array('type' => 'string'),
                'settings' => array('type' => 'object'),
                'options' => array('type' => 'object'),
            ),
        );
    }

    public function checkPermission($input = null): bool
    {
        return \current_user_can('manage_options');
    }

    public function execute(array $input): array
    {
        $config = GeneralConfig::getInstance();
        $meta = (array) $config->getOptionsMeta();
        $section = trim((string) ($input['section'] ?? ''));

        if ($section === '')
        {
            $sections = array();
            foreach ($meta as $m)
            {
                $name = (string) ($m['section'] ?? 'Other');
                $sections[$name] = ($sections[$name] ?? 0) + 1;
            }

            $list = array();
            foreach ($sections as $name => $count)
            {
                $list[] = array('name' => $name, 'option_count' => $count);
            }

            return array('sections' => $list);
        }

        $raw_values = (array) $config->getOptionValues();

        $settings = array();
        $options = array();
        foreach ($meta as $key => $m)
        {
            if ((string) ($m['section'] ?? 'Other') !== $section)
            {
                continue;
            }

            $is_password = !empty($m['is_password']);
            unset($m['is_password']);

            $m['secret'] = $is_password || SecretMasker::isSecretKey((string) $key);

            if ($is_password && !SecretMasker::isSecretKey((string) $key) && isset($raw_values[$key]) && is_scalar($raw_values[$key]))
            {
                $raw_values[$key] = SecretMasker::maskValue($raw_values[$key]);
            }

            $options[$key] = $m;
            $settings[$key] = $raw_values[$key] ?? null;
        }

        $settings = SecretMasker::maskArray($settings);

        if (!$options)
        {
            throw new AbilityInputException(
                "Unknown section '{$section}'. Call content-egg/get-settings without arguments to list sections."
            );
        }

        return array('section' => $section, 'settings' => $settings, 'options' => $options);
    }
}
