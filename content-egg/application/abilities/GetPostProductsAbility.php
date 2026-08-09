<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ContentManager;
use ContentEgg\application\components\ProductDataService;

/**
 * GetPostProductsAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class GetPostProductsAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/get-post-products';
    }

    public function label(): string
    {
        return __('Get Post Products', 'content-egg');
    }

    public function description(): string
    {
        return 'Returns the products attached to a post, grouped per module, with each '
            . 'module\'s search keyword, last update time and a revision token. '
            . 'Keep the revision token: product write operations accept it to detect '
            . 'concurrent edits. Returns compact items by default; pass fields="full" '
            . 'for all raw fields.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => array('object', 'null'),
            'properties' => array(
                'post_id' => array('type' => 'integer', 'minimum' => 1),
                'module_id' => array(
                    'type' => 'string',
                    'description' => 'Limit the response to one module.',
                ),
                'fields' => array(
                    'type' => 'string',
                    'enum' => array('lean', 'full'),
                    'default' => 'lean',
                ),
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
                'post_title' => array('type' => 'string'),
                'post_status' => array('type' => 'string'),
                'modules' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'module_id' => array('type' => 'string'),
                            'count' => array('type' => 'integer'),
                            'keyword' => array('type' => 'string'),
                            'last_update' => array('type' => 'integer'),
                            'revision' => array('type' => 'string'),
                            'items' => array('type' => 'array', 'items' => array('type' => 'object')),
                        ),
                    ),
                ),
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
        $post_id = (int) $post->ID;

        $only_module = trim((string) ($input['module_id'] ?? ''));
        $full = (($input['fields'] ?? 'lean') === 'full');

        $module_ids = array();
        foreach (array_keys((array) \get_post_meta($post_id)) as $meta_key)
        {
            if (strpos($meta_key, ContentManager::META_PREFIX_DATA) === 0)
            {
                $module_ids[] = substr($meta_key, strlen(ContentManager::META_PREFIX_DATA));
            }
        }

        $modules = array();
        foreach ($module_ids as $module_id)
        {
            if ($only_module !== '' && $module_id !== $only_module)
            {
                continue;
            }

            $data = (array) ContentManager::getData($post_id, $module_id);

            $modules[] = array(
                'module_id' => (string) $module_id,
                'count' => count($data),
                'keyword' => (string) \get_post_meta($post_id, ContentManager::META_PREFIX_KEYWORD . $module_id, true),
                'last_update' => (int) \get_post_meta($post_id, ContentManager::META_PREFIX_LAST_ITEMS_UPDATE . $module_id, true),
                'revision' => (string) ProductDataService::revision($post_id, $module_id),
                'items' => $full
                    ? array_values(array_map(array(LeanProduct::class, 'fullItem'), $data))
                    : LeanProduct::mapList($data),
            );
        }

        if ($only_module !== '' && !$modules)
        {
            throw new AbilityInputException(
                "No products for module '{$only_module}' on post {$post_id}. "
                    . 'Attached modules: ' . ($module_ids ? implode(', ', $module_ids) : '(none)') . '.'
            );
        }

        return array(
            'post_id' => $post_id,
            'post_title' => (string) $post->post_title,
            'post_status' => (string) $post->post_status,
            'modules' => $modules,
        );
    }
}
