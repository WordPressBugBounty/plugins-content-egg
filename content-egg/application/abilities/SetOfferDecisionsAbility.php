<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ContentManager;
use ContentEgg\application\components\ProductDataService;
use ContentEgg\application\components\matching\OfferDecisions;
use ContentEgg\application\components\matching\ProductPassport;

/**
 * SetOfferDecisionsAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Record what was decided about the offers of a page that has a product passport:
 * accepted ("this is the passport's product") for an offer that is attached, rejected
 * ("it is not, never attach it") for an attached offer, which is removed, or for one
 * found by a search, which is only remembered. It also logs the searches that found
 * nothing, which issue no search_token. Thin: the decision rules and the storage are
 * in OfferDecisions and DiscoveryLog; this ability checks the whole call before it
 * writes anything.
 */
final class SetOfferDecisionsAbility extends AbilityBase
{
    const MAX_DECISIONS = 100;

    public function name(): string
    {
        return 'content-egg/set-offer-decisions';
    }

    public function label(): string
    {
        return __('Set Offer Decisions', 'content-egg');
    }

    public function description(): string
    {
        return 'Records decisions about the offers of a post that has a product passport (set it first with '
            . 'content-egg/set-passport; a post without one is refused as an input error, nothing written). '
            . 'Each decision is {module_id, unique_id, status, method, reasons?}: status '
            . '"accepted" means the offer is the passport\'s product, "rejected" means it is not: automated saves '
            . 'never bring it back (an add-products-to-post call with a match, or a person adding it in the editor, '
            . 'does, and clears the rejection). method is how you decided (gtin, mpn, agent or user) and reasons are up to 5 short notes. '
            . 'accepted verifies an offer that is already attached to the post (to attach a new one, pass a match '
            . 'with content-egg/add-products-to-post). Rejecting an attached offer removes it from the post, so '
            . 'confirm with the user first, above all for offers they may have added themselves. A rejected offer '
            . 'that is not attached must be in the results of the search_token you pass, which supplies the '
            . 'title and GTINs the rejection is remembered by; one already rejected on the page needs neither '
            . '(sending the rejection again only refreshes it). Use "searched" to log a search that found nothing, '
            . '{module_id, query, results: 0}: such a search issues no search_token, and the log is how the audit '
            . 'knows which modules are due for a new search; a search_token passed here is logged too (a keyword search never '
            . 'replaces the module\'s GTIN, URL or id search). '
            . 'Pass decisions, searched, or both. The call is checked as a whole: when one decision is invalid '
            . 'nothing is written. Returns the number of accepted and rejected decisions, the offers removed, and '
            . 'the module ids written to the discovery log.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => 'object',
            'default' => array(),
            'properties' => array(
                'post_id' => array('type' => 'integer', 'minimum' => 1),
                'decisions' => array(
                    'type' => 'array',
                    'maxItems' => self::MAX_DECISIONS,
                    'description' => 'The decisions to record, at most ' . self::MAX_DECISIONS . ' per call. An offer may be listed once.',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'module_id' => array('type' => 'string', 'description' => 'The module the offer is attached under (or was found by).'),
                            'unique_id' => array('type' => 'string', 'description' => 'The offer\'s unique_id, as content-egg/get-post-products or the search returned it.'),
                            'status' => array(
                                'type' => 'string',
                                'enum' => OfferDecisions::STATUSES,
                                'description' => 'accepted: the offer is the passport\'s product (it must be attached). rejected: it is not (an attached offer is removed).',
                            ),
                            'method' => array(
                                'type' => 'string',
                                'enum' => OfferDecisions::METHODS,
                                'description' => 'How it was decided: gtin or mpn (a code compared), agent (your judgment on the details) or user (the user said so).',
                            ),
                            'reasons' => array(
                                'type' => 'array',
                                'maxItems' => OfferDecisions::MAX_REASONS,
                                'description' => 'Short plain-English reasons, such as "Different model: XM4" or "GTIN matches".',
                                'items' => array('type' => 'string', 'maxLength' => OfferDecisions::MAX_REASON_LENGTH),
                            ),
                        ),
                        'required' => array('module_id', 'unique_id', 'status', 'method'),
                        'additionalProperties' => false,
                    ),
                ),
                'search_token' => array(
                    'type' => 'string',
                    'description' => 'Token from a search response. Offers rejected without being attached are taken from its results, '
                        . 'and the search is logged in the page\'s discovery log (a keyword search never replaces a GTIN, URL or id search).',
                ),
                'searched' => array(
                    'type' => 'array',
                    'maxItems' => DiscoveryRecorder::MAX_SEARCHED,
                    'description' => 'Searches to log, at most ' . DiscoveryRecorder::MAX_SEARCHED . ' per call. Use it for searches that found nothing (they issue no search_token).',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'module_id' => array('type' => 'string', 'description' => 'The module that was searched.'),
                            'query' => array('type' => 'string', 'description' => 'What was searched for: a GTIN, a keyword, a URL or a native id.'),
                            'results' => array('type' => 'integer', 'minimum' => 0, 'description' => 'How many results the search found.'),
                        ),
                        'required' => array('module_id', 'query', 'results'),
                        'additionalProperties' => false,
                    ),
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
                'accepted' => array('type' => 'integer', 'description' => 'How many accepted decisions were recorded.'),
                'rejected' => array('type' => 'integer', 'description' => 'How many rejected decisions were recorded: offers removed, offers taken from the search_token, and rejections that were already on file and were refreshed.'),
                'removed' => array(
                    'type' => 'array',
                    'description' => 'The attached offers that were removed from the post because they were rejected.',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'module_id' => array('type' => 'string'),
                            'unique_id' => array('type' => 'string'),
                        ),
                    ),
                ),
                'discovery' => array(
                    'type' => 'array',
                    'description' => 'Module ids written to the page\'s discovery log by this call.',
                    'items' => array('type' => 'string'),
                ),
            ),
        );
    }

    public function annotations(): array
    {
        // Removes the offers it rejects, so destructive. The nested input is too deep for GET/DELETE query
        // params, so it must be served as POST: idempotent is false because core routes destructive and
        // idempotent abilities to DELETE (the same reason as set-post-status and set-featured-image). The call
        // does behave idempotently: the same input records the same decisions, and a rejection sent again
        // only refreshes the entry on file.
        return array('readonly' => false, 'destructive' => true, 'idempotent' => false);
    }

    public function checkPermission($input = null): bool
    {
        return PostScope::canEditPost($input);
    }

    public function execute(array $input): array
    {
        $post = PostScope::requirePost($input);
        $post_id = (int) $post->ID;

        if (!ProductPassport::has($post_id))
        {
            // An input error (400) like add-products-to-post's `match` and match-offers: there is
            // nothing to retry until a passport is set (409 tells an agent to re-read and retry).
            throw new AbilityInputException(
                'This page has no product passport, so there is no product to decide against. Call content-egg/set-passport first.'
            );
        }

        // Everything below is checked before the first write.
        $read = self::readInput($input);

        $token_items = array();
        $token_module_id = '';
        if ($read['search_token'] !== '')
        {
            $payload = SearchResultCache::get($read['search_token']);
            if ($payload === null)
            {
                throw new AbilityInputException(
                    'Search results expired or unknown search_token — re-run the search and use the '
                        . 'new search_token from its response.'
                );
            }
            $token_module_id = (string) ($payload['module_id'] ?? '');
            $token_items = is_array($payload['items'] ?? null) ? $payload['items'] : array();
        }

        $attached = self::attachedItems($post_id, $read['decisions']);
        $stored = OfferDecisions::all($post_id);
        $plan = self::plan(
            $read['decisions'],
            array_keys($attached),
            array_map('strval', array_keys($token_items)),
            $token_module_id,
            OfferDecisions::rejectedKeys($stored)
        );
        if ($plan['errors'])
        {
            throw new AbilityInputException(implode(' ', $plan['errors']));
        }

        $searched = DiscoveryRecorder::prepareSearched($read['searched']);

        $entries = array();
        $user_id = (int) \get_current_user_id();
        $now = time();
        foreach ($plan['refresh'] as $row)
        {
            $entries[$row['key']] = self::refreshEntry($stored[$row['key']], $row['method'], $row['reasons'], $user_id, $now);
        }
        foreach (array_merge($plan['accept'], $plan['reject_attached'], $plan['reject_detached']) as $row)
        {
            $item = isset($attached[$row['key']]) ? $attached[$row['key']] : (isset($token_items[$row['unique_id']]) ? $token_items[$row['unique_id']] : array());
            try
            {
                $entries[$row['key']] = OfferDecisions::entry($row['status'], $row['method'], $row['reasons'], (array) $item, $user_id, $now);
            }
            catch (\InvalidArgumentException $e)
            {
                throw new AbilityInputException($e->getMessage());
            }
        }

        // Writes. The decisions first: from then on the rejected offers are dropped by any automated save.
        OfferDecisions::put($post_id, $entries);

        $removed = array();
        foreach ($plan['reject_attached'] as $row)
        {
            $removed[] = array('module_id' => $row['module_id'], 'unique_id' => $row['unique_id']);
        }
        if ($removed)
        {
            // One save per module (the same filter and save as removeItem()).
            ProductDataService::removeItems($post_id, $removed);
        }

        $discovery = array();
        if ($read['search_token'] !== '')
        {
            $logged = DiscoveryRecorder::fromToken($post_id, $read['search_token']);
            if ($logged !== '')
            {
                $discovery[] = $logged;
            }
        }
        $discovery = array_values(array_unique(array_merge($discovery, DiscoveryRecorder::record($post_id, $searched))));

        return array(
            'post_id' => $post_id,
            'accepted' => count($plan['accept']),
            'rejected' => count($plan['reject_attached']) + count($plan['reject_detached']) + count($plan['refresh']),
            'removed' => $removed,
            'discovery' => $discovery,
        );
    }

    /**
     * The shape of a call: the decisions and the searches normalized, and the token. Nothing is
     * looked up or written. Pure.
     *
     * @return array {decisions: array[], searched: array[], search_token: string}
     * @throws AbilityInputException when a list is malformed or the call carries neither decisions nor searched
     */
    public static function readInput(array $input): array
    {
        $decisions = $input['decisions'] ?? array();
        $searched = $input['searched'] ?? array();
        if (!is_array($decisions) || !is_array($searched))
        {
            throw new AbilityInputException("'decisions' and 'searched' must be arrays.");
        }

        if (!$decisions && !$searched)
        {
            throw new AbilityInputException(
                "Pass 'decisions' (the offers you accepted or rejected), 'searched' (searches that found nothing), or both."
            );
        }

        return array(
            'decisions' => self::readDecisions($decisions),
            'searched' => DiscoveryRecorder::normalizeSearched($searched),
            'search_token' => trim((string) ($input['search_token'] ?? '')),
        );
    }

    /**
     * Validate and normalize the decision rows: at most MAX_DECISIONS, each with a module id, a
     * unique id, a status and a method from the enums, and optional reasons. Pure.
     *
     * @return array[] {module_id, unique_id, status, method, reasons: string[]}
     * @throws AbilityInputException naming the row that is wrong
     */
    public static function readDecisions(array $decisions): array
    {
        if (count($decisions) > self::MAX_DECISIONS)
        {
            throw new AbilityInputException("'decisions' takes at most " . self::MAX_DECISIONS . ' entries per call.');
        }

        $rows = array();
        foreach (array_values($decisions) as $i => $row)
        {
            if (!is_array($row))
            {
                throw new AbilityInputException("decisions[{$i}] must be an object {module_id, unique_id, status, method, reasons?}.");
            }

            $module_id = (isset($row['module_id']) && is_scalar($row['module_id'])) ? trim((string) $row['module_id']) : '';
            if ($module_id === '')
            {
                throw new AbilityInputException("decisions[{$i}] needs a module_id.");
            }

            $unique_id = (isset($row['unique_id']) && is_scalar($row['unique_id']) && !is_bool($row['unique_id'])) ? (string) $row['unique_id'] : '';
            if ($unique_id === '')
            {
                throw new AbilityInputException("decisions[{$i}] needs a unique_id.");
            }

            $status = (isset($row['status']) && is_string($row['status'])) ? $row['status'] : '';
            if (!in_array($status, OfferDecisions::STATUSES, true))
            {
                throw new AbilityInputException("decisions[{$i}].status must be one of: " . implode(', ', OfferDecisions::STATUSES) . '.');
            }

            $method = (isset($row['method']) && is_string($row['method'])) ? $row['method'] : '';
            if (!in_array($method, OfferDecisions::METHODS, true))
            {
                throw new AbilityInputException("decisions[{$i}].method must be one of: " . implode(', ', OfferDecisions::METHODS) . '.');
            }

            $reasons = array();
            if (isset($row['reasons']))
            {
                if (!is_array($row['reasons']))
                {
                    throw new AbilityInputException("decisions[{$i}].reasons must be a list of short strings.");
                }
                foreach ($row['reasons'] as $reason)
                {
                    if (is_scalar($reason) && !is_bool($reason))
                    {
                        $reasons[] = (string) $reason;
                    }
                }
            }

            $rows[] = array(
                'module_id' => $module_id,
                'unique_id' => $unique_id,
                'status' => $status,
                'method' => $method,
                'reasons' => $reasons,
            );
        }

        return $rows;
    }

    /**
     * Sort the decisions by what has to happen to each:
     * - accept: accepted, and the offer is attached;
     * - reject_attached: rejected, and the offer is attached (it is removed);
     * - reject_detached: rejected, not attached, but among the search_token's results (it is remembered);
     * - refresh: rejected, neither attached nor in the token, but already rejected on the page: the
     *   entry on file is renewed (the same call can be sent again);
     * - errors: one sentence per kind of problem, naming the keys. An accepted offer that is not
     *   attached, a rejected one found nowhere, and an offer listed twice end up here.
     *
     * For a rejection the attached offer wins over the token's, and the token's over the entry on
     * file. A token's unique ids count only for the module the token was issued for
     * ($token_module_id); '' leaves them unconstrained. Only a rejected entry on file helps a
     * rejection: accepting an offer needs it attached, whatever is on file. Pure.
     *
     * @param array[]  $decisions     readDecisions()
     * @param string[] $attached_keys "<module_id>:<unique_id>" of the offers attached to the post
     * @param string[] $token_uids    unique ids of the search_token's results
     * @param string[] $rejected_keys "<module_id>:<unique_id>" of the offers already rejected on the page (OfferDecisions::rejectedKeys())
     * @return array {accept: array[], reject_attached: array[], reject_detached: array[], refresh: array[], errors: string[]}
     */
    public static function plan(array $decisions, array $attached_keys, array $token_uids, string $token_module_id = '', array $rejected_keys = array()): array
    {
        $attached = array_flip(array_map('strval', array_values($attached_keys)));
        $in_token = array_flip(array_map('strval', array_values($token_uids)));
        $known = array_flip(array_map('strval', array_values($rejected_keys)));

        $rows = array();
        $counts = array();
        foreach ($decisions as $row)
        {
            $row['key'] = OfferDecisions::key((string) $row['module_id'], (string) $row['unique_id']);
            $rows[] = $row;
            $counts[$row['key']] = (isset($counts[$row['key']]) ? $counts[$row['key']] : 0) + 1;
        }

        $plan = array('accept' => array(), 'reject_attached' => array(), 'reject_detached' => array(), 'refresh' => array(), 'errors' => array());
        $twice = array();
        $not_attached = array();
        $nowhere = array();

        foreach ($rows as $row)
        {
            $key = $row['key'];
            if ($counts[$key] > 1)
            {
                $twice[$key] = true;
                continue;
            }

            $is_attached = isset($attached[$key]);

            if ($row['status'] === OfferDecisions::STATUS_ACCEPTED)
            {
                if ($is_attached)
                {
                    $plan['accept'][] = $row;
                }
                else
                {
                    $not_attached[] = $key;
                }
                continue;
            }

            if ($is_attached)
            {
                $plan['reject_attached'][] = $row;
            }
            elseif (isset($in_token[$row['unique_id']]) && ($token_module_id === '' || $token_module_id === $row['module_id']))
            {
                $plan['reject_detached'][] = $row;
            }
            elseif (isset($known[$key]))
            {
                $plan['refresh'][] = $row;
            }
            else
            {
                $nowhere[] = $key;
            }
        }

        if ($twice)
        {
            $plan['errors'][] = 'These offers are listed more than once in decisions: ' . implode(', ', array_keys($twice))
                . '. List each offer once.';
        }
        if ($not_attached)
        {
            $plan['errors'][] = 'These offers are not attached to the post, so they cannot be accepted: ' . implode(', ', $not_attached)
                . '. Attach an offer with content-egg/add-products-to-post (pass match to record its decision in the same call).';
        }
        if ($nowhere)
        {
            $plan['errors'][] = 'These rejected offers are not attached to the post, not in the results of the search_token '
                . 'and not rejected on this page yet, so there is nothing to remember them by: ' . implode(', ', $nowhere)
                . '. Pass the search_token of the search that found them.';
        }

        return $plan;
    }

    /**
     * A rejection that is already on file, sent again: the entry keeps its fingerprint (the offer
     * is gone, so there is nothing to read it from) and takes the new method, reasons, time and
     * author. Pure.
     */
    public static function refreshEntry(array $on_file, string $method, array $reasons, int $user_id, int $now): array
    {
        try
        {
            $entry = OfferDecisions::entry(OfferDecisions::STATUS_REJECTED, $method, $reasons, array(), $user_id, $now);
        }
        catch (\InvalidArgumentException $e)
        {
            throw new AbilityInputException($e->getMessage());
        }

        if (isset($on_file['fp']) && is_array($on_file['fp']))
        {
            $entry['fp'] = $on_file['fp'];
        }

        return $entry;
    }

    /**
     * The attached offers of the modules the decisions name: "<module_id>:<unique_id>" => item.
     */
    private static function attachedItems(int $post_id, array $decisions): array
    {
        $attached = array();
        foreach (array_unique(array_column($decisions, 'module_id')) as $module_id)
        {
            foreach ((array) ContentManager::getData($post_id, $module_id) as $index => $item)
            {
                $unique_id = (is_array($item) && isset($item['unique_id']) && is_scalar($item['unique_id']) && (string) $item['unique_id'] !== '')
                    ? (string) $item['unique_id'] : (string) $index;
                $attached[OfferDecisions::key((string) $module_id, $unique_id)] = (array) $item;
            }
        }

        return $attached;
    }
}
