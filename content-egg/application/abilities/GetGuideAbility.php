<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * GetGuideAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Returns the site's agent guide as a first-class ability, so any connected
 * client can load the workflows and block-choice rules through the same
 * authenticated tool channel — without needing a web browser or the separate
 * public /agent-guide endpoint. Especially useful for MCP tool clients.
 */
final class GetGuideAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/get-guide';
    }

    public function label(): string
    {
        return __('Get Agent Guide', 'content-egg');
    }

    public function description(): string
    {
        return 'Call this FIRST, before building or editing any page. '
            . 'Returns this site\'s agent guide: with no input, the core guide plus the topic index; '
            . 'with `topic`, one topic (`all` returns everything). Read core first, then the topic for your task.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => 'object',
            'default' => array(),
            'properties' => array(
                'topic' => array(
                    'type' => 'string',
                    'enum' => array_merge(AgentGuideRestController::topicNames(), array(AgentGuideRestController::TOPIC_ALL)),
                    'description' => 'One guide topic; "all" returns core plus every topic. Omit for the core guide and the topic index.',
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
                'markdown' => array('type' => 'string'),
            ),
        );
    }

    public function checkPermission($input = null): bool
    {
        return \current_user_can('edit_posts');
    }

    public function execute(array $input): array
    {
        return array('markdown' => AgentGuideRestController::markdown(null, (string) ($input['topic'] ?? '')));
    }
}
