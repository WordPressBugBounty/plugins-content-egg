<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\components\ParserModule;
use ContentEgg\application\BlockKit\Validator;
use ContentEgg\application\BlockKit\Serializer;

/**
 * CreatePostAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class CreatePostAbility extends AbilityBase
{
    const STATUSES = array('draft', 'pending', 'publish', 'private');

    public function name(): string
    {
        return 'content-egg/create-post';
    }

    public function label(): string
    {
        return __('Create Post', 'content-egg');
    }

    public function description(): string
    {
        return 'Creates a post from a block tree in one call: validates the tree, attaches '
            . 'the supplied items and serializes to Gutenberg markup. The "products" payload '
            . 'is module_id => items from the matching search ability with fields="full" — it '
            . 'accepts ANY module type: products (search-products / manual Offer items), '
            . 'coupons (search-coupons), images (search-images) and videos (search-videos); '
            . 'each module is attached through the ability that matches its type. product_refs '
            . 'in Egg Blocks reference products from the payload (matched by module_id + '
            . 'unique_id). Product items may carry an optional "overrides" object (title, '
            . 'subtitle, description, short_description, badge, badge_color [a named color: '
            . 'primary, secondary, success, danger, warning, info, light, dark — not hex], promo) applied on '
            . 'top of the source item; overrides apply to products only. Nothing is written '
            . 'when validation fails. Publishing requires the publish_posts capability; '
            . 'default status is draft.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => array('object', 'null'),
            'properties' => array(
                'title' => array('type' => 'string', 'minLength' => 2),
                'status' => array('type' => 'string', 'enum' => self::STATUSES, 'default' => 'draft'),
                'post_type' => array('type' => 'string', 'default' => 'post'),
                'blocks' => array('type' => 'array', 'minItems' => 1, 'items' => array('type' => 'object')),
                'products' => array(
                    'type' => 'object',
                    'description' => 'module_id => array of items to attach before rendering, for any '
                        . 'module type (products, coupons, images, videos) from the matching search '
                        . 'ability with fields="full". Product items may include an optional "overrides" '
                        . 'object of editorial fields (products only).',
                ),
                'keyword' => array('type' => 'string', 'description' => 'Stored as the search keyword for later auto-updates.'),
            ),
            'required' => array('title', 'blocks'),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'post_id' => array('type' => 'integer'),
                'edit_url' => array('type' => 'string'),
                'status' => array('type' => 'string'),
                'block_count' => array('type' => 'integer'),
                'attached' => array('type' => 'object', 'description' => 'module_id => added unique_ids.'),
            ),
        );
    }

    public function annotations(): array
    {
        return array('readonly' => false, 'destructive' => false, 'idempotent' => false);
    }

    public function checkPermission($input = null): bool
    {
        $post_type = is_array($input) ? \sanitize_key((string) ($input['post_type'] ?? 'post')) : 'post';
        $status = is_array($input) ? (string) ($input['status'] ?? 'draft') : 'draft';

        $pt = \get_post_type_object($post_type);
        if (!$pt)
        {
            // Unknown/unregistered type: fall back to the strictest generic gate;
            // execute() rejects the type against the CE post_types list anyway.
            return \current_user_can(PostScope::isPublishingStatus($status) ? 'publish_posts' : 'edit_posts');
        }

        // Creating an object requires the type's create capability (distinct from
        // edit on some custom post types); a publishing status (publish/private)
        // additionally requires the type's publish capability.
        if (!\current_user_can((string) $pt->cap->create_posts))
        {
            return false;
        }

        return PostScope::isPublishingStatus($status)
            ? \current_user_can((string) $pt->cap->publish_posts)
            : true;
    }

    public function execute(array $input): array
    {
        $title = \sanitize_text_field((string) ($input['title'] ?? ''));
        $status = (string) ($input['status'] ?? 'draft');
        $post_type = \sanitize_key((string) ($input['post_type'] ?? 'post'));
        $blocks = is_array($input['blocks'] ?? null) ? $input['blocks'] : array();
        $products = is_array($input['products'] ?? null) ? $input['products'] : array();

        if ($title === '')
        {
            throw new AbilityInputException("'title' is required.");
        }
        if (!in_array($status, self::STATUSES, true))
        {
            throw new AbilityInputException("'status' must be one of: " . implode(', ', self::STATUSES) . '.');
        }

        $post_types = (array) GeneralConfig::getInstance()->option('post_types');
        if (!in_array($post_type, $post_types, true))
        {
            throw new AbilityInputException(
                "Post type '{$post_type}' is not enabled for Content Egg. Enabled types: " . implode(', ', $post_types) . '.'
            );
        }

        // 1. Validate BEFORE any write; refs may resolve from the payload.
        $result = (new Validator())->validate($blocks, 0, $products);
        if (!$result->valid)
        {
            throw new AbilityInputException(
                'Block tree invalid: ' . \wp_json_encode(array_slice($result->errors, 0, 5))
                    . ' Use content-egg/validate-blocks to iterate.'
            );
        }

        // 2. Create the post as a draft first; the requested status is applied
        //    only after content + products are in place, so a failure mid-way
        //    never leaves a live (e.g. published) orphan.
        // wp_insert_post expects slashed input (it wp_unslash()es internally);
        // slash the whole array so the title survives backslashes/quotes.
        $post_id = \wp_insert_post(\wp_slash(array(
            'post_title' => $title,
            'post_status' => 'draft',
            'post_type' => $post_type,
            'post_content' => '',
        )), true);

        if (\is_wp_error($post_id))
        {
            throw new \RuntimeException('Post creation failed: ' . $post_id->get_error_message());
        }
        $post_id = (int) $post_id;

        try
        {
            // 3. Attach each module's items through the ability that matches its
            //    type (products keep Offer/monetization/overrides; coupons, images
            //    and videos go through the generic attach).
            $attached = array();
            foreach ($products as $module_id => $items)
            {
                if (!is_array($items) || !$items)
                {
                    continue;
                }
                $r = self::attachByType($post_id, (string) $module_id, array_values($items), (string) ($input['keyword'] ?? ''));
                $attached[(string) $module_id] = $r['added'];
            }

            // 4. Serialize the content and apply the requested status in one update.
            $updated = \wp_update_post(\wp_slash(array(
                'ID' => $post_id,
                'post_status' => $status,
                'post_content' => Serializer::serialize($result->tree),
            )), true);

            if (\is_wp_error($updated))
            {
                throw new \RuntimeException('Post content write failed: ' . $updated->get_error_message());
            }
        }
        catch (\Throwable $e)
        {
            // Roll back the draft so no orphan (and never a live post) survives a
            // partial create.
            \wp_delete_post($post_id, true);
            throw $e;
        }

        return array(
            'post_id' => $post_id,
            'edit_url' => (string) \get_edit_post_link($post_id, 'raw'),
            'status' => $status,
            'block_count' => count($result->tree),
            'attached' => $attached,
        );
    }

    /**
     * Attach one module's items through the ability matching its parser type,
     * so a single create-post call can seed products, coupons, images and videos.
     * Returns the chosen ability's response (carries 'added').
     */
    private static function attachByType(int $post_id, string $module_id, array $items, string $keyword): array
    {
        if (!ModuleManager::getInstance()->moduleExists($module_id))
        {
            throw new AbilityInputException(
                "Unknown module '{$module_id}' in the products payload. Call content-egg/list-modules for available module ids."
            );
        }

        try
        {
            $type = ModuleManager::parserFactory($module_id)->getParserType();
        }
        catch (\Exception $e)
        {
            throw new AbilityInputException("Module '{$module_id}' is not available in this build.");
        }

        $ability = null;
        switch ($type)
        {
            case ParserModule::PARSER_TYPE_PRODUCT:
                $ability = new AddProductsToPostAbility();
                break;
            case ParserModule::PARSER_TYPE_COUPON:
                $ability = new AddCouponsToPostAbility();
                break;
            case ParserModule::PARSER_TYPE_IMAGE:
                $ability = new AddImagesToPostAbility();
                break;
            case ParserModule::PARSER_TYPE_VIDEO:
                $ability = new AddVideosToPostAbility();
                break;
            default:
                throw new AbilityInputException(
                    "Module '{$module_id}' has a type that cannot be attached to a post."
                );
        }

        return $ability->execute(array(
            'post_id' => $post_id,
            'module_id' => $module_id,
            'items' => $items,
            'keyword' => $keyword,
        ));
    }
}
