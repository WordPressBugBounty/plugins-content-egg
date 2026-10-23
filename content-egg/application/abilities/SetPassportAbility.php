<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ContentManager;
use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\components\WooProductFields;
use ContentEgg\application\components\families\FamilyStore;
use ContentEgg\application\components\families\VariantFamily;
use ContentEgg\application\components\matching\ProductPassport;
use ContentEgg\application\helpers\WooHelper;

/**
 * SetPassportAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Write a page's product passport: the product the page compares. A full
 * replacement of the stored passport, or clear: true to remove it. The rules
 * (what counts as an identity, which GTINs are refused, which sources are
 * accepted) are in ProductPassport::normalize(); this ability turns its errors
 * into a 400, stores the passport and reports what changed around it.
 *
 * On a WooCommerce product the primary GTIN also goes into the product's GTIN
 * field, through WooCommerce's own uniqueness-checked setter.
 */
final class SetPassportAbility extends AbilityBase
{
    // The input fields that make up the passport (the rest of the input is post_id and clear).
    const FIELDS = array('gtins', 'brand', 'mpn', 'model', 'variant', 'conditions', 'reference');

    public function name(): string
    {
        return 'content-egg/set-passport';
    }

    public function label(): string
    {
        return __('Set Product Passport', 'content-egg');
    }

