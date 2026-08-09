<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\BlockKit\TreeParser;

/**
 * GetPostBlocksAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class GetPostBlocksAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/get-post-blocks';
    }

    public function label(): string
    {
        return __('Get Post Blocks', 'content-egg');
    }

    public function description(): string
    {
        return 'Parses a post\'s content into the editable block tree used by '
            . 'content-egg/insert-blocks: eggb/* and content-egg/products nodes with their '
            . 'attributes, core prose nodes with html, and opaque nodes (other plugins\' '
            . 'blocks or freeform HTML) that round-trip verbatim. Enables the read -> '
            . 'modify -> write loop for existing posts.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => array('object', 'null'),
            'properties' => array(
                'post_id' => array('type' => 'integer', 'minimum' => 1),
            ),
            'required' => array('post_id'),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'post_id' => array('type' => 'integer'),
                'block_count' => array('type' => 'integer'),
                'blocks' => array('type' => 'array', 'items' => array('type' => 'object')),
            ),
        );
    }

    public function checkPermission($input = null): bool
    {
        return PostScope::canEditPost($input);
    }

    public function execute(array $input): array
    {
        $post = PostScope::requirePost($input);
        $tree = TreeParser::toTree(\parse_blocks((string) $post->post_content));

        return array(
            'post_id' => (int) $post->ID,
            'block_count' => count($tree),
            'blocks' => $tree,
        );
    }
}
