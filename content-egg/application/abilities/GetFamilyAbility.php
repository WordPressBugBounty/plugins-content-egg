<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\families\FamilyStore;
use ContentEgg\application\components\families\PriceSummary;
use ContentEgg\application\components\families\VariantFamily;

/**
 * GetFamilyAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Read a product family (pages that are variants of one product): its
 * definition and its pages with their variant values and stored price
 * summary, or the list of families. Thin: storage is FamilyStore's, the
 * summary PriceSummary's.
 */
final class GetFamilyAbility extends AbilityBase
{
    const MAX_LIMIT = 50;
    const DEFAULT_LIMIT = 20;

    public function name(): string
    {
        return 'content-egg/get-family';
    }

    public function label(): string
    {
        return __('Get Product Family', 'content-egg');
    }

    public function description(): string
    {
        return 'Reads a product family: pages that are variants of one product (iPhone 15 128 GB, 256 GB, 512 GB), linked so '
            . 'each page shows a variant switcher to its siblings. Pass family_id, or post_id for the family of that page '
            . '(family is null when the page is in none, or when it is not published and you may not edit it). The family has its name, brand, dimensions ({name, schema}: the '
            . 'variant dimensions, with the schema.org property each maps to or null), order (the display order of values per '
            . 'dimension), primary (the page that represents the family in shop lists, 0 for the lowest published one) and '
            . 'members: every page in it, drafts included, with status, its passport variant and price_summary (the stored '
            . 'lowest price {price, currency, in_stock, offers, updated_at}, or null before it is computed). A member\'s values '
            . 'come from its product passport (get-passport / set-passport). Pages that are not published and that you may not '
            . 'edit are left out, and a family made only of such pages reads as not found. Without an id it lists the families by name '
            . '({id, name, members: count of the pages you may see}) with total and next_offset (limit 1-' . self::MAX_LIMIT . ', default '
            . self::DEFAULT_LIMIT . '; next_offset is null on the last page). Change a family with set-family.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => 'object',
            'default' => array(),
            'properties' => array(
                'family_id' => array('type' => 'integer', 'minimum' => 1, 'description' => 'The family to read. Not together with post_id.'),
                'post_id' => array('type' => 'integer', 'minimum' => 1, 'description' => 'Read the family this page belongs to. Not together with family_id.'),
                'limit' => array(
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => self::MAX_LIMIT,
                    'default' => self::DEFAULT_LIMIT,
                    'description' => 'Listing only: how many families in this call.',
                ),
                'offset' => array(
                    'type' => 'integer',
                    'minimum' => 0,
                    'default' => 0,
                    'description' => 'Listing only: how many families to skip, the next_offset of the previous call.',
                ),
            ),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'family' => self::familySchema(),
                'families' => array(
                    'type' => 'array',
                    'description' => 'Without an id: the families by name.',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'id' => array('type' => 'integer'),
                            'name' => array('type' => 'string'),
                            'members' => array('type' => 'integer', 'description' => 'How many pages it holds that you may see: published, or yours to edit (trash not counted).'),
                        ),
                    ),
                ),
                'total' => array('type' => 'integer', 'description' => 'Without an id: how many families the site has.'),
                'next_offset' => array(
                    'type' => array('integer', 'null'),
                    'description' => 'Without an id: the offset for the next call, null when there are no more families.',
                ),
            ),
        );
    }

    public function checkPermission($input = null): bool
    {
        return \current_user_can('edit_posts');
    }

    public function execute(array $input): array
    {
        $read = self::prepare($input);

        if ($read['post_id'] > 0)
        {
            $post = PostScope::requirePost(array('post_id' => $read['post_id']));
            // The family of a page that is not public is only told to someone who may edit it.
            if (!self::revealsFamily((string) $post->post_status, \current_user_can('edit_post', (int) $post->ID)))
            {
                return array('family' => null);
            }
            $family_id = FamilyStore::familyIdOf((int) $post->ID);

            return array('family' => $family_id > 0 ? self::load($family_id) : null);
        }

        if ($read['family_id'] > 0)
        {
            $family = self::load($read['family_id']);
            if ($family === null)
            {
                throw new AbilityInputException(sprintf('Family %d does not exist. Call get-family without an id to list the families.', $read['family_id']));
            }

            return array('family' => $family);
        }

        $list = FamilyStore::listFamilies($read['limit'], $read['offset'], array(__CLASS__, 'seesMember'));

        return array(
            'families' => $list['families'],
            'total' => (int) $list['total'],
            'next_offset' => self::nextOffset($read['offset'], $read['limit'], count($list['families']), (int) $list['total']),
        );
    }

    /**
     * The checked input: {family_id, post_id, limit, offset}, 0 for an id that is not given.
     *
     * @throws AbilityInputException
     */
    public static function prepare(array $input): array
    {
        $family_id = self::readId($input, 'family_id');
        $post_id = self::readId($input, 'post_id');
        if ($family_id > 0 && $post_id > 0)
        {
            throw new AbilityInputException('Pass family_id or post_id, not both.');
        }

        $limit = self::DEFAULT_LIMIT;
        if (isset($input['limit']))
        {
            $limit = self::readInt($input['limit'], 'limit');
            if ($limit < 1 || $limit > self::MAX_LIMIT)
            {
                throw new AbilityInputException(sprintf('limit must be 1 to %d.', self::MAX_LIMIT));
            }
        }

        $offset = 0;
        if (isset($input['offset']))
        {
            $offset = self::readInt($input['offset'], 'offset');
            if ($offset < 0)
            {
                throw new AbilityInputException('offset must be 0 or more.');
            }
        }

        return array('family_id' => $family_id, 'post_id' => $post_id, 'limit' => $limit, 'offset' => $offset);
    }

    /**
     * Whether get-family by post_id may say which family a page is in: a
     * published page, or one the user may edit. Pure.
     */
    public static function revealsFamily(string $status, bool $can_edit): bool
    {
        return $status === 'publish' || $can_edit;
    }

    /**
     * Whether the current user may see a family page: published, or one they may edit.
     */
    public static function seesMember(int $post_id, string $status): bool
    {
        return $status === 'publish' || self::revealsFamily($status, \current_user_can('edit_post', $post_id));
    }

    /**
     * The members the user may see ($sees: callable(int $post_id, string
     * $status): bool), or null when the family has pages and the user may see
     * none of them: such a family reads as not found. Pure given $sees.
     *
     * @param array $members [{post_id, status, ...}]
     */
    public static function visibleMembers(array $members, callable $sees): ?array
    {
        $visible = array();
        foreach ($members as $member)
        {
            if (is_array($member) && isset($member['post_id']) && $sees((int) $member['post_id'], isset($member['status']) ? (string) $member['status'] : ''))
            {
                $visible[] = $member;
            }
        }

        return ($members && !$visible) ? null : $visible;
    }

    /**
     * Where the next listing call starts, null after the last window. Pure.
     */
    public static function nextOffset(int $offset, int $limit, int $returned, int $total): ?int
    {
        $next = $offset + $limit;

        return ($returned > 0 && $next < $total) ? $next : null;
    }

    /**
     * The family as get-family and set-family report it: the definition, then
     * the members with their price summary. An empty variant or order is an
     * object, so it reads as {} and not as []. Pure.
     *
     * @param array $family    FamilyStore::get()
     * @param array $members   FamilyStore::members(): [{post_id, title, url, status, variant}]
     * @param array $summaries post_id => PriceSummary::get() (a missing id is null)
     */
    public static function present(array $family, array $members, array $summaries): array
    {
        $rows = array();
        foreach ($members as $member)
        {
            $post_id = (int) $member['post_id'];
            $variant = (isset($member['variant']) && is_array($member['variant'])) ? $member['variant'] : array();
            $rows[] = array(
                'post_id' => $post_id,
                'title' => (string) $member['title'],
                'url' => (string) $member['url'],
                'status' => (string) $member['status'],
                'variant' => $variant ? $variant : new \stdClass(),
                'price_summary' => (isset($summaries[$post_id]) && is_array($summaries[$post_id])) ? $summaries[$post_id] : null,
            );
        }

        $order = (isset($family['order']) && is_array($family['order'])) ? $family['order'] : array();

        return array(
            'id' => (int) $family['id'],
            'name' => (string) $family['name'],
            'brand' => isset($family['brand']) ? (string) $family['brand'] : '',
            'dimensions' => (isset($family['dimensions']) && is_array($family['dimensions'])) ? array_values($family['dimensions']) : array(),
            'order' => $order ? $order : new \stdClass(),
            'primary' => isset($family['primary']) ? (int) $family['primary'] : 0,
            'members' => $rows,
        );
    }

    /**
     * The family of an id as present() gives it, null when it does not exist
     * or when the user may see none of its pages. Every member is listed,
     * drafts included, except a page that is not published and that the user
     * may not edit.
     */
    public static function load(int $family_id): ?array
    {
        $family = FamilyStore::get($family_id);
        if ($family === null)
        {
            return null;
        }

        $members = self::visibleMembers(FamilyStore::members($family_id, false), array(__CLASS__, 'seesMember'));
        if ($members === null)
        {
            return null;
        }

        $summaries = array();
        foreach ($members as $member)
        {
            $summaries[(int) $member['post_id']] = PriceSummary::get((int) $member['post_id']);
        }

        return self::present($family, $members, $summaries);
    }

    /**
     * {id, name} of the family a page belongs to, or null: the `family` of
     * get-passport and set-passport.
     */
    public static function familyRef(int $post_id): ?array
    {
        $family_id = FamilyStore::familyIdOf($post_id);
        $family = $family_id > 0 ? FamilyStore::get($family_id) : null;

        return $family !== null ? array('id' => (int) $family['id'], 'name' => (string) $family['name']) : null;
    }

    /**
     * The `family` of get-passport and set-passport.
     */
    public static function familyRefSchema(): array
    {
        return array(
            'type' => array('object', 'null'),
            'description' => 'The product family the page belongs to (read it with get-family), or null when it is in none.',
            'properties' => array(
                'id' => array('type' => 'integer'),
                'name' => array('type' => 'string'),
            ),
        );
    }

    /**
     * The family as a JSON schema, shared by get-family and set-family.
     */
    public static function familySchema(): array
    {
        return array(
            'type' => array('object', 'null'),
            'description' => 'The family, or null (a page in no family; a deleted family).',
            'properties' => array(
                'id' => array('type' => 'integer'),
                'name' => array('type' => 'string'),
                'brand' => array('type' => 'string'),
                'dimensions' => array(
                    'type' => 'array',
                    'description' => 'The variant dimensions, in display order.',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'name' => array('type' => 'string'),
                            'schema' => array(
                                'type' => array('string', 'null'),
                                'description' => 'The schema.org property the dimension maps to (' . implode(', ', VariantFamily::SCHEMA_PROPERTIES) . '), or null.',
                            ),
                        ),
                    ),
                ),
                'order' => array(
                    'type' => 'object',
                    'description' => '{dimension: [values]}: the display order of the values; values that are not listed follow.',
                    'additionalProperties' => array('type' => 'array', 'items' => array('type' => 'string')),
                ),
                'primary' => array('type' => 'integer', 'description' => 'The page that represents the family in shop lists; 0 means the lowest published post id.'),
                'members' => array(
                    'type' => 'array',
                    'description' => 'The family\'s pages by post id, drafts included.',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'post_id' => array('type' => 'integer'),
                            'title' => array('type' => 'string'),
                            'url' => array('type' => 'string'),
                            'status' => array('type' => 'string'),
                            'variant' => array(
                                'type' => 'object',
                                'description' => 'The page\'s passport variant, {dimension: value}.',
                                'additionalProperties' => array('type' => 'string'),
                            ),
                            'price_summary' => array(
                                'type' => array('object', 'null'),
                                'description' => 'The stored lowest price of the page, or null before it is computed.',
                                'properties' => array(
                                    'price' => array('type' => array('number', 'null'), 'description' => 'Null when no offer has a price.'),
                                    'currency' => array('type' => 'string'),
                                    'in_stock' => array('type' => 'boolean'),
                                    'offers' => array('type' => 'integer', 'description' => 'Shops with a product offer that is not out of stock (two offers of one shop count once).'),
                                    'updated_at' => array('type' => 'integer'),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        );
    }

    private static function readId(array $input, string $key): int
    {
        if (!isset($input[$key]))
        {
            return 0;
        }

        $id = self::readInt($input[$key], $key);
        if ($id < 1)
        {
            throw new AbilityInputException(sprintf('%s must be a positive integer.', $key));
        }

        return $id;
    }

    /**
     * @param mixed $value
     */
    private static function readInt($value, string $key): int
    {
        if (is_int($value))
        {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', trim($value)))
        {
            return (int) trim($value);
        }

        throw new AbilityInputException(sprintf('%s must be an integer.', $key));
    }
}