    public function description(): string
    {
        return 'Sets the product passport of a post: the one product (and variant) the page compares. '
            . 'Give at least one identity: a GTIN, an MPN or a model name; brand, variant, accepted '
            . 'conditions and a reference price are optional. Every GTIN needs a source: the http(s) URL '
            . 'of the page it was read from, "offer:<module_id>:<unique_id>" for an offer from your '
            . 'searches (attached to the post or not yet), or "user" when the user gave it. A GTIN comes from an offer, a cited page or '
            . 'the user, never from memory: a code recalled by a model is how a page ends up comparing '
            . 'the wrong product. Restricted, coupon and placeholder codes are refused. This replaces '
            . 'the whole passport, so pass every field you want to keep (read it first with '
            . 'get-passport); clear: true removes the passport instead, and the decisions taken on the '
            . 'offers stay. A page with a passport is curated: its keyword is no longer re-searched '
            . '(suspended_keywords lists those product modules), automated saves never bring a rejected offer back, and '
            . 'offers join only by an explicit add. Coupon lists keep their keyword and still refresh. On a WooCommerce product the primary GTIN is also '
            . 'written to the product\'s GTIN field (woo_gtin says what happened; "duplicate" means '
            . 'another product already holds that code). On a page in a product family (family in the output; see get-family) the '
            . 'new passport must still fit the family: a value for each of its dimensions, and not the variant of another page '
            . 'of the family; clear: true also takes the page out of its family (left_family says which). Returns the stored passport.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => 'object',
            'default' => array(),
            'properties' => array(
                'post_id' => array('type' => 'integer', 'minimum' => 1),
                'gtins' => array(
                    'type' => 'array',
                    'maxItems' => ProductPassport::MAX_GTINS,
                    'description' => 'The product\'s GTINs / EANs / UPCs (8, 12, 13 or 14 digits, valid check digit), each with the '
                        . 'source it was read from. Never write a code from memory. One is primary: flag it, or the first is.',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'code' => array('type' => 'string', 'description' => 'The GTIN digits.'),
                            'source' => array(
                                'type' => 'string',
                                'description' => 'Where the code was read from: the http(s) URL of the page (manufacturer or retailer), '
                                    . '"offer:<module_id>:<unique_id>" for an offer from your searches (attached to this post or not yet), or "user" when the user gave it.',
                            ),
                            'primary' => array('type' => 'boolean', 'description' => 'The code that identifies the product best. At most one.'),
                        ),
                        'required' => array('code', 'source'),
                        'additionalProperties' => false,
                    ),
                ),
                'brand' => array('type' => 'string', 'maxLength' => ProductPassport::MAX_TEXT),
                'mpn' => array(
                    'type' => 'string',
                    'maxLength' => ProductPassport::MAX_TEXT,
                    'description' => 'Manufacturer part number, as the manufacturer prints it.',
                ),
                'model' => array(
                    'type' => 'string',
                    'maxLength' => ProductPassport::MAX_TEXT,
                    'description' => 'Model name, such as "WH-1000XM5".',
                ),
                'variant' => array(
                    'type' => 'object',
                    'maxProperties' => ProductPassport::MAX_VARIANT_DIMS,
                    'description' => 'The variant the page compares, as {dimension: value}, such as {"Color": "Black", "Storage": "256GB"}. '
                        . 'Use "any" for a dimension that does not matter: {"Color": "any"}.',
                    'additionalProperties' => array('type' => 'string'),
                ),
                'conditions' => array(
                    'type' => 'array',
                    'minItems' => 1,
                    'description' => 'The offer conditions the page accepts. Defaults to ["new"]; an offer whose condition is unknown counts as new.',
                    'items' => array('type' => 'string', 'enum' => ProductPassport::CONDITIONS),
                ),
                'reference' => array(
                    'type' => 'object',
                    'description' => 'A reference price and image of the product, used to spot offers priced far off the market.',
                    'properties' => array(
                        'price' => array('type' => array('number', 'null'), 'minimum' => 0),
                        'currency' => array('type' => 'string', 'description' => 'Three-letter currency code, such as EUR.'),
                        'img' => array('type' => 'string', 'description' => 'http(s) URL of a product image.'),
                    ),
                    'additionalProperties' => false,
                ),
                'clear' => array(
                    'type' => 'boolean',
                    'description' => 'true removes the passport and ends the curation. Pass it alone, without the passport fields. '
                        . 'The decisions and the discovery log stay.',
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
                'passport' => GetPassportAbility::passportSchema(),
                'created' => array(
                    'type' => 'boolean',
                    'description' => 'True when the page had no passport before this call.',
                ),
                'suspended_keywords' => GetPassportAbility::suspendedKeywordsSchema(),
                'woo_gtin' => array(
                    'type' => array('object', 'null'),
                    'description' => 'What happened to the WooCommerce GTIN field. Null unless the post is a WooCommerce product and the passport has a GTIN.',
                    'properties' => array(
                        'status' => array(
                            'type' => 'string',
                            'enum' => array(
                                WooProductFields::GTIN_SET,
                                WooProductFields::GTIN_UNCHANGED,
                                WooProductFields::GTIN_DUPLICATE,
                                WooProductFields::GTIN_UNSUPPORTED,
                            ),
                            'description' => 'set: written. unchanged: the product already had it. duplicate: another product holds the code, so the field was left as it was. unsupported: this WooCommerce has no GTIN field.',
                        ),
                        'gtin' => array('type' => 'string', 'description' => 'The primary GTIN.'),
                    ),
                ),
                'family' => GetFamilyAbility::familyRefSchema(),
                'left_family' => array(
                    'type' => array('integer', 'null'),
                    'description' => 'After clear: true on a page that was in a product family, the id of that family, which the page has left. Null otherwise.',
                ),
            ),
        );
    }

    public function annotations(): array
    {
        // The same input stores the same passport, and a replaced passport is one call away from the old
        // one. The nested input is too deep for GET query params: readonly=false selects POST.
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

        // Checked before anything is written.
        $normalized = self::prepare($input);

        $family_id = FamilyStore::familyIdOf($post_id);

        if ($normalized === null)
        {
            ProductPassport::delete($post_id);
            // A family member is defined by its passport: without one it leaves the family (Decision 2).
            if ($family_id > 0)
            {
                FamilyStore::leave($post_id);
            }

            return array(
                'post_id' => $post_id,
                'passport' => null,
                'created' => false,
                'suspended_keywords' => array(),
                'woo_gtin' => null,
                'family' => null,
                'left_family' => $family_id > 0 ? $family_id : null,
            );
        }

        if ($family_id > 0)
        {
            $family = FamilyStore::get($family_id);
            if ($family !== null)
            {
                self::checkFamilyFit($family, FamilyStore::members($family_id, false), $post_id, $normalized['variant']);
            }
        }

        $created = !ProductPassport::has($post_id);
        $passport = ProductPassport::save($post_id, $normalized, (int) \get_current_user_id(), time());

        return array(
            'post_id' => $post_id,
            'passport' => GetPassportAbility::presentPassport($passport),
            'created' => $created,
            'suspended_keywords' => self::suspendedKeywords($post_id),
            'woo_gtin' => self::syncWooGtin($post_id, $passport),
            'family' => GetFamilyAbility::familyRef($post_id),
            'left_family' => null,
        );
    }

    /**
     * A family member's new variant must fit its family and must not be the
     * variant of another page of the family in any status except trash
     * (drafts count: they collide once published, R7). Pure.
     *
     * @param array $family  FamilyStore::get()
     * @param array $members FamilyStore::members(): [{post_id, status, variant}]
     * @throws AbilityInputException naming the family and what to do.
     */
    public static function checkFamilyFit(array $family, array $members, int $post_id, array $variant): void
    {
        $label = sprintf('family %d "%s"', (int) $family['id'], (string) $family['name']);

        try
        {
            $twin = VariantFamily::duplicateOf($family, $members, $post_id, $variant);
        }
        catch (\InvalidArgumentException $e)
        {
            throw new AbilityInputException(sprintf(
                'This page is in the product %s, and the new passport does not fit it: %s Give the variant a value for each of the family\'s dimensions (get-family lists them), or take the page out of the family first with set-family (remove). Nothing was changed.',
                $label,
                $e->getMessage()
            ));
        }

        if ($twin > 0)
        {
            // The twin may be a draft the user cannot see: name it only when they can edit it.
            $which = \current_user_can('edit_post', $twin) ? sprintf('page %d', $twin) : 'another page';
            throw new AbilityInputException(sprintf(
                'This page is in the product %s, and %s of that family already holds this variant. Give the page its own variant values, or take one of the two pages out of the family with set-family (remove). Nothing was changed.',
                $label,
                $which
            ));
        }
    }

    /**
     * The normalized passport of an input, or null for clear: true. Nothing is written.
     *
     * @throws AbilityInputException when the input is neither a passport nor a clear, or a field is wrong.
     */
    public static function prepare(array $input): ?array
    {
        $given = array();
        foreach (self::FIELDS as $field)
        {
            if (isset($input[$field]))
            {
                $given[$field] = $input[$field];
            }
        }

        // A REST client may send the flag as the string "false".
        $clear = isset($input['clear']) && filter_var($input['clear'], FILTER_VALIDATE_BOOLEAN);

        if ($clear)
        {
            if ($given)
            {
                throw new AbilityInputException('clear: true removes the passport, so it cannot come with passport fields (' . implode(', ', array_keys($given)) . '). Pass clear alone, or leave it out to replace the passport.');
            }

            return null;
        }

        if (!$given)
        {
            throw new AbilityInputException('Provide the passport (' . implode(', ', self::FIELDS) . ') or clear: true to remove it.');
        }

        try
        {
            return ProductPassport::normalize($given);
        }
        catch (\InvalidArgumentException $e)
        {
            throw new AbilityInputException($e->getMessage());
        }
    }

    /**
     * Module ids of the active product modules that have a stored keyword on
     * the post. A page with a passport does not search them again. Coupon and
     * other non-product modules are not listed: their keyword keeps working.
     * ContentManager::isKeywordUpdateSuspended() decides which modules count, so
     * the list is exactly what the keyword update skips.
     *
     * Call it for a page that has a passport; without one nothing is suspended.
     *
     * @return string[]
     */
    public static function suspendedKeywords(int $post_id): array
    {
        $module_ids = ModuleManager::getInstance()->getParserModulesIdList(true);

        return self::keywordedModules($module_ids, static function ($module_id) use ($post_id)
        {
            if (!ContentManager::isKeywordUpdateSuspended($post_id, $module_id))
            {
                return '';
            }

            return ContentManager::getAutoupdateKeyword($post_id, $module_id);
        });
    }

    /**
     * The modules for which $keyword_for gives a non-empty keyword, in order.
     *
     * @param array    $module_ids
     * @param callable $keyword_for module id => keyword
     * @return string[]
     */
    public static function keywordedModules(array $module_ids, callable $keyword_for): array
    {
        $modules = array();
        foreach ($module_ids as $module_id)
        {
            $keyword = $keyword_for((string) $module_id);
            if (is_string($keyword) && trim($keyword) !== '')
            {
                $modules[] = (string) $module_id;
            }
        }

        return $modules;
    }

    /**
     * The GTIN to write to the WooCommerce product: the passport's primary
     * one, only for a post of type "product" while WooCommerce is active.
     * '' when there is nothing to write.
     */
    public static function wooGtinTarget(array $passport, string $post_type, bool $woo_active): string
    {
        if (!$woo_active || $post_type !== 'product')
        {
            return '';
        }

        return ProductPassport::primaryGtin($passport);
    }

    /**
     * Put the passport's primary GTIN on the WooCommerce product, through
     * WooCommerce's own setter (which refuses a code another product holds).
     *
     * @return array|null {status, gtin}, or null when the post is not a product or the passport has no GTIN.
     */
    private static function syncWooGtin(int $post_id, array $passport): ?array
    {
        $post_type = \get_post_type($post_id);
        $gtin = self::wooGtinTarget($passport, is_string($post_type) ? $post_type : '', WooHelper::isWooActive());
        if ($gtin === '')
        {
            return null;
        }

        $product = \wc_get_product($post_id);
        if (!$product)
        {
            return null;
        }

        $status = WooProductFields::setGlobalUniqueId($product, $gtin);
        if ($status === WooProductFields::GTIN_SET)
        {
            $product->save();
        }

        return array('status' => $status, 'gtin' => $gtin);
    }
}
