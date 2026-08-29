# Content Egg — Agent Guide

Agent-facing API for the Content Egg WordPress plugin, built on the WordPress
Abilities API.

## Connect

- REST, canonical: `GET {site}/wp-json/wp-abilities/v1/abilities` (discovery),
  `GET|POST .../wp-abilities/v1/abilities/{name}/run` (read = GET with
  `input[...]` query params; write = POST with JSON body `{"input": {...}}`).
- REST, all-POST: `POST {site}/wp-json/content-egg/v1/abilities/{name}/run` with
  the arguments at the **top level** of the JSON body, e.g.
  `{"post_id": 12, "mode": "append", "blocks": [...]}` — same abilities, same
  permission checks and activity logging, but **every** ability is a POST, reads
  included. Prefer this from plain HTTP clients (curl, scripts, sandboxed
  assistants): nothing to serialize into `input[...]` query params, and it is
  unaffected by edge/WAF rules that block parameterized GETs. Posting a
  read-only ability to the canonical route instead returns 405
  `rest_ability_invalid_method`. This route also still accepts the canonical
  `{"input": {...}}` envelope, so existing integrations keep working; send one
  shape or the other, not a mix.
- Two route caveats. (1) Do not percent-encode the namespace slash: the path is
  `.../abilities/content-egg/get-status/run`; `content-egg%2Fget-status` returns
  an Apache 404 before WordPress sees it. (2) An ability this build does not
  include answers 402 `cegg_requires_pro` on the all-POST route but a plain 404
  on the canonical one — prefer the all-POST route when a call might be
  edition-gated.
- Auth (both routes): WordPress application password (HTTP Basic).
- OpenAPI: `GET {site}/wp-json/content-egg/v1/openapi`
- MCP (optional, requires the WordPress MCP Adapter **plugin** to be active):
  `{site}/wp-json/content-egg/mcp`. Check `mcp_adapter_present` from
  content-egg/get-status first — the route only exists when that reports true,
  and other plugins bundle the adapter library without serving MCP. When it is
  false the URL returns 404; use the REST routes above.

## Credentials

Every Content Egg ability is authenticated with a WordPress **application
password over HTTP Basic** — the same credential for the `wp-abilities` REST
endpoints, the OpenAPI connector, and MCP. The server enforces it: an
unauthenticated or under-permissioned call returns **401/403**.

If the transport has no credentials configured, **stop and ask the user** for a
WordPress username + application password (or to set up an authenticated
connector). Do not try to obtain or manufacture access another way — reading
`wp-config.php`, the database or option values, minting your own application
password, or calling the plugin's PHP directly. A 401/403 means *authentication
is required*, not *find another route*: stop and ask.

{capabilities}

## Choosing a block system

> **First — does the topic sell something?** This site runs Content Egg (an
> affiliate/product plugin), so "best X", "X vs Y", comparison, review and
> roundup topics are **commercial**: search for the relevant products
> (`search-products`), attach them, and build with **Egg Blocks** — a product
> comparison is **`eggb/comparison-table`**, never a Markdown table or one long
> prose block. Prose is only connective copy *between* blocks, never the whole
> article. Treating a comparison or review as "just informational" and returning
> plain prose is a mistake here — only skip products when the topic genuinely
> has none.

> **Then — are you authoring content, or embedding a display?**
> - **Authoring** (review, roundup, comparison — the agent produces titles,
>   verdicts, pros/cons, section copy): **Egg Blocks** (`eggb/*`). Fixed
>   attribute schemas the agent fills; content is a stored snapshot the agent
>   owns; only volatile commerce data (price, link, stock) hydrates live.
>   25+ editorial blocks beyond products, so whole article formats compose
>   from them.
> - **Embedding** (drop a live list of products, coupons, images or videos into
>   a post without authoring per-item content): the matching **live block** —
>   **`content-egg/products`**, **`content-egg/coupons`**, **`content-egg/images`**
>   or **`content-egg/videos`**. Each renders live from the module data attached
>   to the post (attach it first with the matching `add-*-to-post` ability);
>   choose an output template. Coupons/images/videos have no Egg Blocks
>   equivalent — a live block is the only way to compose them.
> - **Classic editor / WooCommerce / any non-Gutenberg post type:** use the
>   **`[content-egg-block template="…"]`** shortcode instead of the block (same
>   families). Don't generate bare `[content-egg]` module shortcodes.
>
> Litmus test: if the words on the page should change when the source data
> changes → the live block. If the words are editorial and should survive
> refreshes → Egg Blocks.

### The block palette

Every block you can compose a page from. Product and live-display blocks are the
commercial surface; the editorial blocks give a non-product article real
structure instead of a wall of prose.

{palette}

**Match each section's shape to a block.** When a section you are about to write
takes the shape of one of the editorial blocks, use that block instead of prose:

- a step-by-step how-to → `eggb/step-list`
- a set of questions and answers → `eggb/faq`
- a TL;DR near the top → `eggb/key-takeaways`
- "how we tested / reviewed" → `eggb/methodology`
- what-to-look-for buying advice → `eggb/criteria`
- term explanations → `eggb/definitions`
- correcting a misconception → `eggb/myth-fact`
- the opener / closer → `eggb/intro` / `eggb/conclusion`

