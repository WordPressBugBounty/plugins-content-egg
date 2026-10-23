<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ContentManager;
use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\components\matching\OfferAudit;
use ContentEgg\application\components\matching\OfferDecisions;
use ContentEgg\application\components\matching\OfferMatcher;
use ContentEgg\application\components\matching\ProductPassport;
use ContentEgg\application\helpers\CurrencyHelper;

/**
 * MatchOffersAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Run the plugin's objective checks (GTIN, brand and MPN, units, condition, price against
 * the page's usual price) on candidate offers and compare them with a product passport.
 * Read-only and free: it makes no request to any shop, so it can run on every search
 * result before anything is attached. Prices in another currency are converted with the
 * site's ECB daily rates (CurrencyHelper::comparisonRate), the ones sorting and the
 * WooCommerce sync use. The checks themselves are in
 * OfferMatcher; this ability gathers the candidates (search tokens and/or the page's
 * attached offers), the anchor price and the decisions already taken, and returns the
 * signals. The band is preliminary: the verdict in the grey zone is the agent's.
 */
final class MatchOffersAbility extends AbilityBase
{
    const MAX_TOKENS = 10;
    const MAX_CANDIDATES = 100;

    public function name(): string
    {
        return 'content-egg/match-offers';
    }

    public function label(): string
    {
        return __('Match Offers', 'content-egg');
    }

    public function description(): string
    {
        return 'Checks candidate offers against a product passport and returns, for each, the signals found and a '
            . 'preliminary band. Read-only: it writes nothing and makes no requests to shops, '
            . 'so it is safe to run on every search result before attaching anything. The passport is '
            . 'either passed inline (the set-passport fields, which wins) or the one stored on the page (post_id). '
            . 'Candidates are the results of up to ' . self::MAX_TOKENS . ' search_tokens (all of a token\'s results, '
            . 'or the unique_ids you list), and/or the offers already attached to the page (attached: true, needs '
            . 'post_id). Per candidate: gtin (match, different or none; codes ignored as restricted, coupon or '
            . 'placeholder are listed), brand, mpn (match, different, in_title or none), units (memory, volume, '
            . 'weight and pack count that differ from the passport variant; a pack count the passport does not name '
            . 'means one unit), condition, and the price against the anchor: the median of the page\'s accepted '
            . 'offers (in stock and unchanged since they were accepted), else the passport\'s reference price. '
            . 'strong_match means identity is established (same GTIN, or the same brand and MPN), nothing contradicts it and the price is in the usual range of the anchor; '
            . 'it is never given without an anchor. conflict means the offer cannot be the product (a unit, the '
            . 'condition or the brand differs). unclear is everything else. Bands are signals, not verdicts: the final '
            . 'verdict in the grey zone (accessories, bundles, model-name equivalents, colors, price outliers) is your '
            . 'call; read the reasons, look at the images when needed, and record what you decide with '
            . 'content-egg/set-offer-decisions. decision shows what was already decided about an offer on the page. '
            . 'A price in another currency is converted with the site\'s daily ECB exchange rates (the ones sorting and the '
            . 'WooCommerce price use); another currency is never a reason to reject an offer. When no rate is known the '
            . 'price signal is null. At most ' . self::MAX_CANDIDATES . ' candidates are evaluated per call; notice '
            . 'says when more were given.';
    }

