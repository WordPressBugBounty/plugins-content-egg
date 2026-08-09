<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ParserModule;

/**
 * AddCouponsToPostAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class AddCouponsToPostAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/add-coupons-to-post';
    }

    public function label(): string
    {
        return __('Add Coupons to Post', 'content-egg');
    }

    public function description(): string
    {
        return 'Attaches coupons to a post under one active COUPON module so a '
            . 'content-egg/coupons block (or the [content-egg-block] shortcode) can render '
            . 'them. Preferred flow: run content-egg/search-coupons, then pass its search_token plus the '
            . 'chosen unique_ids as items — the server attaches its stored copy of each result, so '
            . 'full objects never need to be echoed back. (Passing full item objects from '
            . 'fields="full" without a token still works.) The module_id must be '
            . 'an active coupon module (see content-egg/list-modules). Pass a revision to '
            . 'guard against concurrent edits (409 cegg_conflict on mismatch). Returns the '
            . 'new revision.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => array('object', 'null'),
            'properties' => array(
                'post_id' => array('type' => 'integer', 'minimum' => 1),
                'module_id' => array('type' => 'string'),
                'search_token' => array(
                    'type' => 'string',
                    'description' => 'Token from the content-egg/search-coupons response. When set, items are '
                        . 'unique_id refs and the server attaches its stored copy of each result.',
                ),
                'items' => array(
                    'type' => 'array',
                    'minItems' => 1,
                    'items' => array(
                        'type' => array('string', 'object'),
                        'description' => 'With search_token: a unique_id string from the search results. '
                            . 'Without: a full coupon object from content-egg/search-coupons (fields="full"), '
                            . 'passed unchanged.',
                    ),
                ),
                'keyword' => array(
                    'type' => 'string',
                    'description' => 'Stored as the module search keyword for later auto-updates.',
                ),
                'revision' => array('type' => 'string'),
            ),
            'required' => array('post_id', 'module_id', 'items'),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'post_id' => array('type' => 'integer'),
                'module_id' => array('type' => 'string'),
                'added' => array('type' => 'array', 'items' => array('type' => 'string')),
                'count' => array('type' => 'integer'),
                'revision' => array('type' => 'string'),
            ),
        );
    }

    public function annotations(): array
    {
        return array('readonly' => false, 'destructive' => false, 'idempotent' => false);
    }

    public function checkPermission($input = null): bool
    {
        return PostScope::canEditPost($input);
    }

    public function execute(array $input): array
    {
        $post = PostScope::requirePost($input);

        return GenericAttachService::attach(
            (int) $post->ID,
            trim((string) ($input['module_id'] ?? '')),
            ParserModule::PARSER_TYPE_COUPON,
            is_array($input['items'] ?? null) ? $input['items'] : array(),
            \sanitize_text_field((string) ($input['keyword'] ?? '')),
            (string) ($input['revision'] ?? ''),
            trim((string) ($input['search_token'] ?? ''))
        );
    }
}
