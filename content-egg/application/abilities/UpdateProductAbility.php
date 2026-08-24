<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\components\ProductDataService;

/**
 * UpdateProductAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class UpdateProductAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/update-product';
    }

    public function label(): string
    {
        return __('Update Product', 'content-egg');
    }

    public function description(): string
    {
        // Front-loaded: the ChatGPT profile trims this to 300 chars, so the
        // editing rules must land first; the long field list is deliberately last, so the
        // clean sentence cut falls just before it rather than mid-list.
        return 'Edits fields of one product attached to a post. Unknown fields are REJECTED '
            . 'with the valid list, so a typo cannot look like success; an empty title is '
            . 'ignored (titles cannot be blanked). Pass the revision from '
            . 'content-egg/get-post-products to detect concurrent edits. '
            . 'Editable fields: title, '
            . 'subtitle, description, short_description, price, priceOld, currencyCode, '
            . 'merchant, domain, manufacturer, url, orig_url, img, rating, ratingDecimal, '
            . 'reviewsCount, badge, badge_color, promo, availability, stock_status, '
            . 'shipping_cost, ean, upc, sku, isbn, group, order_num, features, and '
            . 'extra.priceXpath / extra.deeplink. badge_color takes a named color '
            . '(primary, secondary, success, danger, warning, info, light or dark), not a '
            . 'hex value — a hex value is cleared to empty. NOTE: on API modules '
            . '(Amazon, eBay) the commerce fields (price, priceOld, currencyCode, '
            . 'availability, stock_status, url, img) are refreshed automatically, so '
            . 'hand-edits to them are transient; editorial fields (title, subtitle, '
            . 'description, badge, rating, reviewsCount…) persist. Feed re-imports may '
            . 'overwrite mapped columns.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => 'object',
            'default' => array(),
            'properties' => array(
                'post_id' => array('type' => 'integer', 'minimum' => 1),
                'module_id' => array('type' => 'string'),
                'unique_id' => array('type' => 'string'),
                'fields' => self::freeFormObject('Field => new value map.'),
                'revision' => array('type' => 'string'),
            ),
            'required' => array('post_id', 'module_id', 'unique_id', 'fields'),
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
                'item' => array('type' => 'object', 'description' => 'The updated product (lean envelope).'),
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
        $post_id = (int) $post->ID;
        $module_id = trim((string) ($input['module_id'] ?? ''));
        $unique_id = (string) ($input['unique_id'] ?? '');
        $fields = is_array($input['fields'] ?? null) ? $input['fields'] : array();

        if (!$fields)
        {
            throw new AbilityInputException("'fields' must be a non-empty object of field => value.");
        }
        if (!ModuleManager::getInstance()->moduleExists($module_id))
        {
            throw new AbilityInputException("Unknown module '{$module_id}'.");
        }

        // Reject unknown keys rather than filtering them out silently: a typo
        // ('short_desc', 'badgeColor') returned HTTP 200 and was indistinguishable
        // from success. Mirrors update-settings, which reports the valid key list.
        //
        // Validate against exactly what applyFieldUpdates() will honour. This
        // ability calls updateItem() with no $allowed argument, so the writable
        // set is FIELD_SANITIZERS, NOT the narrower EDITABLE_FIELDS: checking the
        // latter rejected `features`, which this ability documents and which has
        // always been writable here.
        $editable = array_keys(ProductDataService::FIELD_SANITIZERS);
        $unknown = array_diff(array_keys($fields), $editable, array('extra'));
        if ($unknown)
        {
            throw new AbilityInputException(
                "Unknown field(s) for module '{$module_id}': " . implode(', ', $unknown) . '. '
                    . 'Editable fields here: ' . implode(', ', $editable) . '.'
            );
        }

        RevisionGuard::check($post_id, $module_id, (string) ($input['revision'] ?? ''));

        $result = ProductDataService::updateItem($post_id, $module_id, $unique_id, $fields);

        if (empty($result['updated']))
        {
            throw new AbilityInputException(
                "No product with unique_id '{$unique_id}' in module '{$module_id}' on post {$post_id}. "
                    . 'Call content-egg/get-post-products to list attached products.'
            );
        }

        $item = null;
        foreach ((array) $result['data'] as $row)
        {
            $row = (array) $row;
            if ((string) ($row['unique_id'] ?? '') === $unique_id)
            {
                $item = LeanProduct::map($row);
                break;
            }
        }

        return array(
            'post_id' => $post_id,
            'module_id' => $module_id,
            'item' => $item,
            'revision' => (string) ProductDataService::revision($post_id, $module_id),
        );
    }
}
