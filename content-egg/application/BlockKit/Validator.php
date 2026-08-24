<?php

namespace ContentEgg\application\BlockKit;

defined('\ABSPATH') || exit;

/**
 * Validator class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

final class Validator
{
    /**
     * The documented block-node shape. Anything else at node level is rejected
     * (unknown_node_key) rather than silently ignored: product_ref is legal here
     * OR inside attrs, and opaque/raw_markup carry pass-through nodes from
     * TreeParser. Block attributes always belong in attrs.
     */
    const NODE_KEYS = array('type' => true, 'attrs' => true, 'product_ref' => true,
        'opaque' => true, 'raw_markup' => true);

    /**
     * TreeParser's third node shape: simple core prose carried as {type, attrs,
     * html} because Serializer can reproduce it exactly. Values mirror
     * TreeParser::coreProseRoundTrips() -- the attributes Serializer emits.
     */
    const CORE_PROSE = array(
        'core/paragraph' => array(),
        'core/heading' => array('level'),
        'core/list' => array('ordered'),
    );

    private $resolve;
    private $sanitize;
    private $products_templates;
    private $attached;

    public function __construct(?callable $product_resolver = null, ?callable $rich_sanitizer = null, ?array $products_templates = null, ?callable $attached_products = null)
    {
        $this->resolve = $product_resolver ?: function (array $ref)
        {
            return class_exists('\ContentEgg\application\EggBlocks\shared\CeProductResolver')
                ? \ContentEgg\application\EggBlocks\shared\CeProductResolver::resolve($ref)
                : null;
        };
        $this->sanitize = $rich_sanitizer ?: function ($html)
        {
            return class_exists('\ContentEgg\application\EggBlocks\shared\EggbSanitizer')
                ? \ContentEgg\application\EggBlocks\shared\EggbSanitizer::basicRichText((string) $html)
                : (string) $html;
        };
        // Validation-only lookup. CeProductResolver deliberately refuses modules
        // that are no longer installed (resolving one would fatal in the
        // ModuleManager factory), which is right for rendering but wrong for the
        // question validation actually asks: "is this ref attached to this post?"
        // That answer lives in post meta and needs no module class. Without this,
        // every product post on a site downgraded from Pro -- and Free strips every
        // Pro module -- failed validation with unresolved_product_ref.
        $this->attached = $attached_products ?: function (int $post_id, string $module_id)
        {
            return class_exists('\ContentEgg\application\components\ContentManager')
                ? (array) \ContentEgg\application\components\ContentManager::getData($post_id, $module_id)
                : array();
        };
        $this->products_templates = $products_templates;
    }

    public function validate(array $blocks, int $post_id = 0, array $products_payload = array()): ValidationResult
    {
        $result = new ValidationResult();
        $payload_keys = array();
        foreach ($products_payload as $module_id => $items)
        {
            foreach ((array) $items as $item)
            {
                $payload_keys[$module_id . '|' . (string) (is_array($item) ? ($item['unique_id'] ?? '') : '')] = true;
            }
        }

        foreach (array_values($blocks) as $i => $node)
        {
            $path = '/blocks/' . $i;
            $errors_before = count($result->errors);

            if (!is_array($node) || empty($node['type']) || !is_string($node['type']))
            {
                $result->error($path, 'bad_node', 'Each block must be an object with a string "type".');
                continue;
            }

            $type = $node['type'];

            // Opaque nodes come from TreeParser via content-egg/get-post-blocks:
            // core prose, classic content and third-party blocks the catalog does
            // not model. They must round-trip verbatim or the documented edit loop
            // (get-post-blocks -> modify -> validate-blocks -> insert-blocks) cannot
            // even no-op -- InsertBlocksAbility runs this same validator, so before
            // this branch existed opaque nodes could be read and serialized but
            // never written. Deliberately not sanitized here: wp_insert_post /
            // wp_update_post run content_save_pre -> wp_filter_post_kses for users
            // without unfiltered_html, and kses strips HTML comments, which ARE the
            // block delimiters.
            if (!empty($node['opaque']))
            {
                self::rejectUnknownKeys($node, array('type', 'opaque', 'raw_markup'), $path, $result);

                $raw = is_string($node['raw_markup'] ?? null) ? $node['raw_markup'] : '';
                if (trim($raw) === '')
                {
                    $result->error($path . '/raw_markup', 'bad_opaque',
                        'An opaque node must carry non-empty raw_markup. '
                            . 'Send opaque nodes exactly as content-egg/get-post-blocks returned them.');
                    continue;
                }

                $result->tree[] = array('type' => $type, 'opaque' => true, 'raw_markup' => $raw);
                continue;
            }

            // Core prose from get-post-blocks: paragraphs, headings and lists that
            // Serializer reproduces verbatim. Rejected as unknown_type before this
            // branch existed, which broke the documented edit loop for the most
            // common content on a real post. Same trust model as opaque nodes --
            // wp_insert_post/wp_update_post kses-filter on save -- and passed
            // through unsanitized so an unchanged tree round-trips unchanged.
            if (isset(self::CORE_PROSE[$type]) && is_string($node['html'] ?? null))
            {
                self::rejectUnknownKeys($node, array('type', 'attrs', 'html'), $path, $result);

                // Defense in depth for the innerBlocks case TreeParser now keeps
                // opaque: an itemless list is never legitimate, and accepting one
                // would serialize an empty <ul>, silently deleting its items.
                // Empty paragraphs and headings DO occur in real posts, so only
                // lists fail closed here.
                if ($type === 'core/list' && trim($node['html']) === '')
                {
                    $result->error($path . '/html', 'empty_list',
                        'A core/list node carries no items. Lists whose items live in inner blocks must be '
                            . 'sent back exactly as content-egg/get-post-blocks returned them (opaque, with '
                            . 'raw_markup); writing this node would erase the list.');
                    continue;
                }

                $prose_attrs = array();
                foreach ((array) ($node['attrs'] ?? array()) as $key => $value)
                {
                    if (!in_array($key, self::CORE_PROSE[$type], true))
                    {
                        // Serializer cannot reproduce align/className/anchor on a
                        // prose node, so they would vanish on write. Say so rather
                        // than dropping them silently -- the same failure class
                        // unknown_node_key exists to prevent.
                        $result->warn($path . '/attrs/' . $key, 'unknown_attr',
                            "Attribute '{$key}' cannot round-trip on {$type} and was dropped. "
                                . 'Send the node back as content-egg/get-post-blocks returned it '
                                . '(opaque, with raw_markup) to preserve it.');
                        continue;
                    }

                    // The only two permitted keys; cast to what Serializer reads.
                    if ($key === 'level')
                    {
                        // Report the value actually stored, after clamping to h1-h6.
                        $level = max(1, min(6, (int) $value));
                        if ((string) $value !== (string) $level)
                        {
                            $result->warn($path . '/attrs/level', 'normalized_type',
                                "Heading level '" . (is_scalar($value) ? (string) $value : gettype($value))
                                    . "' is not a valid integer 1-6; used {$level}.");
                        }
                        $prose_attrs['level'] = $level;
                    }
                    else
                    {
                        $prose_attrs[$key] = (bool) $value;
                    }
                }

                $result->tree[] = array('type' => $type, 'attrs' => $prose_attrs, 'html' => (string) $node['html']);
                continue;
            }

            if ($type === 'core/markdown' || (strpos($type, 'core/') === 0 && isset($node['markdown'])))
            {
                self::rejectUnknownKeys($node, array('type', 'markdown', 'attrs'), $path, $result);

                // Catalog advertises attributes.markdown, so accept the documented
                // shape as well as the historical top-level key. Reading only the
                // latter made the advertised form fail with "markdown must be a
                // non-empty string" when a non-empty string had been supplied.
                $md = (string) ($node['markdown'] ?? ($node['attrs']['markdown'] ?? ''));
                if (trim($md) === '')
                {
                    $result->error($path . '/markdown', 'bad_markdown', 'markdown must be a non-empty string.');
                    continue;
                }
                foreach (MarkdownCompiler::compile($md) as $core_node)
                {
                    $result->tree[] = $core_node;
                }
                continue;
            }

            $descriptor = Catalog::get($type, $this->products_templates);
            if (!$descriptor || $descriptor['family'] === 'core')
            {
                $result->error($path . '/type', 'unknown_type',
                    "Unknown block type '{$type}'. Rule: authoring content -> eggb/* blocks; "
                        . 'live display -> content-egg/products, content-egg/coupons, content-egg/images or '
                        . 'content-egg/videos; prose -> core/markdown. '
                        . 'Call content-egg/list-blocks for the catalog.');
                continue;
            }

            // Fail closed on unrecognized node-level keys. Without this a flat
            // {"type":"eggb/faq","title":"..."} node validated clean and serialized
            // to `{}` -- every attribute silently lost under a "valid" stamp, which
            // is worse than an error because there is nothing to self-correct from.
            // product_ref is only legal on a single-bound block; allowing it
            // everywhere let an orphan ref be discarded while the agent believed it
            // had bound a product.
            $allowed = ($descriptor['product_binding'] === 'single')
                ? array('type', 'attrs', 'product_ref')
                : array('type', 'attrs');
            self::rejectUnknownKeys($node, $allowed, $path, $result);

            $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : array();

            // Single-bound blocks (product-card, verdict) carry one product_ref.
            // Accept it at the node top level OR nested in attrs (the stored
            // block-attribute location that list-blocks advertises), then keep
            // it out of the attribute checks so it lands only at the node level
            // of the clean tree.
            $single_ref = array();
            if ($descriptor['product_binding'] === 'single')
            {
                if (is_array($node['product_ref'] ?? null))
                {
                    $single_ref = $node['product_ref'];
                }
                elseif (is_array($attrs['product_ref'] ?? null))
                {
                    $single_ref = $attrs['product_ref'];
                }
                unset($attrs['product_ref']);
            }

            if ($descriptor['family'] === 'products')
            {
                // Products alone can receive a dynamic template list (from the
                // products payload); coupons/images/videos carry their typed enum
                // baked into the catalog descriptor.
                $descriptor = $this->ensureProductsTemplateEnum($descriptor);
            }
            if (self::isLiveFamily($descriptor['family']))
            {
                $attrs = $this->normalizeProductsTemplate($attrs, $path, $result);
            }

            $attrs = $this->checkAttrs($attrs, $descriptor, $path, $result);
            $attrs = $this->checkArrayElements($attrs, $descriptor, $path, $result);
            $attrs = $this->sanitizeRichText($attrs, $descriptor);
            $attrs = $this->checkItems($attrs, $descriptor, $path, $result, $post_id, $payload_keys);

            $clean = array('type' => $type, 'attrs' => self::stripInternal($attrs));

            if ($descriptor['product_binding'] === 'single')
            {
                if (!$this->refResolves($single_ref, $post_id, $payload_keys))
                {
                    $result->error($path . '/product_ref', 'unresolved_product_ref',
                        'product_ref must reference a product attached to the post (or included in the products payload). '
                            . 'Put it at the block top level or in attrs.product_ref. '
                            . 'Call content-egg/get-post-products for attached products.');
                }
                else
                {
                    $clean['product_ref'] = array(
                        'module_id' => is_scalar($single_ref['module_id'] ?? null) ? (string) $single_ref['module_id'] : '',
                        'unique_id' => is_scalar($single_ref['unique_id'] ?? null) ? (string) $single_ref['unique_id'] : '',
                    );
                }
            }

            if (count($result->errors) > $errors_before)
            {
                continue; // fail closed: invalid nodes never reach the tree
            }

            $result->tree[] = $clean;
        }

        return $result;
    }

    /**
     * Catalog only injects the 'template' enum when it already found a
     * 'template' attribute via ProductBlock::getAttributes() (a WP-loaded
     * class). Standalone callers that inject an explicit template list via
     * the constructor still need enum enforcement without WP present, so
     * make sure the schema carries it regardless of where 'attributes' came
     * from.
     */
    /** The content-egg/* live-display families (render attached module data, no product_ref). */
    private static function isLiveFamily(string $family): bool
    {
        return in_array($family, array('products', 'coupons', 'images', 'videos'), true);
    }

    private function ensureProductsTemplateEnum(array $descriptor): array
    {
        if (is_array($this->products_templates))
        {
            $schema = $descriptor['attributes']['template'] ?? array('type' => 'string', 'default' => '');
            $schema['type'] = $schema['type'] ?? 'string';
            $schema['enum'] = array_values($this->products_templates);
            $descriptor['attributes']['template'] = $schema;
        }

        return $descriptor;
    }

    private function normalizeProductsTemplate(array $attrs, string $path, ValidationResult $result): array
    {
        $template = (string) ($attrs['template'] ?? '');
        if (strpos($template, 'data_') === 0)
        {
            $attrs['template'] = substr($template, strlen('data_'));
            $result->warn($path . '/attrs/template', 'normalized_template',
                "Template '{$template}' uses the module-settings vocabulary; normalized to '{$attrs['template']}'.");
        }
        return $attrs;
    }

    private function checkAttrs(array $attrs, array $descriptor, string $path, ValidationResult $result): array
    {
        foreach ($attrs as $key => $value)
        {
            if (!is_string($key) || strpos($key, '_') === 0)
            {
                continue;
            }
            $schema = $descriptor['attributes'][$key] ?? null;
            if (!$schema)
            {
                unset($attrs[$key]);
                $result->warn($path . '/attrs/' . $key, 'unknown_attr', "Attribute '{$key}' is not part of {$descriptor['type']}; dropped.");
                continue;
            }

            $type_ok = true;
            switch ($schema['type'] ?? 'string')
            {
                case 'string':
                    $type_ok = is_string($value) || is_numeric($value);
                    if ($type_ok)
                    {
                        // The ability description promises normalizations come back
                        // as warnings; this one was silent.
                        if (!is_string($value))
                        {
                            $result->warn($path . '/attrs/' . $key, 'normalized_type',
                                "Attribute '{$key}' is declared string; the numeric value was coerced.");
                        }
                        $attrs[$key] = (string) $value;
                    }
                    break;
                case 'number':
                case 'integer':
                    $type_ok = is_numeric($value);
                    if ($type_ok) $attrs[$key] = ($schema['type'] === 'integer') ? (int) $value : (float) $value;
                    break;
                case 'boolean':
                    $type_ok = is_bool($value) || $value === 0 || $value === 1 || $value === '0' || $value === '1';
                    if ($type_ok) $attrs[$key] = (bool) $value;
                    break;
                case 'array':
                    $type_ok = is_array($value);
                    break;
                case 'object':
                    $type_ok = is_array($value);
                    break;
            }

            if (!$type_ok)
            {
                $result->error($path . '/attrs/' . $key, 'bad_attr_type',
                    "Attribute '{$key}' must be of type {$schema['type']}.");
                continue;
            }

            if (isset($schema['enum']) && is_array($schema['enum']) && is_scalar($attrs[$key])
                && !in_array($attrs[$key], $schema['enum'], true) && !in_array((string) $attrs[$key], array_map('strval', $schema['enum']), true))
            {
                $result->error($path . '/attrs/' . $key, 'bad_enum',
                    "Attribute '{$key}' must be one of: " . implode(', ', array_map('strval', $schema['enum'])) . '.');
            }
        }

        return $attrs;
    }

    /**
     * Enforce the element kind of every eggb array attribute.
     *
     * The one silent failure left in the block path: a wrong element kind
     * validated clean and rendered nothing. eggb/key-takeaways items as plain
     * strings returned valid:true with no warning and produced 92 bytes of
     * empty wrapper; eggb/intro points as objects fatalled inside the renderer
     * and produced an empty string. Both stamped as success, so the agent had
     * nothing to self-correct from — the same reasoning that already makes
     * unknown node keys and unknown attributes fail closed one level up.
     *
     * Only the element KIND is enforced, and nothing is ever dropped: the
     * published shapes are hand-maintained guidance that lags the renderers,
     * so anything stricter would reject content that renders correctly.
     */
    private function checkArrayElements(array $attrs, array $descriptor, string $path, ValidationResult $result): array
    {
        if ($descriptor['family'] !== 'eggb')
        {
            return $attrs;
        }

        $slug = substr($descriptor['type'], strlen('eggb/'));
        $kinds = Hints::ARRAY_ELEMENTS[$slug] ?? array();

        foreach ($kinds as $attr => $kind)
        {
            if (!isset($attrs[$attr]) || !is_array($attrs[$attr]))
            {
                continue;
            }

            $wants_object = ($kind !== 'string');
            $allowed = $wants_object ? self::shapeKeys($slug, $attr, $kind, $descriptor) : array();

            foreach (array_values($attrs[$attr]) as $i => $element)
            {
                $epath = $path . '/attrs/' . $attr . '/' . $i;

                if ($wants_object && !is_array($element))
                {
                    $result->error($epath, 'bad_item_type',
                        "Each element of '{$attr}' must be an object, not a " . gettype($element) . '. '
                            . ($allowed
                                ? 'Keys: ' . implode(', ', $allowed) . '.'
                                : "See this block's hint in content-egg/list-blocks for the keys."));
                    continue;
                }

                if (!$wants_object && is_array($element))
                {
                    $result->error($epath, 'bad_item_type',
                        "Each element of '{$attr}' must be a plain string, not an object.");
                    continue;
                }

                // Deliberately NO unknown-key check inside an element. The
                // published shapes are agent guidance, not complete key lists:
                // validating 47 posts of real editor-written content against
                // them produced 360 complaints about keys that render fine
                // (key-takeaways items[].title among them). A rule that tells
                // an agent to delete working content is worse than no rule.
            }
        }

        return $attrs;
    }

    /** Documented keys for one object array, for the error message; [] when undeclared. */
    private static function shapeKeys(string $slug, string $attr, string $kind, array $descriptor): array
    {
        $meta = array(
            'item_shape' => $descriptor['item_shape'] ?? null,
            'criteria_shape' => $descriptor['criteria_shape'] ?? null,
        );
        $shape = Catalog::elementShape($slug, $attr, $kind, $meta);

        return $shape ? array_keys($shape) : array();
    }

    private function sanitizeRichText(array $attrs, array $descriptor): array
    {
        foreach ($descriptor['rich_text'] as $field)
        {
            if (preg_match('/^([a-z0-9_]+)\[\]\.([a-z0-9_]+)$/i', $field, $m))
            {
                $list_attr = $m[1];
                $sub = $m[2];
                if (isset($attrs[$list_attr]) && is_array($attrs[$list_attr]))
                {
                    foreach ($attrs[$list_attr] as $k => $item)
                    {
                        if (is_array($item) && isset($item[$sub]) && is_string($item[$sub]))
                        {
                            $attrs[$list_attr][$k][$sub] = call_user_func($this->sanitize, $item[$sub]);
                        }
                    }
                }
            }
            elseif (isset($attrs[$field]) && is_string($attrs[$field]))
            {
                $attrs[$field] = call_user_func($this->sanitize, $attrs[$field]);
            }
        }

        return $attrs;
    }

    private function checkItems(array $attrs, array $descriptor, string $path, ValidationResult $result, int $post_id, array $payload_keys): array
    {
        if ($descriptor['product_binding'] !== 'items')
        {
            return $attrs;
        }

        $items = is_array($attrs['items'] ?? null) ? array_values($attrs['items']) : array();
        foreach ($items as $k => $item)
        {
            $ipath = $path . '/attrs/items/' . $k;
            if (!is_array($item))
            {
                // checkArrayElements() already reported this one, with the
                // block's actual item keys attached; every product_binding=items
                // block is registered in Hints::ARRAY_ELEMENTS. Reporting it
                // again just prints two errors for one mistake.
                continue;
            }
            $ref = is_array($item['product_ref'] ?? null) ? $item['product_ref'] : array();
            if (!$this->refResolves($ref, $post_id, $payload_keys))
            {
                $result->error($ipath . '/product_ref', 'unresolved_product_ref',
                    'Each item of ' . $descriptor['type'] . ' must carry a product_ref referencing an attached (or payload) product.');
            }
        }
        $attrs['items'] = $items;

        return $attrs;
    }

    private function refResolves(array $ref, int $post_id, array $payload_keys): bool
    {
        $module_id = is_scalar($ref['module_id'] ?? null) ? (string) $ref['module_id'] : '';
        $unique_id = is_scalar($ref['unique_id'] ?? null) ? (string) $ref['unique_id'] : '';

        if ($module_id === '' || $unique_id === '')
        {
            return false;
        }

        if (isset($payload_keys[$module_id . '|' . $unique_id]))
        {
            return true;
        }

        if ($post_id > 0 && $this->attachedHasRef($post_id, $module_id, $unique_id))
        {
            return true;
        }

        $lookup = array('module_id' => $module_id, 'unique_id' => $unique_id);
        if ($post_id > 0)
        {
            $lookup['post_id'] = $post_id;
        }

        return call_user_func($this->resolve, $lookup) !== null;
    }

    /**
     * Is this ref present in the module data physically attached to the post?
     * Mirrors ContentManager::getProductbyUniqueId()'s matching (exact key, then
     * per-item unique_id) but reads raw attached data, so refs survive modules
     * that are inactive, renamed or no longer installed.
     */
    private function attachedHasRef(int $post_id, string $module_id, string $unique_id): bool
    {
        $data = call_user_func($this->attached, $post_id, $module_id);
        if (!is_array($data) || !$data)
        {
            return false;
        }

        if (isset($data[$unique_id]))
        {
            return true;
        }

        foreach ($data as $id => $item)
        {
            $item_id = is_array($item) ? (string) ($item['unique_id'] ?? '') : '';
            if ((string) $id === $unique_id || $item_id === $unique_id)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Fail closed on node-level keys outside the shape this branch accepts.
     *
     * Every branch gets its own allowlist: a stray key means the caller built the
     * wrong node shape, and silently dropping it is how attributes, product_refs
     * and list items went missing under a "valid" stamp. Keys are compared as
     * strings so a numeric key ({"0":"x"}, which PHP stores as int 0) cannot slip
     * past.
     *
     * @param array $allowed Flat list of legal key names for this branch.
     */
    private static function rejectUnknownKeys(array $node, array $allowed, string $path, ValidationResult $result): void
    {
        $legal = array_flip($allowed);

        foreach ($node as $key => $ignored)
        {
            if (isset($legal[(string) $key]))
            {
                continue;
            }

            $result->error($path . '/' . $key, 'unknown_node_key',
                "'{$key}' is not a valid key for this node. This node accepts: "
                    . implode(', ', $allowed) . '. Block attributes belong inside "attrs"; '
                    . 'send nodes back exactly as content-egg/get-post-blocks returned them.');
        }
    }

    private static function stripInternal(array $attrs): array
    {
        foreach ($attrs as $key => $value)
        {
            if (is_string($key) && strpos($key, '_') === 0)
            {
                unset($attrs[$key]);
                continue;
            }
            if (is_array($value))
            {
                $attrs[$key] = self::stripInternal($value);
            }
        }
        return $attrs;
    }
}
