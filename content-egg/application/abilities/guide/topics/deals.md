# Deals

## What a deal is
A deal is a post of type `cegg_deal`: **one offer from one store**. Its page
assembles itself — price, old price, discount, store, buy button, status,
price history, price alert, other offers and related deals — and the post
content you write shows on it as "Deal details". get-status `deals.enabled`
says whether Deals are turned on; when it is false, create-post (for
`cegg_deal`), find-deals and update-deal refuse with a 400 that says so. Turning
Deals on changes the site (the setting `deals_enabled` adds the Deal post type
and a deals archive), so ask the user to do it in Settings → Deals, or to give
you the go-ahead for update-settings; never switch it on unasked.

Every deal follows one attached product, its **pin**. The deal's price, old
price and discount always come from that product; they are never deal fields.
Pin a product from a module (search-products) for live prices that Content Egg
keeps updating, or a manual Offer item (see `products-and-modules`) for a store
without a module. An Offer price stays as you entered it unless the Offer
module's XPath price update is set for that domain.

## Status
A deal is `active` or `expired`. It expires, with this `reason`, when:

- `manual` — someone closed it (update-deal `state: "expired"`);
- `ended` — its end date passed;
- `unavailable` — the pinned product is gone from the post;
- `out_of_stock` — the pinned product is out of stock;
- `price_up` — the price rose above the base price by more than the site's
  threshold (get-status `deals.price_up_threshold`, percent).

`out_of_stock`, `unavailable` and `price_up` revive on their own when the
condition clears (unless `deals.auto_reactivate` is off); `manual` and `ended`
do not. update-deal `state: "active"` reopens any deal with today's price as
the new base price. Expired deals keep their page, greyed.

## Finding deals: find-deals
Look a deal up by its offer before creating one:

- `module_id` + `unique_id` (a search result) — `match: "key"`, the same offer;
- `url` — the product link; tracking parameters, `www.` and a trailing slash
  are ignored — `match: "url"`;
- `gtin` — `match: "gtin"`: the same product, possibly from another store.
  That is information, not necessarily a duplicate: a deal is one store's offer.

Without a lookup, find-deals lists deals by `status`, `post_status`, `store`,
`category`, `ends_before` and title `search`, sorted `new`, `discount`,
`popular` or `ending` — for example the active deals ending in the next day.

## Creating a deal: create-post with a deal object
create-post with `post_type: "cegg_deal"` and the product in `products`
creates the deal. The optional `deal` object sets:

- `pin` — `{module_id, unique_id}` of a payload product; default: the first
  product with a price;
- `ends` — an ISO 8601 end date, or `"offer"` (the default) to follow the
  store's own deal end time when the module provides one;
- `category` — a path such as `"Electronics > Tablets"` (missing levels are
  created) or a `deal_category` term id;
- `coupon` — `{code, note}`, shown next to the price;
- `featured_image` — `"product"` imports the pinned product's image. Optional:
  deal cards show the product image anyway;
- `allow_duplicate` — see below.

The store (`deal_store`) is set from the pinned product.

```json
{"title": "Fire HD 10 tablet drops to $72.99", "post_type": "cegg_deal",
 "blocks": [{"type": "core/markdown", "attrs": {"markdown": "Why this is a good price..."}}],
 "search_tokens": {"Amazon": "<token>"}, "products": {"Amazon": ["us-B0GVTPKJVT"]},
 "deal": {"category": "Electronics > Tablets"}}
```

If an **active** deal for the same offer exists, create-post fails with 409
`cegg_duplicate_deal` and `data.deal_id`, and nothing is written. Don't retry:
update the existing deal with update-deal, or pass `deal.allow_duplicate:
true` if a second deal is really wanted. When only an **expired** deal
matches, the deal is created and `warnings` names the old one, which
update-deal `state: "active"` could reopen instead.

## Changing a deal: update-deal
update-deal takes `post_id` and any of `pin` (an attached product), `ends`,
`category`, `coupon` (an empty code removes it), `state` (`"expired"` or
`"active"`) and `featured_image`. It returns the deal and `changed`, the fields
it wrote. To change the price, change the product: update-product or
refresh-post-products.

## End dates
A deal's end date has a source:

- `"offer"` (the default) — it follows the store's own deal end time from the
  pinned product, when the module has one (Amazon does), and moves with it.
  If the store extends a deal that already ended, the deal comes back;
- a date you set (ISO 8601) — kept as set, whatever the store does;
- `null` in update-deal — no end date, and store end times are ignored.

Reopening a deal with `state: "active"` also stops it following the store,
so a past store end time can't close it again. `ends: "offer"` switches back.

## A deals homepage
Deals already have an archive at `/deals/` (the slug is a setting), with
sorting and store and category filters; each store and category has its own
page. For a homepage, build a page from deal listings with the
`[content-egg-deals]` shortcode in a `core/markdown` node: create-post with
`post_type: "page"` (it must be in the `post_types` setting), each shortcode
separated from the next by a blank line, values unquoted. Options: `sort`
(`new`, `discount`, `popular`, `ending`), `limit` (1-48), `columns` (1-6),
`days` (only deals from the last N days), `store` and `category` (slugs,
comma-separated), `coupon_only=1`; expired deals are hidden by default.

```json
{"type": "core/markdown", "attrs": {"markdown": "## Hot right now\n\n[content-egg-deals sort=popular days=7 limit=8]\n\n## Biggest discounts\n\n[content-egg-deals sort=discount limit=8]\n\n## Ending soon\n\n[content-egg-deals sort=ending limit=4]\n\n[All deals](/deals/)"}}
```

Making it the site's front page is a core WordPress setting (`show_on_front:
"page"` and `page_on_front` in `/wp/v2/settings`, which needs manage_options):
it changes what every visitor sees first, so ask the user before you set it,
or leave it to them (Settings → Reading).

## Deal signals in search results
search-products, search-all-products and get-post-products add a `deal`
object to lean and match items when the module stores the store's own deal
facts: `badge` (e.g. "Limited time deal", "Clearance"), `starts` and `ends`
(ISO 8601 UTC), `access` (e.g. `all`, `prime_exclusive`), `percent_claimed`,
and `price_basis` with the store's `price_basis_label`. `price_basis` says
what the old price is: `was_price` — a price the product recently sold at
(Amazon labels it "Typical price" or "Was price"); `list_price` — the
manufacturer's list price, which says less about a real drop; `other`.
