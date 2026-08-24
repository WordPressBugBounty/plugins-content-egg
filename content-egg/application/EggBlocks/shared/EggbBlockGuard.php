<?php

namespace ContentEgg\application\EggBlocks\shared;

defined('ABSPATH') || exit;

/**
 * Rejects writes to post_content that would leave eggb/* blocks more damaged
 * than they already are.
 *
 * Egg Blocks keep every attribute, including prose, inside the block comment's
 * JSON. Plugins that rewrite post_content as HTML text (internal linking,
 * translation, search-and-replace) can insert markup at a keyword position that
 * falls inside that JSON. The payload then stops being valid JSON, the block's
 * attributes fail to decode, and on the next save WordPress escapes the whole
 * region so the raw markup renders as visible text.
 *
 * The guard is deliberately field-agnostic: it never inspects which attributes
 * hold rich text, only whether a block's attribute JSON still decodes. That
 * keeps it correct as blocks gain, lose or rename fields.
 *
 * @see docs/block-integrity-guard.md
 */
class EggbBlockGuard
{
    /**
     * Weight applied when block delimiters have been escaped into visible text.
     * Far heavier than a single undecodable block: at that point the markup has
     * stopped being a block at all.
     */
    private const ESCAPED_DELIMITER_WEIGHT = 10;

    public static function initAction(): void
    {
        // Priority 9999 so the guard sees the final content other plugins
        // intend to store, after every other wp_insert_post_data filter.
        add_filter('wp_insert_post_data', [self::class, 'filterPostData'], 9999, 2);
    }

    /**
     * Score how badly the eggb/* blocks in a content string are damaged.
     *
     * @param string $content Unslashed post content.
     * @return int Damage score; 0 means healthy.
     */
    public static function damageScore(string $content): int
    {
        $prefixes = self::namespaces();
        if (!$prefixes)
        {
            return 0;
        }

        $score = 0;

        foreach (parse_blocks($content) as $block)
        {
            if (empty($block['blockName']))
            {
                continue;
            }

            if (!self::isOurs((string) $block['blockName'], $prefixes))
            {
                continue;
            }

            // parse_blocks() returns attrs === null when the attribute JSON
            // could not be decoded.
            if (!array_key_exists('attrs', $block) || $block['attrs'] === null)
            {
                $score++;
            }
        }

        if (preg_match('/&lt;!--\s*wp:eggb/', $content))
        {
            $score += self::ESCAPED_DELIMITER_WEIGHT;
        }

        return $score;
    }

    /**
     * @param array $data    Slashed post data about to be written.
     * @param array $postarr Slashed post array as passed to wp_insert_post().
     * @return array
     */
    public static function filterPostData($data, $postarr)
    {
        if (!self::isEnabled())
        {
            return $data;
        }

        $new = isset($data['post_content']) ? $data['post_content'] : '';
        if (!is_string($new) || $new === '' || strpos($new, 'wp:eggb') === false)
        {
            // Nothing of ours in the incoming content. Cheap exit for every
            // post on the site that does not use Egg Blocks.
            return $data;
        }

        $post_id = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
        if (!$post_id)
        {
            // A new post has no previous version to protect.
            return $data;
        }

        $old = get_post_field('post_content', $post_id, 'raw');
        if (!is_string($old) || strpos($old, '<!-- wp:eggb') === false)
        {
            // The stored version had no intact blocks to lose.
            return $data;
        }

        $old_score = self::damageScore($old);
        $new_score = self::damageScore(wp_unslash($new));

        // Compare against the stored version rather than testing an absolute
        // threshold, so a write that repairs damage is always accepted.
        if ($new_score > $old_score)
        {
            do_action('cegg_block_guard_rejected', $post_id, $old_score, $new_score);
            $data['post_content'] = wp_slash($old);
        }

        return $data;
    }

    private static function isEnabled(): bool
    {
        if (defined('CEGG_DISABLE_BLOCK_GUARD') && \CEGG_DISABLE_BLOCK_GUARD)
        {
            return false;
        }

        return (bool) apply_filters('cegg_block_guard_enabled', true);
    }

    /**
     * @return string[] Block name prefixes the guard protects.
     */
    private static function namespaces(): array
    {
        $prefixes = apply_filters('cegg_block_guard_namespaces', ['eggb/']);

        return is_array($prefixes) ? array_filter(array_map('strval', $prefixes)) : [];
    }

    /**
     * @param string[] $prefixes
     */
    private static function isOurs(string $blockName, array $prefixes): bool
    {
        foreach ($prefixes as $prefix)
        {
            if (strpos($blockName, $prefix) === 0)
            {
                return true;
            }
        }

        return false;
    }
}
