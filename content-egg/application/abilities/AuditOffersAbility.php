<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\components\matching\DiscoveryLog;
use ContentEgg\application\components\matching\OfferAudit;
use ContentEgg\application\components\matching\OfferDecisions;
use ContentEgg\application\components\matching\OfferMatcher;
use ContentEgg\application\components\matching\ProductPassport;
use ContentEgg\application\helpers\CurrencyHelper;

/**
 * AuditOffersAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * "Check my pages": per page, what needs attention (unverified offers, accepted offers
 * that changed, out-of-stock, non-new and off-price offers, GTIN searches that are due,
 * suspended keywords, pages without a passport). Read-only and free; it reads what is
 * stored on the site and makes no request to shops (another currency is converted with
 * the site's ECB daily rates). The findings are OfferAudit's; this
 * ability picks the pages, gathers each page's passport, decisions, discovery log and
 * attached offers, and reports.
 *
 * Which pages: either the post_ids the caller names (a page that does not exist or that the
 * caller may not edit is reported in `skipped`), or the pages that have a passport, in the post
 * types Content Egg is enabled on. A listing never names a page the caller cannot edit: such a
 * row is dropped silently, so walking the list does not reveal which pages hold a passport. It
 * still counts toward the window, so next_offset stays correct.
 */
final class AuditOffersAbility extends AbilityBase
{
    const MAX_POSTS = 50;
    const DEFAULT_LIMIT = 20;
    const MAX_DUE_DAYS = 365;

    public function name(): string
    {
        return 'content-egg/audit-offers';
    }

    public function label(): string
    {
        return __('Audit Offers', 'content-egg');
    }

