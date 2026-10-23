<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\components\WooProductFields;
use ContentEgg\application\components\matching\ProductPassport;
use ContentEgg\application\helpers\WooHelper;

/**
 * UpdateWooProductAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Set the WooCommerce fields an agent may own on an existing product: the type
 * (external or simple), the product categories, the GTIN and the catalog
 * visibility. The rules live in WooProductFields; this ability checks the post,
 * turns the input into those rules' arguments and reports the product back.
 *
 * The same four fields are the `woo` block of create-post, so the schema, the
 * category lookup and the input checks are shared through the static helpers
 * below.
 */
final class UpdateWooProductAbility extends AbilityBase
{
    // The fields WooProductFields::validate() understands.
    const FIELDS = array('type', 'categories', 'gtin', 'catalog_visibility');

    const PASSPORT_GTIN_MESSAGE = 'This page has a product passport: its primary GTIN sets the WooCommerce GTIN. Change it with set-passport.';

    const SYNC_GTIN_WARNING = 'Sync GTIN is enabled: the next price sync replaces this GTIN with the synced offer\'s — set a product passport to keep it, or turn the setting off.';

    public function name(): string
    {
        return 'content-egg/update-woo-product';
    }

    public function label(): string
    {
        return __('Update WooCommerce Product', 'content-egg');
    }

