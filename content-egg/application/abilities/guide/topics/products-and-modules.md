# Products and modules

## Audit a site
get-status -> list-modules -> get-settings (sections) -> get-module-settings

list-modules reports each module's `capabilities`: what it can search by
(`search_by`: keyword / gtin / url / native_id, with `native_id_hint` such as
ASIN or `id:`), whether it compares many shops (`price_comparison`,
`multi_merchant`), whether one product comes back as many sellers' listings
(`marketplace`, such as eBay: 5 results unless you set `limit`), whether its
items carry a GTIN (`returns_gtin`), its `cost`
(local feed, api, or scrape with `requests_per_search`), filters and locales.
Pick modules from these instead of finding out by spending quota.

Changing a module (administrators): activate-module / deactivate-module,
update-module-settings (partial patch; keys from get-module-settings). Global
options: update-settings (partial patch; keys from get-settings, one section at
a time).

## Attach products to a post
search-products -> add-products-to-post (search_token + unique_ids) ->
get-post-products (keep the revision token for later edits: each module of a post has its own)

Attach by reference: pass the search response's search_token plus the chosen
unique_ids as items — the server attaches its stored copy of each result, so
you never echo product JSON back (lean results suffice; fields="full" is only
for inspecting data). Tokens expire after ~30 minutes; on an "expired" error,
re-run the search and use the new token. To clean up a noisy source title or
add a badge, pass {unique_id, overrides} instead of a bare id: overrides
(title, subtitle, description, short_description, badge, badge_color, promo,
group — editorial fields only; price, url, ean, stock and the like come from the
search result and are rejected) is applied on top of the source item and
preserved across price/stock refreshes. Pass `group` (e.g. "Black" or "Refurbished"; one page = one variant) to put
every attached offer in one product group; a per-item overrides.group wins.
For heavy rewriting, use Egg Blocks instead (they own the copy as a snapshot).

To compare across networks in one call, search-all-products (pass explicit
module_ids, up to 6) returns results grouped per module; one broken module
reports a per-module error instead of failing the call. Results are grouped,
not merged — compare them yourself.
Each module group with results returns its own search_token — one add-products-to-post
call per module. Use search-products for a single module.

Deciding whether offers from different shops are the same product (a price
comparison page) is its own topic: load `price-comparison`.

## Add a custom / manual product (Offer module)
For a product with no feed or affiliate API behind it (a hand-picked deal, a
merchant the site doesn't have a module for), use the built-in "Offer" module:
list-modules (find module_id "Offer"; it may be inactive) -> add-products-to-post
with module_id="Offer" and items you construct yourself, each needing unique_id,
title and url (or orig_url), plus optional price/currencyCode/description/img.
Offer auto-activates on first use — no separate activate-module call. It is a
product module, so the products block and Egg Blocks render it like any other.
Affiliate links are applied automatically from the Offer module's per-domain
deeplink rules; the response's `monetization` block reports how many links
became affiliate links and lists any `domains_without_deeplink` — relay those
to the user so they can add a deeplink rule in the Offer settings.

## Edit attached products

- update-product edits any editable field (editor parity), but on API modules
  (Amazon/eBay) commerce fields (price, url, img, availability, stock) are
  refreshed automatically, so hand-edits there are transient; editorial fields
  (title, description, badge, rating…) persist. Feed re-imports may overwrite
  mapped columns.
