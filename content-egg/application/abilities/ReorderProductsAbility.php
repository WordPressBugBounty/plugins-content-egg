<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ProductDataService;

/**
 * ReorderProductsAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class ReorderProductsAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/reorder-products';
    }

    public function label(): string
    {
        return __('Reorder Products', 'content-egg');
    }

    public function description(): string
    {
        return 'Sets the stored display order (order_num) of a post\'s products. This drives '
            . 'the content-egg/products block and the classic comparison/listing templates '
            . 'that auto-pull a module\'s products. It does NOT reorder Egg Blocks: those render '
            . 'products in the order of the product_refs stored in the block itself — to reorder '
            . 'an Egg Block, read it with content-egg/get-post-blocks, reorder its items, and '
            . 'write it back with content-egg/insert-blocks (mode "replace_at_index"). Pass '
            . '"order" as the complete desired sequence of {module_id, unique_id} across all '
            . 'modules; items get order positions 1..N in the given sequence. Get current '
            . 'products and ids from content-egg/get-post-products. This changes stored product '
            . 'data, so any revision tokens you hold go stale — re-read get-post-products before further edits.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => array('object', 'null'),
            'properties' => array(
                'post_id' => array('type' => 'integer', 'minimum' => 1),
                'order' => array(
                    'type' => 'array',
                    'minItems' => 1,
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'module_id' => array('type' => 'string'),
                            'unique_id' => array('type' => 'string'),
                        ),
                        'required' => array('module_id', 'unique_id'),
                    ),
                ),
            ),
            'required' => array('post_id', 'order'),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'post_id' => array('type' => 'integer'),
                'ok' => array('type' => 'boolean'),
            ),
        );
    }

    public function annotations(): array
    {
        return array('readonly' => false, 'destructive' => false, 'idempotent' => true);
    }

    public function checkPermission($input = null): bool
    {
        return PostScope::canEditPost($input);
    }

    public function execute(array $input): array
    {
        $post = PostScope::requirePost($input);
        $post_id = (int) $post->ID;
        $order = is_array($input['order'] ?? null) ? $input['order'] : array();

        if (!$order)
        {
            throw new AbilityInputException("'order' must be a non-empty array of {module_id, unique_id}.");
        }

        $clean = array();
        foreach ($order as $i => $entry)
        {
            if (!is_array($entry) || empty($entry['module_id']) || !isset($entry['unique_id']) || $entry['unique_id'] === '')
            {
                throw new AbilityInputException("order[{$i}] must be {module_id, unique_id}.");
            }
            $clean[] = array(
                'module_id' => (string) $entry['module_id'],
                'unique_id' => (string) $entry['unique_id'],
            );
        }

        ProductDataService::reorder($post_id, $clean);

        return array('post_id' => $post_id, 'ok' => true);
    }
}
