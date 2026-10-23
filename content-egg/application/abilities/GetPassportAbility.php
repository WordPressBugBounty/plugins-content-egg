<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\matching\DiscoveryLog;
use ContentEgg\application\components\matching\OfferDecisions;
use ContentEgg\application\components\matching\ProductPassport;
use ContentEgg\application\helpers\WooHelper;

/**
 * GetPassportAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Read a page's product passport: the product the page compares, with the
 * decisions taken on its offers and the log of the last searches. This is the
 * record a second chat session reads to carry on the work. Thin: the rules and
 * the storage are in ProductPassport, OfferDecisions and DiscoveryLog.
 */
final class GetPassportAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/get-passport';
    }

    public function label(): string
    {
        return __('Get Product Passport', 'content-egg');
    }

    public function description(): string
    {
        return 'Reads the product passport of a post: the one product (and variant) the page compares, '
            . 'with each GTIN and the source it was read from, brand, MPN, model, the variant values '
            . '("any" for a dimension that does not matter), the offer conditions the page accepts and a '
            . 'reference price. Also returns the decisions taken on the page\'s offers (how many were '
            . 'accepted, and the rejected ones with the reasons: automated saves never bring a rejected offer back, only an add with a match or a person does), '
            . 'the last search per module (discovery; a keyword search never replaces a GTIN, URL or id search), and suspended_keywords: the product modules whose '
            . 'stored keyword is not re-searched because the page has a passport (coupon lists keep '
            . 'theirs). passport is null when the page has none. woo_gtin is the GTIN now in the WooCommerce product\'s GTIN field '
            . '(null for a post that is not a product, a WooCommerce without that field, or an empty field): a starting point when you '
            . 'build a missing passport, not a source, because the price sync may have copied it from an offer. '
            . 'family is the product family the page belongs to ({id, name}, or null; read it with get-family): a member\'s variant '
            . 'gives its values in the family. '
            . 'Read it first when you continue work on a page: it says what the page is '
            . 'about, so you do not have to work it out again. Set it with set-passport; its GTINs come '
            . 'from an offer, a cited page or the user, never from memory.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => 'object',
            'default' => array(),
            'properties' => array(
                'post_id' => array('type' => 'integer', 'minimum' => 1),
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
                'passport' => self::passportSchema(),
                'decisions' => array(
                    'type' => 'object',
                    'description' => 'What was decided about the page\'s offers. Decisions are kept when the passport is removed.',
                    'properties' => array(
                        'accepted' => array(
                            'type' => 'integer',
                            'description' => 'How many offers were accepted as the passport\'s product.',
                        ),
                        'rejected' => array(
                            'type' => 'array',
                            'description' => 'Offers that are not the passport\'s product. Automated saves never bring them back; an add with a match, or a person adding one in the editor, does (and clears the rejection).',
                            'items' => array(
                                'type' => 'object',
                                'properties' => array(
                                    'module_id' => array('type' => 'string'),
                                    'unique_id' => array('type' => 'string'),
                                    'title' => array('type' => 'string', 'description' => 'The offer\'s title when it was decided on.'),
                                    'method' => array('type' => 'string', 'description' => 'gtin, mpn, agent or user.'),
                                    'reasons' => array('type' => 'array', 'items' => array('type' => 'string')),
                                    'checked_at' => array('type' => 'integer', 'description' => 'Unix time of the decision.'),
                                ),
                            ),
                        ),
                    ),
                ),
                'discovery' => array(
                    'type' => 'object',
                    'description' => 'The last search logged per module id: {at, mode, query, results}.',
                    'additionalProperties' => array(
                        'type' => 'object',
                        'properties' => array(
                            'at' => array('type' => 'integer', 'description' => 'Unix time of the search.'),
                            'mode' => array('type' => 'string', 'description' => 'gtin, url, native_id or keyword.'),
                            'query' => array('type' => 'string'),
                            'results' => array('type' => 'integer'),
                        ),
                    ),
                ),
                'suspended_keywords' => self::suspendedKeywordsSchema(),
                'woo_gtin' => array(
                    'type' => array('string', 'null'),
                    'description' => 'The GTIN in the WooCommerce product\'s own GTIN field, as it is now. Null when the post is not a '
                        . 'WooCommerce product, this WooCommerce has no GTIN field (before 9.2) or the field is empty. Not a source '
                        . 'for the passport: confirm it with an attached offer that carries the same code, or with the user.',
                ),
                'family' => GetFamilyAbility::familyRefSchema(),
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

        $passport = ProductPassport::get($post_id);

        return array(
            'post_id' => $post_id,
            'passport' => self::presentPassport($passport),
            'decisions' => self::summarizeDecisions(OfferDecisions::all($post_id)),
            // A map: an empty one must read as {} to the agent.
            'discovery' => (object) DiscoveryLog::all($post_id),
            // The keywords are suspended by the passport; without one they are searched as usual.
            'suspended_keywords' => $passport !== null ? SetPassportAbility::suspendedKeywords($post_id) : array(),
            'woo_gtin' => self::currentWooGtin($post_id),
            'family' => GetFamilyAbility::familyRef($post_id),
        );
    }

    /**
     * The GTIN to report as woo_gtin: the field's value for a WooCommerce product on a
     * WooCommerce that has the GTIN field, else null. An empty (or blank) field is null
     * too, so the agent never has to tell "" from "none". Pure.
     *
     * @param bool  $is_product Whether the post is a product and WooCommerce is active.
     * @param bool  $supported  Whether this WooCommerce has the GTIN field (9.2+).
     * @param mixed $raw        WC_Product::get_global_unique_id().
     */
    public static function wooGtinValue(bool $is_product, bool $supported, $raw): ?string
    {
        if (!$is_product || !$supported || !is_scalar($raw) || is_bool($raw))
        {
            return null;
        }

        $gtin = trim((string) $raw);

        return $gtin !== '' ? $gtin : null;
    }

    /**
     * The WooCommerce product's GTIN field, read through the product object. Read-only.
     */
    private static function currentWooGtin(int $post_id): ?string
    {
        $is_product = WooHelper::isWooActive() && \get_post_type($post_id) === 'product' && function_exists('wc_get_product');
        $supported = $is_product && WooHelper::supportsGlobalUniqueId();
        if (!$supported)
        {
            return null;
        }

        $product = \wc_get_product($post_id);
        if (!$product || !method_exists($product, 'get_global_unique_id'))
        {
            return null;
        }

        return self::wooGtinValue(true, true, $product->get_global_unique_id());
    }

    /**
     * The decisions of a page as an agent needs them: how many offers were
     * accepted, and the rejected ones with the key split into module and unique
     * id. Entries that are not decisions are skipped; a thin entry gets empty
     * fields.
     *
     * @param array $decisions OfferDecisions::all(): "<module_id>:<unique_id>" => entry
     * @return array {accepted: int, rejected: array[]}
     */
    public static function summarizeDecisions(array $decisions): array
    {
        $accepted = 0;
        $rejected = array();

        foreach ($decisions as $key => $entry)
        {
            if (!is_array($entry) || !isset($entry['status']))
            {
                continue;
            }

            if ($entry['status'] === OfferDecisions::STATUS_ACCEPTED)
            {
                $accepted++;
                continue;
            }
            if ($entry['status'] !== OfferDecisions::STATUS_REJECTED)
            {
                continue;
            }

            list($module_id, $unique_id) = OfferDecisions::splitKey((string) $key);
            $rejected[] = array(
                'module_id' => $module_id,
                'unique_id' => $unique_id,
                'title' => (isset($entry['fp']) && is_array($entry['fp']) && isset($entry['fp']['title']) && is_scalar($entry['fp']['title']))
                    ? (string) $entry['fp']['title'] : '',
                'method' => (isset($entry['method']) && is_scalar($entry['method'])) ? (string) $entry['method'] : '',
                'reasons' => (isset($entry['reasons']) && is_array($entry['reasons']))
                    ? array_values(array_map('strval', array_filter($entry['reasons'], 'is_scalar'))) : array(),
                'checked_at' => isset($entry['checked_at']) ? (int) $entry['checked_at'] : 0,
            );
        }

        return array('accepted' => $accepted, 'rejected' => $rejected);
    }

    /**
     * The stored passport for the output: an empty variant is an object, so
     * it reads as {} and not as [].
     */
    public static function presentPassport(?array $passport): ?array
    {
        if ($passport === null)
        {
            return null;
        }

        if (isset($passport['variant']) && is_array($passport['variant']) && !$passport['variant'])
        {
            $passport['variant'] = new \stdClass();
        }

        return $passport;
    }

    /**
     * The passport as a JSON schema, shared by get-passport and set-passport.
     */
    public static function passportSchema(): array
    {
        return array(
            'type' => array('object', 'null'),
            'description' => 'The page\'s product passport, or null when it has none.',
            'properties' => array(
                'v' => array('type' => 'integer'),
                'gtins' => array(
                    'type' => 'array',
                    'description' => 'The product\'s GTINs, each with where it was read from. One is primary.',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'code' => array('type' => 'string'),
                            'source' => array('type' => 'string', 'description' => 'An http(s) URL, "offer:<module_id>:<unique_id>" or "user".'),
                            'primary' => array('type' => 'boolean'),
                        ),
                    ),
                ),
                'brand' => array('type' => 'string'),
                'mpn' => array('type' => 'string'),
                'model' => array('type' => 'string'),
                'variant' => array(
                    'type' => 'object',
                    'description' => '{dimension: value}; "any" means every value of that dimension is fine.',
                    'additionalProperties' => array('type' => 'string'),
                ),
                'conditions' => array('type' => 'array', 'items' => array('type' => 'string')),
                'reference' => array(
                    'type' => 'object',
                    'properties' => array(
                        'price' => array('type' => array('number', 'null')),
                        'currency' => array('type' => 'string'),
                        'img' => array('type' => 'string'),
                    ),
                ),
                'created_at' => array('type' => 'integer'),
                'created_by' => array('type' => 'integer'),
                'updated_at' => array('type' => 'integer'),
                'updated_by' => array('type' => 'integer'),
            ),
        );
    }

    /**
     * The suspended_keywords output, shared by get-passport and set-passport.
     */
    public static function suspendedKeywordsSchema(): array
    {
        return array(
            'type' => 'array',
            'description' => 'Product module ids that have a stored keyword. On a page with a passport the keyword is no longer '
                . 'searched again by the cron or by refresh-post-products: the page\'s offers change only by an explicit add or decision. '
                . 'Coupon and other non-product modules are not listed: their keyword keeps working. '
                . 'Empty when the page has no passport.',
            'items' => array('type' => 'string'),
        );
    }
}
