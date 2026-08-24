<?php

namespace ContentEgg\application\admin\import;

defined('\ABSPATH') || exit;

/**
 * AiValueResolver
 *
 * Lazy, memoised resolution of %AI.<name>% values for one imported product.
 *
 * A *canonical key* is one generation: 'title', 'content' and 'short_desc' for
 * the three sinks, or the prompt's own name for a placeholder-only prompt. An
 * *alias* is a name a user may write inside %AI.…%; a prompt selected as a sink
 * has two aliases pointing at one canonical key, which is what keeps
 * %AI.title% and %AI.<that_prompt>% a single AI call.
 *
 * Deliberately free of WordPress calls and of any Prompt dependency — the
 * generator arrives as a callable — so the logic can be unit-tested standalone.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class AiValueResolver
{
    /** @var array<string, string> alias (lowercase) => canonical key */
    private array $aliasMap;

    /** @var callable(string):string */
    private $generator;

    /** @var array<string, string> canonical key => generated value */
    private array $values = [];

    /** @var string[] canonical keys currently being generated */
    private array $stack = [];

    /** @var string[] */
    private array $notices = [];

    public function __construct(array $aliasMap, callable $generator)
    {
        $this->aliasMap  = array_change_key_case($aliasMap, CASE_LOWER);
        $this->generator = $generator;
    }

    /**
     * Dependency resolution. Never throws: a failure or a cycle yields ''.
     */
    public function resolve(string $name): string
    {
        try
        {
            return $this->resolveOrFail($name);
        }
        catch (\Throwable $e)
        {
            $key = $this->canonical($name);

            if ($key !== null)
            {
                // Memoise the failure: one attempt per product, not one per consumer.
                $this->values[$key] = '';

                $this->notices[] = sprintf(
                    'AI custom prompt "%s" failed: %s.',
                    $name,
                    $e->getMessage()
                );
            }

            return '';
        }
    }

    /**
     * Entry-point resolution for the three sinks, where a failure must fail the
     * import row rather than silently produce an empty post title.
     */
    public function resolveOrFail(string $name): string
    {
        $key = $this->canonical($name);

        if ($key === null)
        {
            return '';
        }

        if (array_key_exists($key, $this->values))
        {
            return $this->values[$key];
        }

        if (in_array($key, $this->stack, true))
        {
            // Do not memoise: the legitimate consumer further up the stack is
            // still going to produce a real value for this key.
            $this->notices[] = sprintf(
                'Custom prompt "%s" refers back to itself through another prompt. The reference was left empty.',
                $name
            );

            return '';
        }

        $this->stack[] = $key;

        try
        {
            $value = (string) call_user_func($this->generator, $key);
        }
        finally
        {
            array_pop($this->stack);
        }

        $this->values[$key] = $value;

        return $value;
    }

    /**
     * Every generated value under every alias that points at it, keyed for
     * ProductHelper::replaceImportPatterns().
     */
    public function all(): array
    {
        $out = [];

        foreach ($this->aliasMap as $alias => $key)
        {
            if (array_key_exists($key, $this->values))
            {
                $out['AI.' . $alias] = $this->values[$key];
            }
        }

        return $out;
    }

    /**
     * @return string[]
     */
    public function notices(): array
    {
        return $this->notices;
    }

    private function canonical(string $name): ?string
    {
        return $this->aliasMap[strtolower($name)] ?? null;
    }
}
