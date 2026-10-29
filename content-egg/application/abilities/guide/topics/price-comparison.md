# Price comparison

A comparison page shows one product's offers from several shops — a post, or
a WooCommerce product (also load `woocommerce`). The hard part is matching:
is each offer really the same product? A wrong product attached silently is
worse than a missing shop: when the data is not enough, ask the user.

## The passport
Every comparison page carries a product passport: the one product (and
variant) it compares. It lives on the page (set-passport, get-passport), so a
later session continues from it. It holds `gtins` (`{code, source,
primary}`), `brand`, `mpn`, `model` (at least one GTIN, an MPN or a model),
`variant` (`{"Storage": "256 GB", "Color": "any"}`), `conditions` (default
`["new"]`) and `reference` (`{price, currency, img}`). set-passport replaces
the whole passport: read it first and pass every field you keep.

Source rule: every GTIN names where you read it — the http(s) URL of the
page, `offer:<module_id>:<unique_id>` for the offer it came from (one from
your searches, attached or not yet), or `user`. An MPN or model may come from
an offer's `mpn`/`model` field or its Part Number / Model feature row.
Never write an identifier from memory; restricted, coupon and placeholder
codes are refused. `reference.price` likewise comes only from the
identified product's own offer or page, or from the user — never guessed: a
guessed anchor can open `strong_match`. Browser and web search are for
discovery and verification (a shop's product page, a GTIN or MPN on a
manufacturer page, images in a dispute); offers reach a page only through a
module.

An offer can list several GTINs: make the primary one the code the brand
itself uses (the brand's GS1 prefix, shared by its other sizes), and add
another shop's code for the same model and market as a second GTIN rather
than asking.

One page = one variant ("iPhone 15 256 GB"); ask which one when there are
several. A dimension that does not matter is "any": a Color = any page
accepts every color, as long as each offer's color is visible (its title, or
overrides.badge). Write units with the value ("256 GB", "150 ml") and a pack
count as `"Pack": "2"`; no pack count means one unit.

## Where to search, in this order
list-modules `{"only_active": true, "type": "product"}`: its `capabilities`
say what each module can do. Search with `fields: "match"`: the identity
fields you compare.

1. Feed modules (`is_feed`, `cost` "local"): free and exact; by GTIN, URL or
   `id:<feed product id>` as their `search_by` allows.
2. API modules whose `search_by` includes "gtin": by the GTIN.
3. Price-comparison modules (`price_comparison`: many shops per request), by
   GTIN or URL.
4. API modules without GTIN search: keyword search by the exact model name.
5. Affiliate Egg shops (`cost` "scrape"): find the product page on the
   module's `shop_domain` with your browser or web search (the URL is
   enough) and search by it; keyword
   search only as a last resort. With no web access at all, skip a shop
   whose search a notice says loads with JavaScript and record it under
   `searched`.

A GTIN, URL or native id goes in `keyword`; when `search_mode` comes back
"keyword", it was searched as text: judge those results as keyword hits. Use
a small `limit`. One search per module and identifier per session: reuse the
`search_token` (about 30 minutes); about 30 searches a minute; prefer
`default_locale` unless the page targets another market (`locales`). A search
with no results issues no token: note its module, query and count.

## A. New page
Check the settings first and create the page before discovery, so a blocking
setting surfaces before any quota is spent and the search tokens are fresh
when you attach.

