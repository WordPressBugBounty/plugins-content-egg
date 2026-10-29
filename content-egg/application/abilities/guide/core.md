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

## Topics

Load core first, then the topic for your task. This core covers connecting,
credentials and the rules that apply everywhere; each topic holds the workflows
for one kind of task. Fetch a topic with content-egg/get-guide and
`{"topic": "<name>"}`, or `GET {site}/wp-json/content-egg/v1/agent-guide?topic=<name>`;
`"all"` returns the whole guide at once. A topic you have already loaded stays
valid while `guide_version` in content-egg/get-status is unchanged.

{topics}

## Beyond Content Egg (core WordPress)

Featured image, publish/schedule and finding posts are first-class abilities now
— find-posts, set-featured-image, set-post-status — so reach for those; they run
through the same channel as every other ability (including OpenAPI/connector
agents). Anything else in core WordPress (categories/tags, excerpt, slug…) lives
in the full WordPress REST API at `{site}/wp-json/wp/v2/`, under the same
application password, no separate auth — but ONLY if you can call arbitrary
URLs. Check that before promising it:

- A direct HTTP client — you run curl or Python yourself (Claude, Claude Code,
  ChatGPT Work, Codex) — or an MCP/connector that exposes WordPress core: yes,
  call `/wp/v2/` directly.
- A tool that only calls operations imported from Content Egg's OpenAPI
  document: NO. Core WordPress is not in that schema. Say so rather than
  attempting it — the fix is for the user to add core WordPress as a SEPARATE
  import, so Content Egg's schema stays untouched and survives plugin updates.

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
  `cegg_duplicate_deal` (409, an active deal for that offer exists — `data.deal_id`;
  don't retry: update-deal it, or set deal.allow_duplicate),
  `cegg_rate_limited` (429, respect retry_after), `cegg_feed_pending` (409,
  poll get-feed-status), `cegg_search_failed` (502, the module's API failed —
  e.g. a missing/invalid API key; relay the message to the user, do not retry
  blindly). Schema-level rejections carry a different code per transport — the
  all-POST route returns `cegg_validation_failed`, the canonical route returns
  `ability_invalid_input` (WordPress core's own code) — so branch on the 400
  status, not the code. Ability-level errors are identical on both. A 3xx or
  an HTML body comes from the host's proxy, not WordPress: retry a read once;
  before retrying a write, read the state to see whether it went through.
- Secrets are masked on read (`••••…`) and must never be written back masked.
- `remove-products`, `insert-blocks`, `set-featured-image` and `set-post-status`
  are destructive/outward-facing — confirm with the user before calling
  (especially publishing, which is hard to undo).
- For a live display use the block (`content-egg/products` / `coupons` / `images`
  / `videos`) in the block editor, or `[content-egg-block template=offers_logo]`
  for the classic editor / WooCommerce / any non-Gutenberg post type. Write
  shortcode values unquoted (template ids contain no spaces): inside a
  core/markdown node quotes are escaped, which breaks the shortcode. Never
  generate bare `[content-egg]` module shortcodes. Live-block template ids are
  unprefixed (`offers_grid`, not `data_offers_grid`); read each block's
  `attributes.template` enum from list-blocks.