    public function inputSchema(): array
    {
        $passport = array();
        $set = (new SetPassportAbility())->inputSchema()['properties'];
        foreach (SetPassportAbility::FIELDS as $field)
        {
            $passport[$field] = $set[$field];
        }

        return array(
            'type' => 'object',
            'default' => array(),
            'properties' => array(
                'post_id' => array(
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'The page. Gives the stored passport (when passport is not passed), the decisions taken on its offers, the offers for attached, and the accepted offers the price anchor is taken from.',
                ),
                'passport' => array(
                    'type' => 'object',
                    'description' => 'The product to match against, in the set-passport shape. Wins over the passport stored on the page. Nothing is stored. '
                        . 'Pass it, or the post_id of a page that has a passport.',
                    'properties' => $passport,
                    'additionalProperties' => false,
                ),
                'candidates' => array(
                    'type' => 'array',
                    'maxItems' => self::MAX_TOKENS,
                    'description' => 'Search results to check, at most ' . self::MAX_TOKENS . ' search_tokens. Each token comes from a search response.',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'search_token' => array('type' => 'string', 'description' => 'Token from a search response.'),
                            'unique_ids' => array(
                                'type' => 'array',
                                'description' => 'The results to check. Leave it out to check every result of the token.',
                                'items' => array('type' => 'string'),
                            ),
                        ),
                        'required' => array('search_token'),
                        'additionalProperties' => false,
                    ),
                ),
                'attached' => array(
                    'type' => 'boolean',
                    'description' => 'true also checks the product offers already attached to the page (needs post_id). Pass candidates, attached, or both.',
                ),
            ),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        $bands = array(OfferMatcher::BAND_STRONG, OfferMatcher::BAND_CONFLICT, OfferMatcher::BAND_UNCLEAR);