Do not invent a section just to use a block — if the content genuinely is not
that shape, prose is correct, and a thin informational post may need very few
blocks. But when the shape is there, reach for the block: an FAQ rendered as
plain paragraphs, or a how-to written as one prose blob, is the mistake this
palette exists to prevent. Before composing a block, call `list-blocks` (with
its `type`) for the exact attributes, templates and item shapes.

### Block node shapes

A block tree is a flat list of nodes. There are four shapes, and
`get-post-blocks` returns whichever fits — send them back **exactly as returned**.
Any key outside a node's shape is rejected (`unknown_node_key`) rather than
dropped, so a wrong shape fails loudly instead of losing content silently.

| Shape | Keys | When |
| --- | --- | --- |
| Content Egg block | `type`, `attrs` (+ `product_ref` on `product-card` / `verdict` / `editorial-product` only) | `eggb/*` and `content-egg/*` — you author these |
| Prose | `type`, `attrs`, `html` | simple `core/paragraph` / `core/heading` / `core/list` — editable; `attrs` holds only `level` (heading) or `ordered` (list) |
| Opaque | `type`, `opaque: true`, `raw_markup` | classic content, third-party blocks, and any core block with inner blocks (including modern lists). **Round-trip verbatim; never hand-author one** |
| Markdown | `type: "core/markdown"`, `markdown` (or `attrs.markdown`) | authoring new prose; compiled to core blocks on save, so it comes back as prose or opaque nodes, never as `core/markdown` |

Two things that make a re-read differ from what you sent, neither an error:
rich-text attributes on `eggb/*` blocks are normalized (plain text gains a `<p>`
wrapper), and prose attributes the serializer cannot reproduce (`align`,
`className`, `anchor`) are dropped with an `unknown_attr` warning — if you need
them preserved, keep the node opaque instead.

## Workflows

### Audit a site
get-status -> list-modules -> get-settings (sections) -> get-module-settings

### Attach products to a post
search-products -> add-products-to-post (search_token + unique_ids) ->
get-post-products (keep the revision token for later edits)

Attach by reference: pass the search response's search_token plus the chosen
unique_ids as items — the server attaches its stored copy of each result, so
you never echo product JSON back (lean results suffice; fields="full" is only
for inspecting data). Tokens expire after ~30 minutes; on an "expired" error,
re-run the search and use the new token. To clean up a noisy source title or
add a badge, pass {unique_id, overrides} instead of a bare id: overrides
(title, subtitle, description, short_description, badge, badge_color, promo)
is applied on top of the source item and preserved across price/stock
refreshes. For heavy rewriting, use Egg Blocks instead (they own the copy as
a snapshot).

To compare across networks in one call, search-all-products (pass explicit
module_ids, up to 6) returns results grouped per module; one broken module
reports a per-module error instead of failing the call. Results are grouped,
not merged — compare them yourself.
Each module group with results returns its own search_token — one add-products-to-post
call per module. Use search-products for a single module.

### Add a custom / manual product (Offer module)
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

### Add images, videos or coupons
search-images / search-videos / search-coupons (one active module of that type;
module_id from list-modules) -> add-images-to-post / add-videos-to-post /
add-coupons-to-post with the response's search_token plus the chosen
unique_ids as items -> the matching live block
(`content-egg/images` / `content-egg/videos` / `content-egg/coupons`) or, in a
classic-editor post, `[content-egg-block]`. The attach step writes the data onto
the post; the block only renders what's attached — insert-without-attach shows
nothing. Each block has its own templates (list-blocks -> its `attributes.template`
enum). You can also just use the returned URLs/codes inline (e.g. a core embed)
without attaching. These families have no Egg Blocks equivalent.

### Connect a new affiliate shop (Affiliate Egg)
When the site has no module for a merchant you need, connect-shop creates one
from its domain: connect-shop (domain, e.g. "walmart.com") -> search-products
(new module_id) -> add-products-to-post. Requires the Affiliate Egg plugin
(clear error if absent). Use this for real affiliate coverage; use the Offer
module (above) only for one-off manual products.

Shops Affiliate Egg recognizes ("known") are searchable the moment they connect
— pass only the domain. A custom/unregistered domain (Affiliate Egg 11.0+) has
no built-in search, so it needs a `search_url` with `%KEYWORD%` where the term
goes, or keyword search silently won't work. Don't connect a custom domain
without one. Get it, in order of preference:
1. Supply it yourself when you know the shop's search pattern — from your own
   knowledge or research — e.g. goodeggs.com ->
   `https://www.goodeggs.com/search?q=%KEYWORD%`.
2. Otherwise ask the user to run any search on the shop and paste the results
   URL (e.g. `https://www.goodeggs.com/search?q=eggs`); connect-shop converts a
   real `?q=term` URL to the `%KEYWORD%` form for you.

