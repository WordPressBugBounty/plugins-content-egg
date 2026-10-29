<?php

namespace ContentEgg\application\abilities;

use ContentEgg\application\deals\DealCategoryHelper;
use ContentEgg\application\deals\DealKey;
use ContentEgg\application\deals\DealPin;
use ContentEgg\application\deals\DealPostType;
use ContentEgg\application\deals\DealService;
use ContentEgg\application\deals\DealsSettings;

defined('\ABSPATH') || exit;

/**
 * DealFields class file
 *
 * The `deal` object of create-post and the fields of update-deal: which offer
 * the deal follows, its end date, category, coupon, state and featured image.
 * Never a price - a deal's price always follows its pinned product.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class DealFields
{
    const FIELDS_CREATE = array('pin', 'ends', 'category', 'coupon', 'featured_image', 'allow_duplicate');
    const FIELDS_UPDATE = array('pin', 'ends', 'category', 'coupon', 'featured_image', 'state');
    const PRICE_KEYS = array('price', 'price_old', 'discount', 'base_price');
    const ENDS_OFFER = 'offer';
    const DEALS_OFF = 'Deals are turned off in Content Egg settings (Settings → Deals). Ask the user to turn them on: '
        . 'that adds the Deal post type and the deals archive to the site, so do not do it without their go-ahead.';

    /** @throws AbilityInputException when Deals are turned off */
    public static function requireDealsEnabled(): void
    {
        if (!DealsSettings::enabled())
            throw new AbilityInputException(self::DEALS_OFF);
    }

    public static function schema(bool $for_update): array
    {
        $props = array(
            'pin' => array(
                'type' => 'object',
                'description' => 'The attached product the deal follows (its price, stock and link). Default: the first product with a price.',
                'properties' => array(
                    'module_id' => array('type' => 'string'),
                    'unique_id' => array('type' => 'string'),
                ),
                'required' => array('module_id', 'unique_id'),
                'additionalProperties' => false,
            ),
            'ends' => array(
                'type' => $for_update ? array('string', 'null') : 'string',
                'description' => 'ISO 8601 end date (site time without an offset), or "offer" to follow the store\'s own deal end time when the module provides one'
                    . ($for_update ? '; null = no end date.' : ' (the default).'),
            ),
            'category' => array(
                'type' => array('string', 'integer'),
                'description' => 'Deal category: a path such as "Electronics > Tablets" (missing levels are created) or a term id.',
            ),
            'coupon' => array(
                'type' => 'object',
                'description' => 'The deal\'s own coupon code, shown next to its price' . ($for_update ? '; an empty code removes it.' : '.'),
                'properties' => array(
                    'code' => array('type' => 'string'),
                    'note' => array('type' => 'string'),
                ),
                'additionalProperties' => false,
            ),
            'featured_image' => array(
                'type' => 'string',
                'enum' => array('product'),
                'description' => '"product" imports the pinned product\'s image into the media library as the featured image. Optional: deal cards show the product image anyway.',
            ),
        );

        if ($for_update)
        {
            $props['state'] = array(
                'type' => 'string',
                'enum' => array('active', 'expired'),
                'description' => '"expired" closes the deal; "active" reopens it with today\'s price as the new base and drops a past end date.',
            );
        }
        else
        {
            $props['allow_duplicate'] = array(
                'type' => 'boolean',
                'default' => false,
                'description' => 'Create the deal even though an active deal for the same offer exists.',
            );
        }

        return array(
            'type' => 'object',
            'description' => 'Deal fields. A deal\'s price, old price and discount always follow its pinned product and are never set here.',
            'properties' => $props,
            'additionalProperties' => false,
        );
    }

    /**
     * Checks the fields without writing anything. Only the given keys are
     * returned: ends as a timestamp, 0 (no end) or "offer"; category as a path
     * (string[]) or a term id.
     *
     * @throws AbilityInputException
     */
    public static function prepare(array $deal, bool $for_update): array
    {
        $allowed = $for_update ? self::FIELDS_UPDATE : self::FIELDS_CREATE;

        foreach (array_keys($deal) as $key)
        {
            if (in_array($key, self::PRICE_KEYS, true))
                throw new AbilityInputException("'{$key}': a deal's price always follows its pinned product; change the product instead.");
            if (!in_array($key, $allowed, true))
                throw new AbilityInputException("Unknown deal field '{$key}'. Valid fields: " . implode(', ', $allowed) . '.');
        }

        $out = array();

        if (array_key_exists('pin', $deal))
        {
            $pin = (array) $deal['pin'];
            $module_id = trim((string) ($pin['module_id'] ?? ''));
            $unique_id = trim((string) ($pin['unique_id'] ?? ''));
            if ($module_id === '' || $unique_id === '')
                throw new AbilityInputException("'pin' needs both module_id and unique_id.");
            $out['pin'] = array('module_id' => $module_id, 'unique_id' => $unique_id);
        }

        if (array_key_exists('ends', $deal))
        {
            $ends = $deal['ends'];
            if ($ends === null)
            {
                if (!$for_update)
                    throw new AbilityInputException("'ends' can be null only in update-deal; leave it out for no end date.");
                $out['ends'] = 0;
            }
            elseif ((string) $ends === self::ENDS_OFFER)
                $out['ends'] = self::ENDS_OFFER;
            else
                $out['ends'] = FindDealsAbility::parseTime((string) $ends);
        }

        if (array_key_exists('category', $deal))
        {
            $category = $deal['category'];
            if (is_int($category) || (is_string($category) && ctype_digit($category)))
                $out['category'] = (int) $category;
            else
            {
                $path = array_values(array_filter(array_map('trim', explode('>', (string) $category)), 'strlen'));
                if (!$path)
                    throw new AbilityInputException("'category' is empty.");
                $out['category'] = $path;
            }
        }

        if (array_key_exists('coupon', $deal))
        {
            $coupon = (array) $deal['coupon'];
            $out['coupon'] = array('code' => (string) ($coupon['code'] ?? ''), 'note' => (string) ($coupon['note'] ?? ''));
        }

        if (array_key_exists('featured_image', $deal))
        {
            if ($deal['featured_image'] !== 'product')
                throw new AbilityInputException("'featured_image' must be \"product\".");
            $out['featured_image'] = 'product';
        }

        if (array_key_exists('state', $deal))
        {
            if (!in_array($deal['state'], array('active', 'expired'), true))
                throw new AbilityInputException("'state' must be \"active\" or \"expired\".");
            $out['state'] = $deal['state'];
        }

        if (array_key_exists('allow_duplicate', $deal))
            $out['allow_duplicate'] = (bool) $deal['allow_duplicate'];

        return $out;
    }

    /**
     * The item a new deal will be pinned to, from the create-post products
     * payload: deal.pin, else the first priced item. Null when nothing is priced.
     *
     * @return array|null {module_id, unique_id, item}
     * @throws AbilityInputException when deal.pin names an item the payload lacks
     */
    public static function pinFor(array $prepared, array $products): ?array
    {
        $products = array_map(function ($items) { return array_values(array_filter((array) $items, 'is_array')); }, $products);

        if (isset($prepared['pin']))
        {
            $item = DealPin::find($products, $prepared['pin']);
            if (!$item)
            {
                throw new AbilityInputException(
                    'deal.pin ' . DealKey::key($prepared['pin']['module_id'], $prepared['pin']['unique_id'])
                        . ' is not in the products payload. Payload items: ' . implode(', ', self::itemKeys($products)) . '.'
                );
            }

            return $prepared['pin'] + array('item' => $item);
        }

        $pin = DealPin::choose($products, '');

        return $pin ? $pin + array('item' => DealPin::find($products, $pin)) : null;
    }

    /**
     * Writes the prepared fields to an existing deal, in the order pin, state,
     * ends, category, coupon, featured image.
     *
     * @return array {changed: string[], warnings: string[]}
     * @throws AbilityInputException
     */
    public static function apply(int $post_id, array $prepared): array
    {
        $changed = array();
        $warnings = array();

        if (isset($prepared['pin']))
        {
            if (!DealService::pin($post_id, $prepared['pin']['module_id'], $prepared['pin']['unique_id']))
            {
                throw new AbilityInputException(
                    'That product is not attached to this deal. Attached: '
                        . implode(', ', self::itemKeys(DealService::itemsByModule($post_id))) . '.'
                );
            }
            $changed[] = 'pin';
        }

        // State before ends: reopening switches the end date to manual, and an
        // ends: "offer" in the same call must win.
        if (isset($prepared['state']))
        {
            if ($prepared['state'] === 'expired')
                DealService::markExpired($post_id);
            else
                DealService::markActive($post_id);
            $changed[] = 'state';
        }

        if (array_key_exists('ends', $prepared))
        {
            if ($prepared['ends'] === self::ENDS_OFFER)
                DealService::followStoreEnds($post_id);
            else
                DealService::setEnds($post_id, (int) $prepared['ends']);
            $changed[] = 'ends';
        }

        if (isset($prepared['category']))
        {
            if (is_int($prepared['category']))
            {
                $term = \get_term($prepared['category'], DealPostType::TAX_CATEGORY);
                if (!$term || \is_wp_error($term))
                    throw new AbilityInputException("Deal category {$prepared['category']} does not exist.");
                $term_id = (int) $term->term_id;
            }
            else
                $term_id = self::categoryFromPath($prepared['category']);

            \wp_set_object_terms($post_id, array($term_id), DealPostType::TAX_CATEGORY, false);
            $changed[] = 'category';
        }

        if (isset($prepared['coupon']))
        {
            DealService::setCoupon($post_id, $prepared['coupon']['code'], $prepared['coupon']['note']);
            $changed[] = 'coupon';
        }

        if (isset($prepared['featured_image']))
        {
            // A failed download must not cost the agent the deal itself.
            $item = DealService::pinnedItem($post_id);
            $url = $item ? (string) ($item['img'] ?? '') : '';
            try
            {
                // The same gate as set-featured-image: importing a remote file is an upload.
                if (!\current_user_can('upload_files'))
                    throw new AbilityInputException('importing an image needs the upload_files capability.');
                if ($url === '')
                    throw new AbilityInputException('the pinned product has no image.');
                \set_post_thumbnail($post_id, RemoteImageImporter::import($url, $post_id));
                $changed[] = 'featured_image';
            }
            catch (AbilityInputException $e)
            {
                $warnings[] = 'Featured image not set: ' . $e->getMessage();
            }
        }

        return array('changed' => $changed, 'warnings' => $warnings);
    }

    /**
     * The deal category at the end of a path. Missing levels are created only
     * for users who may manage deal categories; anyone who may edit the deal
     * can assign existing ones.
     *
     * @throws AbilityInputException
     */
    private static function categoryFromPath(array $path): int
    {
        $parent = 0;
        foreach ($path as $name)
        {
            $existing = \term_exists($name, DealPostType::TAX_CATEGORY, $parent);
            if (!$existing)
            {
                $parent = 0;
                break;
            }
            $parent = (int) (is_array($existing) ? $existing['term_id'] : $existing);
        }
        if ($parent)
            return $parent;

        $label = implode(' > ', $path);
        $taxonomy = \get_taxonomy(DealPostType::TAX_CATEGORY);
        $cap = $taxonomy ? (string) $taxonomy->cap->manage_terms : 'manage_categories';
        if (!\current_user_can($cap))
        {
            throw new AbilityInputException(
                "Deal category \"{$label}\" does not exist, and creating categories needs the {$cap} capability. "
                    . 'Use an existing category or ask an editor to create it.'
            );
        }

        $term_id = DealCategoryHelper::createNestedCategories($path);
        if (!$term_id)
            throw new AbilityInputException("Deal category \"{$label}\" could not be created.");

        return $term_id;
    }

    /** "module_id|unique_id" of every item, for error messages. */
    private static function itemKeys(array $itemsByModule): array
    {
        $keys = array();
        foreach ($itemsByModule as $module_id => $items)
        {
            foreach ((array) $items as $key => $item)
            {
                $uid = is_array($item) && isset($item['unique_id']) ? (string) $item['unique_id'] : (string) $key;
                $keys[] = DealKey::key((string) $module_id, $uid);
            }
        }

        return $keys;
    }
}
