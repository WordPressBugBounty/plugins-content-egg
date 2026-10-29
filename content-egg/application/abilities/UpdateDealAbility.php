<?php

namespace ContentEgg\application\abilities;

use ContentEgg\application\deals\DealPostType;
use ContentEgg\application\deals\DealsSettings;

defined('\ABSPATH') || exit;

/**
 * UpdateDealAbility class file
 *
 * Changes an existing deal: the offer it follows, its end date, category,
 * coupon, state or featured image. The price is not a deal field - it follows
 * the pinned product (update-product / refresh-post-products).
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class UpdateDealAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/update-deal';
    }

    public function label(): string
    {
        return __('Update Deal', 'content-egg');
    }

    public function description(): string
    {
        return 'Changes an existing deal (post type cegg_deal): pin (the attached product the deal follows), '
            . 'ends (ISO 8601; "offer" follows the store\'s own deal end time; null = no end date), '
            . 'category (path "A > B" or term id), coupon ({code, note}; empty code removes it), '
            . 'state ("expired" closes it; "active" reopens it with today\'s price as the new base), '
            . 'featured_image ("product"). A deal\'s price always follows its pinned product: change '
            . 'the product with update-product or refresh-post-products instead. Returns the deal '
            . 'and the fields that changed. Find deals with find-deals.';
    }

    public function inputSchema(): array
    {
        $deal = DealFields::schema(true);

        return array(
            'type' => 'object',
            'default' => array(),
            'properties' => array_merge(
                array('post_id' => array('type' => 'integer', 'minimum' => 1)),
                $deal['properties']
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
                'deal' => LeanDeal::schema(),
                'changed' => array('type' => 'array', 'items' => array('type' => 'string')),
                'warnings' => array('type' => 'array', 'items' => array('type' => 'string')),
            ),
        );
    }

    public function annotations(): array
    {
        // Expiring is reversible (state "active"), so not destructive.
        return array('readonly' => false, 'destructive' => false, 'idempotent' => false);
    }

    public function checkPermission($input = null): bool
    {
        // With Deals off, cegg_deal leaves the post_types list and canEditPost()
        // would answer a bare 403; execute() says why instead, writing nothing.
        if (!DealsSettings::enabled())
            return \current_user_can('edit_posts');

        return PostScope::canEditPost($input);
    }

    public function execute(array $input): array
    {
        DealFields::requireDealsEnabled();

        $post = PostScope::requirePost($input);
        $post_id = (int) $post->ID;

        if ($post->post_type !== DealPostType::POST_TYPE)
            throw new AbilityInputException("Post {$post_id} is not a deal (post type is \"{$post->post_type}\").");

        $fields = $input;
        unset($fields['post_id']);
        if (!$fields)
            throw new AbilityInputException('Nothing to change: pass at least one of ' . implode(', ', DealFields::FIELDS_UPDATE) . '.');

        $result = DealFields::apply($post_id, DealFields::prepare($fields, true));

        $output = array('deal' => LeanDeal::row($post_id), 'changed' => $result['changed']);
        if ($result['warnings'])
            $output['warnings'] = $result['warnings'];

        return $output;
    }
}
