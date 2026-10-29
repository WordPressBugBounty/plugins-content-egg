<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\families\FamilyStore;
use ContentEgg\application\components\families\PriceSummary;
use ContentEgg\application\components\families\VariantFamily;
use ContentEgg\application\components\matching\ProductPassport;

/**
 * SetFamilyAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Create, change or delete a product family, and add or remove its pages.
 *
 * The call is checked as a whole before anything is written: prepare() reads
 * the input alone, plan() checks it against what is stored (the family, its
 * members, each page to add or remove) and reports every problem at once.
 * Only then does execute() write, through FamilyStore. The rules of a family
 * (dimensions, member values, duplicates) are VariantFamily's.
 */
final class SetFamilyAbility extends AbilityBase
{
    // At most this many post ids in add, and in remove, per call.
    const MAX_IDS = 50;

    // The input fields that make up the family definition (besides the name).
    const DEFINITION_FIELDS = array('brand', 'dimensions', 'order', 'primary');

    // What plan() assumes about a page the call did not read.
    const CANDIDATE_DEFAULTS = array(
        'exists' => false,
        'can_edit' => false,
        'type_ok' => false,
        'post_type' => '',
        'status' => '',
        'passport' => false,
        'variant' => array(),
        'family_id' => 0,
        'family_name' => '',
    );

    public function name(): string
    {
        return 'content-egg/set-family';
    }

    public function label(): string
    {
        return __('Set Product Family', 'content-egg');
    }

    public function description(): string
    {
        return 'Creates, changes or deletes a product family: pages that are variants of one product (iPhone 15 128 GB, 256 GB, '
            . '512 GB), linked so each page shows a variant switcher to its siblings and shop lists show the family once. Without '
            . 'family_id it creates one: name and dimensions are required ([{name, schema}], 1-' . VariantFamily::MAX_DIMENSIONS
            . ' dimensions; schema is the schema.org property the dimension maps to: ' . implode(', ', VariantFamily::SCHEMA_PROPERTIES)
            . ', or null). With family_id it changes the fields you pass (name, brand, dimensions, order, primary) and keeps the rest. '
            . 'order is {dimension: [values]}, the display order of the values. primary is the page that represents the family in '
            . 'shop lists (a member, or 0 for the lowest published one). add and remove take at most ' . self::MAX_IDS . ' post ids '
            . 'each. A page joins with its product passport: the passport\'s variant must have a value for every dimension (or "any"; '
            . 'set it with set-passport first) and must not be the variant of another page of the family (drafts count, trash does not). '
            . 'Adding or removing a page needs edit rights on that page; changing name, brand, dimensions, order or primary needs '
            . 'edit rights on every page of the family. A page belongs to one family: '
            . 'a page in another family is refused, remove it there first. New dimensions must still fit every page that stays. '
            . 'delete: true, alone with family_id, deletes the family; its pages and their passports stay, removed lists them. '
            . 'Everything is checked before anything is written, and every problem of the call is reported at once. Returns the '
            . 'family as get-family does, with the post ids added and removed. Confirm a delete with the user first.';
    }

