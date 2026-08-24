<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

/**
 * Decides whether an incoming feed product should be saved to the local
 * database, based on a user-configured field/value list.
 *
 * Matching is a case-insensitive substring test, OR'd across all configured
 * values. Substring rather than equality is deliberate: feed category paths
 * are hierarchical (e.g. "Party & Celebration~~Party Supplies~~Party Games"),
 * so a parent path naturally matches all of its children without the user
 * having to enumerate every leaf.
 *
 * In "include" mode a product is kept only when it matches; in "exclude" mode
 * it is kept only when it does not. With no usable values configured the
 * filter is a no-op and keeps everything.
 *
 * Stateless: safe to reuse for every row of an import run.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class FeedProductFilter
{
    public const MODE_INCLUDE = 'include';
    public const MODE_EXCLUDE = 'exclude';

    private string $field;
    private bool $exclude;

    /** @var string[] Lowercased, non-empty needles. */
    private array $needles = array();

    public function __construct(string $field, string $mode, array $values)
    {
        $this->field   = $field;
        $this->exclude = ($mode === self::MODE_EXCLUDE);

        foreach ($values as $value)
        {
            $value = trim((string) $value);
            if ($value === '')
            {
                continue;
            }
            $this->needles[] = mb_strtolower($value);
        }
    }

    /**
     * True when the product should be saved, false when it should be skipped.
     */
    public function keeps(array $product): bool
    {
        if (!$this->needles)
        {
            return true;
        }

        $haystack = mb_strtolower(trim((string) ($product[$this->field] ?? '')));

        $matched = false;
        if ($haystack !== '')
        {
            foreach ($this->needles as $needle)
            {
                if (mb_strpos($haystack, $needle) !== false)
                {
                    $matched = true;
                    break;
                }
            }
        }

        return $this->exclude ? !$matched : $matched;
    }

    /**
     * True when no usable values are configured, i.e. the filter would keep
     * every product. Lets callers skip instantiating it altogether.
     */
    public function isEmpty(): bool
    {
        return !$this->needles;
    }
}
