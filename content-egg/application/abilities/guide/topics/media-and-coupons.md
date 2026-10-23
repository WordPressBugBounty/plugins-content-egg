# Images, videos and coupons

## Add images, videos or coupons
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

create-post's `products` payload takes coupon, image and video modules too
(module_id => unique_ids, with `search_tokens`), so a new post can be created
with them attached in one call.
