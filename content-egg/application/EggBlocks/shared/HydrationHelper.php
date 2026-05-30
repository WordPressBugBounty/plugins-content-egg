<?php

namespace ContentEgg\application\EggBlocks\shared;

defined('ABSPATH') || exit;

/**
 * Resolves block attribute values that can come either from a profile linked
 * to the current post (auto mode) or from literal attribute values (manual).
 *
 * Auto mode delegates to filter hooks so EggBlocks remains decoupled from any
 * specific resolver plugin (e.g. TMN).
 *
 * Two flavors of resolution:
 *
 *   • URL hydration (resolveUrl) — needs a vocabulary key (trial/pricing/...)
 *     because the AI/user picks WHICH URL. If the source-key is set and
 *     resolves empty, returns empty (no fallback to the literal URL — an
 *     AI-generated label may be tied to that source). If source-key is empty,
 *     returns the literal value (manual / legacy blocks).
 *
 *   • Binary hydration (resolveLogo, resolveItems, resolvePromotions) — only
 *     ever sourced from "profile" (the linked profile's primary collection).
 *     The block-level `data_source` flag is the toggle. In auto mode, calls
 *     the filter; if it returns empty (no linkage / linkage but no data /
 *     no resolver registered), falls back to the literal attribute. This
 *     preserves backward compat: legacy blocks with literal items but no
 *     profile linkage continue to render.
 *
 * Manual mode always uses the literal attribute regardless of flavor.
 *
 * Filter contract:
 *   apply_filters('eggb/resolve_url_source',          '', $source_key, ['post_id' => int]) : string
 *   apply_filters('eggb/resolve_logo_source',         '', $source_key, ['post_id' => int]) : string
 *   apply_filters('eggb/resolve_items_source',        [], $source_key, ['post_id' => int]) : array
 *   apply_filters('eggb/resolve_promotions_source',   [], $source_key, ['post_id' => int]) : array
 *   apply_filters('eggb/resolve_testimonials_source', [], $source_key, ['post_id' => int]) : array
 *   apply_filters('eggb/resolve_aggregate_source',  null, $source_key, ['post_id' => int]) : ?array
 */
class HydrationHelper
{
    public const MODE_AUTO   = 'auto';
    public const MODE_MANUAL = 'manual';

    private const PROFILE_SOURCE_KEY = 'profile';

    /**
     * Resolve a URL attribute pair (source-key + literal) honoring the block's
     * data_source mode. Returns "" when the active value is empty.
     */
    public static function resolveUrl(array $attrs, string $source_attr, string $literal_attr, int $post_id): string
    {
        if (self::isAutoMode($attrs))
        {
            $key = (string) ($attrs[$source_attr] ?? '');
            if ($key !== '')
            {
                return (string) apply_filters('eggb/resolve_url_source', '', $key, ['post_id' => $post_id]);
            }
        }

        return (string) ($attrs[$literal_attr] ?? '');
    }

    /**
     * Resolve a logo attribute. In auto mode, fetches the linked profile's
     * logo; falls back to the literal logo URL if hydration is empty.
     */
    public static function resolveLogo(array $attrs, string $literal_attr, int $post_id): string
    {
        if (self::isAutoMode($attrs))
        {
            $hydrated = (string) apply_filters(
                'eggb/resolve_logo_source',
                '',
                self::PROFILE_SOURCE_KEY,
                ['post_id' => $post_id]
            );
            if ($hydrated !== '')
            {
                return $hydrated;
            }
        }

        return (string) ($attrs[$literal_attr] ?? '');
    }

    /**
     * Resolve an items[] collection attribute. In auto mode, fetches plans/
     * services from the linked profile; falls back to the literal items[] if
     * hydration is empty.
     *
     * @return array<int, array<string,mixed>>
     */
    public static function resolveItems(array $attrs, string $literal_attr, int $post_id): array
    {
        if (self::isAutoMode($attrs))
        {
            $hydrated = (array) apply_filters(
                'eggb/resolve_items_source',
                [],
                self::PROFILE_SOURCE_KEY,
                ['post_id' => $post_id]
            );
            if (!empty($hydrated))
            {
                return $hydrated;
            }
        }

        return (array) ($attrs[$literal_attr] ?? []);
    }

    /**
     * Resolve a promotions[] collection attribute. Same shape as resolveItems().
     *
     * @return array<int, array<string,mixed>>
     */
    public static function resolvePromotions(array $attrs, string $literal_attr, int $post_id): array
    {
        if (self::isAutoMode($attrs))
        {
            $hydrated = (array) apply_filters(
                'eggb/resolve_promotions_source',
                [],
                self::PROFILE_SOURCE_KEY,
                ['post_id' => $post_id]
            );
            if (!empty($hydrated))
            {
                return $hydrated;
            }
        }

        return (array) ($attrs[$literal_attr] ?? []);
    }

    /**
     * Resolve a testimonials[] collection. Same auto/manual rules as resolveItems().
     *
     * @return array<int, array<string,mixed>>
     */
    public static function resolveTestimonials(array $attrs, string $literal_attr, int $post_id): array
    {
        if (self::isAutoMode($attrs))
        {
            $hydrated = (array) apply_filters(
                'eggb/resolve_testimonials_source',
                [],
                self::PROFILE_SOURCE_KEY,
                ['post_id' => $post_id]
            );
            if (!empty($hydrated))
            {
                return $hydrated;
            }
        }

        return (array) ($attrs[$literal_attr] ?? []);
    }

    /**
     * Resolve aggregate rating data (multi-field). In auto mode, fetches a
     * structured dict from the linked profile; falls back to the block's
     * 4 literal aggregate attrs (`aggregate_rating`, `aggregate_rating_max`,
     * `aggregate_count`, `aggregate_source`). Returns null when neither
     * source has a valid (rating > 0 + non-empty source) combo — block hides
     * the aggregate display.
     *
     * @return array{rating: float, rating_max: float, count: int, source: string}|null
     */
    public static function resolveAggregate(array $attrs, int $post_id): ?array
    {
        if (self::isAutoMode($attrs))
        {
            $hydrated = apply_filters(
                'eggb/resolve_aggregate_source',
                null,
                self::PROFILE_SOURCE_KEY,
                ['post_id' => $post_id]
            );
            if (is_array($hydrated)
                && (float) ($hydrated['rating'] ?? 0) > 0
                && (string) ($hydrated['source'] ?? '') !== '')
            {
                $max = (float) ($hydrated['rating_max'] ?? 5);
                return [
                    'rating'     => (float) $hydrated['rating'],
                    'rating_max' => $max > 0 ? $max : 5.0,
                    'count'      => max(0, (int) ($hydrated['count'] ?? 0)),
                    'source'     => (string) $hydrated['source'],
                ];
            }
        }

        $rating = (float) ($attrs['aggregate_rating'] ?? 0);
        $source = (string) ($attrs['aggregate_source'] ?? '');
        if ($rating <= 0 || $source === '')
        {
            return null;
        }

        $max = (float) ($attrs['aggregate_rating_max'] ?? 5);
        return [
            'rating'     => $rating,
            'rating_max' => $max > 0 ? $max : 5.0,
            'count'      => max(0, (int) ($attrs['aggregate_count'] ?? 0)),
            'source'     => $source,
        ];
    }

    private static function isAutoMode(array $attrs): bool
    {
        $mode = (string) ($attrs['data_source'] ?? self::MODE_AUTO);
        return $mode !== self::MODE_MANUAL;
    }
}
