<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ContentManager;
use ContentEgg\application\components\ProductDataService;

/**
 * RemoveProductsAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class RemoveProductsAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/remove-products';
    }

    public function label(): string
    {
        return __('Remove Products', 'content-egg');
    }

    public function description(): string
    {
        return 'DESTRUCTIVE: removes products from a post. Scopes: pass "targets" '
            . '(list of {module_id, unique_id}) for specific products; or "module_id" + '
            . '"unique_id" for one product; or "module_id" alone to remove that whole '
            . 'module\'s products; or "all": true to remove every product from the post. '
            . 'The "all" flag is required for full wipes to prevent accidents. Optional '
            . '"revision" (single-product and whole-module scopes only) detects '
            . 'concurrent edits.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => 'object',
            'default' => array(),
            'properties' => array(
                'post_id' => array('type' => 'integer', 'minimum' => 1),
                'targets' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'module_id' => array('type' => 'string'),
                            'unique_id' => array('type' => 'string'),
                        ),
                        'required' => array('module_id', 'unique_id'),
                    ),
                ),
                'module_id' => array('type' => 'string'),
                'unique_id' => array('type' => 'string'),
                'all' => array('type' => 'boolean', 'description' => 'Set true to remove ALL products from the post.'),
                'revision' => array('type' => 'string'),
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
                'remaining_modules' => array('type' => 'array', 'items' => array('type' => 'string')),
                'revisions' => array(
                    'type' => 'object',
                    'description' => 'module_id => fresh revision for every remaining module, so another '
                        . 'edit can be chained without re-reading with content-egg/get-post-products.',
                ),
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
        $targets = is_array($input['targets'] ?? null) ? $input['targets'] : array();
        $module_id = trim((string) ($input['module_id'] ?? ''));
        $unique_id = (string) ($input['unique_id'] ?? '');
        $revision = (string) ($input['revision'] ?? '');

        if ($targets)
        {
            $clean = array();
            foreach ($targets as $i => $t)
            {
                if (!is_array($t) || empty($t['module_id']) || !isset($t['unique_id']) || $t['unique_id'] === '')
                {
                    throw new AbilityInputException("targets[{$i}] must be {module_id, unique_id}.");
                }
                $clean[] = array(
                    'module_id' => (string) $t['module_id'],
                    'unique_id' => (string) $t['unique_id'],
                );
            }
            ProductDataService::removeItems($post_id, $clean);
        }
        elseif ($module_id !== '' && $unique_id !== '')
        {
            RevisionGuard::check($post_id, $module_id, $revision);
            ProductDataService::removeItem($post_id, $module_id, $unique_id);
        }
        elseif ($module_id !== '')
        {
            RevisionGuard::check($post_id, $module_id, $revision);
            ProductDataService::removeAll($post_id, $module_id);
        }
        elseif (!empty($input['all']))
        {
            ProductDataService::removeAll($post_id);
        }
        else
        {
            throw new AbilityInputException(
                'Specify what to remove: "targets", "module_id" (+ optional "unique_id"), or "all": true for everything.'
            );
        }

        $remaining = array();
        foreach (array_keys((array) \get_post_meta($post_id)) as $meta_key)
        {
            if (strpos($meta_key, ContentManager::META_PREFIX_DATA) === 0)
            {
                $remaining[] = substr($meta_key, strlen(ContentManager::META_PREFIX_DATA));
            }
        }

        // Fresh revision per surviving module. update-product returns one, so a
        // caller could chain edits after an update but not after a removal —
        // the removal invalidated the revision it was holding and the only way
        // to get the new one was another get-post-products round trip.
        $revisions = array();
        foreach ($remaining as $mid)
        {
            $revisions[$mid] = (string) ProductDataService::revision($post_id, $mid);
        }

        return array(
            'post_id' => $post_id,
            'remaining_modules' => $remaining,
            'revisions' => (object) $revisions,
        );
    }
}
