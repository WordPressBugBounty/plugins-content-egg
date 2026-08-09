<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\BlockKit\Validator;
use ContentEgg\application\BlockKit\Serializer;
use ContentEgg\application\BlockKit\TreeParser;
use ContentEgg\application\BlockKit\Catalog;

/**
 * InsertBlocksAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

final class InsertBlocksAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/insert-blocks';
    }

    public function label(): string
    {
        return __('Insert Blocks', 'content-egg');
    }

    public function description(): string
    {
        return 'Writes a validated block tree into an existing post. Modes: append, prepend, '
            . 'at_index (insert before the existing block at "index"), replace_at_index '
            . '(replace the single existing block at "index" — read positions from '
            . 'content-egg/get-post-blocks; use to edit or reorder one block in place, e.g. '
            . 'reorder an Egg Block\'s products, without rewriting the whole post), replace_all '
            . '(DESTRUCTIVE: discards the current content). product_refs must resolve '
            . 'against products already attached to the post - attach them first with '
            . 'content-egg/add-products-to-post. Existing non-Content-Egg blocks are '
            . 'preserved verbatim in all modes except replace_all.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => array('object', 'null'),
            'properties' => array(
                'post_id' => array('type' => 'integer', 'minimum' => 1),
                'blocks' => array('type' => 'array', 'minItems' => 1, 'items' => array('type' => 'object')),
                'mode' => array('type' => 'string', 'enum' => array('append', 'prepend', 'at_index', 'replace_at_index', 'replace_all'), 'default' => 'append'),
                'index' => array(
                    'type' => 'integer',
                    'minimum' => 0,
                    'description' => 'Required for at_index (insert before this block) and replace_at_index '
                        . '(replace this block). 0-based position in the top-level block list from get-post-blocks.',
                ),
            ),
            'required' => array('post_id', 'blocks'),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'post_id' => array('type' => 'integer'),
                'mode' => array('type' => 'string'),
                'block_count' => array('type' => 'integer', 'description' => 'Total blocks in the post after the write.'),
            ),
        );
    }

    public function annotations(): array
    {
        return array('readonly' => false, 'destructive' => true, 'idempotent' => false);
    }

    public function checkPermission($input = null): bool
    {
        return PostScope::canEditPost($input);
    }

    public function execute(array $input): array
    {
        $post = PostScope::requirePost($input);
        $post_id = (int) $post->ID;
        $blocks = is_array($input['blocks'] ?? null) ? $input['blocks'] : array();
        $mode = (string) ($input['mode'] ?? 'append');

        if (!$blocks)
        {
            throw new AbilityInputException("'blocks' must be a non-empty array of block nodes.");
        }

        $result = (new Validator())->validate($blocks, $post_id);
        if (!$result->valid)
        {
            throw new AbilityInputException(
                'Block tree invalid: ' . \wp_json_encode(array_slice($result->errors, 0, 5))
                    . ' Use content-egg/validate-blocks to iterate.'
            );
        }

        // Drop a redundant post_id the agent set on the blocks it's inserting:
        // 0 is the default, and a value equal to this post is the implicit
        // source anyway. A post_id aimed at a DIFFERENT post is left intact
        // (deliberate cross-post embedding).
        $new_blocks = self::stripRedundantPostId($result->tree, $post_id);

        $existing = TreeParser::toTree(\parse_blocks((string) $post->post_content));

        switch ($mode)
        {
            case 'replace_all':
                $tree = $new_blocks;
                break;
            case 'prepend':
                $tree = array_merge($new_blocks, $existing);
                break;
            case 'at_index':
                $index = max(0, min(count($existing), (int) ($input['index'] ?? 0)));
                $tree = array_merge(array_slice($existing, 0, $index), $new_blocks, array_slice($existing, $index));
                break;
            case 'replace_at_index':
                // Replace the single existing block at $index with the new blocks
                // (1->N). A replace must hit an existing block, so unlike at_index
                // (an insert, which clamps) an out-of-range index is rejected.
                $count = count($existing);
                $index = (int) ($input['index'] ?? -1);
                if ($count === 0 || $index < 0 || $index >= $count)
                {
                    throw new AbilityInputException(
                        "replace_at_index needs 'index' in 0.." . ($count - 1)
                            . " (the post has {$count} top-level blocks). "
                            . "Read positions from content-egg/get-post-blocks."
                    );
                }
                $tree = array_merge(
                    array_slice($existing, 0, $index),
                    $new_blocks,
                    array_slice($existing, $index + 1)
                );
                break;
            case 'append':
            default:
                $tree = array_merge($existing, $new_blocks);
                break;
        }

        // wp_update_post expects slashed input; slash the whole array (not just
        // the content string) so no sibling field is silently unslashed.
        $updated = \wp_update_post(\wp_slash(array(
            'ID' => $post_id,
            'post_content' => Serializer::serialize($tree),
        )), true);

        if (\is_wp_error($updated))
        {
            throw new \RuntimeException('Post update failed: ' . $updated->get_error_message());
        }

        return array('post_id' => $post_id, 'mode' => $mode, 'block_count' => count($tree));
    }

    /**
     * Strip a redundant post_id from live-display blocks in $tree: unset it when
     * it's 0 (the block default) or equal to $post_id (the destination is the
     * implicit source anyway). A post_id pointing at a DIFFERENT post is kept —
     * that's a deliberate cross-post embed. The tree is flat (BlockKit has no
     * nested containers), so a single pass covers it.
     */
    private static function stripRedundantPostId(array $tree, int $post_id): array
    {
        foreach ($tree as $i => $node)
        {
            if (!in_array((string) ($node['type'] ?? ''), Catalog::LIVE_DISPLAY_TYPES, true))
            {
                continue;
            }
            if (!isset($node['attrs']) || !is_array($node['attrs']) || !array_key_exists('post_id', $node['attrs']))
            {
                continue;
            }
            $val = (int) $node['attrs']['post_id'];
            if ($val === 0 || $val === $post_id)
            {
                unset($tree[$i]['attrs']['post_id']);
            }
        }
        return $tree;
    }
}
