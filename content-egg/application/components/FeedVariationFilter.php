<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

/**
 * Detects whether an incoming feed product is a variation of a recently
 * imported product (different color/size/etc. of the same base item).
 *
 * Strategy: variations cluster together in feed order, so we only compare
 * against a small sliding window of recently accepted products. A candidate
 * is treated as a variation when ANY of the following match an entry in the
 * window:
 *
 *   1. URL path equality (after stripping query string) — strongest signal,
 *      catches Shopify/Woo "?variant=" patterns with near-zero false positives.
 *   2. GTIN equality (when both are non-empty) — duplicate SKU.
 *   3. Description equality (when both are non-empty and length >= 50) —
 *      variations almost always share descriptions; short blurbs are excluded
 *      to avoid grouping unrelated items with boilerplate text.
 *   4. Title prefix similarity >= 0.85 (longest common prefix / shorter title)
 *      — fallback for feeds without clean URLs/descriptions.
 *
 * Stateful: each accepted (non-variation) product is pushed onto the window;
 * older entries fall off once the window is full. One instance per import run.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class FeedVariationFilter
{
    public const DEFAULT_WINDOW_SIZE = 5;
    private const TITLE_PREFIX_THRESHOLD = 0.85;
    private const MIN_DESCRIPTION_LENGTH = 50;

    private int $windowSize;

    /** @var array<int, array{title: string, url: string, ean: string, desc: string}> */
    private array $window = [];

    public function __construct(int $windowSize = self::DEFAULT_WINDOW_SIZE)
    {
        $this->windowSize = max(1, $windowSize);
    }

    /**
     * Returns true if the candidate is a variation of a recently accepted
     * product. Otherwise records the candidate in the window and returns false.
     *
     * Pass empty strings for signals that are not available — the filter
     * will silently ignore them.
     */
    public function isVariation(string $title, string $url = '', string $ean = '', string $description = ''): bool
    {
        $titleNorm = mb_strtolower(trim($title));
        $urlPath   = $this->stripQueryString($url);
        $ean       = trim($ean);
        $descNorm  = trim($description);

        foreach ($this->window as $entry)
        {
            if ($urlPath !== '' && $urlPath === $entry['url'])
            {
                return true;
            }

            if ($ean !== '' && $ean === $entry['ean'])
            {
                return true;
            }

            if (
                $descNorm !== '' &&
                mb_strlen($descNorm) >= self::MIN_DESCRIPTION_LENGTH &&
                $descNorm === $entry['desc']
            )
            {
                return true;
            }

            if (
                $titleNorm !== '' &&
                $entry['title'] !== '' &&
                $this->titlePrefixSimilarity($titleNorm, $entry['title']) >= self::TITLE_PREFIX_THRESHOLD
            )
            {
                return true;
            }
        }

        $this->push([
            'title' => $titleNorm,
            'url'   => $urlPath,
            'ean'   => $ean,
            'desc'  => $descNorm,
        ]);

        return false;
    }

    /**
     * Reset the sliding window. Useful between runs when reusing the
     * same instance.
     */
    public function reset(): void
    {
        $this->window = [];
    }

    /**
     * @param array{title: string, url: string, ean: string, desc: string} $entry
     */
    private function push(array $entry): void
    {
        $this->window[] = $entry;
        if (count($this->window) > $this->windowSize)
        {
            array_shift($this->window);
        }
    }

    private function stripQueryString(string $url): string
    {
        $url = trim($url);
        if ($url === '')
        {
            return '';
        }

        $pos = strpos($url, '?');
        if ($pos !== false)
        {
            $url = substr($url, 0, $pos);
        }

        // Drop a single trailing slash so "/foo" and "/foo/" compare equal.
        return rtrim($url, '/');
    }

    /**
     * Byte-level longest-common-prefix ratio against the shorter string.
     * Operating on bytes is safe for equality on UTF-8 inputs.
     */
    private function titlePrefixSimilarity(string $a, string $b): float
    {
        $shorter = min(strlen($a), strlen($b));
        if ($shorter === 0)
        {
            return 0.0;
        }

        $lcp = 0;
        while ($lcp < $shorter && $a[$lcp] === $b[$lcp])
        {
            $lcp++;
        }

        return $lcp / $shorter;
    }
}