1. Preflight once per site, before any search: for a product page get-status
   `woocommerce` — `products_enabled` must be true and
   `auto_sync.off_reason` null (see `woocommerce`); when not, tell the user
   and stop. Then the template the user wants (none named: the
   recommended product in `woocommerce`, on a post `price_comparison`);
   `logo_source` and `exclude_non_new_offers` (get-settings "General
   settings").
2. Identify the product with one search (the shop the user named, or the
   module most likely to carry it): its GTIN, brand, MPN, model, price. Ask
   which variant when there are several.
3. create-post as a draft without `products`: the `content-egg/products`
   block (on a WooCommerce product the shortcode, see `woocommerce`) with a
   comparison template — `price_comparison`, `price_comparison_card`,
   `offers_logo_btn`, `offers_logo`, `offers_logo_shipping` or `offers_list`
   (`offers_logo_groups`, `offers_logo_shipping_groups`, `offers_list_groups`
   for labeled groups) — plus `price_history`, `price_statistics` and
   `price_alert`. On a product add `"woo": {"type": "external"}` and an
   existing category that fits (none fits: leave it and say so; create one
   only when the user asks); set-passport writes the GTIN. When the offers come in more
   than one currency, add `currency=<the page's currency>` to each shortcode
   (block attribute `currency`) so visitors see one currency.
4. set-passport, its fields at the top level:
   `{"post_id": 12, "gtins": [{"code": "<GTIN from the offer>", "source":
   "offer:<module_id>:<unique_id>", "primary": true}], "brand": "<brand>",
   "mpn": "<MPN>", "model": "<model>", "variant": {}, "conditions": ["new"],
   "reference": {"price": <that offer's price>, "currency": "USD"}}`.
   When the product's name has a bundle word ("Combo Kit"), the page title
   tells match-offers that the page is that bundle.
   On a product, tell the user when `woo_gtin.status` is "duplicate"
   (another product holds the code) or "unsupported" (no GTIN field:
   WooCommerce older than 9.2).
5. Discover in the order above; the identifying search counts (reuse its
   token).
6. match-offers `{"post_id": 12, "candidates": [{"search_token": "…",
   "unique_ids": ["…"]}]}` (at most 10 tokens, 100 candidates): it uses the
   stored passport and returns `results`, one per candidate. Without a post,
   pass it inline as
   `"passport": {"gtins": […], "brand": …}`. The only price anchor of a new
   page is `reference.price`: without it nothing is `strong_match`.
7. Decide every candidate with the bands and the rubric; look at images when
   needed.
   - Out of stock: an offer whose identity is confirmed is attached anyway.
     It shows as out of stock, and the price refresh keeps checking it.
   - One shop, one offer: when two modules reach the same shop (Walmart and
     a price-comparison module's Walmart offer; Amazon and AmazonNoApi),
     attach it from the shop's own module, else from the first that
     confirms it, and leave the duplicate unrecorded.
   - Marketplace modules (`capabilities.marketplace`, such as eBay): attach
     at most 3 offers per module unless the user asks for another number —
     the cheapest new ones in stock, one per seller (`seller`). Leave the
     other matching listings unrecorded: they are the same product, and a
     later discovery may pick them when prices change.
8. Show one review table: image, title, shop, price, band, verdict, reason.
   Wait for the user's answer on the "ask" rows. Unattended (a scheduled job,
   or the user said not to wait): attach only the "accept" rows, leave the
   "ask" rows unrecorded (a rejection would hide them for good) and list
   them in your final report.
9. add-products-to-post per module: the search_token and, per accepted
   offer, `{"unique_id": "…", "match": {"method": "gtin", "reasons": […]}}`
   (method gtin or mpn when a code decided, agent for your judgment, user for
   the user's). This logs the search.
10. set-offer-decisions: the rejected offers (`{module_id, unique_id,
    status: "rejected", method, reasons}`) with the search_token they came
    from (one per call, before it expires), and `searched`
    `{module_id, query, results}` for each search no token logged (no
    results, or nothing attached or rejected from it).
11. Verify: get-post-products (`fields: "match"`) shows exactly the accepted
    offers, each `decision.status` "accepted"; on a product the price is the
    cheapest valid new offer (WooCommerce REST `/wc/v3/products/<id>` when
    you can call it). The shortcodes render only on the page itself: ask the
    user to check the draft's Preview.
12. Publish only with the user's confirmation (set-post-status).

## B. Extend an existing page
1. get-passport. When `passport` is null, build one. Start from its
   `woo_gtin` (the WooCommerce GTIN field; the sync may have copied it from an
   offer) and the offers' GTINs (get-post-products `fields: "match"`): source
   `offer:<module_id>:<unique_id>` when an attached offer carries the code,
   `user` only when the user confirms it. Brand, MPN and model come from the
   offers and the title; ask the user when they disagree. Then set-passport,
   and tell the user that keyword updates for the page's product modules are
   now paused (`suspended_keywords`; `clear: true` undoes it).
2. Check the attached offers: match-offers `{post_id, attached: true}` ->
   set-offer-decisions ("accepted", or "rejected", which removes the offer:
   confirm first).
3. Discover only where due: a module whose `discovery` entry
   (get-passport) is missing, has `mode` "keyword" (only an identifier search
   counts), or is 30 days old or more.
4. match-offers `{post_id, candidates: [{search_token, unique_ids?}]}` (it
   needs `candidates` or `attached: true`; it uses the stored passport, and
   the accepted offers' median anchors the price) -> A 7–10.

Leave accepted offers alone unless there is a reason, and say what it is.

## C. Variant families
Pages that are variants of one model ("iPhone 15 128 GB", "… 256 GB") can be
linked as a family: each page shows a switcher to its siblings with their
lowest prices. get-family is free; set-family is Pro.
1. Every member needs a passport whose `variant` has a value for each
   dimension ("any" counts). Dimension names are the passports' variant keys;
   `schema` (`color`, `size`, `material`, `pattern`, `suggestedAge`,
   `suggestedGender`) only where the dimension truly is that property
   (Memory has none), each for one dimension only: only these go into
   Google's `variesBy`.
2. A page belongs to one family: get-family `{post_id}` first; to move it,
   `remove` it there, then `add` it.
3. set-family `{name, brand, dimensions: [{name, schema}], order: {"Memory":
   ["128 GB", "256 GB"]}, add: [post ids]}`: dimensions and values in the
   order visitors see them. Two pages with the same variant are refused,
   drafts included. `primary` stands for the family in shop lists (0 = lowest
   published). All problems are reported at once; nothing is written until
   there are none.
4. Changing a family needs edit rights on all its pages; adding or removing
   a page, on that page. A WooCommerce product joins only when `product` is
   among Content Egg's post types (else "not allowed to edit page N").
5. set-passport `clear: true` also takes the page out of its family
   (`left_family`); a new passport must still fit.
6. Check with get-family. Visitors never see drafts or trashed pages; a page
   restored from the trash leaves the family when it no longer fits; a
   family whose last page is deleted for good is deleted with it.
7. On a post put `eggb/variant-switcher` (`variant`: chips | list) directly
   above the offer list, or `[content-egg-variants style="chips|list"]`.
   WooCommerce products get it automatically ("Variant Switcher" setting);
   "Variant Families in Shop" = one product per family hides the other
   variants in shop lists (a family whose primary page is not in a category
   is missing from it; search always lists every variant).
8. Chips change one dimension at a time and a combination without a page is
   not clickable, so keep families dense: create the combinations that exist
   and use "any" for a dimension that does not vary on a page. For a sparse
   family (128/Black, 256/Blue, 512/Pink) use the list style.

## D. Recheck on request
audit-offers: no input audits the 20 most recently modified passport pages;
`passport_pages` `{limit, offset}` walks them all (`next_offset`); `post_ids`
names pages. Pages built before passports existed are never listed: find
them with find-posts (`has_module`) and pass their `post_ids`. Per finding:
- `no_passport`: build one as in B 1, telling the user keyword updates pause.
- `unverified` (no decision; often an offer a person added): match-offers
  `{post_id, attached: true}`, then record the verdict (set-offer-decisions).
- `fingerprint_changed`: an accepted offer's title or GTINs changed
  (`detail.was`, `detail.now`). Check it again; accept again or propose the
  rejection.
- `rejected_attached`: a rejected offer is on the page. Ask the user:
  reject it again (removes it) or accept it.
- `out_of_stock`: report it; price refresh keeps checking it.
- `price_outlier`: outside 60–167 % of the median of the page's priced
  offers in one currency (`detail.ratio`); needs 3 such offers; rejected and
  out-of-stock offers are left out. Apply the rubric; ask when the identity
  matches.
- `non_new`: a condition the passport does not accept. Propose the rejection.
- `discovery_due`: search the modules in `detail.modules` by the passport's
  GTIN, then as in B 4.
- `keyword_suspended`: that module's stored keyword is no longer searched.
  Tell the user; nothing to fix.

Take an offer off a passport page by rejecting it (set-offer-decisions): it
is removed and stays out. remove-products keeps no record, so the next
discovery proposes it again. Every removal needs the user's confirmation.
Never propose an offer whose `decision` is rejected.

## Bands
match-offers returns a band, signals and `reasons` per candidate:
- `strong_match`: identity established (a shared GTIN, or the same brand +
  MPN), nothing contradicts it, price 0.6–1.67 × the anchor, no bundle word
  in the title ("Bundle", "Combo", "with … Set", "Pack" that is not a count,
  or a second product joined with " + " or " & " after the MPN or model).
  Still read the title: the plugin knows no accessories, and bundles listed
  under the product's own GTIN often cost only a little more.
- `conflict`: a unit (memory, volume, weight, pack count), the condition or
  the brand contradicts the passport. Reject — unless the title shows the
  plugin misread the offer; then treat it as unclear.
- `unclear`: everything else. You decide with the rubric; ask when unsure.
  `mpn` "in_title" (the passport's MPN as a whole code in a title, never a
  longer code: X100 is not X100-T) is evidence for your judgment, not
  identity: with the brand and a sane price it supports an "agent" accept.

The anchor is the median of the page's accepted offers (two or more, in stock
and unchanged since they were accepted), else `reference.price`. Without an
anchor, or when no exchange rate is known for an offer's currency, the price
is not checked and nothing is `strong_match`. Other currencies are converted
with the site's daily ECB rates, as sorting and the WooCommerce price do. Units come from the title; feature rows
only fill a dimension the title lacks. Colors and other text values are yours
to compare. Reuse the `reasons` you agree with as the decision's reasons.

## What the site enforces on passport pages
- Keyword auto-update and refresh-post-products "listings" do not re-search
  product modules (`suspended_keywords`, `listings_suspended`); coupon lists
  still refresh by keyword; price refresh by id continues; Prefill skips the
  page.
- A rejected offer never returns through an automated save;
  add-products-to-post skips it (`rejected_skipped`) unless the item carries
  `match`, which replaces the rejection. Repeating a rejection is safe.
- A person adding an offer in the editor clears its rejection (their choice
  wins); it shows as `unverified` in the next audit.
- In the Product Manager an accepted offer, unchanged since, shows a green
  dot; other offers show nothing.
- get-post-products returns `decision` only when `has_passport` is true.

On a page without a passport never set a keyword auto-update or run
"listings": both replace the module's whole list by a keyword search.

## Condition
Every offer has `condition`: new, used, refurbished or unknown — from the
module or feed, or from a clear title word (refurbished, generalüberholt,
renewed, B-Ware, reconditionné, ricondizionato, gebraucht). A title word
wins over a module's "new" (Amazon Renewed listings report New); a module's
used or refurbished always stands. `unknown` counts as new.
- Default: reject used and refurbished offers (a condition the passport does
  not accept is a `conflict`).
- Only when the user asks: add them to the passport's `conditions` and attach
  them in their own labeled group (`group` "Refurbished" / "Used") under the
  new offers, with a groups template (e.g. `offers_logo_groups`). On a
  passport page they never set the best price, WooCommerce price, price
  history or alerts.
- No new offer in stock: the page shows the offers as out of stock; never fall
  back to a non-new offer.

## Matching rubric
Give every candidate one verdict — accept, reject or ask — with its reason.

- Compare every offer with the passport, never offers with each other.
- Strong evidence for: the same valid GTIN without contradictions; the same
  brand + MPN.
- Evidence against (blocking): a conflict in a variant value that is not
  "any" (memory, size, pack count, volume, generation, region); a condition
  the page does not allow; a different brand.
- A GTIN match loses to a contradicting essential attribute (marketplaces
  misuse GTINs). Different GTINs with the same brand + MPN + attributes are
  unclear (regional packaging), not different.
- Not a reason to reject: another currency (prices are compared after
  conversion) or a shop in another country. The product's own regional
  version (plug, region lock, carrier lock) is a variant value.
- Grey zone — you decide, and ask the user when unsure: accessories ("for",
  "für", "compatible", case/cover, charger…), bundles, model-name equivalence
  ("WH1000XM5" = "WH-1000XM5"; "S24" ≠ "S24+"), color names, price outliers.

Examples:
- 128 GB vs 256 GB -> reject.
- "Hülle für iPhone 15" at 12 € -> reject.
- Same GTIN, titled "Generalüberholt" -> reject (condition).
- Hue E27 single bulb vs "2er-Pack" -> reject.
- 150 ml vs 4×150 ml -> reject.
- WH-1000XM5 black vs silver on a Color = any page -> accept; the color must
  be visible.
- Switch OLED vs a Mario Kart bundle -> ask.
- Same MPN, EU vs UK GTIN -> ask.
- eBay listing with the right GTIN titled "charger for S24" -> reject.
- MacBook Air M2 2022 vs M3 2024 -> reject.
- Same GTIN at 35 % of the median price -> ask.
