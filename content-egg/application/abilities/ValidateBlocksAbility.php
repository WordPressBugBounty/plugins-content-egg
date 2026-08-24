<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\BlockKit\Validator;

/**
 * ValidateBlocksAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class ValidateBlocksAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/validate-blocks';
    }

    public function label(): string
    {
        return __('Validate Blocks', 'content-egg');
    }

    public function description(): string
    {
        return 'Validates a block tree before writing it: unknown types, attribute types and '
            . 'enums, rich-text sanitization, product_ref resolution against a post\'s attached '
            . 'products (or the "products" payload). Returns structured errors with JSON-pointer '
            . 'paths so you can self-correct, plus warnings for normalizations. Send it as a POST '
            . 'with a JSON body (a block tree is impractical as GET query params). The same '
            . 'validation runs inside content-egg/insert-blocks and content-egg/create-post — '
            . 'validating first is recommended, skipping it is safe.';
    }

    public function annotations(): array
    {
        // Not a write, but it carries a full block tree (+ optional products
        // payload) that is impractical to encode as GET query parameters.
        // readonly=false selects POST in the core run controller; no side effects.
        return array('readonly' => false, 'destructive' => false, 'idempotent' => true);
    }

    public function inputSchema(): array
    {
        return array(
            'type' => 'object',
            'default' => array(),
            'properties' => array(
                'blocks' => array('type' => 'array', 'minItems' => 1, 'items' => self::blockNodeSchema()),
                'post_id' => array('type' => 'integer', 'description' => 'Resolve product_refs against this post\'s products.'),
                'products' => self::productsPayloadSchema(
                    'Optional product payload (module_id => items from content-egg/search-products, or '
                        . 'unique_id strings when search_tokens is set), so product_refs resolve for a post '
                        . 'that does not exist yet. Mirrors content-egg/create-post, letting you fully '
                        . 'validate a new product-bound draft before creating it.'
                ),
                'search_tokens' => self::searchTokensSchema(),
            ),
            'required' => array('blocks'),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'valid' => array('type' => 'boolean'),
                'errors' => array('type' => 'array', 'items' => array('type' => 'object')),
                'warnings' => array('type' => 'array', 'items' => array('type' => 'object')),
            ),
        );
    }

    public function checkPermission($input = null): bool
    {
        // When a post_id is supplied, product_refs resolve against that post's
        // attached products — so require edit access to it, or a caller could use
        // validation success/failure as a product-existence oracle on posts outside
        // their scope. Mirrors PreviewBlocksAbility::checkPermission().
        $post_id = is_array($input) ? (int) ($input['post_id'] ?? 0) : 0;
        return $post_id > 0 ? PostScope::canEditPost($input) : \current_user_can('edit_posts');
    }

    public function execute(array $input): array
    {
        $blocks = is_array($input['blocks'] ?? null) ? $input['blocks'] : array();
        if (!$blocks)
        {
            throw new AbilityInputException("'blocks' must be a non-empty array of block nodes.");
        }

        $products = self::resolveProductSearchTokens(
            is_array($input['products'] ?? null) ? $input['products'] : array(),
            is_array($input['search_tokens'] ?? null) ? $input['search_tokens'] : array()
        );

        $validator = new Validator();
        $result = $validator->validate($blocks, (int) ($input['post_id'] ?? 0), $products);

        return $result->toArray();
    }
}
