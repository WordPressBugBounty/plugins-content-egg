<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ContentManager;
use ContentEgg\application\components\ProductDataService;
use ContentEgg\application\components\matching\OfferDecisions;
use ContentEgg\application\components\matching\ProductPassport;

/**
 * GetPostProductsAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class GetPostProductsAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/get-post-products';
    }

    public function label(): string
    {
        return __('Get Post Products', 'content-egg');
    }

    public function description(): string
    {
        return 'Returns the products attached to a post, grouped per module, with each '
            . 'module\'s search keyword, last update time and a revision token. '
            . 'Keep the revision token: product write operations accept it to detect '
            . 'concurrent edits. Returns compact items by default; pass fields="full" '
            . 'for all raw fields. fields="match" returns identity fields (GTINs, brand, MPN, model, condition, stock, shipping) for comparing offers, '
            . 'and, on a page with a product passport only, each offer\'s decision (accepted or rejected, how, when; null when none; '
            . 'the key is left out on a page without a passport). '
            . 'has_passport says whether the page has a passport (content-egg/get-passport).';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => 'object',
            'default' => array(),
            'properties' => array(
                'post_id' => array('type' => 'integer', 'minimum' => 1),
                'module_id' => array(
                    'type' => 'string',
                    'description' => 'Limit the response to one module.',
                ),
                'fields' => array(
                    'type' => 'string',
                    'enum' => array('lean', 'full', 'match'),
                    'default' => 'lean',
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
                'post_title' => array('type' => 'string'),
                'post_status' => array('type' => 'string'),
                'has_passport' => array(
                    'type' => 'boolean',
                    'description' => 'Whether the page has a product passport. Read it with content-egg/get-passport.',
                ),
                'modules' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'module_id' => array('type' => 'string'),
                            'count' => array('type' => 'integer'),
                            'keyword' => array('type' => 'string'),
                            'last_update' => array('type' => 'integer'),
                            'revision' => array('type' => 'string'),
                            'items' => array(
                                'type' => 'array',
                                'items' => array(
                                    'type' => 'object',
                                    'properties' => array(
                                        'decision' => array(
                                            'type' => array('object', 'null'),
                                            'description' => 'Only with fields="match" on a page with a passport (has_passport true); left out otherwise. What was decided about this offer, or null when nothing was. '
                                                . 'accepted: checked as the passport\'s product. rejected: not the product (automated saves never bring it back; an add with a match, or a person adding it in the editor, does).',
                                            'properties' => array(
                                                'status' => array('type' => 'string', 'description' => 'accepted or rejected.'),
                                                'method' => array('type' => 'string', 'description' => 'gtin, mpn, agent or user.'),
                                                'checked_at' => array('type' => 'integer', 'description' => 'Unix time of the decision.'),
                                            ),
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
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

        $only_module = trim((string) ($input['module_id'] ?? ''));
        $fields = (string) ($input['fields'] ?? 'lean');

        $module_ids = PostScope::attachedModuleIds($post_id);

        // Read once for the whole page: only the identity view of a page with a passport carries the decisions.
        $has_passport = ProductPassport::has($post_id);
        $decisions = ($fields === 'match' && $has_passport) ? OfferDecisions::all($post_id) : array();

        $modules = array();
        foreach ($module_ids as $module_id)
        {
            if ($only_module !== '' && $module_id !== $only_module)
            {
                continue;
            }

            $data = (array) ContentManager::getData($post_id, $module_id);

            $modules[] = array(
                'module_id' => (string) $module_id,
                'count' => count($data),
                'keyword' => (string) \get_post_meta($post_id, ContentManager::META_PREFIX_KEYWORD . $module_id, true),
                'last_update' => (int) \get_post_meta($post_id, ContentManager::META_PREFIX_LAST_ITEMS_UPDATE . $module_id, true),
                'revision' => (string) ProductDataService::revision($post_id, $module_id),
                'items' => $fields === 'full'
                    ? array_values(array_map(array(LeanProduct::class, 'fullItem'), $data))
                    : ($fields === 'match' ? self::matchItems(LeanProduct::matchList($data, (string) $module_id), (string) $module_id, $has_passport, $decisions) : LeanProduct::mapList($data)),
            );
        }

        if ($only_module !== '' && !$modules)
        {
            throw new AbilityInputException(
                "No products for module '{$only_module}' on post {$post_id}. "
                    . 'Attached modules: ' . ($module_ids ? implode(', ', $module_ids) : '(none)') . '.'
            );
        }

        return array(
            'post_id' => $post_id,
            'post_title' => (string) $post->post_title,
            'post_status' => (string) $post->post_status,
            'has_passport' => $has_passport,
            'modules' => $modules,
        );
    }

    /**
     * The identity items as get-post-products returns them: with the decision of each offer on a
     * page with a passport, untouched (no `decision` key) on a page without one. Pure.
     *
     * @param array[] $items     LeanProduct::matchList()
     * @param array   $decisions OfferDecisions::all()
     */
    public static function matchItems(array $items, string $module_id, bool $has_passport, array $decisions): array
    {
        return $has_passport ? self::withDecisions($items, $module_id, $decisions) : $items;
    }

    /**
     * The identity items with the decision taken on each: {status, method, checked_at}, or null when
     * none was. LeanProduct::matchItem() is left alone (its key list is part of the contract), so the
     * decision is added here. An item's own module_id wins over $module_id, as in matchItem(). Pure.
     *
     * @param array[] $items     LeanProduct::matchList()
     * @param array   $decisions OfferDecisions::all(): "<module_id>:<unique_id>" => entry
     */
    public static function withDecisions(array $items, string $module_id, array $decisions): array
    {
        foreach ($items as $i => $item)
        {
            $own = (isset($item['module_id']) && is_scalar($item['module_id']) && (string) $item['module_id'] !== '')
                ? (string) $item['module_id'] : $module_id;
            $unique_id = (isset($item['unique_id']) && is_scalar($item['unique_id'])) ? (string) $item['unique_id'] : '';

            $entry = $decisions[OfferDecisions::key($own, $unique_id)] ?? null;
            $items[$i]['decision'] = (is_array($entry) && isset($entry['status']))
                ? array(
                    'status' => (string) $entry['status'],
                    'method' => isset($entry['method']) ? (string) $entry['method'] : '',
                    'checked_at' => isset($entry['checked_at']) ? (int) $entry['checked_at'] : 0,
                )
                : null;
        }

        return $items;
    }
}
