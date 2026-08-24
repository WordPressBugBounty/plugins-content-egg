<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * AbilityBase class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Base class for Content Egg agent abilities. Subclasses are thin: schema
 * + permission + delegation to an existing service. No business logic here
 * (design spec §4).
 */
abstract class AbilityBase
{
    /** Fully qualified ability name, e.g. 'content-egg/get-status'. */
    abstract public function name(): string;

    /** Short translated label for UIs. */
    abstract public function label(): string;

    /**
     * LLM-facing description. Plain English, NOT translated: agents rely on
     * a stable, locale-independent contract.
     */
    abstract public function description(): string;

    /**
     * JSON Schema (array form) for the execute input.
     *
     * The root MUST be the literal type 'object', never the union
     * ['object','null']: the WordPress MCP Adapter compares the root type with
     * a strict string check, so a union reads as a "flattened" scalar schema and
     * the adapter wraps the whole thing in a single required property named
     * "input". Clients then validate against that wrapper and reject every call
     * with a missing-'input' error before it ever reaches the server.
     *
     * 'null' was in that union so a call carrying no input at all still
     * validated (the canonical wp-abilities route passes null for a bare GET).
     * A root 'default' => array() preserves that: WP_Ability::execute() runs
     * normalize_input() — which substitutes the default for null — before
     * validate_input(). Keep both keys together.
     */
    abstract public function inputSchema(): array;

    /** JSON Schema (array form) for the execute output. */
    abstract public function outputSchema(): array;

    /**
     * Capability check; receives the raw input so post-scoped abilities can
     * check per-post capabilities.
     *
     * @param mixed $input
     */
    abstract public function checkPermission($input = null): bool;

    /**
     * Executes the ability. Throw AbilityInputException (invalid input),
     * AbilityRateLimitException, or let service exceptions bubble — the
     * registrar maps them to structured WP_Error codes.
     */
    abstract public function execute(array $input): array;

    public function annotations(): array
    {
        return array('readonly' => true, 'destructive' => false, 'idempotent' => true);
    }

    /**
     * A free-form object property that survives ChatGPT's Action importer.
     *
     * NEVER publish a bare array('type' => 'object') with no 'properties'. The
     * importer silently DELETES such a property from the callable schema — the
     * operation keeps its description promising the payload, while the agent has
     * no parameter to put it in. That is how update-product shipped as a no-op
     * for Custom GPTs: it advertised "edits fields of one product" with no
     * 'fields' parameter, so a call could only ever edit nothing.
     *
     * A union type is the documented escape hatch: the importer cannot resolve
     * it to a concrete shape, so it emits a permissive `any` and the property
     * survives. Use this for genuinely dynamic maps (module settings, search
     * filters, module_id => items payloads) whose keys cannot be declared up
     * front. When the keys ARE knowable, declare real 'properties' instead —
     * that is strictly better, because the agent then sees the field names.
     *
     * Nested unions are safe: the MCP adapter only inspects a schema's ROOT
     * type, which is why inputSchema() roots must stay the literal 'object'.
     */
    protected static function freeFormObject(string $description): array
    {
        return array(
            'type' => array('object', 'null'),
            'description' => $description,
        );
    }

    /**
     * Schema for one node of a BlockKit block tree.
     *
     * Declared rather than left as array('type' => 'object') for two reasons:
     * the bare form is dropped outright by ChatGPT's importer (see
     * freeFormObject()), and the product_ref contract — the single most
     * important shape in product binding — was previously documented nowhere,
     * leaving agents to guess the key names against the validator.
     */
    protected static function blockNodeSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'type' => array(
                    'type' => 'string',
                    'description' => 'Block type from content-egg/list-blocks, e.g. "eggb/intro" '
                        . 'or "content-egg/products".',
                ),
                'attrs' => self::freeFormObject(
                    'Block attributes. The allowed keys, types, defaults and item shapes for this '
                        . 'block type come from content-egg/list-blocks with type="<block type>".'
                ),
                'product_ref' => array(
                    'type' => 'object',
                    'description' => 'Binds this block to one product already attached to the post. '
                        . 'Both values come from content-egg/get-post-products or the search results '
                        . 'you attached. Some blocks instead take a product_ref per item inside '
                        . 'attrs.items — content-egg/list-blocks says which.',
                    'properties' => array(
                        'module_id' => array('type' => 'string'),
                        'unique_id' => array('type' => 'string'),
                    ),
                    'required' => array('module_id', 'unique_id'),
                ),
            ),
            'required' => array('type'),
        );
    }

    /**
     * The "products" payload shared by create-post, validate-blocks and
     * preview-blocks: module_id => items, where an item is either a full search
     * result or — with the matching search_token — just its unique_id.
     *
     * The item type is a union for the reason freeFormObject() documents, and
     * 'string' belongs in it: without it the schema rejects the very reference
     * form the guide tells agents to use, which is how validate-blocks came to
     * refuse payloads create-post accepted.
     */
    protected static function productsPayloadSchema(string $description): array
    {
        return array(
            'type' => array('object', 'null'),
            'description' => $description,
            'additionalProperties' => array(
                'type' => 'array',
                'items' => array('type' => array('object', 'string', 'null')),
            ),
        );
    }

    /** The search_tokens sibling of productsPayloadSchema(). */
    protected static function searchTokensSchema(): array
    {
        return self::freeFormObject(
            'Optional module_id => search_token map (the token comes from the search response). '
                . 'For any module listed here, its "products" entry may be a list of unique_id '
                . 'strings (or {unique_id, overrides}) instead of full item objects — the server '
                . 'substitutes its own stored copy of each result, so large product objects never '
                . 'need to be echoed back.'
        );
    }

    /**
     * Swap each token-backed module's item refs for the server's stored copy of
     * the search results.
     *
     * Must run BEFORE validation everywhere it is used: the Validator resolves
     * product_refs against this payload, so it has to see real items, and
     * everything downstream then behaves exactly as if full objects had been
     * echoed back. A module absent from $tokens keeps its literal items, so the
     * full-object form still works unchanged.
     *
     * @throws AbilityInputException unknown/expired token, or one belonging to a
     *                               different module (via SearchTokenResolver)
     */
    protected static function resolveProductSearchTokens(array $products, array $tokens): array
    {
        foreach ($products as $module_id => $items)
        {
            if (!is_array($items) || !$items)
            {
                continue;
            }

            $token = trim((string) ($tokens[$module_id] ?? ''));
            if ($token === '')
            {
                // Reference form without the token that makes it resolvable.
                // Left alone, the id strings simply never match anything and the
                // caller is told its product_ref "is not in the products payload"
                // — pointing at the ref, which is correct, instead of at the
                // missing token, which is the actual mistake.
                foreach ($items as $item)
                {
                    if (is_string($item))
                    {
                        throw new AbilityInputException(
                            "products['{$module_id}'] lists unique_id strings, but search_tokens['{$module_id}'] "
                                . 'is missing, so they cannot be resolved. Add the search_token from that '
                                . "module's search response, or send the full item objects instead."
                        );
                    }
                }
                continue;
            }

            $resolved = SearchTokenResolver::resolve($token, array_values($items), (string) $module_id);
            $products[$module_id] = $resolved['items'];
        }

        return $products;
    }
}
