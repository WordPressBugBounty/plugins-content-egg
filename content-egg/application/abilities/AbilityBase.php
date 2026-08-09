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

    /** JSON Schema (array form) for the execute input. */
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
}