        return array(
            'type' => 'object',
            'properties' => array(
                'passport_source' => array(
                    'type' => 'string',
                    'enum' => array('inline', 'post'),
                    'description' => 'inline: the passport you passed. post: the one stored on the page.',
                ),
                'anchor' => array(
                    'type' => array('object', 'null'),
                    'description' => 'The price the offers are compared with, or null when there is none (then no offer is strong_match).',
                    'properties' => array(
                        'price' => array('type' => 'number'),
                        'currency' => array('type' => 'string'),
                        'basis' => array(
                            'type' => 'string',
                            'enum' => array('accepted_median', 'reference'),
                            'description' => 'accepted_median: the median of the page\'s accepted offers that are in stock and unchanged since they were accepted. reference: the passport\'s reference price.',
                        ),
                    ),
                ),
                'results' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'module_id' => array('type' => 'string'),
                            'unique_id' => array('type' => 'string'),
                            'title' => array('type' => 'string'),
                            'band' => array(
                                'type' => 'string',
                                'enum' => $bands,
                                'description' => 'strong_match: identity established, nothing contradicts it, price in range. conflict: cannot be the product. unclear: you decide.',
                            ),
                            'decision' => array(
                                'type' => array('object', 'null'),
                                'description' => 'What was already decided about this offer on the page, or null.',
                                'properties' => array(
                                    'status' => array('type' => 'string', 'description' => 'accepted or rejected.'),
                                    'method' => array('type' => 'string', 'description' => 'gtin, mpn, agent or user.'),
                                    'checked_at' => array('type' => 'integer', 'description' => 'Unix time of the decision.'),
                                ),
                            ),
                            'signals' => self::signalsSchema(),
                            'reasons' => array(
                                'type' => 'array',
                                'description' => 'What decided the band, most important first, in plain English. They can be passed as the reasons of a decision.',
                                'items' => array('type' => 'string'),
                            ),
                        ),
                    ),
                ),
                'counts' => array(
                    'type' => 'object',
                    'properties' => array(
                        OfferMatcher::BAND_STRONG => array('type' => 'integer'),
                        OfferMatcher::BAND_CONFLICT => array('type' => 'integer'),
                        OfferMatcher::BAND_UNCLEAR => array('type' => 'integer'),
                    ),
                ),
                'notice' => array(
                    'type' => 'string',
                    'description' => 'Empty, or says that the evaluation stopped at ' . self::MAX_CANDIDATES . ' candidates.',
                ),
            ),
        );
    }

    public function annotations(): array
    {
        // Writes nothing and calls nothing, but the passport and the candidate objects are too deep for
        // GET query params: readonly=false selects POST (like validate-blocks).
        return array('readonly' => false, 'destructive' => false, 'idempotent' => true);
    }

    public function checkPermission($input = null): bool
    {
        if (!\current_user_can('edit_posts'))
        {
            return false;
        }

        // The offers, decisions and passport of a page are read only by someone who may edit it.
        $post_id = is_array($input) ? (int) ($input['post_id'] ?? 0) : 0;

        return $post_id > 0 ? PostScope::canEditPost($input) : true;
    }

    public function execute(array $input): array
    {
        $post_id = (int) ($input['post_id'] ?? 0);
        if ($post_id > 0)
        {
            $post_id = (int) PostScope::requirePost($input)->ID;
        }

        $stored = ProductPassport::get($post_id);
        self::checkInput($input, $stored !== null);
        $tokens = self::readCandidates($input['candidates'] ?? null);

        $given = self::givenPassport($input);
        if ($given !== null)
        {
            try
            {
                $passport = ProductPassport::normalize($given);
            }
            catch (\InvalidArgumentException $e)
            {
                throw new AbilityInputException('Invalid passport: ' . $e->getMessage());
            }
            $source = 'inline';
        }
        else
        {
            $passport = (array) $stored;
            $source = 'post';
        }
        if ($post_id > 0)
        {
            // The product's own name: a "Combo Kit" page is not a bundle of itself.
            $passport['name'] = (string) \get_post_field('post_title', $post_id, 'raw');
        }

        // Only a stored page has attached offers, and only a page with a passport has decisions
        // that count. The accepted attached offers anchor the price whether or not they are listed
        // among the candidates; their stored item ('raw') tells whether they changed since.
        $attached = $post_id > 0 ? self::attachedCandidates($post_id, true) : array();
        $decisions = self::pageDecisions($post_id);

        $candidates = self::searchCandidates($tokens);
        if (self::flag($input, 'attached'))
        {
            $candidates = array_merge(array_map(static function (array $item)
            {
                unset($item['raw']);

                return $item;
            }, $attached), $candidates);
        }

        $rate = array(CurrencyHelper::class, 'comparisonRate');

        return array('passport_source' => $source) + self::run($passport, $attached, $candidates, $decisions, $rate);
    }

    /**
     * The decisions of a page that has a passport; none on any other page. A cleared passport
     * leaves its decisions on file, and they must not anchor the price or label a candidate
     * (as get-post-products shows a decision only on a passport page).
     */
    public static function pageDecisions(int $post_id): array
    {
        return ($post_id > 0 && ProductPassport::has($post_id)) ? OfferDecisions::all($post_id) : array();
    }

    /**
     * The checks that need no lookup: a passport (inline or stored), something to check, and a
     * page for attached. Pure.
     *
     * @param array $input
     * @param bool  $has_stored_passport Whether the page named by post_id has a stored passport.
     * @throws AbilityInputException
     */
    public static function checkInput(array $input, bool $has_stored_passport): void
    {
        if (self::givenPassport($input) === null && !$has_stored_passport)
        {
            throw new AbilityInputException('Pass passport, or the post_id of a page that has one.');
        }

        $has_candidates = !empty($input['candidates']);
        $attached = self::flag($input, 'attached');
        if (!$has_candidates && !$attached)
        {
            throw new AbilityInputException(
                'Pass candidates (search_tokens, with unique_ids if you want only some results), attached: true '
                    . '(the offers already on the page), or both.'
            );
        }

        if ($attached && (int) ($input['post_id'] ?? 0) <= 0)
        {
            throw new AbilityInputException('attached: true checks the offers already attached to a page, so it needs the post_id of that page.');
        }
    }

    /**
     * The candidates entries as {search_token, unique_ids[]}: at most MAX_TOKENS, each with a token,
     * unique ids as trimmed unique strings. Pure.
     *
     * @param mixed $candidates
     * @return array[]
     * @throws AbilityInputException
     */
    public static function readCandidates($candidates): array
    {
        if ($candidates === null || $candidates === array())
        {
            return array();
        }
        if (!is_array($candidates))
        {
            throw new AbilityInputException('candidates must be an array of {search_token, unique_ids?}.');
        }
        if (count($candidates) > self::MAX_TOKENS)
        {
            throw new AbilityInputException('Pass at most ' . self::MAX_TOKENS . ' search tokens in candidates; check the rest in another call.');
        }

        $read = array();
        foreach (array_values($candidates) as $i => $entry)
        {
            if (!is_array($entry))
            {
                throw new AbilityInputException("candidates[{$i}] must be an object with a search_token.");
            }
            $token = (isset($entry['search_token']) && is_scalar($entry['search_token'])) ? trim((string) $entry['search_token']) : '';
            if ($token === '')
            {
                throw new AbilityInputException("candidates[{$i}] needs a search_token (it comes from a search response).");
            }

            $unique_ids = array();
            if (isset($entry['unique_ids']))
            {
                if (!is_array($entry['unique_ids']))
                {
                    throw new AbilityInputException("candidates[{$i}].unique_ids must be an array of unique_id strings.");
                }
                foreach ($entry['unique_ids'] as $unique_id)
                {
                    if (!is_scalar($unique_id) || is_bool($unique_id))
                    {
                        throw new AbilityInputException("candidates[{$i}].unique_ids must hold unique_id strings.");
                    }
                    $unique_id = trim((string) $unique_id);
                    if ($unique_id !== '' && !in_array($unique_id, $unique_ids, true))
                    {
                        $unique_ids[] = $unique_id;
                    }
                }
            }

            $read[] = array('search_token' => $token, 'unique_ids' => $unique_ids);
        }

        return $read;
    }

    /**
     * The search results named by the candidates, as LeanProduct::matchItem() arrays of the token's
     * module. A token with no unique_ids gives all its results, in the order of the search.
     *
     * @param array[] $candidates readCandidates()
     * @return array[]
     * @throws AbilityInputException an unknown or expired token, or a unique_id the token does not hold
     */
    public static function searchCandidates(array $candidates): array
    {
        $items = array();
        foreach ($candidates as $entry)
        {
            $payload = SearchTokenResolver::payload($entry['search_token']);
            $module_id = (string) ($payload['module_id'] ?? '');
            $results = (isset($payload['items']) && is_array($payload['items'])) ? $payload['items'] : array();

            if ($entry['unique_ids'])
            {
                $missing = array();
                foreach ($entry['unique_ids'] as $unique_id)
                {
                    if (!isset($results[$unique_id]))
                    {
                        $missing[] = $unique_id;
                    }
                }
                if ($missing)
                {
                    throw new AbilityInputException(
                        "These unique_ids are not in this search_token's results: " . implode(', ', $missing)
                            . '. Pass unique_ids exactly as returned by the search that issued the token, or leave unique_ids out to check every result.'
                    );
                }
                $wanted = array_map('strval', $entry['unique_ids']);
            }
            else
            {
                $wanted = array_map('strval', array_keys($results));
            }

            foreach ($wanted as $unique_id)
            {
                $item = LeanProduct::matchItem($results[$unique_id], $module_id);
                if ($item['unique_id'] === '')
                {
                    $item['unique_id'] = $unique_id;
                }
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * The product offers attached to the page, as LeanProduct::matchItem() arrays. Coupon, image and
     * video modules hold no offers and are left out.
     *
     * @param bool $with_raw Adds the stored item as 'raw': the decision fingerprint is taken from it
     *                       (audit-offers compares it), so it is not for output.
     * @return array[]
     */
    public static function attachedCandidates(int $post_id, bool $with_raw = false): array
    {
        $product_modules = ModuleManager::getInstance()->getParserModuleIdsByTypes('PRODUCT', false);

        $items = array();
        foreach (PostScope::attachedModuleIds($post_id) as $module_id)
        {
            if (!in_array($module_id, $product_modules, true))
            {
                continue;
            }

            foreach ((array) ContentManager::getData($post_id, $module_id) as $index => $item)
            {
                if (!is_array($item) && !is_object($item))
                {
                    continue;
                }
                $match = LeanProduct::matchItem($item, $module_id);
                // Decisions are keyed by the module the offer is attached under.
                $match['module_id'] = $module_id;
                if ($match['unique_id'] === '')
                {
                    $match['unique_id'] = (string) $index;
                }
                if ($with_raw)
                {
                    $match['raw'] = (array) $item;
                }
                $items[] = $match;
            }
        }

        return $items;
    }

    /**
     * Anchor, evaluate and count. Pure but for the rate callable.
     *
     * @param array         $passport   Passport shape.
     * @param array[]       $attached   The page's attached offers (matchItem shape, optionally with the stored
     *                                  item as 'raw'): the accepted ones set the anchor, but not one that is
     *                                  out of stock or no longer what was accepted (its fingerprint changed),
     *                                  as audit-offers leaves them out of its price pool.
     * @param array[]       $candidates The offers to evaluate (matchItem shape). An offer listed twice is evaluated
     *                                  once, the first copy winning; at most MAX_CANDIDATES are evaluated.
     * @param array         $decisions  OfferDecisions::all(): "<module_id>:<unique_id>" => entry.
     * @param callable|null $rate       function (string $from, string $to): float, 0 = unknown.
     * @return array {anchor, results, counts, notice}
     */
    public static function run(array $passport, array $attached, array $candidates, array $decisions, ?callable $rate): array
    {
        $accepted = array();
        foreach ($attached as $item)
        {
            $entry = $decisions[OfferDecisions::key((string) $item['module_id'], (string) $item['unique_id'])] ?? null;
            if (!is_array($entry) || ($entry['status'] ?? '') !== OfferDecisions::STATUS_ACCEPTED || $item['price'] === null)
            {
                continue;
            }
            if ((int) ($item['stock_status'] ?? 0) === OfferAudit::STOCK_OUT)
            {
                continue;
            }
            $stored = (isset($item['raw']) && is_array($item['raw']))
                ? $item['raw']
                : array('title' => (string) $item['title'], 'extra' => array('EANs' => array_values((array) ($item['gtins'] ?? array()))));
            if (OfferDecisions::fingerprintChanged($entry, $stored))
            {
                continue;
            }
            $accepted[] = array('price' => $item['price'], 'currency' => $item['currency']);
        }
        $anchor = OfferMatcher::anchor($passport, $accepted, $rate);

        $seen = array();
        $unique = array();
        foreach ($candidates as $item)
        {
            $key = OfferDecisions::key((string) $item['module_id'], (string) $item['unique_id']);
            if (isset($seen[$key]))
            {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $item;
        }

        $notice = '';
        if (count($unique) > self::MAX_CANDIDATES)
        {
            $notice = sprintf(
                'Evaluated the first %d of %d candidates. Check the rest in another call: list them in unique_ids, at most %d candidates per call.',
                self::MAX_CANDIDATES,
                count($unique),
                self::MAX_CANDIDATES
            );
            $unique = array_slice($unique, 0, self::MAX_CANDIDATES);
        }

        $counts = array(OfferMatcher::BAND_STRONG => 0, OfferMatcher::BAND_CONFLICT => 0, OfferMatcher::BAND_UNCLEAR => 0);
        $results = array();
        foreach (GetPostProductsAbility::withDecisions($unique, '', $decisions) as $item)
        {
            $evaluation = OfferMatcher::evaluate($passport, $item, $anchor, $rate);
            $counts[$evaluation['band']]++;
            $results[] = array(
                'module_id' => $item['module_id'],
                'unique_id' => $item['unique_id'],
                'title' => $item['title'],
                'band' => $evaluation['band'],
                'decision' => $item['decision'],
                'signals' => $evaluation['signals'],
                'reasons' => $evaluation['reasons'],
            );
        }

        return array('anchor' => $anchor, 'results' => $results, 'counts' => $counts, 'notice' => $notice);
    }

    /**
     * The passport the caller passed, or null when none (an empty one is none).
     *
     * @throws AbilityInputException when it is not an object
     */
    private static function givenPassport(array $input): ?array
    {
        if (!isset($input['passport']))
        {
            return null;
        }
        if (!is_array($input['passport']))
        {
            throw new AbilityInputException('passport must be an object with the set-passport fields (gtins, brand, mpn, model, variant, conditions, reference).');
        }

        return $input['passport'] ? $input['passport'] : null;
    }

    /**
     * A boolean input. A REST client may send "false" as a string.
     */
    private static function flag(array $input, string $name): bool
    {
        return isset($input[$name]) && filter_var($input[$name], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * What the matcher found about one offer, as a JSON schema.
     */
    private static function signalsSchema(): array
    {
        $match = array('match', 'different', 'none');

        return array(
            'type' => 'object',
            'description' => 'The objective checks behind the band.',
            'properties' => array(
                'gtin' => array(
                    'type' => 'object',
                    'properties' => array(
                        'status' => array('type' => 'string', 'enum' => $match, 'description' => 'match: a GTIN is shared. different: both have GTINs and none is shared. none: one side has none.'),
                        'offer' => array('type' => 'array', 'description' => 'The offer\'s usable GTINs.', 'items' => array('type' => 'string')),
                        'flagged' => array(
                            'type' => 'array',
                            'description' => 'Codes ignored as unusable.',
                            'items' => array(
                                'type' => 'object',
                                'properties' => array(
                                    'code' => array('type' => 'string'),
                                    'issue' => array('type' => 'string', 'enum' => array('restricted', 'coupon', 'placeholder')),
                                ),
                            ),
                        ),
                    ),
                ),
                'brand' => array(
                    'type' => 'object',
                    'properties' => array(
                        'status' => array('type' => 'string', 'enum' => $match),
                        'offer' => array('type' => 'string'),
                        'passport' => array('type' => 'string'),
                    ),
                ),
                'mpn' => array(
                    'type' => 'object',
                    'properties' => array(
                        'status' => array('type' => 'string', 'enum' => array('match', 'different', 'in_title', 'none'), 'description' => 'in_title: the offer has no MPN but its title holds the passport\'s as a whole code (X100 is not in X100-T). A weak signal: it never establishes identity.'),
                        'offer' => array('type' => 'string'),
                        'passport' => array('type' => 'string'),
                    ),
                ),
                'units' => array(
                    'type' => 'array',
                    'description' => 'One row per passport variant value whose dimension the offer also states.',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'dimension' => array('type' => 'string', 'enum' => array('memory', 'volume', 'weight', 'pack'), 'description' => 'memory in GB, volume in ml, weight in g, pack as a count.'),
                            'passport' => array('type' => 'number'),
                            'offer' => array('type' => 'array', 'items' => array('type' => 'number')),
                            'status' => array('type' => 'string', 'enum' => array('match', 'conflict')),
                        ),
                    ),
                ),
                'condition' => array(
                    'type' => 'object',
                    'properties' => array(
                        'value' => array('type' => 'string', 'description' => 'new, used, refurbished or unknown (which counts as new).'),
                        'allowed' => array('type' => 'boolean', 'description' => 'Whether the passport accepts this condition.'),
                    ),
                ),
                'price' => array(
                    'type' => 'object',
                    'properties' => array(
                        'value' => array('type' => array('number', 'null')),
                        'currency' => array('type' => 'string'),
                        'ratio' => array('type' => array('number', 'null'), 'description' => 'The price divided by the anchor price; null when it cannot be compared.'),
                        'outlier' => array('type' => array('boolean', 'null'), 'description' => 'true when the ratio is under ' . OfferMatcher::PRICE_LOW_RATIO . ' or over ' . OfferMatcher::PRICE_HIGH_RATIO . '; null when it cannot be compared.'),
                    ),
                ),
                'bundle' => array(
                    'type' => 'object',
                    'properties' => array(
                        'marker' => array('type' => 'string', 'description' => 'The bundle word in the title ("Bundle", "Combo", "with … Set", "Pack" that is not a count, or a second product joined with " + " / " & " after the passport\'s MPN or model), or "". An offer with one is never strong_match: read what it includes.'),
                    ),
                ),
            ),
        );
    }
}