    public function description(): string
    {
        return 'Audits pages that compare one product and reports, per page, what needs attention. Read-only and free: it reads '
            . 'what is stored on the site and makes no searches (another currency is converted with the site\'s daily ECB rates), so use it whenever '
            . 'the user asks you to check their pages. Pass post_ids (at most ' . self::MAX_POSTS . ' pages), or passport_pages '
            . '{limit 1-' . self::MAX_POSTS . ' (default ' . self::DEFAULT_LIMIT . '), offset} to walk the pages that have a product '
            . 'passport, most recently modified first: next_offset is where the next call starts, null on the last page. With neither, '
            . 'it audits the ' . self::DEFAULT_LIMIT . ' most recently modified passport pages. Findings per page; an offer finding '
            . 'carries module_id, unique_id and title: '
            . 'no_passport (the page has none; then only out_of_stock and price_outlier are checked); '
            . 'unverified (an offer nobody accepted or rejected); '
            . 'fingerprint_changed (an accepted offer whose title or GTIN is no longer what was accepted: detail has was and now); '
            . 'rejected_attached (a rejected offer is attached again); '
            . 'out_of_stock; '
            . 'non_new (the offer\'s condition is not one the passport accepts; detail has the condition); '
            . sprintf(
                'price_outlier (under %d%% or over %d%% of the median of the page\'s priced offers, checked from %d priced offers in one currency; detail has ratio, median and currency); ',
                (int) round(OfferMatcher::PRICE_LOW_RATIO * 100),
                (int) round(OfferMatcher::PRICE_HIGH_RATIO * 100),
                OfferAudit::MIN_OUTLIER_OFFERS
            )
            . 'discovery_due (once per page, no module_id: detail.modules lists each module that can search by GTIN and has not been '
            . 'searched for this page by an identifier (GTIN, URL or native id; a keyword search does not count) in due_days, default '
            . DiscoveryLog::DUE_DAYS . ', or never, with its last_at and mode when it was searched, and detail.count; only when the '
            . 'passport has a GTIN); '
            . 'keyword_suspended (a product module has a stored keyword that is no longer searched again because the page has a passport). '
            . 'Acting on a finding follows the price-comparison topic of get-guide: verify with match-offers, record with '
            . 'set-offer-decisions, search only the modules that are due. Removing an offer needs the user\'s confirmation. '
            . 'Rejected and out-of-stock offers are left out of the price check. With post_ids, a page that does not exist or that you '
            . 'cannot edit is listed in skipped, not audited; passport_pages lists only pages you can edit, in the post types Content Egg is '
            . 'enabled on, and skipped is empty.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => 'object',
            'default' => array(),
            'properties' => array(
                'post_ids' => array(
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => self::MAX_POSTS,
                    'description' => 'The pages to audit, at most ' . self::MAX_POSTS . '. A page that does not exist or that you cannot edit is listed in skipped. Not together with passport_pages.',
                    'items' => array('type' => 'integer', 'minimum' => 1),
                ),
                'passport_pages' => array(
                    'type' => 'object',
                    'description' => 'Audit the pages you can edit that have a product passport, in the post types Content Egg is enabled on, most recently modified first. This is the default, with limit ' . self::DEFAULT_LIMIT . '. Not together with post_ids.',
                    'properties' => array(
                        'limit' => array('type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_POSTS, 'default' => self::DEFAULT_LIMIT, 'description' => 'How many pages in this call.'),
                        'offset' => array('type' => 'integer', 'minimum' => 0, 'default' => 0, 'description' => 'How many pages to skip: the next_offset of the previous call.'),
                    ),
                    'additionalProperties' => false,
                ),
                'due_days' => array(
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => self::MAX_DUE_DAYS,
                    'default' => DiscoveryLog::DUE_DAYS,
                    'description' => 'A module is due for a new GTIN search when it was last searched for the page this many days ago or more, or never.',
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
                'posts' => array(
                    'type' => 'array',
                    'description' => 'The audited pages, in the order of the request (post_ids) or the most recently modified first (passport_pages).',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'post_id' => array('type' => 'integer'),
                            'title' => array('type' => 'string'),
                            'post_type' => array('type' => 'string'),
                            'status' => array('type' => 'string'),
                            'has_passport' => array('type' => 'boolean'),
                            'offers' => array('type' => 'integer', 'description' => 'How many product offers are attached to the page.'),
                            'findings' => array(
                                'type' => 'array',
                                'description' => 'What needs attention; empty when the page is in order.',
                                'items' => self::findingSchema(),
                            ),
                        ),
                    ),
                ),
                'summary' => array(
                    'type' => 'object',
                    'properties' => array(
                        'posts' => array('type' => 'integer', 'description' => 'How many pages were audited.'),
                        'findings' => array(
                            'type' => 'object',
                            'description' => 'How many findings of each type, over all audited pages (so discovery_due counts pages, not modules). A type that was not found is left out.',
                            'additionalProperties' => array('type' => 'integer'),
                        ),
                    ),
                ),
                'skipped' => array(
                    'type' => 'array',
                    'description' => 'With post_ids: the ids that were not audited because the page does not exist or you may not edit it. Always empty with passport_pages, which lists only pages you can edit.',
                    'items' => array('type' => 'integer'),
                ),
                'next_offset' => array(
                    'type' => array('integer', 'null'),
                    'description' => 'With passport_pages: the offset for the next call, null when there are no more pages. Always null with post_ids.',
                ),
            ),
        );
    }

    public function checkPermission($input = null): bool
    {
        // Access to each page is checked in execute(): a page you may not edit goes to skipped.
        return \current_user_can('edit_posts');
    }

    public function execute(array $input): array
    {
        $read = self::readInput($input);

        $targets = array();
        $next_offset = null;
        if ($read['post_ids'] !== null)
        {
            foreach ($read['post_ids'] as $post_id)
            {
                $post = \get_post($post_id);
                $targets[] = array($post_id, $post ? $post : null);
            }
        }
        else
        {
            // One more than the limit tells whether another page follows.
            $window = self::window(self::passportPages($read['limit'] + 1, $read['offset']), $read['limit'], $read['offset']);
            $next_offset = $window['next_offset'];
            foreach ($window['rows'] as $post)
            {
                $targets[] = array((int) $post->ID, $post);
            }
        }

        $shared = array(
            'gtin_module_ids' => ModuleManager::getInstance()->getGtinSearchModuleIds(true),
            'now' => time(),
            'due_days' => $read['due_days'],
            // The rates sorting and the Woo sync use: stored, else one ECB lookup.
            'rate' => array(CurrencyHelper::class, 'comparisonRate'),
        );

        // A missing page and one you may not edit look the same: nothing is said about either.
        $partition = self::partition($targets, $read['post_ids'] !== null, static function ($post_id, $post)
        {
            return $post !== null && PostScope::canEditPost(array('post_id' => $post_id));
        });

        $posts = array();
        foreach ($partition['audit'] as $target)
        {
            $posts[] = self::auditPost($target[1], $shared);
        }
        $skipped = $partition['skipped'];

        return array(
            'posts' => $posts,
            'summary' => self::summarize($posts),
            'skipped' => $skipped,
            'next_offset' => $next_offset,
        );
    }

    /**
     * What the call asks for: either post_ids (unique, in order) or a window of passport pages,
     * and the age after which a GTIN search is due again. Pure.
     *
     * @param array $input
     * @return array {post_ids: ?int[], limit: int, offset: int, due_days: int}
     * @throws AbilityInputException
     */
    public static function readInput(array $input): array
    {
        $has_ids = array_key_exists('post_ids', $input) && $input['post_ids'] !== null;
        $has_pages = array_key_exists('passport_pages', $input) && $input['passport_pages'] !== null;

        if ($has_ids && $has_pages)
        {
            throw new AbilityInputException('Pass post_ids or passport_pages, not both.');
        }

        $due_days = DiscoveryLog::DUE_DAYS;
        if (array_key_exists('due_days', $input) && $input['due_days'] !== null)
        {
            $due_days = self::integer($input['due_days']);
            if ($due_days === null || $due_days < 1 || $due_days > self::MAX_DUE_DAYS)
            {
                throw new AbilityInputException('due_days must be a whole number from 1 to ' . self::MAX_DUE_DAYS . '.');
            }
        }

        $read = array('post_ids' => null, 'limit' => self::DEFAULT_LIMIT, 'offset' => 0, 'due_days' => $due_days);

        if ($has_ids)
        {
            $read['post_ids'] = self::readPostIds($input['post_ids']);

            return $read;
        }

        if ($has_pages)
        {
            $pages = $input['passport_pages'];
            if (!is_array($pages))
            {
                throw new AbilityInputException('passport_pages must be an object: {limit, offset}.');
            }

            $unknown = array_diff(array_map('strval', array_keys($pages)), array('limit', 'offset'));
            if ($unknown)
            {
                throw new AbilityInputException('passport_pages takes limit and offset only, not ' . implode(', ', $unknown) . '.');
            }

            if (array_key_exists('limit', $pages) && $pages['limit'] !== null)
            {
                $limit = self::integer($pages['limit']);
                if ($limit === null || $limit < 1 || $limit > self::MAX_POSTS)
                {
                    throw new AbilityInputException('passport_pages.limit must be a whole number from 1 to ' . self::MAX_POSTS . '.');
                }
                $read['limit'] = $limit;
            }

            if (array_key_exists('offset', $pages) && $pages['offset'] !== null)
            {
                $offset = self::integer($pages['offset']);
                if ($offset === null || $offset < 0)
                {
                    throw new AbilityInputException('passport_pages.offset must be a whole number, 0 or more.');
                }
                $read['offset'] = $offset;
            }
        }

        return $read;
    }

    /**
     * Which pages are audited and which are reported as skipped. Explicit post_ids (the caller named
     * them) report a page that cannot be audited in `skipped`; a listing (passport_pages) drops such
     * a page silently, so the caller learns nothing about pages it may not edit. Order is kept. Pure.
     *
     * @param array[]  $targets   List of [post_id, post or null].
     * @param bool     $explicit  true = post_ids, false = a listing.
     * @param callable $can_audit function (int $post_id, $post): bool
     * @return array {audit: array[] (the targets kept), skipped: int[]}
     */
    public static function partition(array $targets, bool $explicit, callable $can_audit): array
    {
        $audit = array();
        $skipped = array();
        foreach ($targets as $target)
        {
            if (call_user_func($can_audit, (int) $target[0], $target[1]))
            {
                $audit[] = $target;
            }
            elseif ($explicit)
            {
                $skipped[] = (int) $target[0];
            }
        }

        return array('audit' => $audit, 'skipped' => $skipped);
    }

    /**
     * The rows of one page of results: the first $limit of the rows fetched (the query asks for
     * one more than the limit), and the offset of the next page when that extra row was there.
     * Pure.
     *
     * @param array $rows
     * @return array {rows: array, next_offset: ?int}
     */
    public static function window(array $rows, int $limit, int $offset): array
    {
        $rows = array_values($rows);
        if (count($rows) <= $limit)
        {
            return array('rows' => $rows, 'next_offset' => null);
        }

        return array('rows' => array_slice($rows, 0, $limit), 'next_offset' => $offset + $limit);
    }

    /**
     * One page's row of the report. Pure: $ctx is OfferAudit's context.
     *
     * @param array $ctx See OfferAudit.
     */
    public static function postRow(int $post_id, string $title, string $post_type, string $status, array $ctx): array
    {
        return array(
            'post_id' => $post_id,
            'title' => $title,
            'post_type' => $post_type,
            'status' => $status,
            'has_passport' => !empty($ctx['passport']),
            'offers' => (isset($ctx['offers']) && is_array($ctx['offers'])) ? count($ctx['offers']) : 0,
            'findings' => OfferAudit::findings($ctx),
        );
    }

    /**
     * The audited pages and the findings counted by type (in the order of OfferAudit::TYPES; a
     * type that was not found is left out). The map is an object so an empty one reads as {}.
     *
     * @param array[] $posts postRow() rows
     * @return array {posts: int, findings: object}
     */
    public static function summarize(array $posts): array
    {
        $counts = array();
        foreach ($posts as $post)
        {
            foreach ((array) ($post['findings'] ?? array()) as $finding)
            {
                $type = (string) ($finding['type'] ?? '');
                $counts[$type] = (isset($counts[$type]) ? $counts[$type] : 0) + 1;
            }
        }

        $ordered = array();
        foreach (OfferAudit::TYPES as $type)
        {
            if (isset($counts[$type]))
            {
                $ordered[$type] = $counts[$type];
            }
        }

        return array('posts' => count($posts), 'findings' => (object) $ordered);
    }

    /**
     * One page: gather its passport, decisions, discovery log and attached offers, and report.
     *
     * @param \WP_Post $post
     * @param array    $shared gtin_module_ids, now, due_days, rate
     */
    private static function auditPost($post, array $shared): array
    {
        $post_id = (int) $post->ID;
        $passport = ProductPassport::get($post_id);

        $ctx = $shared + array(
            'passport' => $passport,
            'decisions' => OfferDecisions::all($post_id),
            'discovery' => DiscoveryLog::all($post_id),
            // The raw stored item rides along: the decisions' fingerprint is taken from it.
            'offers' => MatchOffersAbility::attachedCandidates($post_id, true),
            // The keywords are suspended by the passport; without one they are searched as usual.
            'keyword_module_ids' => $passport !== null ? SetPassportAbility::suspendedKeywords($post_id) : array(),
        );

        return self::postRow(
            $post_id,
            \html_entity_decode((string) \get_the_title($post), ENT_QUOTES, 'UTF-8'),
            (string) $post->post_type,
            (string) $post->post_status,
            $ctx
        );
    }

    /**
     * The pages that have a passport, in the post types Content Egg is enabled on, most recently
     * modified first (the id breaks ties, so a page is never listed twice or missed between two
     * calls). Trash and auto-drafts are not listed. 'any' is not used: it leaves out post types
     * registered with exclude_from_search, and it would list types the plugin no longer serves.
     *
     * @return \WP_Post[]
     */
    private static function passportPages(int $fetch, int $offset): array
    {
        $post_types = array_values(array_filter((array) GeneralConfig::getInstance()->option('post_types'), 'is_string'));
        if (!$post_types)
        {
            return array();
        }

        $query = new \WP_Query(array(
            'post_type' => $post_types,
            'post_status' => 'any',
            'meta_key' => ProductPassport::META,
            'orderby' => array('modified' => 'DESC', 'ID' => 'DESC'),
            'posts_per_page' => $fetch,
            'offset' => $offset,
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
        ));

        return array_values(array_filter($query->posts, static function ($post)
        {
            return $post instanceof \WP_Post;
        }));
    }

    /**
     * post_ids as unique positive integers, in order.
     *
     * @param mixed $ids
     * @return int[]
     * @throws AbilityInputException
     */
    private static function readPostIds($ids): array
    {
        if (!is_array($ids) || !$ids)
        {
            throw new AbilityInputException('post_ids must be a list of 1 to ' . self::MAX_POSTS . ' post ids.');
        }
        if (count($ids) > self::MAX_POSTS)
        {
            throw new AbilityInputException('Pass at most ' . self::MAX_POSTS . ' post_ids per call; audit the rest in another call, or walk the passport pages with passport_pages.');
        }

        $read = array();
        foreach ($ids as $id)
        {
            $id = self::integer($id);
            if ($id === null || $id < 1)
            {
                throw new AbilityInputException('post_ids must hold positive whole numbers (post ids).');
            }
            if (!in_array($id, $read, true))
            {
                $read[] = $id;
            }
        }

        return $read;
    }

    /**
     * A whole number from an int, an integral float or a string of digits (a REST client sends
     * strings), else null. A boolean is not a number here.
     *
     * @param mixed $value
     */
    private static function integer($value): ?int
    {
        if (is_int($value))
        {
            return $value;
        }
        if (is_float($value) && is_finite($value) && floor($value) === $value)
        {
            return (int) $value;
        }
        if (is_string($value) && preg_match('/^-?[0-9]+$/', trim($value)))
        {
            return (int) trim($value);
        }

        return null;
    }

    /**
     * One finding as a JSON schema.
     */
    private static function findingSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'type' => array(
                    'type' => 'string',
                    'enum' => OfferAudit::TYPES,
                    'description' => 'What needs attention. See the ability description for each type.',
                ),
                'module_id' => array('type' => 'string', 'description' => 'The module the offer is attached under, or the module of a keyword_suspended finding. Absent on no_passport and discovery_due.'),
                'unique_id' => array('type' => 'string', 'description' => 'The offer, with module_id. Absent on page and module findings.'),
                'title' => array('type' => 'string', 'description' => 'The offer\'s title.'),
                'detail' => array(
                    'type' => 'object',
                    'description' => 'Present on some findings. fingerprint_changed: was and now ({title, gtins}; was is null when the decision stored no fingerprint). non_new: condition. price_outlier: ratio (the offer\'s price divided by the median), median and currency. discovery_due: count and modules, one row per due module with its last_at (Unix time) and mode, both left out for a module never searched.',
                    'properties' => array(
                        'was' => array('type' => array('object', 'null'), 'properties' => array('title' => array('type' => 'string'), 'gtins' => array('type' => 'array', 'items' => array('type' => 'string')))),
                        'now' => array('type' => 'object', 'properties' => array('title' => array('type' => 'string'), 'gtins' => array('type' => 'array', 'items' => array('type' => 'string')))),
                        'condition' => array('type' => 'string'),
                        'ratio' => array('type' => 'number'),
                        'median' => array('type' => 'number'),
                        'currency' => array('type' => 'string'),
                        'count' => array('type' => 'integer', 'description' => 'discovery_due: how many modules are due.'),
                        'modules' => array(
                            'type' => 'array',
                            'description' => 'discovery_due: the modules due for a GTIN search.',
                            'items' => array(
                                'type' => 'object',
                                'properties' => array(
                                    'module_id' => array('type' => 'string'),
                                    'last_at' => array('type' => 'integer', 'description' => 'Unix time of the last search for this page; absent when never searched.'),
                                    'mode' => array('type' => 'string', 'description' => 'How that search read its query (gtin, url, native_id or keyword); absent when never searched.'),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        );
    }
}
