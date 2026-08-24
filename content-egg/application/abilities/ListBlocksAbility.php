<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\BlockKit\Catalog;
use ContentEgg\application\BlockKit\Hints;

/**
 * ListBlocksAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class ListBlocksAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/list-blocks';
    }

    public function label(): string
    {
        return __('List Blocks', 'content-egg');
    }

    public function description(): string
    {
        return 'Catalog of every block type an agent can compose pages from, with attribute '
            . 'schemas, usage hints, and product-binding shapes. Three families: eggb/* '
            . '(authoring: fixed schemas, content snapshot), content-egg/products (live '
            . 'product display), core/markdown (prose). ' . Hints::DECISION_RULE
            . ' Use with content-egg/validate-blocks and content-egg/create-post.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => 'object',
            'default' => array(),
            'properties' => array(
                'family' => array('type' => 'string', 'enum' => array('eggb', 'products', 'coupons', 'images', 'videos', 'core')),
                'type' => array('type' => 'string', 'description' => 'Return one block type in full detail.'),
            ),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'decision_rule' => array('type' => 'string'),
                'count' => array('type' => 'integer'),
                'blocks' => array('type' => 'array', 'items' => array('type' => 'object')),
            ),
        );
    }

    public function checkPermission($input = null): bool
    {
        return \current_user_can('edit_posts');
    }

    public function execute(array $input): array
    {
        $all = Catalog::all();
        $family = (string) ($input['family'] ?? '');
        $one = (string) ($input['type'] ?? '');

        if ($one !== '')
        {
            $d = $all[$one] ?? null;
            if (!$d)
            {
                throw new AbilityInputException("Unknown block type '{$one}'. Call without 'type' for the full catalog.");
            }
            return array('decision_rule' => Hints::DECISION_RULE, 'count' => 1, 'blocks' => array($d));
        }

        $blocks = array();
        foreach ($all as $d)
        {
            if ($family !== '' && $d['family'] !== $family)
            {
                continue;
            }
            // Full attribute maps for every block would blow up agent context;
            // the list view is a summary — fetch one type for details.
            $blocks[] = array(
                'type' => $d['type'],
                'family' => $d['family'],
                'title' => $d['title'],
                'hint' => $d['hint'],
                'product_binding' => $d['product_binding'],
                'attribute_names' => array_keys($d['attributes']),
            );
        }

        return array('decision_rule' => Hints::DECISION_RULE, 'count' => count($blocks), 'blocks' => $blocks);
    }
}
