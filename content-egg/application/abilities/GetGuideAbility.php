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
        return 'Returns this site\'s Content Egg agent guide: the workflows and the '
            . 'block-choice rules for building and editing pages. Call this FIRST, before '
            . 'composing or editing any page, and follow it — it prevents common mistakes '
            . '(e.g. Markdown tables instead of the comparison block, or bare product '
            . 'blocks). The guide is plain markdown with this site\'s real URLs filled in.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => array('object', 'null'),
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
        return array('markdown' => AgentGuideRestController::markdown());
    }
}
