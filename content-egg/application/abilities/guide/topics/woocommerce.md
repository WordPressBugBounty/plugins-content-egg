# WooCommerce

## Products are posts of type `product`
A WooCommerce product is a post with `post_type: "product"`: create-post
(`"post_type": "product"`), find-posts, get-post-products and
add-products-to-post work on it as on any post. WooCommerce must be active,
and `product` must be ticked in Content Egg's `post_types` setting
(get-settings, section "General settings") — otherwise create-post rejects
the type. get-status `woocommerce.products_enabled` reports it: when false,
tell the user before you search for anything (the fix is ticking Products
under Post Types; with permission, update-settings on `post_types`).

The WooCommerce product editor is normally the classic editor, so add live displays to the product
content with the `[content-egg-block template=…]` shortcode (the same template
ids as the `content-egg/products` block, listed in `price-comparison` A 3),
never the block. In a create-post block tree, put the shortcodes in a
`core/markdown` node, each separated from the next by a blank line (single
newlines collapse into one paragraph), and leave the values unquoted, because
markdown escapes quotes, which breaks the shortcode:

```json
{"type": "core/markdown", "attrs": {"markdown": "## Compare prices\n\n[content-egg-block template=offers_logo_btn]\n\n## Price history\n\n[content-egg-block template=price_history]\n\n[content-egg-block template=price_statistics]\n\n[content-egg-block template=price_alert]"}}
```

Check
`woocommerce_shortcode_single` (section WooCommerce) first: when set, the site
already adds that shortcode to every product page, so don't add it twice.

## Check the price sync setup
Content Egg sets the product's price (and, on an external product, the Buy
link) from one attached offer; you never set the price yourself. Before
building products, read get-status `woocommerce` (null: WooCommerce is not
active) and report to the user any setup under which prices would not
update. `auto_sync.off_reason` is the preflight:

- `no_modules`: no module is in the automatic sync scope (usually
  `woocommerce_sync_scope` "selected" with an empty `woocommerce_modules`
  list; offers marked for manual sync still sync). Offer the switch to `"all"`
  (update-settings `{"settings": {"woocommerce_sync_scope": "all"}}`) and
  confirm first: from then on every product with Content Egg offers gets its
  price from them.
- `manual_only`: `woocommerce_product_sync` is "manually": nothing syncs
  unless an offer is marked for sync by hand. For a comparison page
  "min_price" is the right value — the cheapest offer sets the price.
- null: the sync is on. `auto_sync.module_ids` lists the modules that set
  prices: offers of a module not in it never set the price (with "selected"
  add it to `woocommerce_modules`; with "all" take it out of
  `woocommerce_modules_exclude`).

Also read:
- `exclude_non_new`: true keeps used and refurbished offers from setting the
  price; false lets a cheap used offer become the product price (on a page
  with a product passport they never set it).
- `sync_gtin`: true copies the synced offer's GTIN into WooCommerce's GTIN
  field and clears it when that offer has none, so the field follows the
  cheapest offer. On a page with a product passport the sync never writes or
  clears it: the field is the passport's primary GTIN (set-passport).
- `gtin_field`: false on WooCommerce older than 9.2, which has no GTIN field.

Settings live in get-settings section "WooCommerce". Checkbox lists take a
plain JSON list of module ids, e.g.
`{"woocommerce_modules": ["Amazon", "Feed__1"]}`. Change settings only with the
user's confirmation.

## Product fields
create-post with `"post_type": "product"` takes a `woo` object, and
update-woo-product changes the same fields later (fields you leave out stay):
- `type`: "external" (the Buy button follows the synced offer's link; the
  right type for a comparison page) or "simple", the default. The type of a
  variable or grouped product is left to the product editor.
- `categories`: existing product category ids or exact names; they replace
  the current ones. Categories are not created: an agent that can call
  arbitrary URLs (see "Beyond Content Egg" in core) creates them at
  `{site}/wp-json/wp/v2/product_cat`; others ask the user.
- `gtin`: from a cited page, never from memory; a code another product holds
  is not written and comes back as a warning. On a page with a product
  passport update-woo-product refuses it: set-passport writes the passport's
  primary GTIN instead. Without a passport and with `sync_gtin` on, the next
  price sync replaces it (a warning says so): tell the user.
- `catalog_visibility`: visible, catalog, search or hidden.

Never the price, stock or SKU: Content Egg syncs the price from the offers.

## Recommended comparison product
A comparison list — `[content-egg-block template=offers_logo_btn]` or
`template=price_comparison` — plus `price_history`, `price_alert` and
`price_statistics`, each as its own shortcode (a blank line between them).
Which offers to attach is the `price-comparison` topic.
