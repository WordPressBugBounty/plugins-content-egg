<?php

namespace ContentEgg\application\BlockKit;

defined('\ABSPATH') || exit;

/**
 * Hints class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

final class Hints
{
    const DECISION_RULE = 'This is a Content Egg (affiliate/product) site: "best X", "X vs Y", comparison, '
        . 'review and roundup topics are commercial - search relevant products, attach them, and build with Egg Blocks '
        . '(a product comparison is eggb/comparison-table, never a Markdown table or one long prose block); '
        . 'plain prose is connective copy between blocks, never the whole article. Only skip products when the topic has none. '
        . 'Are you authoring content, or embedding a display? '
        . 'AUTHORING (review, roundup, comparison - you produce titles, verdicts, pros/cons, section copy): '
        . 'use Egg Blocks (eggb/*). Fixed attribute schemas you fill; content is a stored snapshot you own; '
        . 'only volatile commerce data (price, link, stock) hydrates live. 25+ editorial blocks compose whole article formats: '
        . 'match each section to the block whose shape it takes (how-to -> step-list, Q&A -> faq, TL;DR -> key-takeaways, '
        . 'buying advice -> criteria, how-we-tested -> methodology, myth correction -> myth-fact, opener/closer -> intro/conclusion) '
        . 'rather than writing it as prose; do not invent sections just to use blocks. '
        . 'EMBEDDING (drop a live list of products, coupons, images or videos into a post without authoring per-item content): '
        . 'use the matching live block - content-egg/products, content-egg/coupons, content-egg/images or content-egg/videos. '
        . 'Each renders directly from the plugin data attached to the post (attach it first with the matching add-*-to-post ability); pick an output template. '
        . 'In the classic editor or a non-Gutenberg post type, use the [content-egg-block] shortcode instead of the block. '
        . 'Litmus test: if the words on the page should change when the source data changes, use the live block; '
        . 'if the words are editorial and should survive refreshes, use Egg Blocks.';

    const PRODUCTS_HINT = 'Live product display: renders all product data directly from the plugin at view time. '
        . 'Best for embedding a product list/cards into a post without authoring per-product content. '
        . 'Pick a template; template ids are unprefixed (grid-style ids like offers_grid - never data_-prefixed module-settings values). '
        . 'By default it shows every product a module has on the post. Prefer attaching exactly the products you want and '
        . 'leaving it in show-all mode - do not over-attach and then hide. The one reason to narrow it: placing MULTIPLE '
        . 'product blocks on the same page, each showing a different subset of the attached pool. For that, give each block '
        . 'its own subset via selection_mode="products" plus their unique_ids in chosen_products (comma-separated); leave '
        . 'selection_mode empty to show all. To give specific products editorial treatment (custom titles, verdicts, '
        . 'pros/cons), bind them individually with Egg Blocks instead (product-card, quick-picks, comparison-table).';

    const COUPONS_HINT = 'Live coupon display: renders coupon data attached to the post directly from the plugin at view time. '
        . 'Attach coupons first with content-egg/add-coupons-to-post (items from content-egg/search-coupons). '
        . 'Pick a coupon template (unprefixed, e.g. coupons_ticket). By default it shows every coupon the post has; '
        . 'narrow it with the modules attribute. Coupons have no Egg Blocks equivalent - this block (or [content-egg-block]) is the way to show them.';

    const IMAGES_HINT = 'Live image display: renders image data attached to the post directly from the plugin at view time. '
        . 'Attach images first with content-egg/add-images-to-post (items from content-egg/search-images). '
        . 'Pick an image template (unprefixed, e.g. images). By default it shows every image the post has; narrow it with the modules attribute. '
        . 'Images have no Egg Blocks equivalent - this block (or [content-egg-block]) is the way to show them.';

    const VIDEOS_HINT = 'Live video display: renders video data attached to the post directly from the plugin at view time. '
        . 'Attach videos first with content-egg/add-videos-to-post (items from content-egg/search-videos). '
        . 'Pick a video template (unprefixed, e.g. videos_stacked). By default it shows every video the post has; narrow it with the modules attribute. '
        . 'Videos have no Egg Blocks equivalent - this block (or [content-egg-block]) is the way to show them.';

    const MARKDOWN_HINT = 'Free-form prose between blocks. Compiled to core paragraph/heading/list blocks; '
        . 'supports # headings, - lists, **bold**, *italic*, [links](url), `code`.';

    /**
     * What the elements of each eggb array attribute have to be.
     *
     * block.json declares these attributes as bare arrays, so nothing in the
     * schema said whether an element is a plain string or an object — and the
     * answer is per block and not guessable: eggb/intro's `points` takes
     * strings while eggb/key-takeaways' `items` takes {text} objects. Get it
     * wrong and the renderer produces an empty block (some fatal on the type
     * error, some skip every element), which the validator used to stamp
     * valid:true with no warning — a blank section shipped as a success. This
     * registry is what lets both the catalog publish the element kind and the
     * validator reject the wrong one.
     *
     * Kinds: 'string', 'object', or 'object:item'/'object:criteria' when the
     * allowed keys are the item_shape/criteria_shape published below. Each was
     * read off the block's own renderer, not inferred from the attribute name.
     *
     * Nested arrays inside an item (quick-picks items[].chips, comparison-table
     * criteria[].values) are not covered here; their shape is in the hint.
     */
    /**
     * Element shapes for the object arrays that no item_shape/criteria_shape
     * covers, because their block has more than one of them.
     *
     * Their keys previously lived only in prose hints, which had drifted from
     * the renderers: the pricing hint promised snake_case `cta_label` where the
     * renderer reads `ctaLabel`, called `promotions` a string list when it
     * reads {title, description}, and trust-signals advertised an `icon` on
     * metrics that sanitizeMetrics() drops. Each entry below was read off the
     * renderer that consumes it.
     */
    const ARRAY_SHAPES = array(
        'conclusion' => array(
            'next_steps' => array(
                'text' => array('type' => 'string'),
                'url' => array('type' => 'string', 'description' => 'Optional link target.'),
            ),
        ),
        'pricing' => array(
            'items' => array(
                'name' => array('type' => 'string', 'description' => 'Tier name, e.g. "Pro".'),
                'description' => array('type' => 'string'),
                'price' => array('type' => 'string', 'description' => 'Exact price, e.g. "$9". Leave empty to use priceFrom/priceTo.'),
                'priceFrom' => array('type' => 'string', 'description' => 'Lower bound; alone renders "From X".'),
                'priceTo' => array('type' => 'string', 'description' => 'Upper bound; with priceFrom renders a range.'),
                'billingPeriod' => array('type' => 'string', 'description' => 'e.g. "per month".'),
                'featuresSummary' => array('type' => 'array', 'description' => 'Feature lines — plain strings.'),
                'isFeatured' => array('type' => 'boolean', 'description' => 'Highlights this tier.'),
                'ctaLabel' => array('type' => 'string', 'description' => 'Button text. camelCase — not cta_label.'),
                'ctaUrl' => array('type' => 'string', 'description' => 'Button link; needed for the button to show.'),
            ),
            'promotions' => array(
                'title' => array('type' => 'string'),
                'description' => array('type' => 'string'),
            ),
        ),
        'trust-signals' => array(
            'metrics' => array(
                'value' => array('type' => 'string', 'description' => 'The figure, e.g. "50k". Required — an empty value drops the metric.'),
                'label' => array('type' => 'string'),
            ),
            'badges' => array(
                'label' => array('type' => 'string'),
                'icon' => array('type' => 'string', 'description' => 'Icon key; defaults to shield-check.'),
            ),
        ),
        'toc' => array(
            'items' => array(
                'text' => array('type' => 'string', 'description' => 'Entry label. Required — an empty text drops the entry.'),
                'anchor' => array('type' => 'string'),
                'sub_items' => array('type' => 'array', 'description' => 'Nested entries, same {text, anchor} shape.'),
            ),
        ),
    );

    const ARRAY_ELEMENTS = array(
        'comparison-table' => array('items' => 'object:item', 'criteria' => 'object:criteria'),
        'conclusion' => array('points' => 'string', 'next_steps' => 'object'),
        'criteria' => array('criteria' => 'object:item'),
        'definitions' => array('items' => 'object:item'),
        'faq' => array('items' => 'object:item'),
        'intro' => array('points' => 'string'),
        'key-takeaways' => array('items' => 'object:item'),
        'methodology' => array('items' => 'object:item'),
        'myth-fact' => array('items' => 'object:item'),
        'pricing' => array('items' => 'object', 'promotions' => 'object'),
        'product-card' => array('chips' => 'string'),
        'editorial-product' => array('specs' => 'object:item'),
        'pros-cons' => array('pros' => 'string', 'cons' => 'string', 'best_for' => 'string', 'not_for' => 'string'),
        'quick-picks' => array('items' => 'object:item'),
        'rating-breakdown' => array('categories' => 'object:item'),
        'related-posts' => array('items' => 'object:item'),
        'section-header' => array('chips' => 'string'),
        'specifications' => array('specs' => 'object:item'),
        'step-list' => array('steps' => 'object:item'),
        'testimonial' => array('items' => 'object:item'),
        'toc' => array('items' => 'object'),
        'trust-signals' => array('metrics' => 'object', 'badges' => 'object'),
        'verdict' => array('chips' => 'string'),
        'where-to-buy' => array('items' => 'object:item'),
    );

    /**
     * Per-eggb-slug agent guidance. item_shape entries follow block.json
     * attribute style; rich_text lists attrs (or "items[].field") that accept
     * limited HTML via EggbSanitizer::basicRichText.
     */
    const DATA = array(
        'pros-cons' => array('hint' => 'Pros and cons lists for a product or approach; supports best-for/not-for lists.', 'rich_text' => array(), 'item_shape' => null),
        'verdict' => array('hint' => 'Final verdict box for one product: score, summary, CTA. Product-bound (product_ref).', 'rich_text' => array('verdict_text'), 'item_shape' => null),
        'product-card' => array('hint' => 'Single product card with title, badge, chips, CTA. Product-bound (product_ref).', 'rich_text' => array('subtitle', 'description'), 'item_shape' => null),
        'editorial-product' => array('hint' => 'One product shown as editorial content, with no price, score, rating or CTA; variants figure, claim, specimen, mention. Product-bound (product_ref). image_alt describes what the photograph shows, for screen readers - leave it empty to fall back to the product name. image_index picks which photo: 0 is the catalog hero, 1 and up walk the gallery and step down when the product has fewer.', 'rich_text' => array('body'), 'item_shape' => array('label' => array('type' => 'string', 'description' => 'Attribute name, e.g. "Capacity".'), 'value' => array('type' => 'string'))),
        'quick-picks' => array('hint' => 'Top-picks summary list (best overall / best budget...). Each item binds a product; per-item copy is editorial (title/subtitle/badge/description/chips/score).', 'rich_text' => array(), 'item_shape' => array('product_ref' => array('type' => 'object'), 'title' => array('type' => 'string'), 'subtitle' => array('type' => 'string'), 'badge' => array('type' => 'string'), 'description' => array('type' => 'string'), 'chips' => array('type' => 'array', 'description' => '0–3 short labels.'), 'score' => array('type' => 'string', 'description' => '0–10 scale, e.g. "8.5".'), 'merchant' => array('type' => 'string'))),
        'comparison-table' => array('hint' => 'Side-by-side product comparison. items binds one product per COLUMN (title/role_label/is_winner). The comparison content is the block-level `criteria` array: each entry is one ROW with a label, a type, and a `values` array holding one cell per product in items order — NOT a flat list of labels, and NOT a per-item highlight. 3–5 criteria.', 'rich_text' => array(), 'item_shape' => array('product_ref' => array('type' => 'object'), 'title' => array('type' => 'string', 'description' => 'Ultra-compact product name; falls back to the source title.'), 'role_label' => array('type' => 'string', 'description' => 'Short column label, e.g. "Best overall".'), 'is_winner' => array('type' => 'boolean', 'description' => 'Marks the recommended column.')), 'criteria_shape' => array('label' => array('type' => 'string', 'description' => 'Row label in article language, e.g. "Battery life".'), 'type' => array('type' => 'string', 'enum' => array('text', 'score', 'star', 'boolean', 'price'), 'default' => 'text'), 'values' => array('type' => 'array', 'description' => 'One cell per product, in items order. text: 1–5 word value; score: number like 9.2; star: 1|2|3 (low|med|high); boolean: yes|no (empty = dash); price: leave empty (auto-filled from the product).'), 'key' => array('type' => 'string', 'description' => 'Optional machine key, e.g. "battery_life".'))),
        'where-to-buy' => array('hint' => 'Merchant/offer listing for bound products with live prices. Prices come from the product; items supply merchant/title/chips.', 'rich_text' => array(), 'item_shape' => array('product_ref' => array('type' => 'object'), 'merchant' => array('type' => 'string', 'description' => 'Merchant/store name; falls back to the product domain.'), 'title' => array('type' => 'string'), 'chips' => array('type' => 'array', 'description' => '0–2 labels, e.g. "Free shipping".'))),
        'specifications' => array('hint' => 'Key-value specification table.', 'rich_text' => array(), 'item_shape' => array('label' => array('type' => 'string', 'description' => 'Spec name, e.g. "Weight".'), 'value' => array('type' => 'string'), 'icon' => array('type' => 'string', 'description' => 'Optional icon key.'))),
        'callout' => array('hint' => 'Highlighted note/tip/warning box.', 'rich_text' => array('body'), 'item_shape' => null),
        'intro' => array('hint' => 'Article opener: lead paragraph, key points, optional CTA.', 'rich_text' => array('body'), 'item_shape' => null),
        'conclusion' => array('hint' => 'Article closer: summary and final recommendation.', 'rich_text' => array('summary'), 'item_shape' => null),
        'faq' => array('hint' => 'FAQ list; answers accept limited HTML; optional FAQ schema.org output.', 'rich_text' => array('items[].answer'), 'item_shape' => array('question' => array('type' => 'string'), 'answer' => array('type' => 'string'))),
        'definitions' => array('hint' => 'Glossary of terms.', 'rich_text' => array('items[].definition'), 'item_shape' => array('term' => array('type' => 'string'), 'definition' => array('type' => 'string'))),
        'myth-fact' => array('hint' => 'Myth vs fact pairs; each item may carry an optional verdict badge.', 'rich_text' => array('items[].fact_text', 'items[].why_text'), 'item_shape' => array('myth_text' => array('type' => 'string'), 'fact_text' => array('type' => 'string'), 'why_text' => array('type' => 'string'), 'verdict' => array('type' => 'string', 'description' => 'Optional short verdict badge, e.g. "Mostly false".'), 'verdict_text' => array('type' => 'string', 'description' => 'Optional one-line verdict explanation.'), 'verdict_tone' => array('type' => 'string', 'description' => 'Badge colour cue: true|false|partial.'))),
        'methodology' => array('hint' => 'How-we-tested/reviewed methodology section.', 'rich_text' => array('description', 'note', 'items[].description'), 'item_shape' => array('title' => array('type' => 'string'), 'description' => array('type' => 'string'), 'icon' => array('type' => 'string', 'description' => 'Optional icon key.'))),
        'criteria' => array('hint' => 'Buying criteria/what-to-look-for list.', 'rich_text' => array(), 'item_shape' => array('title' => array('type' => 'string'), 'description' => array('type' => 'string'), 'importance' => array('type' => 'string', 'enum' => array('high', 'medium', 'low'), 'default' => 'medium'), 'look_for' => array('type' => 'string', 'description' => 'What good looks like.'), 'avoid' => array('type' => 'string', 'description' => 'Red flags to avoid.'))),
        'key-takeaways' => array('hint' => 'TL;DR takeaway bullets near the top of an article.', 'rich_text' => array('note'), 'item_shape' => array('text' => array('type' => 'string'), 'title' => array('type' => 'string', 'description' => 'Optional bold lead-in above the text.'))),
        'step-list' => array('hint' => 'Numbered how-to steps.', 'rich_text' => array('note', 'steps[].description'), 'item_shape' => array('title' => array('type' => 'string'), 'description' => array('type' => 'string'))),
        'section-header' => array('hint' => 'Standalone section heading with optional label; TOC-eligible.', 'rich_text' => array(), 'item_shape' => null),
        'rating-breakdown' => array('hint' => 'Per-criterion rating bars (categories).', 'rich_text' => array(), 'item_shape' => array('label' => array('type' => 'string', 'description' => 'Category name, e.g. "Battery".'), 'score' => array('type' => 'string', 'description' => '0–10 scale, e.g. "8.5".'))),
        'toc' => array('hint' => 'Table of contents; auto-collects TOC-eligible blocks. Place once, near the top.', 'rich_text' => array(), 'item_shape' => null),
        'related-posts' => array('hint' => 'Links to related posts on this site.', 'rich_text' => array(), 'item_shape' => array('post_id' => array('type' => 'integer', 'description' => 'Target post ID on this site.'), 'title' => array('type' => 'string', 'description' => 'Link title; may differ from the post title.'), 'badge' => array('type' => 'string', 'description' => '1–3 word category label. Optional.'), 'snippet' => array('type' => 'string', 'description' => 'One-line teaser. Optional.'))),
        'contextual-cta' => array('hint' => 'Inline call-to-action banner.', 'rich_text' => array('text'), 'item_shape' => null),
        // pricing and trust-signals each carry TWO object arrays, so their
        // per-array field lists live in the hint rather than a single item_shape.
        'pricing' => array('hint' => 'Pricing tiers/plans table (editorial, not product-bound). Two arrays, both objects: `items` are tiers, `promotions` is a separate list. The keys of each are on the attribute itself in content-egg/list-blocks.', 'rich_text' => array(), 'item_shape' => null),
        'testimonial' => array('hint' => 'Quote/testimonial cards.', 'rich_text' => array(), 'item_shape' => array('quote' => array('type' => 'string'), 'author' => array('type' => 'string'), 'attribution' => array('type' => 'string', 'description' => 'Role/company under the author. Optional.'), 'rating' => array('type' => 'number', 'description' => '0–5 stars. Optional.'))),
        'trust-signals' => array('hint' => 'Trust badges/metrics strip (aggregate rating, counts). Two arrays of objects, `metrics` and `badges`; their keys are on the attribute itself in content-egg/list-blocks. Aggregate rating fields are block-level.', 'rich_text' => array(), 'item_shape' => null),
    );
}