    public function inputSchema(): array
    {
        $ids = array(
            'type' => 'array',
            'maxItems' => self::MAX_IDS,
            'items' => array('type' => 'integer', 'minimum' => 1),
        );

        return array(
            'type' => 'object',
            'default' => array(),
            'properties' => array(
                'family_id' => array(
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'The family to change or delete. Leave it out to create a family.',
                ),
                'name' => array(
                    'type' => 'string',
                    'maxLength' => VariantFamily::MAX_NAME,
                    'description' => 'The family name, such as "iPhone 15". Required to create.',
                ),
                'brand' => array('type' => 'string', 'maxLength' => VariantFamily::MAX_BRAND),
                'dimensions' => array(
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => VariantFamily::MAX_DIMENSIONS,
                    'description' => 'The variant dimensions, in display order, such as [{"name": "Memory", "schema": null}, {"name": "Color", "schema": "color"}]. '
                        . 'Names are the keys of the members\' passport variants (compared without case). Required to create; '
                        . 'when you change them, every page that stays must still fit.',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'name' => array('type' => 'string', 'description' => '1 to 60 characters, unique without case.'),
                            'schema' => array(
                                'type' => array('string', 'null'),
                                'description' => 'The schema.org property: ' . implode(', ', VariantFamily::SCHEMA_PROPERTIES) . ', or null. A property can describe one dimension only.',
                            ),
                        ),
                        'required' => array('name'),
                        'additionalProperties' => false,
                    ),
                ),
                'order' => array(
                    'type' => 'object',
                    'description' => '{dimension: [values]}: the display order of the values, at most ' . VariantFamily::MAX_ORDER_VALUES
                        . ' per dimension, such as {"Memory": ["128 GB", "256 GB", "512 GB"]}. Replaces the stored order.',
                    'additionalProperties' => array('type' => 'array', 'items' => array('type' => 'string')),
                ),
                'primary' => array(
                    'type' => 'integer',
                    'minimum' => 0,
                    'description' => 'The page that represents the family in shop lists: a member after this call, or 0 for the lowest published one.',
                ),
                'add' => $ids + array('description' => 'Post ids to add. Each needs a product passport whose variant fits the family.'),
                'remove' => $ids + array('description' => 'Post ids to take out of the family. The pages and their passports stay.'),
                'delete' => array(
                    'type' => 'boolean',
                    'description' => 'true deletes the family. Pass it alone with family_id. The pages and their passports stay.',
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
                'family' => GetFamilyAbility::familySchema(),
                'added' => array(
                    'type' => 'array',
                    'description' => 'The post ids that joined (a page that was already a member is not listed).',
                    'items' => array('type' => 'integer'),
                ),
                'removed' => array(
                    'type' => 'array',
                    'description' => 'The post ids that left; after a delete, every page the family held.',
                    'items' => array('type' => 'integer'),
                ),
                'deleted' => array('type' => 'boolean', 'description' => 'True when the family was deleted (family is then null).'),
            ),
        );
    }

    public function annotations(): array
    {
        // It can delete a family (destructive). Not idempotent: core serves destructive + idempotent
        // as DELETE with the input in the query string, and this input is too nested for that.
        return array('readonly' => false, 'destructive' => true, 'idempotent' => false);
    }

    public function checkPermission($input = null): bool
    {
        // The pages added or removed are checked one by one in execute().
        return \current_user_can('edit_posts');
    }

    public function execute(array $input): array
    {
        $prepared = self::prepare($input);

        if ($prepared['delete'])
        {
            return self::deleteFamily($prepared['family_id']);
        }

        $family_id = $prepared['family_id'];
        $family = $family_id > 0 ? FamilyStore::get($family_id) : null;
        $members = array();
        if ($family !== null)
        {
            $stored = FamilyStore::members($family_id, false);
            if (GetFamilyAbility::visibleMembers($stored, array(GetFamilyAbility::class, 'seesMember')) === null)
            {
                // A family made only of pages the user may not see reads as not found, as in get-family.
                $family = null;
                $stored = array();
            }
            foreach ($stored as $member)
            {
                // Changing the family's name or definition changes every member's page: plan() needs to know which the user may edit.
                $member['can_edit'] = PostScope::canEditPost(array('post_id' => (int) $member['post_id']));
                $members[] = $member;
            }
        }
        $candidates = self::readCandidates(array_merge($prepared['add'], $prepared['remove']));

        // Checked as a whole before anything is written.
        $plan = self::plan($prepared, $family, $members, $candidates, \current_user_can('edit_others_posts'));

        // Every join and leave would rebuild the catalog cache: hold the rebuilds and run one at the end,
        // also when a write fails half-way.
        FamilyStore::suspendCatalog();
        try
        {
            try
            {
                if ($family_id === 0)
                {
                    $family_id = FamilyStore::create((string) $plan['name'], $plan['definition']);
                }
                elseif ($plan['name'] !== null || $plan['update_definition'])
                {
                    FamilyStore::update($family_id, $plan['name'], $plan['definition']);
                }
            }
            catch (\RuntimeException $e)
            {
                // WordPress refused the term, most often a name that is empty once HTML is stripped.
                throw new AbilityInputException($e->getMessage() . ' When WordPress refused the name, give the family a plain-text one. Nothing was changed.');
            }

            $created = $prepared['family_id'] === 0;
            $removed = array();
            $added = array();
            $current = 0;
            try
            {
                foreach ($plan['remove'] as $post_id)
                {
                    $current = $post_id;
                    FamilyStore::leave($post_id);
                    $removed[] = $post_id;
                }
                foreach ($plan['add'] as $post_id)
                {
                    $current = $post_id;
                    FamilyStore::join($family_id, $post_id);
                    $added[] = $post_id;
                }
                foreach ($plan['add'] as $post_id)
                {
                    $current = $post_id;
                    PriceSummary::refresh($post_id);
                }
            }
            catch (\RuntimeException $e)
            {
                throw new AbilityInputException(self::partialWriteMessage($family_id, $created, $removed, $added, $current, $e->getMessage()));
            }
            catch (\InvalidArgumentException $e)
            {
                throw new AbilityInputException(self::partialWriteMessage($family_id, $created, $removed, $added, $current, $e->getMessage()));
            }
        }
        finally
        {
            FamilyStore::resumeCatalog();
        }

        return array(
            'family' => GetFamilyAbility::load($family_id),
            'added' => $plan['add'],
            'removed' => $plan['remove'],
            'deleted' => false,
        );
    }

    /**
     * The input alone, checked: shape, mutually exclusive fields, limits. For a
     * new family the definition is checked too (an existing one is checked in
     * plan(), against what is stored). Nothing is read or written.
     *
     * @return array {family_id: int (0 creates), delete: bool, name: ?string, fields: array, add: int[], remove: int[]}
     * @throws AbilityInputException
     */
    public static function prepare(array $input): array
    {
        $family_id = 0;
        if (isset($input['family_id']))
        {
            $family_id = self::postId($input['family_id']);
            if ($family_id === 0)
            {
                throw new AbilityInputException('family_id must be a positive integer.');
            }
        }

        // A REST client may send the flag as the string "false".
        $delete = isset($input['delete']) && filter_var($input['delete'], FILTER_VALIDATE_BOOLEAN);

        if ($delete)
        {
            if ($family_id === 0)
            {
                throw new AbilityInputException('delete: true needs the family_id of the family to delete.');
            }

            $given = array();
            foreach (array_merge(array('name'), self::DEFINITION_FIELDS, array('add', 'remove')) as $field)
            {
                if (array_key_exists($field, $input) && $input[$field] !== null)
                {
                    $given[] = $field;
                }
            }
            if ($given)
            {
                throw new AbilityInputException('delete: true deletes the whole family, so it comes alone with family_id: leave out ' . implode(', ', $given) . '.');
            }

            return array('family_id' => $family_id, 'delete' => true, 'name' => null, 'fields' => array(), 'add' => array(), 'remove' => array());
        }

        $name = null;
        if (array_key_exists('name', $input) && $input['name'] !== null)
        {
            if (!is_string($input['name']))
            {
                throw new AbilityInputException('name must be text.');
            }
            $name = trim($input['name']);
            $length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
            if ($name === '' || $length > VariantFamily::MAX_NAME)
            {
                throw new AbilityInputException(sprintf('name must be 1 to %d characters.', VariantFamily::MAX_NAME));
            }
        }

        $fields = array();
        foreach (self::DEFINITION_FIELDS as $field)
        {
            if (array_key_exists($field, $input) && $input[$field] !== null)
            {
                $fields[$field] = $input[$field];
            }
        }

        $add = self::readIds($input, 'add');
        $remove = self::readIds($input, 'remove');

        $both = array_values(array_intersect($add, $remove));
        if ($both)
        {
            throw new AbilityInputException(sprintf('Post %s is in both add and remove: pick one.', implode(', ', $both)));
        }

        if ($family_id === 0)
        {
            if ($name === null)
            {
                throw new AbilityInputException('A new family needs a name (pass family_id to change an existing family).');
            }
            if (empty($fields['dimensions']))
            {
                throw new AbilityInputException('A new family needs dimensions: the variant dimensions as [{name, schema}], such as [{"name": "Memory", "schema": null}].');
            }
            if ($remove)
            {
                throw new AbilityInputException('A new family has no pages to remove: leave out remove.');
            }

            try
            {
                VariantFamily::normalize($fields);
            }
            catch (\InvalidArgumentException $e)
            {
                throw new AbilityInputException($e->getMessage());
            }
        }
        elseif ($name === null && !$fields && !$add && !$remove)
        {
            throw new AbilityInputException(sprintf(
                'Nothing to do: pass name, %s to change family %d, add or remove to change its pages, or delete: true to delete it.',
                implode(', ', self::DEFINITION_FIELDS),
                $family_id
            ));
        }

        return array('family_id' => $family_id, 'delete' => false, 'name' => $name, 'fields' => $fields, 'add' => $add, 'remove' => $remove);
    }

    /**
     * The whole call checked against what is stored, before anything is
     * written. Every problem is collected and reported in one 400. Pure.
     *
     * @param array      $prepared   prepare()
     * @param array|null $family     FamilyStore::get() of family_id; null to create, or when it is gone
     * @param array      $members    FamilyStore::members($id, false) with can_edit per member: [{post_id, status, variant, can_edit, ...}]
     * @param array      $candidates post_id => {exists, can_edit, type_ok, post_type, status, passport, variant, family_id, family_name}
     *                               for every post id in add and remove (see readCandidates())
     * @param bool       $edits_others whether the user may edit others' pages (edit_others_posts)
     * @return array {name: ?string, definition: array (normalized), update_definition: bool, add: int[], remove: int[]}
     * @throws AbilityInputException
     */
    public static function plan(array $prepared, ?array $family, array $members, array $candidates, bool $edits_others = false): array
    {
        $family_id = (int) $prepared['family_id'];
        $creating = $family_id === 0;
        $fields = $prepared['fields'];

        if (!$creating && $family === null)
        {
            throw new AbilityInputException(sprintf('Family %d does not exist. Call get-family without an id to list the families, or leave out family_id to create one.', $family_id));
        }

        // The definition after the call: a new one, or the stored one with the given fields replaced.
        if ($creating)
        {
            $merged = $fields;
        }
        else
        {
            $merged = array(
                'dimensions' => $family['dimensions'],
                'order' => $family['order'],
                'primary' => $family['primary'],
                'brand' => $family['brand'],
            );
            if (isset($fields['dimensions']) && !array_key_exists('order', $fields))
            {
                // The stored order of a dimension that is gone would not fit the new dimensions.
                $merged['order'] = self::orderFor((array) $family['order'], $fields['dimensions']);
            }
            $merged = array_merge($merged, $fields);
        }

        try
        {
            $definition = VariantFamily::normalize($merged);
        }
        catch (\InvalidArgumentException $e)
        {
            throw new AbilityInputException($e->getMessage() . ' Nothing was changed.');
        }

        $problems = array();
        $label = $creating ? 'the new family' : sprintf('family %d', $family_id);

        // A page the user may not edit is never named by its id: messages say "a page you cannot edit".
        $editable = array();
        foreach ($members as $member)
        {
            if (is_array($member) && isset($member['post_id']))
            {
                $editable[(int) $member['post_id']] = !empty($member['can_edit']);
            }
        }
        foreach ($prepared['add'] as $post_id)
        {
            $editable[(int) $post_id] = isset($candidates[$post_id]['can_edit']) && $candidates[$post_id]['can_edit'];
        }
        $page = function (int $post_id) use ($editable): string
        {
            return !empty($editable[$post_id]) ? sprintf('page %d', $post_id) : 'a page you cannot edit';
        };

        // A new name or definition changes every member's page (switcher, markup, catalog), so the
        // user must be allowed to edit each of them. A member without the flag is not assumed editable.
        if (!$creating && ($prepared['name'] !== null || $fields))
        {
            $locked = array();
            foreach ($members as $member)
            {
                if (is_array($member) && isset($member['post_id']) && empty($member['can_edit']))
                {
                    $locked[] = (int) $member['post_id'];
                }
            }
            if ($locked)
            {
                $problems[] = sprintf(
                    '%s holds %s, so you cannot change its name, brand, dimensions, order or primary (adding and removing the pages you can edit is fine).',
                    ucfirst($label),
                    self::cannotEdit(count($locked))
                );
            }

            // An empty family has no page to decide who may change it.
            if (!$members && !$edits_others)
            {
                $problems[] = sprintf(
                    '%s has no pages, so only a user who can edit others\' pages can change its name, brand, dimensions, order or primary (adding pages you can edit is fine).',
                    ucfirst($label)
                );
            }
        }

        $current = array();
        foreach ($members as $member)
        {
            if (is_array($member) && isset($member['post_id']))
            {
                $current[(int) $member['post_id']] = $member;
            }
        }

        $remove = array();
        foreach ($prepared['remove'] as $post_id)
        {
            $candidate = isset($candidates[$post_id]) ? $candidates[$post_id] + self::CANDIDATE_DEFAULTS : null;
            // Rights first: whether a page the user may not edit is in the family is not theirs to learn.
            if ($candidate === null || !$candidate['exists'])
            {
                $problems[] = sprintf('Post %d not found.', $post_id);
                continue;
            }
            if (!$candidate['can_edit'])
            {
                $problems[] = sprintf('You are not allowed to edit page %d.', $post_id);
                continue;
            }
            if (!isset($current[$post_id]))
            {
                $problems[] = sprintf('Page %d is not in %s.', $post_id, $label);
                continue;
            }
            $remove[] = $post_id;
        }

        $kept = $current;
        foreach ($prepared['remove'] as $post_id)
        {
            unset($kept[$post_id]);
        }

        $joining = array();
        foreach ($prepared['add'] as $post_id)
        {
            $candidate = isset($candidates[$post_id]) ? $candidates[$post_id] + self::CANDIDATE_DEFAULTS : null;
            if ($candidate === null || !$candidate['exists'])
            {
                $problems[] = sprintf('Post %d not found.', $post_id);
                continue;
            }
            if (!$candidate['can_edit'])
            {
                $problems[] = sprintf('You are not allowed to edit page %d.', $post_id);
                continue;
            }
            if (!$creating && (isset($current[$post_id]) || (int) $candidate['family_id'] === $family_id))
            {
                // Already a member: nothing to do.
                continue;
            }
            if ($candidate['status'] === 'trash')
            {
                $problems[] = sprintf('Page %d is in the trash: restore it first.', $post_id);
                continue;
            }
            if (!$candidate['type_ok'])
            {
                $problems[] = sprintf('Page %d is a %s, which cannot be in a product family (the post types Content Egg is enabled on can, and WooCommerce products).', $post_id, $candidate['post_type'] !== '' ? $candidate['post_type'] : 'post type');
                continue;
            }
            if ((int) $candidate['family_id'] > 0)
            {
                $problems[] = sprintf(
                    'Page %1$d is in family %2$d "%3$s": a page belongs to one family, so remove it there first (set-family with family_id %2$d and remove [%1$d]), then add it here.',
                    $post_id,
                    (int) $candidate['family_id'],
                    (string) $candidate['family_name']
                );
                continue;
            }
            if (!$candidate['passport'])
            {
                $problems[] = sprintf('Page %d has no product passport: set one with set-passport first; its variant gives the page\'s values in the family.', $post_id);
                continue;
            }

            $variant = is_array($candidate['variant']) ? $candidate['variant'] : array();
            try
            {
                VariantFamily::memberValues($definition, $variant);
            }
            catch (\InvalidArgumentException $e)
            {
                $problems[] = sprintf('Page %d: %s Fix its passport with set-passport.', $post_id, $e->getMessage());
                continue;
            }

            $joining[$post_id] = array('post_id' => $post_id, 'status' => (string) $candidate['status'], 'variant' => $variant);
        }

        if (count($kept) + count($joining) > FamilyStore::MAX_MEMBERS)
        {
            $problems[] = sprintf(
                'A family holds at most %d pages; %s would hold %d. Remove pages first.',
                FamilyStore::MAX_MEMBERS,
                $label,
                count($kept) + count($joining)
            );
        }

        $dimensions_change = !$creating && isset($fields['dimensions']);
        if ($dimensions_change)
        {
            $misfits = array();
            $locked_misfits = 0;
            foreach ($kept as $post_id => $member)
            {
                try
                {
                    VariantFamily::memberValues($definition, isset($member['variant']) && is_array($member['variant']) ? $member['variant'] : array());
                }
                catch (\InvalidArgumentException $e)
                {
                    if (!empty($editable[(int) $post_id]))
                    {
                        $misfits[] = $post_id;
                    }
                    else
                    {
                        $locked_misfits++;
                    }
                }
            }
            if ($misfits || $locked_misfits)
            {
                $which = array();
                if ($misfits)
                {
                    $which[] = 'pages ' . implode(', ', $misfits);
                }
                if ($locked_misfits)
                {
                    $which[] = self::cannotEdit($locked_misfits);
                }
                $problems[] = sprintf(
                    'The new dimensions do not fit the variant of %s: update their passports with set-passport first, or remove them from the family in this call.',
                    implode(' and of ', $which)
                );
            }
        }

        // No two pages of the family may be the same variant: the added ones, and every one when the dimensions change.
        $final = array_values($kept + $joining);
        $check = $dimensions_change ? array_merge(array_keys($kept), array_keys($joining)) : array_keys($joining);
        $reported = array();
        foreach ($check as $post_id)
        {
            $row = isset($joining[$post_id]) ? $joining[$post_id] : $kept[$post_id];
            try
            {
                $twin = VariantFamily::duplicateOf($definition, $final, (int) $post_id, isset($row['variant']) && is_array($row['variant']) ? $row['variant'] : array());
            }
            catch (\InvalidArgumentException $e)
            {
                // A misfit, reported above.
                continue;
            }
            if ($twin === 0)
            {
                continue;
            }

            $pair = min($twin, (int) $post_id) . '-' . max($twin, (int) $post_id);
            if (!isset($reported[$pair]))
            {
                $reported[$pair] = true;
                $problems[] = sprintf('%s has the same variant as %s: two pages of one family cannot be the same variant. Give one of them its own variant values with set-passport.', ucfirst($page((int) $post_id)), $page($twin));
            }
        }

        $final_ids = array_merge(array_keys($kept), array_keys($joining));
        $primary_reset = false;
        if (array_key_exists('primary', $fields))
        {
            if ($definition['primary'] > 0 && !in_array($definition['primary'], $final_ids, true))
            {
                $problems[] = sprintf('primary %d is not a page of %s after this call: pick one of its pages, or 0 for the lowest published one.', $definition['primary'], $label);
            }
        }
        elseif (!$creating && $definition['primary'] > 0 && in_array($definition['primary'], $remove, true))
        {
            // The primary leaves: the lowest published page represents the family from now on.
            $definition['primary'] = 0;
            $primary_reset = true;
        }

        if ($problems)
        {
            throw new AbilityInputException(implode(' ', $problems) . ' Nothing was changed.');
        }

        return array(
            'name' => $prepared['name'],
            'definition' => $definition,
            'update_definition' => !$creating && ($fields || $primary_reset),
            'add' => array_keys($joining),
            'remove' => $remove,
        );
    }

    /**
     * "a page you cannot edit", or "N pages you cannot edit". Pure.
     */
    public static function cannotEdit(int $count): string
    {
        return $count === 1 ? 'a page you cannot edit' : sprintf('%d pages you cannot edit', $count);
    }

    /**
     * The error of a write that failed after others were done: what is already
     * written, which page failed and why. Pure.
     *
     * @param int[] $removed the pages that left before the failure
     * @param int[] $added   the pages that joined before the failure
     */
    public static function partialWriteMessage(int $family_id, bool $created, array $removed, array $added, int $failed_post_id, string $reason): string
    {
        $done = array();
        if ($created)
        {
            $done[] = sprintf('family %d was created', $family_id);
        }
        if ($removed)
        {
            $done[] = 'removed ' . implode(', ', $removed);
        }
        if ($added)
        {
            $done[] = 'added ' . implode(', ', $added);
        }

        return sprintf(
            'The change of family %d stopped at page %d: %s %s Call get-family with family_id %d to see the family as it is now, then send the rest again.',
            $family_id,
            $failed_post_id,
            rtrim($reason, '.') . '.',
            $done ? 'Already written: ' . implode('; ', $done) . '.' : 'Nothing else was written.',
            $family_id
        );
    }

    /**
     * What plan() needs to know about each page to add or remove.
     *
     * @param int[] $post_ids
     */
    private static function readCandidates(array $post_ids): array
    {
        $types = FamilyStore::postTypes();
        $names = array();
        $out = array();

        foreach (array_unique($post_ids) as $post_id)
        {
            $post = \get_post($post_id);
            if (!$post)
            {
                $out[$post_id] = self::CANDIDATE_DEFAULTS;
                continue;
            }

            $passport = ProductPassport::get($post_id);
            $family_id = FamilyStore::familyIdOf($post_id);
            if ($family_id > 0 && !isset($names[$family_id]))
            {
                $family = FamilyStore::get($family_id);
                $names[$family_id] = $family !== null ? (string) $family['name'] : '';
            }

            $out[$post_id] = array(
                'exists' => true,
                'can_edit' => PostScope::canEditPost(array('post_id' => $post_id)),
                'type_ok' => in_array($post->post_type, $types, true),
                'post_type' => (string) $post->post_type,
                'status' => (string) $post->post_status,
                'passport' => $passport !== null,
                'variant' => ($passport !== null && isset($passport['variant']) && is_array($passport['variant'])) ? $passport['variant'] : array(),
                'family_id' => $family_id,
                'family_name' => $family_id > 0 ? $names[$family_id] : '',
            );
        }

        return $out;
    }

    /**
     * Delete a family; its pages and their passports stay. Every page must be
     * one the user may edit.
     */
    private static function deleteFamily(int $family_id): array
    {
        $members = FamilyStore::get($family_id) !== null ? FamilyStore::members($family_id, false) : null;
        if ($members === null || GetFamilyAbility::visibleMembers($members, array(GetFamilyAbility::class, 'seesMember')) === null)
        {
            throw new AbilityInputException(sprintf('Family %d does not exist. Call get-family without an id to list the families.', $family_id));
        }

        $post_ids = array();
        $locked = 0;
        foreach ($members as $member)
        {
            $post_id = (int) $member['post_id'];
            $post_ids[] = $post_id;
            if (!PostScope::canEditPost(array('post_id' => $post_id)))
            {
                $locked++;
            }
        }

        if ($locked)
        {
            throw new AbilityInputException(sprintf('Family %d holds %s, so you cannot delete it. Nothing was changed.', $family_id, self::cannotEdit($locked)));
        }

        if (!$members && !\current_user_can('edit_others_posts'))
        {
            throw new AbilityInputException(sprintf('Family %d has no pages, so only a user who can edit others\' pages can delete it. Nothing was changed.', $family_id));
        }

        FamilyStore::delete($family_id);

        return array('family' => null, 'added' => array(), 'removed' => $post_ids, 'deleted' => true);
    }

    /**
     * The stored order restricted to the dimensions that stay. Pure.
     */
    private static function orderFor(array $order, $dimensions): array
    {
        $names = array();
        foreach (is_array($dimensions) ? $dimensions : array() as $row)
        {
            if (is_array($row) && isset($row['name']) && is_scalar($row['name']))
            {
                $names[self::lower(trim((string) $row['name']))] = true;
            }
        }

        $out = array();
        foreach ($order as $key => $values)
        {
            if (isset($names[self::lower(trim((string) $key))]))
            {
                $out[$key] = $values;
            }
        }

        return $out;
    }

    /**
     * @return int[] the ids of an add or remove list, once each, in order
     * @throws AbilityInputException
     */
    private static function readIds(array $input, string $key): array
    {
        if (!isset($input[$key]))
        {
            return array();
        }

        $list = $input[$key];
        if (!is_array($list) || ($list && array_keys($list) !== range(0, count($list) - 1)))
        {
            throw new AbilityInputException(sprintf('%s must be a list of post ids.', $key));
        }
        if (count($list) > self::MAX_IDS)
        {
            throw new AbilityInputException(sprintf('%s can hold at most %d post ids per call.', $key, self::MAX_IDS));
        }

        $ids = array();
        foreach ($list as $i => $value)
        {
            $id = self::postId($value);
            if ($id === 0)
            {
                throw new AbilityInputException(sprintf('%s[%d] must be a post id (a positive integer).', $key, $i));
            }
            $ids[$id] = true;
        }

        return array_keys($ids);
    }

    /**
     * A positive integer id, 0 for anything else.
     *
     * @param mixed $value
     */
    private static function postId($value): int
    {
        if (is_int($value))
        {
            return $value > 0 ? $value : 0;
        }
        if (is_string($value) && ctype_digit(trim($value)))
        {
            return (int) trim($value);
        }

        return 0;
    }

    private static function lower(string $text): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    }
}
