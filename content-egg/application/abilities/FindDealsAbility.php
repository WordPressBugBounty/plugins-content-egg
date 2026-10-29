<?php

namespace ContentEgg\application\abilities;

use ContentEgg\application\deals\DealKey;
use ContentEgg\application\deals\DealLookup;
use ContentEgg\application\deals\DealPostType;
use ContentEgg\application\deals\DealQuery;
use ContentEgg\application\deals\DealStatus;

defined('\ABSPATH') || exit;

/**
 * FindDealsAbility class file
 *
 * Finds existing deals: by the offer (to avoid a duplicate before creating
 * one), or as a filtered list for keeping deals current.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class FindDealsAbility extends AbilityBase
{
    const DEFAULT_LIMIT = 20;
    const MAX_LIMIT = 100;
    const STATUSES = array('any', 'active', 'expired');
    const POST_STATUSES = array('any', 'draft', 'pending', 'publish', 'future', 'private');

    public function name(): string
    {
        return 'content-egg/find-deals';
    }

    public function label(): string
    {
        return __('Find Deals', 'content-egg');
    }

    public function description(): string
    {
        return 'Finds existing deals (post type cegg_deal). Read-only. Look one up by the offer before '
            . 'creating a deal - module_id + unique_id of a search result, its product url, or a gtin - '
            . 'or list deals by status (active/expired), post_status, store, category, ends_before and '
            . 'title search, sorted new, discount, popular or ending. Each deal comes with its price, '
            . 'discount, status and expiry reason, end date, coupon, votes and the offer it follows '
            . '(pin). On a lookup, "match" says what matched: key (the same offer), url (the same product '
            . 'link) or gtin (the same product, possibly another store - not necessarily a duplicate). '
            . 'Pairs with create-post (deal object) and update-deal.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => 'object',
            'default' => array(),
            'properties' => array(
                'module_id' => array('type' => 'string', 'description' => 'With unique_id: find the deal for this exact offer.'),
                'unique_id' => array('type' => 'string', 'description' => 'With module_id, e.g. a search result\'s unique_id.'),
                'url' => array('type' => 'string', 'description' => 'Product URL; tracking parameters, "www." and the trailing slash are ignored.'),
                'gtin' => array('type' => 'string', 'description' => 'EAN/UPC/GTIN of the product.'),
                'status' => array('type' => 'string', 'enum' => self::STATUSES, 'default' => 'any'),
                'post_status' => array('type' => 'string', 'enum' => self::POST_STATUSES, 'default' => 'any'),
                'store' => array('type' => 'string', 'description' => 'Store name or slug.'),
                'category' => array('type' => 'string', 'description' => 'Deal category name or slug (includes subcategories).'),
                'ends_before' => array('type' => 'string', 'description' => 'ISO 8601; only deals with an end date before it.'),
                'search' => array('type' => 'string', 'description' => 'Words in the deal title.'),
                'sort' => array('type' => 'string', 'enum' => DealQuery::SORTS, 'default' => 'new'),
                'limit' => array('type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_LIMIT, 'default' => self::DEFAULT_LIMIT),
            ),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'count' => array('type' => 'integer'),
                'deals' => array('type' => 'array', 'items' => LeanDeal::schema()),
            ),
        );
    }

    public function checkPermission($input = null): bool
    {
        return \current_user_can('edit_posts');
    }

    public function execute(array $input): array
    {
        DealFields::requireDealsEnabled();

        $limit = min(self::MAX_LIMIT, max(1, (int) ($input['limit'] ?? self::DEFAULT_LIMIT)));
        $status = in_array($input['status'] ?? 'any', self::STATUSES, true) ? (string) ($input['status'] ?? 'any') : 'any';
        $post_status = in_array($input['post_status'] ?? 'any', self::POST_STATUSES, true) ? (string) ($input['post_status'] ?? 'any') : 'any';

        $match = $this->lookup($input);
        if ($match !== null && !$match)
            return array('count' => 0, 'deals' => array());

        $args = DealQuery::args(array(
            'sort' => (string) ($input['sort'] ?? 'new'),
            'limit' => $limit,
            'store' => $this->termSlug((string) ($input['store'] ?? ''), DealPostType::TAX_STORE),
            'category' => $this->termSlug((string) ($input['category'] ?? ''), DealPostType::TAX_CATEGORY),
        ), time());

        // The agent works on drafts and protected deals too; DealQuery is built for visitors.
        unset($args['has_password']);
        $args['post_status'] = $post_status === 'any' ? DealLookup::POST_STATUSES : array($post_status);
        // Same disclosure guard as find-posts: other authors' unpublished deals
        // stay hidden unless the caller may edit them.
        $args['perm'] = 'editable';
        $args['no_found_rows'] = true;
        $args['meta_query'] = (array) ($args['meta_query'] ?? array('relation' => 'AND'));

        if ($status !== 'any')
            $args['meta_query']['status_clause'] = array('key' => DealPostType::META_STATUS, 'value' => $status);

        $ends_before = trim((string) ($input['ends_before'] ?? ''));
        if ($ends_before !== '')
        {
            $ts = self::parseTime($ends_before);
            $args['meta_query']['ends_before_clause'] = array('key' => DealPostType::META_ENDS, 'value' => array(1, $ts - 1), 'compare' => 'BETWEEN', 'type' => 'NUMERIC');
        }
        if (count($args['meta_query']) === 1)
            unset($args['meta_query']);

        $search = trim((string) ($input['search'] ?? ''));
        if ($search !== '')
            $args['s'] = $search;

        if ($match !== null)
            $args['post__in'] = array_keys($match);

        $args['fields'] = 'ids';
        $deals = array();
        foreach ((new \WP_Query($args))->posts as $id)
        {
            $id = (int) $id;
            if (!\current_user_can('edit_post', $id))
                continue;

            $row = LeanDeal::row($id);
            if ($match !== null)
                $row['match'] = $match[$id];
            $deals[] = $row;
        }

        return array('count' => count($deals), 'deals' => $deals);
    }

    /**
     * Deal id => what matched, strongest first (key, url, gtin); null when the
     * input asks for no lookup.
     */
    private function lookup(array $input): ?array
    {
        $module_id = trim((string) ($input['module_id'] ?? ''));
        $unique_id = trim((string) ($input['unique_id'] ?? ''));
        $url = trim((string) ($input['url'] ?? ''));
        $gtin = trim((string) ($input['gtin'] ?? ''));

        if (($module_id === '') !== ($unique_id === ''))
            throw new AbilityInputException('module_id and unique_id go together: pass both or neither.');

        if ($module_id === '' && $url === '' && $gtin === '')
            return null;

        $match = array();
        $add = function (array $ids, string $how) use (&$match)
        {
            foreach ($ids as $id)
            {
                if (!isset($match[$id]))
                    $match[$id] = $how;
            }
        };

        if ($module_id !== '')
            $add(DealLookup::byKey(DealKey::key($module_id, $unique_id)), 'key');
        if ($url !== '')
        {
            $url_key = DealKey::urlKey($url);
            if ($url_key === '')
                throw new AbilityInputException("'url' is not an http(s) URL.");
            $add(DealLookup::byUrlKey($url_key), 'url');
        }
        if ($gtin !== '')
        {
            $gtins = \ContentEgg\application\helpers\Gtin::fromItem(array('ean' => $gtin));
            $add(DealLookup::byGtin($gtins ? (string) $gtins[0] : $gtin), 'gtin');
        }

        return $match;
    }

    /** A term slug from a slug or a name; a missing term gives a slug that matches nothing. */
    private function termSlug(string $value, string $taxonomy): string
    {
        if ($value === '')
            return '';

        $term = \get_term_by('slug', \sanitize_title($value), $taxonomy) ?: \get_term_by('name', $value, $taxonomy);

        return $term ? (string) $term->slug : '__none__';
    }

    /** ISO 8601 with an offset, or site time without one (as the Deal panel reads dates). */
    public static function parseTime(string $value): int
    {
        // DateTimeImmutable would read "" as now and "tomorrow" as a date; an
        // agent's typo must not become an end date that expires a deal.
        $value = trim($value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?)?(Z|[+-]\d{2}:?\d{2})?$/i', $value))
            throw new AbilityInputException("'{$value}' is not an ISO 8601 date (e.g. 2026-11-08T07:55:00Z).");

        try
        {
            return (new \DateTimeImmutable($value, \wp_timezone()))->getTimestamp();
        }
        catch (\Exception $e)
        {
            throw new AbilityInputException("'{$value}' is not an ISO 8601 date.");
        }
    }
}
