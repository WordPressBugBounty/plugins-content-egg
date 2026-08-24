<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\BlockKit\Serializer;
use ContentEgg\application\components\ContentManager;
use ContentEgg\application\BlockKit\Validator;

/**
 * PreviewBlocksAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class PreviewBlocksAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/preview-blocks';
    }

    public function label(): string
    {
        return __('Preview Blocks', 'content-egg');
    }

    public function description(): string
    {
        // Front-loaded: the ChatGPT profile trims this to 300 chars, so the
        // post_id/products contract must be complete before the cut.
        return 'Validates a block tree, serializes it, and renders it to HTML exactly as the '
            . 'frontend would. Send it as a POST with a JSON body. Pass post_id when the tree '
            . 'references that post\'s products, or "products" to let refs validate for a post that '
            . 'does not exist yet. Dynamic blocks execute their render callbacks. '
            . 'Live prices/images/links only hydrate once products are ATTACHED to a real post, so '
            . 'a product-bound block whose ref came only from the "products" payload renders as an '
            . 'empty container — the response then carries an unhydrated_product_ref warning naming '
            . 'those refs, so a blank block is never ambiguous. Editorial blocks preview exactly as '
            . 'they publish. Use to verify output before content-egg/create-post; returns the '
            . 'rendered HTML, the serialized Gutenberg markup and any warnings.';
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
                'post_id' => array('type' => 'integer'),
                'products' => self::productsPayloadSchema(
                    'Optional product payload (module_id => items from content-egg/search-products, or '
                        . 'unique_id strings when search_tokens is set) so product_refs validate for a '
                        . 'post that does not exist yet.'
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
                'warnings' => array(
                    'type' => 'array',
                    'items' => array('type' => 'object'),
                    'description' => 'Non-fatal notes. unhydrated_product_ref means the block rendered '
                        . 'empty because its products are not attached to a post yet.',
                ),
                'html' => array('type' => 'string'),
                'markup' => array('type' => 'string'),
            ),
        );
    }

    public function checkPermission($input = null): bool
    {
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

        $post_id = (int) ($input['post_id'] ?? 0);
        $products = self::resolveProductSearchTokens(
            is_array($input['products'] ?? null) ? $input['products'] : array(),
            is_array($input['search_tokens'] ?? null) ? $input['search_tokens'] : array()
        );
        $result = (new Validator())->validate($blocks, $post_id, $products);

        if (!$result->valid)
        {
            return array('valid' => false, 'errors' => $result->errors, 'warnings' => array(), 'html' => '', 'markup' => '');
        }

        $markup = Serializer::serialize($result->tree);

        $has_postdata = false;
        if ($post_id > 0 && ($post = \get_post($post_id)))
        {
            $GLOBALS['post'] = $post;
            \setup_postdata($post);
            $has_postdata = true;
        }

        try
        {
            $html = \do_blocks($markup);
        }
        finally
        {
            // Always restore global post state, even if a render callback throws.
            if ($has_postdata)
            {
                \wp_reset_postdata();
            }
        }

        return array(
            'valid' => true,
            'errors' => array(),
            'warnings' => self::unhydratedWarnings($result->tree, $post_id),
            'html' => $html,
            'markup' => $markup,
        );
    }

    /**
     * Product-bound blocks render from data attached to a real post. Refs that
     * resolved only from the "products" payload therefore render as an empty
     * container, and returning valid:true with blank HTML gave an agent no way to
     * tell a mis-composed block from a preview that cannot hydrate. Say so.
     *
     * @param array $tree Clean tree from the Validator.
     */
    private static function unhydratedWarnings(array $tree, int $post_id): array
    {
        $refs = array();
        foreach ($tree as $node)
        {
            if (is_array($node['product_ref'] ?? null))
            {
                $refs[] = $node['product_ref'];
            }
            foreach ((array) ($node['attrs']['items'] ?? array()) as $item)
            {
                if (is_array($item) && is_array($item['product_ref'] ?? null))
                {
                    $refs[] = $item['product_ref'];
                }
            }
        }

        $unhydrated = array();
        foreach ($refs as $ref)
        {
            $module_id = (string) ($ref['module_id'] ?? '');
            $unique_id = (string) ($ref['unique_id'] ?? '');
            if ($module_id === '' || $unique_id === '')
            {
                continue;
            }

            $attached = $post_id > 0 ? (array) ContentManager::getData($post_id, $module_id) : array();
            if (!isset($attached[$unique_id]))
            {
                $unhydrated[$module_id . '|' . $unique_id] = $module_id . ':' . $unique_id;
            }
        }

        if (!$unhydrated)
        {
            return array();
        }

        return array(array(
            'path' => '/blocks',
            'code' => 'unhydrated_product_ref',
            'message' => 'These product_refs are not attached to a post, so their blocks render empty here: '
                . implode(', ', $unhydrated) . '. The "products" payload lets refs VALIDATE before a post '
                . 'exists, but live prices, images and links only hydrate once products are attached. '
                . 'Attach them (content-egg/add-products-to-post, or content-egg/create-post with the same '
                . 'payload) and preview again with that post_id.',
        ));
    }
}