    public function description(): string
    {
        return 'Sets WooCommerce fields on an existing product (a post of type "product"): type '
            . '(external or simple), categories (existing product category ids or names; they '
            . 'replace the current ones), gtin and catalog_visibility (visible, catalog, search '
            . 'or hidden). Pass post_id and at least one field; fields you leave out stay as '
            . 'they are. Never sets the price, stock or SKU: Content Egg syncs the price from '
            . 'the attached offers. An unknown category is rejected with the existing categories '
            . 'listed. A GTIN that another product already holds is not written and comes back '
            . 'as a warning. A product with a product passport refuses gtin: the passport\'s '
            . 'primary GTIN sets it, so change it with set-passport. The type of a variable or '
            . 'grouped product is left alone (a switch would drop its variations). Returns what was applied, '
            . 'the warnings, and the product\'s fields as they are now.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => 'object',
            'default' => array(),
            'properties' => array_merge(
                array('post_id' => array('type' => 'integer', 'minimum' => 1)),
                self::fieldProperties()
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
                'applied' => array(
                    'type' => 'array',
                    'items' => array('type' => 'string'),
                    'description' => 'The fields that were written.',
                ),
                'warnings' => array(
                    'type' => 'array',
                    'items' => array('type' => 'string'),
                    'description' => 'Fields that were skipped and why, such as a GTIN another product already holds, or a GTIN the next price sync will replace (Sync GTIN on, no product passport).',
                ),
                'product' => array(
                    'type' => 'object',
                    'description' => 'The product\'s fields as they are now.',
                    'properties' => array(
                        'type' => array('type' => 'string'),
                        'categories' => array(
                            'type' => 'array',
                            'items' => array(
                                'type' => 'object',
                                'properties' => array(
                                    'id' => array('type' => 'integer'),
                                    'name' => array('type' => 'string'),
                                ),
                            ),
                        ),
                        'gtin' => array('type' => 'string'),
                        'catalog_visibility' => array('type' => 'string'),
                    ),
                ),
            ),
        );
    }

    public function annotations(): array
    {
        // Setting the same fields again changes nothing more, and nothing is lost
        // that a second call cannot put back.
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

        $woo = self::pickFields($input);
        self::requireProduct($post_id);

        // A passport page takes its GTIN from the passport; a hand-set one would be overwritten or fight it.
        if (isset($woo['gtin']) && ProductPassport::has($post_id))
        {
            throw new AbilityInputException(self::PASSPORT_GTIN_MESSAGE);
        }

        $prepared = self::prepare($woo);
        if (isset($woo['type']))
        {
            self::requireChangeableType((string) \WC_Product_Factory::get_product_type($post_id));
        }

        try
        {
            $result = WooProductFields::apply($post_id, $prepared['fields'], $prepared['category_ids']);
        }
        catch (\InvalidArgumentException $e)
        {
            throw new AbilityInputException($e->getMessage());
        }

        $warning = self::syncGtinWarning($result['applied'], self::syncGtinEnabled(), ProductPassport::has($post_id));
        if ($warning !== null)
        {
            $result['warnings'][] = $warning;
        }

        $product = \wc_get_product($post_id);

        return array(
            'post_id' => $post_id,
            'applied' => $result['applied'],
            'warnings' => $result['warnings'],
            'product' => $product ? WooProductFields::snapshot($product) : null,
        );
    }

    /**
     * The warning for a GTIN that was written but will not last: with Sync GTIN on, every price
     * sync copies the synced offer's GTIN into the field (or clears it), except on a page with a
     * product passport (WooIntegrator::syncGtin()). Null when there is nothing to warn about. Pure.
     *
     * @param string[] $applied WooProductFields::apply()['applied']
     */
    public static function syncGtinWarning(array $applied, bool $sync_gtin_enabled, bool $has_passport): ?string
    {
        if (!in_array('gtin', $applied, true) || !$sync_gtin_enabled || $has_passport)
        {
            return null;
        }

        return self::SYNC_GTIN_WARNING;
    }

    /**
     * Whether the "Sync GTIN" setting is on (WooIntegrator::syncGtin() reads the same option).
     */
    public static function syncGtinEnabled(): bool
    {
        return GeneralConfig::getInstance()->option('sync_gtin') === 'enabled';
    }

    /**
     * The four product fields as a JSON schema, shared by this ability (as
     * top-level inputs) and the `woo` block of create-post.
     */
    public static function fieldProperties(): array
    {
        return array(
            'type' => array(
                'type' => 'string',
                'enum' => WooProductFields::TYPES,
                'description' => 'WooCommerce product type. "external" suits a price comparison page: '
                    . 'its Buy button follows the synced offer\'s link. "simple" is a plain product.',
            ),
            'categories' => array(
                'type' => 'array',
                'minItems' => 1,
                'maxItems' => WooProductFields::MAX_CATEGORIES,
                'items' => array('type' => array('integer', 'string')),
                'description' => 'Existing product categories, each a category id or an exact (case-insensitive) '
                    . 'name. They replace the product\'s current categories. Categories are not created.',
            ),
            'gtin' => array(
                'type' => 'string',
                'description' => 'The product\'s GTIN / EAN / UPC (8, 12, 13 or 14 digits, valid check digit), '
                    . 'taken from the manufacturer\'s or a retailer\'s page, never from memory. '
                    . 'Refused on a page with a product passport.',
            ),
            'catalog_visibility' => array(
                'type' => 'string',
                'enum' => WooProductFields::VISIBILITY,
                'description' => 'Where the product shows in the shop: visible (shop and search), catalog '
                    . '(shop pages only), search (search results only) or hidden.',
            ),
        );
    }

    /**
     * The given product fields (null counts as not given).
     *
     * @throws AbilityInputException when none was given.
     */
    public static function pickFields(array $input): array
    {
        $woo = array();
        foreach (self::FIELDS as $field)
        {
            if (isset($input[$field]))
            {
                $woo[$field] = $input[$field];
            }
        }

        if (!$woo)
        {
            throw new AbilityInputException('Provide at least one of: ' . implode(', ', self::FIELDS) . '.');
        }

        return $woo;
    }

    /**
     * Check the fields and resolve the categories against this site's product
     * categories. Nothing is written.
     *
     * @return array {fields: array (WooProductFields::validate()), category_ids: int[]}
     * @throws AbilityInputException naming the field that is wrong.
     */
    public static function prepare(array $woo): array
    {
        try
        {
            $fields = WooProductFields::validate($woo);
            $category_ids = isset($fields['categories'])
                ? WooProductFields::resolveCategoryIds($fields['categories'], self::loadCategoryTerms())
                : array();
        }
        catch (\InvalidArgumentException $e)
        {
            throw new AbilityInputException($e->getMessage());
        }

        return array('fields' => $fields, 'category_ids' => $category_ids);
    }

    /**
     * Every product category of the site, empty ones included, as [term_id => name].
     */
    public static function loadCategoryTerms(): array
    {
        $terms = \get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));

        return (is_array($terms) && !\is_wp_error($terms)) ? self::categoryTerms($terms) : array();
    }

    /**
     * Terms as [term_id => name] with the names as people write them.
     * WordPress keeps a term name HTML-encoded ("Tools &amp; Accessories"),
     * and an agent types "Tools & Accessories". One decode gives the name back
     * even when it really contains an entity (it is stored double-encoded).
     *
     * @param array $wp_terms WP_Term objects (term_id, name); anything else is skipped.
     */
    public static function categoryTerms(array $wp_terms): array
    {
        $terms = array();
        foreach ($wp_terms as $term)
        {
            if (!is_object($term) || !isset($term->term_id, $term->name) || (int) $term->term_id < 1)
            {
                continue;
            }
            $terms[(int) $term->term_id] = html_entity_decode((string) $term->name, ENT_QUOTES, 'UTF-8');
        }

        return $terms;
    }

    /**
     * A variable or grouped product (or one of another extension's types) keeps
     * data a type switch would drop: WooCommerce deletes a variable product's
     * variations when it becomes simple. This ability is not destructive, so it
     * only moves a product between the types it can set.
     *
     * @throws AbilityInputException
     */
    public static function requireChangeableType(string $current_type): void
    {
        if ($current_type !== '' && !in_array($current_type, WooProductFields::TYPES, true))
        {
            throw new AbilityInputException(sprintf(
                'This product is a %s product. Changing its type would drop its variations or linked products, so it is left to the product editor: leave type out, or change it there.',
                $current_type
            ));
        }
    }

    /**
     * The post must be a WooCommerce product, checked before WooProductFields
     * writes anything: its type term is written before the product is loaded.
     *
     * @throws AbilityInputException
     */
    public static function requireProduct(int $post_id): void
    {
        if (!WooHelper::isWooActive())
        {
            throw new AbilityInputException('WooCommerce is not active on this site, so there are no products to change.');
        }

        $post_type = \get_post_type($post_id);
        if ($post_type !== 'product')
        {
            throw new AbilityInputException(sprintf(
                'Post %d is not a WooCommerce product (its post type is "%s"). Products are posts of type "product"; create one with create-post and post_type "product".',
                $post_id,
                is_string($post_type) ? $post_type : 'unknown'
            ));
        }
    }
}