Confirm the final search_url with the user before connecting — a wrong one
breaks search — then connect-shop (domain + search_url) -> search-products.

### Build a page (the LEGO loop)
list-blocks -> search-products -> compose a block tree -> validate-blocks
(iterate until valid) -> create-post (products payload + tree in one call)
-> preview-blocks / open edit_url

validate-blocks and preview-blocks take a POST body (the tree is too nested
for query params) and accept an optional `products` payload (module_id =>
search-products items), so a new product-bound draft fully schema-validates
BEFORE create-post. All three take `search_tokens` (module_id => search_token)
alongside it, so the whole loop runs on lean results: list unique_id strings in
`products` and never echo product JSON back. Rendering is a separate step: a product block only fills
with live price/image/link once the products are ATTACHED to a real post, so
preview-blocks renders it as an empty container and returns an
unhydrated_product_ref warning. Preview again after create-post (with post_id)
to see the hydrated block. product_ref for single-bound blocks (product-card,
verdict, editorial-product) may sit at the block top level or in attrs.product_ref — both resolve;
the top level is the tree form get-post-blocks returns, attrs is how the post
stores it.

### Edit an existing page
get-post-blocks -> modify the tree -> validate-blocks (post_id) ->
insert-blocks (mode append/at_index/replace_at_index/replace_all).
To reorder or edit ONE block in place (e.g. reorder an Egg Block's products by
reordering its items), replace just that block: insert-blocks mode=replace_at_index
with the block's index from get-post-blocks — no need to rewrite the whole post.
(reorder-products only affects the content-egg/products block + comparison
templates via order_num, not Egg Blocks.)

### Finish a post (featured image, publish/schedule)
find-posts (locate the post_id — by search, status or has_module) ->
set-featured-image (an attachment_id, or an image_url, e.g. one from
search-images) -> set-post-status. Publishing is outward-facing and hard to
undo — confirm with the user first. Schedule with status "future" + a future
date_gmt (UTC) or date (site time).

## Beyond Content Egg (core WordPress)

Featured image, publish/schedule and finding posts are first-class abilities now
— find-posts, set-featured-image, set-post-status — so reach for those; they run
through the same channel as every other ability (including OpenAPI/connector
agents). Anything else in core WordPress (categories/tags, excerpt, slug…) lives
in the full WordPress REST API at `{site}/wp-json/wp/v2/`, under the same
application password, no separate auth — but ONLY if you can call arbitrary
URLs. Check that before promising it:

- A direct HTTP client (e.g. Claude Code), or an MCP/connector that exposes
  WordPress core: yes, call `/wp/v2/` directly.
- A custom GPT or any OpenAPI Action agent: NO. You can only call operations
  that are in your schema, and core WordPress is not in Content Egg's. Say so
  rather than attempting it — the fix is for the user to add core WordPress as
  a SEPARATE action (its own schema, its own operation budget; Content Egg's
  schema stays untouched and survives plugin updates).

For example, once reachable, assign categories/tags via `/wp/v2/categories` +
`/wp/v2/tags` and the post's `categories`/`tags` arrays.

Post meta / custom fields (Rank Math, theme fields…) are writable through
`/wp/v2/posts/<id>` only when the plugin registered them with
`register_post_meta(..., show_in_rest => true)`. An UNREGISTERED key is not an
error: the write returns 200 and is silently discarded. Read
`/wp/v2/posts/<id>?context=edit` first — whatever is listed in its `meta`
object is what you can actually write.

## Conventions

- Errors are structured: `cegg_validation_failed` (400, self-correct from the
  message), `cegg_conflict` (409, re-read and retry with the new revision),
  `cegg_rate_limited` (429, respect retry_after), `cegg_feed_pending` (409,
  poll get-feed-status), `cegg_search_failed` (502, the module's API failed —
  e.g. a missing/invalid API key; relay the message to the user, do not retry
  blindly). Schema-level rejections carry a different code per transport — the
  all-POST route returns `cegg_validation_failed`, the canonical route returns
  `ability_invalid_input` (WordPress core's own code) — so branch on the 400
  status, not the code. Ability-level errors are identical on both.
- update-product edits any editable field (editor parity), but on API modules
  (Amazon/eBay) commerce fields (price, url, img, availability, stock) are
  refreshed automatically, so hand-edits there are transient; editorial fields
  (title, description, badge, rating…) persist. Feed re-imports may overwrite
  mapped columns.
- Secrets are masked on read (`••••…`) and must never be written back masked.
- `remove-products`, `insert-blocks`, `set-featured-image` and `set-post-status`
  are destructive/outward-facing — confirm with the user before calling
  (especially publishing, which is hard to undo).
- For a live display use the block (`content-egg/products` / `coupons` / `images`
  / `videos`) in the block editor, or `[content-egg-block template="…"]` for the
  classic editor / WooCommerce / any non-Gutenberg post type. Never generate bare
  `[content-egg]` module shortcodes. Live-block template ids are unprefixed
  (`offers_grid`, not `data_offers_grid`); read each block's `attributes.template`
  enum from list-blocks.
