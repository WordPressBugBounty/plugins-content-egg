# Feeds and shops

## Connect a new affiliate shop (Affiliate Egg)
When the site has no module for a merchant you need, connect-shop creates one
from its domain: connect-shop (domain, e.g. "walmart.com") -> search-products
(new module_id) -> add-products-to-post. Requires the Affiliate Egg plugin
(clear error if absent). Use this for real affiliate coverage; use the Offer
module (topic `products-and-modules`) only for one-off manual products.

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
The response's `searchable` flag says whether keyword search will work.

## Add a product feed
create-feed-module (feed_url) -> poll get-feed-status (the returned module_id,
e.g. "Feed__1") until its state is "completed" (or "failed") ->
search-products (that module_id) -> add-products-to-post. Format, compression,
encoding, delimiter, currency, merchant domain and name are detected from a
sample; pass one only to override a wrong detection. The import runs in the
background: don't wait, poll — a search while it runs returns
`cegg_feed_pending`. If required columns cannot be mapped automatically (no AI
key configured), the call is rejected with the detected columns: pass an
explicit `mapping` (product field => feed column) and retry. A feed is a local
catalog: searches cost no API quota (list-modules `capabilities`: `cost`
"local", `search_by`).
