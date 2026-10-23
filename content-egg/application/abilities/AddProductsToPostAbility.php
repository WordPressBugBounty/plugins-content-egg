<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\components\ParserModule;
use ContentEgg\application\components\ProductDataService;
use ContentEgg\application\components\matching\OfferDecisions;
use ContentEgg\application\components\matching\ProductPassport;

/**
 * AddProductsToPostAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class AddProductsToPostAbility extends AbilityBase
{
    /**
     * The only fields a per-item "overrides" object may set: editorial copy, plus
     * `group` (the price-comparison grouping key). Everything else on a searched
     * item — price, url, ean, stock_status, unique_id, merchant, img… — comes from
     * the server's stored search result and is never caller-writable here.
     */
    const OVERRIDE_FIELDS = array(
        'title', 'subtitle', 'description', 'short_description',
        'badge', 'badge_color', 'promo', 'group',
    );

    const GROUP_MAX_LENGTH = 100;

    public function name(): string
    {
        return 'content-egg/add-products-to-post';
    }

    public function label(): string
    {
        return __('Add Products to Post', 'content-egg');
    }

    public function description(): string
    {
        return 'Attaches products to a post under one module. For searchable modules (Amazon, Ebay, '
            . 'feeds): run content-egg/search-products, then pass its search_token plus the chosen '
            . 'unique_ids as items — the server attaches its own stored copy, so full product '
            . 'objects are never echoed back. (Passing full item objects from '
            . 'fields="full" without a token still works.) You MAY '
            . 'tailor editorial fields per item with an optional "overrides" object. Overrides may '
            . 'set only title, subtitle, description, short_description, badge, badge_color '
            . '[a named color: primary, secondary, success, danger, warning, info, light, dark — not hex], '
            . 'promo, group — any other key (price, url, ean, stock_status…) is rejected with 400, '
            . 'because those come from the search result. Attach overrides directly to an item, or '
            . 'alongside a unique_id as {unique_id, overrides} in token mode; they apply on top of '
            . 'the source item and survive price/stock refreshes (which only update price, '
            . 'availability, url and image). Pass group to put every attached offer in one product '
            . 'group (a per-item overrides.group wins). For heavy rewriting, prefer Egg Blocks. '
            . 'For the manual "Offer" module (which has no search_token) you may construct '
            . 'items yourself; each needs unique_id, title and url (or orig_url), plus optional '
            . 'price/currencyCode/description/img. '
            . 'Pass the revision from content-egg/get-post-products to detect concurrent '
            . 'edits (409 cegg_conflict on mismatch). Returns the new revision. For the '
            . 'Offer module the response also reports monetization: how many added links '
            . 'became affiliate links and any domains still needing a deeplink configured '
            . 'in the Offer module settings. '
            . 'On a page with a product passport (content-egg/set-passport) an item may carry '
            . '"match": {method: gtin|mpn|agent|user, reasons?}: it records that you checked the offer '
            . 'is the passport\'s product, and the offer is attached with an accepted decision. '
            . 'A page with a passport is curated: an offer rejected earlier (content-egg/set-offer-decisions) '
            . 'is skipped with a rejected_skipped warning unless its item carries a match, which replaces '
            . 'the rejection with an accepted decision. match on a page without a passport is a 400. '
            . 'Passing the search_token also logs the search in the page\'s discovery log (a keyword search never replaces a '
            . 'GTIN, URL or id search). '
            . 'The response then reports decisions.accepted.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => 'object',
            'default' => array(),
            'properties' => array(
                'post_id' => array('type' => 'integer', 'minimum' => 1),
                'module_id' => array('type' => 'string'),
                'search_token' => array(
                    'type' => 'string',
                    'description' => 'Token from the content-egg/search-products response. When set, items are '
                        . 'unique_id refs (string, or {unique_id, overrides}) and the server attaches its '
                        . 'stored copy of each result. Not applicable to the manual Offer module.',
                ),
                'items' => array(
                    'type' => 'array',
                    'minItems' => 1,
                    'items' => array(
                        'type' => array('string', 'object'),
                        'description' => 'With search_token: a unique_id string from the search results, or '
                            . '{unique_id, overrides} to tailor editorial fields (title, subtitle, description, '
                            . 'short_description, badge, badge_color, promo, group only). Without: a full product '
                            . 'object from content-egg/search-products (fields="full") passed unchanged '
                            . '(optionally with "overrides"), or a constructed item for the Offer module. '
                            . 'On a page with a product passport an item object may also carry '
                            . 'match: {method (gtin, mpn, agent or user), reasons? (at most 5 short strings)} '
                            . 'to record that you checked it is the passport\'s product.',
                    ),
                ),
                'group' => array(
                    'type' => 'string',
                    'maxLength' => self::GROUP_MAX_LENGTH,
                    'description' => 'Optional product-group name applied to every attached item (e.g. "Black" or "Refurbished") '
                        . 'so price-comparison blocks can show those offers together. A per-item '
                        . 'overrides.group wins over this value.',
                ),
                'revision' => array('type' => 'string'),
            ),
            'required' => array('post_id', 'module_id', 'items'),
            'additionalProperties' => false,
        );
    }

    /**
     * Reject link-less items, and report the ones that will render thin.
     *
     * A caller trimming the payload to save tokens (or passing the lean search
     * result, which carries no url at all) produced a stored product with no
     * price, no image and no affiliate link — and every signal said success:
     * the attach returned 200, validation passed, and preview-blocks rendered a
     * confident-looking card with zero warnings. For an affiliate plugin that is
     * the worst silent failure there is, because the card can never earn.
     *
     * No link is fatal, so it throws: a product that cannot be clicked is not a
     * product, and there is no legitimate way to land here. Missing price or
     * image only degrades the card, so those are reported and the write goes
     * ahead. Token mode never trips either check — the server attaches its own
     * stored copy.
     *
     * @throws AbilityInputException
     */
    private static function checkItemCompleteness(array $items): array
    {
        $warnings = array();

        foreach (array_values($items) as $i => $item)
        {
            if (!is_array($item))
            {
                continue;
            }

            $uid = (string) ($item['unique_id'] ?? '');
            $has_link = trim((string) ($item['url'] ?? '')) !== ''
                || trim((string) ($item['orig_url'] ?? '')) !== '';

            if (!$has_link)
            {
                throw new AbilityInputException(
                    "items[{$i}] ('{$uid}') has neither url nor orig_url, so it would be stored as a "
                        . 'product that links nowhere and can never earn. The lean search result omits '
                        . 'both fields — pass the items from content-egg/search-products with '
                        . 'fields="full", or (better) pass that response\'s search_token with the '
                        . 'unique_ids and let the server attach its own stored copy.'
                );
            }

            $thin = array();
            if (($item['price'] ?? null) === null || (string) $item['price'] === '')
            {
                $thin[] = 'price';
            }
            if (trim((string) ($item['img'] ?? '')) === '')
            {
                $thin[] = 'img';
            }

            if ($thin)
            {
                $warnings[] = array(
                    'code' => 'thin_product',
                    'unique_id' => $uid,
                    'message' => "Stored without " . implode(' and ', $thin) . ", so its block renders "
                        . 'incomplete. Pass fields="full" items, or the search_token plus unique_ids.',
                );
            }
        }

        return $warnings;
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'post_id' => array('type' => 'integer'),
                'module_id' => array('type' => 'string'),
                'added' => array('type' => 'array', 'items' => array('type' => 'string')),
                'count' => array('type' => 'integer', 'description' => 'Products of this module on the post after the call; added lists the new ones.'),
                'revision' => array('type' => 'string'),
                'warnings' => array(
                    'type' => 'array',
                    'description' => 'Non-fatal notes. thin_product means the item was stored without a '
                        . 'price or image, so its block renders incomplete. rejected_skipped means the offer was '
                        . 'rejected on this page earlier and carried no match, so it was not attached (and is not in added). '
                        . 'match_dropped means the module did not store the offer, so no decision was kept for it.',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'code' => array('type' => 'string'),
                            'unique_id' => array('type' => 'string'),
                            'message' => array('type' => 'string'),
                        ),
                    ),
                ),
                'decisions' => array(
                    'type' => 'object',
                    'description' => 'Only on a page with a product passport: the decisions this call recorded.',
                    'properties' => array(
                        'accepted' => array(
                            'type' => 'integer',
                            'description' => 'How many attached offers were recorded as the passport\'s product (items that carried a match).',
                        ),
                    ),
                ),
                'monetization' => array(
                    'type' => 'object',
                    'description' => 'Offer module only: whether the added links became affiliate links.',
                    'properties' => array(
                        'monetized' => array('type' => 'integer'),
                        'unmonetized' => array('type' => 'integer'),
                        'domains_without_deeplink' => array(
                            'type' => 'array',
                            'items' => array('type' => 'string'),
                        ),
                    ),
                ),
            ),
        );
    }

    public function annotations(): array
    {
        return array('readonly' => false, 'destructive' => false, 'idempotent' => false);
    }

    public function checkPermission($input = null): bool
    {
        return PostScope::canEditPost($input);
    }

    public function execute(array $input): array
    {
        $post = PostScope::requirePost($input);
        $post_id = (int) $post->ID;
        $module_id = trim((string) ($input['module_id'] ?? ''));
        $items = is_array($input['items'] ?? null) ? array_values($input['items']) : array();
        $search_token = trim((string) ($input['search_token'] ?? ''));
        $group = self::readGroup($input);

        if (!$items)
        {
            throw new AbilityInputException("'items' must be a non-empty array of product objects.");
        }

        // Read from the raw refs: SearchTokenResolver::resolve() keeps only the unique_id and the
        // overrides of a ref, so the match would be lost once the items are resolved.
        $matches = self::matchByUid($items);
        $has_passport = ProductPassport::has($post_id);
        if ($matches && !$has_passport)
        {
            throw new AbilityInputException('match needs a product passport on the page: call set-passport first.');
        }

        $manager = ModuleManager::getInstance();
        if (!$manager->moduleExists($module_id))
        {
            throw new AbilityInputException(
                "Unknown module '{$module_id}'. Call content-egg/list-modules for available module ids."
            );
        }
        try
        {
            $parser = ModuleManager::parserFactory($module_id);
        }
        catch (\Exception $e)
        {
            throw new AbilityInputException(
                "Module '{$module_id}' is not available in this build."
            );
        }

        // Activity-independent product check (isProductParserModule() is
        // gated on active state, which would defeat auto-activation below).
        if ($parser->getParserType() !== ParserModule::PARSER_TYPE_PRODUCT)
        {
            throw new AbilityInputException("Module '{$module_id}' is not a product module.");
        }

        if (!$parser->isSearchable())
        {
            if ($search_token !== '')
            {
                throw new AbilityInputException(
                    "search_token cannot be used with '{$module_id}': it is a manual module whose items "
                        . 'are constructed by the caller, not taken from a search. Pass full item objects instead.'
                );
            }

            // Manual module (Offer): auto-activate on first use, mirroring
            // PostProductsRestController::add_items.
            if (!$manager->isModuleActive($module_id))
            {
                $manager->activateModule($module_id);
            }

            $prepared = array();
            foreach ($items as $i => $item)
            {
                if (!is_array($item))
                {
                    throw new AbilityInputException("items[{$i}] must be an object.");
                }

                $clean = ProductDataService::applyFieldUpdates(array(), $item);
                $clean['unique_id'] = \sanitize_text_field((string) ($item['unique_id'] ?? ''));

                // The stored unique_id is the sanitized one; the match follows it.
                $raw_uid = (string) ($item['unique_id'] ?? '');
                if ($raw_uid !== $clean['unique_id'] && isset($matches[$raw_uid]))
                {
                    $matches[$clean['unique_id']] = $matches[$raw_uid];
                    unset($matches[$raw_uid]);
                }

                if ($clean['unique_id'] === '' || empty($clean['title']) || (empty($clean['url']) && empty($clean['orig_url'])))
                {
                    throw new AbilityInputException(
                        "items[{$i}] requires unique_id, title and url (or orig_url)."
                    );
                }

                if (empty($clean['orig_url']) && !empty($clean['url']))
                {
                    $clean['orig_url'] = $clean['url'];
                }

                // Seed the full field skeleton OfferModule::presavePrepare()
                // reads: sparse items trigger PHP 8 undefined-key warnings
                // and url-less items are silently dropped at save time.
                $clean += array(
                    'subtitle' => '',
                    'badge' => '',
                    'badge_color' => '',
                    'description' => '',
                    'img' => '',
                    'logo' => '',
                    'ratingDecimal' => '',
                    'order' => 0,
                    'price' => '',
                    'priceOld' => '',
                    'merchant' => '',
                    'domain' => '',
                );

                // The top-level group covers constructed items too; an item
                // that carries its own group keeps it.
                if ($group !== '' && !array_key_exists('group', $clean))
                {
                    $clean['group'] = ProductDataService::sanitizeGroup($group);
                }

                $prepared[] = $clean;
            }
            $items = $prepared;
        }
        elseif (!$manager->isModuleActive($module_id))
        {
            throw new AbilityInputException(
                "Module '{$module_id}' is not active. An administrator can activate it with content-egg/activate-module."
            );
        }
        else
        {
            // Active searchable module (Amazon, Ebay, feeds…): keep each source
            // item intact, but apply any per-item "overrides" — editorial fields
            // and group only (OVERRIDE_FIELDS; any other key is rejected) — so
            // identity and commerce data stay untouched.
            if ($search_token !== '')
            {
                $resolved = SearchTokenResolver::resolve($search_token, $items, $module_id);
                $items = $resolved['items'];
            }
            $items = $this->applyItemOverrides($items, $group);
        }

        // A page with a passport is curated: an offer rejected earlier is not attached again unless the
        // caller vouches for it with a match. Dropped here, not by saveData(): addItems() reports `added`
        // before it saves, so an offer saveData() dropped would still be reported as added.
        $warnings = array();
        if ($has_passport)
        {
            $split = self::skipRejected($items, $module_id, OfferDecisions::rejectedKeys(OfferDecisions::all($post_id)), $matches);
            $items = $split['kept'];
            foreach ($split['skipped'] as $skipped_uid)
            {
                $warnings[] = array(
                    'code' => 'rejected_skipped',
                    'unique_id' => (string) $skipped_uid,
                    'message' => 'This offer was rejected on this page earlier, so it was not attached. '
                        . 'Pass match with the item to attach it again.',
                );
            }
        }

        $warnings = array_merge($warnings, self::checkItemCompleteness($items));

        RevisionGuard::check($post_id, $module_id, (string) ($input['revision'] ?? ''));

        // The accepted decision goes in BEFORE the save: ContentManager::saveData() drops the rejected
        // offers of a passport page, and this offer may be one the page rejected earlier.
        $prior_decisions = array();
        $entries = array();
        $decided = array();
        if ($has_passport && $matches)
        {
            $prior_decisions = OfferDecisions::all($post_id);
            foreach ($items as $item)
            {
                $uid = (string) ($item['unique_id'] ?? '');
                if ($uid === '' || !isset($matches[$uid]))
                {
                    continue;
                }

                $key = OfferDecisions::key($module_id, $uid);
                $entries[$key] = OfferDecisions::entry(
                    OfferDecisions::STATUS_ACCEPTED,
                    $matches[$uid]['method'],
                    $matches[$uid]['reasons'],
                    $item,
                    (int) \get_current_user_id(),
                    time()
                );
                $decided[$key] = $uid;
            }
            OfferDecisions::put($post_id, $entries);
        }

        // No keyword is ever passed from here. A module's auto-update keyword
        // makes the scheduler re-run the search and REPLACE that module's
        // product list — which would strand every product_ref an agent bound
        // its blocks to, days later, with the article silently rendering empty
        // containers. It is a deliberate, human-only setting (the Product
        // Manager's Settings tab, or the classic metabox), not something an
        // agent should switch on as a side effect of attaching products.
        $result = ProductDataService::addItems($post_id, $module_id, $items, '');

        // An accepted decision stands only for an offer that is attached. A module may drop an item at
        // save time (the Offer module drops one with no usable link): its decision goes back to what it was.
        $accepted = 0;
        if ($decided)
        {
            $stored = array_flip(array_map('strval', array_column((array) $result['data'], 'unique_id')));
            $undo = array();
            foreach ($decided as $key => $uid)
            {
                if (isset($stored[$uid]))
                {
                    $accepted++;
                    continue;
                }

                $undo[] = $key;
                $warnings[] = array(
                    'code' => 'match_dropped',
                    'unique_id' => $uid,
                    'message' => 'The module did not store this offer, so no decision was recorded for it.',
                );
            }

            OfferDecisions::forget($post_id, $undo);
            $restore = array_intersect_key($prior_decisions, array_flip($undo));
            OfferDecisions::put($post_id, $restore);

            // The fingerprint was taken from the item as sent; the save may change it (a sanitized
            // title, or an offer already attached that keeps its stored copy). The decision vouches for
            // the stored offer, else the Product Manager would show it "Not verified" at once.
            OfferDecisions::put($post_id, self::storedFingerprints($entries, $decided, (array) $result['data']));
        }

        // The search that produced these offers is now acted on: log it.
        if ($has_passport && $search_token !== '')
        {
            DiscoveryRecorder::fromToken($post_id, $search_token);
        }

        $response = array(
            'post_id' => $post_id,
            'module_id' => $module_id,
            'added' => array_values((array) $result['added']),
            'count' => count((array) $result['data']),
            'revision' => (string) ProductDataService::revision($post_id, $module_id),
        );

        if ($has_passport)
        {
            $response['decisions'] = array('accepted' => $accepted);
        }

        if ($warnings)
        {
            $response['warnings'] = $warnings;
        }

        // Manual Offer module: report whether each newly added link became an
        // affiliate link. presavePrepare() rewrites `url` from the per-domain
        // deeplink rule; with no matching rule `url` stays equal to `orig_url`
        // (stored as-is, earns nothing). Surface it so the agent can warn the
        // user and name the domains that still need a deeplink configured.
        if (!$parser->isSearchable())
        {
            $added_ids = array_flip(array_map('strval', (array) $result['added']));
            $monetized = 0;
            $domains_without_deeplink = array();
            foreach ((array) $result['data'] as $item)
            {
                $uid = (string) ($item['unique_id'] ?? '');
                if ($uid === '' || !isset($added_ids[$uid]))
                {
                    continue;
                }

                $url = (string) ($item['url'] ?? '');
                $orig = (string) ($item['orig_url'] ?? '');
                if ($url !== '' && $url !== $orig)
                {
                    $monetized++;
                }
                elseif ($domain = (string) ($item['domain'] ?? ''))
                {
                    $domains_without_deeplink[$domain] = true;
                }
            }

            $response['monetization'] = array(
                'monetized' => $monetized,
                'unmonetized' => count((array) $result['added']) - $monetized,
                'domains_without_deeplink' => array_keys($domains_without_deeplink),
            );
        }

        return $response;
    }

    /**
     * The `match` objects of the item refs, by unique_id: [unique_id => {method, reasons}].
     * A ref that is a plain unique_id string, or an object without `match`, has none. Pure.
     *
     * `match` says the caller checked the offer is the page's product (its passport):
     * method is how (gtin, mpn, agent or user), reasons are short plain-English notes.
     *
     * @param array $refs the raw `items` of the call
     * @return array
     * @throws AbilityInputException naming the item with a bad match
     */
    public static function matchByUid(array $refs): array
    {
        $matches = array();
        foreach (array_values($refs) as $i => $ref)
        {
            if (!is_array($ref) || !isset($ref['match']))
            {
                continue;
            }

            $match = $ref['match'];
            if (!is_array($match))
            {
                throw new AbilityInputException("items[{$i}].match must be an object {method, reasons?}.");
            }

            $uid = (isset($ref['unique_id']) && is_scalar($ref['unique_id'])) ? (string) $ref['unique_id'] : '';
            if ($uid === '')
            {
                throw new AbilityInputException("items[{$i}] carries a match, so it needs a unique_id.");
            }

            $method = isset($match['method']) && is_string($match['method']) ? $match['method'] : '';
            if (!in_array($method, OfferDecisions::METHODS, true))
            {
                throw new AbilityInputException(
                    "items[{$i}].match.method must be one of: " . implode(', ', OfferDecisions::METHODS) . '.'
                );
            }

            $reasons = array();
            if (isset($match['reasons']))
            {
                if (!is_array($match['reasons']))
                {
                    throw new AbilityInputException("items[{$i}].match.reasons must be a list of short strings.");
                }
                foreach ($match['reasons'] as $reason)
                {
                    if (is_scalar($reason) && !is_bool($reason))
                    {
                        $reasons[] = (string) $reason;
                    }
                }
            }

            $matches[$uid] = array('method' => $method, 'reasons' => $reasons);
        }

        return $matches;
    }

    /**
     * The accepted entries whose offer, as stored, no longer matches the fingerprint taken from the
     * item as sent, each with the fingerprint of the stored offer. Entries whose offer was not
     * stored, or is unchanged, are left out. Pure.
     *
     * @param array $entries "<module_id>:<unique_id>" => OfferDecisions::entry()
     * @param array $decided "<module_id>:<unique_id>" => unique_id
     * @param array $stored  the module's items as stored (ContentManager::getData())
     * @return array "<module_id>:<unique_id>" => entry
     */
    public static function storedFingerprints(array $entries, array $decided, array $stored): array
    {
        $by_uid = array();
        foreach ($stored as $index => $row)
        {
            $row = (array) $row;
            $uid = (isset($row['unique_id']) && is_scalar($row['unique_id']) && (string) $row['unique_id'] !== '') ? (string) $row['unique_id'] : (string) $index;
            $by_uid[$uid] = $row;
        }

        $out = array();
        foreach ($decided as $key => $uid)
        {
            $uid = (string) $uid;
            if (!isset($entries[$key]) || !isset($by_uid[$uid]) || !OfferDecisions::fingerprintChanged($entries[$key], $by_uid[$uid]))
            {
                continue;
            }

            $entry = $entries[$key];
            $entry['fp'] = OfferDecisions::fingerprint($by_uid[$uid]);
            $out[$key] = $entry;
        }

        return $out;
    }

    /**
     * Split the items of a passport page into those to attach and the unique_ids of the rejected
     * offers to skip. An offer is skipped when its key is rejected and the caller gave no match for
     * it. Order is kept. Pure.
     *
     * @param array    $items         resolved items, each with a unique_id
     * @param string[] $rejected_keys OfferDecisions::rejectedKeys(): "<module_id>:<unique_id>"
     * @param array    $matches       matchByUid()
     * @return array {kept: array[], skipped: string[]}
     */
    public static function skipRejected(array $items, string $module_id, array $rejected_keys, array $matches): array
    {
        $rejected = array_flip(array_map('strval', $rejected_keys));
        $kept = array();
        $skipped = array();

        foreach ($items as $item)
        {
            $uid = is_array($item) ? (string) ($item['unique_id'] ?? '') : '';
            if ($uid !== '' && isset($rejected[OfferDecisions::key($module_id, $uid)]) && !isset($matches[$uid]))
            {
                $skipped[] = $uid;
                continue;
            }

            $kept[] = $item;
        }

        return array('kept' => $kept, 'skipped' => $skipped);
    }

    /**
     * Reject any override key outside OVERRIDE_FIELDS. The ability promises
     * "editorial fields only"; ProductDataService::applyFieldUpdates alone would
     * happily rewrite price, url, ean or stock_status on an item that came from the
     * server's stored search result, and silently drop keys it does not know.
     * Failing loudly names every offending key and lists the allowed ones, so a
     * caller can correct the call instead of believing a rejected edit landed.
     *
     * @throws AbilityInputException
     */
    public static function validateOverrides(array $overrides): void
    {
        $rejected = array();
        foreach (array_keys($overrides) as $key)
        {
            if (!in_array((string) $key, self::OVERRIDE_FIELDS, true))
            {
                $rejected[] = (string) $key;
            }
        }

        if ($rejected)
        {
            throw new AbilityInputException(
                'overrides cannot set: ' . implode(', ', $rejected) . '. Price, url, identifiers and stock '
                    . 'come from the search result and are not editable here. Allowed override fields: '
                    . implode(', ', self::OVERRIDE_FIELDS) . '.'
            );
        }
    }

    /**
     * Fold the call-level group into one item's overrides. A group the item's own
     * overrides already carry wins; an empty call-level group changes nothing.
     */
    public static function overridesWithGroup(array $overrides, string $group): array
    {
        if ($group !== '' && !array_key_exists('group', $overrides))
        {
            $overrides['group'] = $group;
        }

        return $overrides;
    }

    /**
     * The call-level `group`: trimmed, '' when absent. The schema caps it at
     * GROUP_MAX_LENGTH, but a schema is advice to a client, so enforce it here.
     *
     * @throws AbilityInputException
     */
    private static function readGroup(array $input): string
    {
        if (!isset($input['group']))
        {
            return '';
        }
        if (!is_string($input['group']))
        {
            throw new AbilityInputException("'group' must be a string.");
        }

        $group = trim($input['group']);
        if (mb_strlen($group) > self::GROUP_MAX_LENGTH)
        {
            throw new AbilityInputException(
                "'group' must be at most " . self::GROUP_MAX_LENGTH . ' characters.'
            );
        }

        return $group;
    }

    /**
     * Apply each item's optional "overrides" (plus the call-level group) onto its
     * source item. Only OVERRIDE_FIELDS are accepted — validateOverrides() rejects
     * anything else, and the same list is handed to
     * ProductDataService::applyFieldUpdates as its $allowed filter — so unique_id,
     * ASIN/locale and commerce data cannot be changed here. The `overrides` key
     * itself is stripped before save.
     */
    private function applyItemOverrides(array $items, string $group = ''): array
    {
        $out = array();
        foreach ($items as $i => $item)
        {
            if (!is_array($item))
            {
                throw new AbilityInputException(
                    "items[{$i}] must be an object. To reference search results by unique_id, "
                        . 'also pass the search_token from the search response.'
                );
            }

            $overrides = (isset($item['overrides']) && is_array($item['overrides'])) ? $item['overrides'] : array();
            // Neither belongs in the stored item: overrides are applied below, match is a decision (matchByUid()).
            unset($item['overrides'], $item['match']);

            try
            {
                self::validateOverrides($overrides);
            }
            catch (AbilityInputException $e)
            {
                throw new AbilityInputException("items[{$i}]: " . $e->getMessage(), 0, $e);
            }

            $overrides = self::overridesWithGroup($overrides, $group);

            if ($overrides)
            {
                $item = ProductDataService::applyFieldUpdates($item, $overrides, self::OVERRIDE_FIELDS);
            }

            $out[] = $item;
        }

        return $out;
    }
}
