# Page building

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
>   **`[content-egg-block template=offers_logo]`** shortcode instead of the block
>   (same families). Write the values unquoted: inside a core/markdown node
>   quotes are escaped, which breaks the shortcode, and template ids contain no
>   spaces. Don't generate bare `[content-egg]` module shortcodes.
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

## Build a page (the LEGO loop)
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

## Edit an existing page
get-post-blocks -> modify the tree -> validate-blocks (post_id) ->
insert-blocks (mode append/at_index/replace_at_index/replace_all).
To reorder or edit ONE block in place (e.g. reorder an Egg Block's products by
reordering its items), replace just that block: insert-blocks mode=replace_at_index
with the block's index from get-post-blocks — no need to rewrite the whole post.
(reorder-products only affects the content-egg/products block + comparison
templates via order_num, not Egg Blocks.)

## Finish a post (featured image, publish/schedule)
find-posts (locate the post_id — by search, status or has_module) ->
set-featured-image (an attachment_id, or an image_url, e.g. one from
search-images) -> set-post-status. Publishing is outward-facing and hard to
undo — confirm with the user first. Schedule with status "future" + a future
date_gmt (UTC) or date (site time).
