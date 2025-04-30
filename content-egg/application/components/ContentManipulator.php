<?php

namespace ContentEgg\application\components;

use function ContentEgg\prn;
use function ContentEgg\prnx;

defined('\ABSPATH') || exit;

/**
 * ContentManipulator abstract class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2025 keywordrush.com
 */
class ContentManipulator
{
    protected array $blocks = [];
    protected array $paragraphChunks = [];
    protected array $sectionChunks = [];
    protected array $injectedPositions = [];
    protected bool $isGutenberg = false;
    protected $excerptChunkIndex = null;

    public function __construct()
    {
        if (!function_exists('parse_blocks') || !function_exists('serialize_block'))
        {
            throw new \RuntimeException('WordPress 5.9 or higher is required for block parsing.');
        }
    }

    /**
     * Inject snippets and save the result back to WordPress.
     *
     * @param  \WP_Post $post    Target post object.
     * @param  array    $config  Injection specification.
     * @return int|\WP_Error     0 = no change, post-ID on success, or WP_Error.
     */
    public function injectAndSave(\WP_Post $post, array $config)
    {
        $modified = $this->inject($post->post_content, $config);

        if ($modified === $post->post_content)
        {
            return 0;
        }

        $update = [
            'ID'           => $post->ID,
            'post_content' => $modified,
        ];

        return wp_update_post(wp_slash($update), true);
    }

    /**
     * Return the content with snippets injected according to $config.
     *
     * @param  string $content  Raw post_content (blocks or classic HTML).
     * @param  array  $config   Same structure used in injectBlocks().
     * @return string           Modified content (ready to save).
     */
    public function inject(string $content, array $config): string
    {
        // Detect if we can use the cheap path
        $simple = true;
        foreach ($config as $item)
        {
            $pos = strtolower($item['position'] ?? '');
            if ($pos !== 'before_content' && $pos !== 'after_content')
            {
                $simple = false;
                break;
            }
        }

        // 2.  FAST path  (only before/after content)
        if ($simple)
        {
            $this->isGutenberg = strpos($content, '<!-- wp:') !== false;

            $before = $after = [];

            foreach ($config as $item)
            {
                $code = $this->prepareSnippet($item['code'] ?? '');

                (strtolower($item['position']) === 'before_content')
                    ? $before[] = $code
                    : $after[]  = $code;
            }

            $out = '';
            if ($before)
            {
                $out .= implode("\n", $before) . "\n";
            }
            $out .= $content;
            if ($after)
            {
                $out .= "\n" . implode("\n", $after);
            }

            return $this->normalizePostWhitespace($out);
        }

        // 3. FULL path  (middle, after_excerpt, after_paragraph_X …)
        $this->blocks = parse_blocks($content);
        $this->buildParagraphChunks();
        $out = $this->injectBlocks($config);

        return $this->normalizePostWhitespace($out);
    }

    protected function splitIntoBlocks(string $content): array
    {
        return $this->blocks = parse_blocks($content);
    }

    /**
     * Builds $this->paragraphChunks so that:
     *   • each chunk ends with a real paragraph (<p>…</p>)
     *   • core/more is glued to the previous chunk
     *   • classic-editor (<p>…</p>) content is split correctly
     */
    protected function buildParagraphChunks(): void
    {
        $this->paragraphChunks   = [];
        $this->excerptChunkIndex = null;
        $this->isGutenberg       = false;

        $buffer         = '';
        $excerptPending = false;

        foreach ($this->blocks as $block)
        {

            if ($block['blockName'] !== null)
            {
                $this->isGutenberg = true;
            }

            /* ── 0. Gutenberg “more” (core/more) ────────────────────── */
            if ($block['blockName'] === 'core/more')
            {
                $buffer .= serialize_block($block);

                if ($this->paragraphChunks)
                {
                    $this->paragraphChunks[array_key_last($this->paragraphChunks)] .= $buffer;
                    $this->excerptChunkIndex = array_key_last($this->paragraphChunks);
                    $buffer         = '';
                    $excerptPending = false;
                }
                else
                {
                    $excerptPending = true;                    // more before first <p>
                }
                continue;
            }

            /* ── 1. Gutenberg paragraph ─────────────────────────────── */
            if ($block['blockName'] === 'core/paragraph')
            {
                $buffer .= serialize_block($block);
                $this->paragraphChunks[] = $buffer;
                $buffer = '';

                if ($excerptPending)
                {
                    $this->excerptChunkIndex = array_key_last($this->paragraphChunks);
                    $excerptPending          = false;
                }
                continue;
            }

            /* ── 2. Classic raw HTML (no block wrapper) ─────────────── */
            if ($block['blockName'] === null)
            {
                $buffer = $this->flushParagraphsFromHtml($block['innerHTML'], $buffer, $excerptPending);
                $excerptPending = false;
                continue;
            }

            /* ── 3. Any other block (core/html, custom, etc.) ───────── */
            $buffer .= serialize_block($block);

            if (
                ! empty($block['innerContent']) &&
                stripos(implode('', (array) $block['innerContent']), '<p') !== false
            )
            {

                $this->paragraphChunks[] = $buffer;
                $buffer = '';

                if ($excerptPending)
                {
                    $this->excerptChunkIndex = array_key_last($this->paragraphChunks);
                    $excerptPending          = false;
                }
            }
        }

        /* ── final flush ────────────────────────────────────────────── */
        if ($buffer !== '')
        {
            $this->paragraphChunks[] = $buffer;
            if ($excerptPending)
            {
                $this->excerptChunkIndex = array_key_last($this->paragraphChunks);
            }
        }
    }

    /**
     * Split a raw-HTML chunk (classic editor) into paragraph-ending chunks.
     *
     * @param string $html            The raw HTML from the current block.
     * @param string $buffer          Accumulated HTML *before* this block.
     * @param bool   &$excerptPending Whether the excerpt ends on the next <p>.
     * @return string                 Residual buffer (no complete <p> yet).
     */
    protected function flushParagraphsFromHtml(string $html, string $buffer, bool &$excerptPending): string
    {
        /* Add the raw HTML exactly once – don’t prepend earlier */
        $parts = preg_split(
            '/(<p\b[^>]*>.*?<\/p>)/is',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        foreach ($parts as $part)
        {
            $buffer .= $part;

            if (preg_match('/^<p\b/i', $part))
            {
                $this->paragraphChunks[] = $buffer;
                $buffer = '';

                if ($excerptPending)
                {
                    $this->excerptChunkIndex = array_key_last($this->paragraphChunks);
                    $excerptPending          = false;
                }
            }
        }

        return $buffer;           // may contain trailing non-<p> HTML
    }

    public function insertBlockAt(int $position, string $blockContent): void
    {
        array_splice($this->blocks, $position, 0, $blockContent);
    }

    public function appendBlock(string $blockContent): void
    {
        $this->blocks[] = $blockContent;
    }

    public function prependBlock(string $blockContent): void
    {
        array_unshift($this->blocks, $blockContent);
    }

    /**
     * Insert each “code” snippet at its declared “position” and
     * return the complete, ready-to-save post_content.
     *
     * Allowed positions:
     *   - before_content, after_content, middle, after_excerpt
     *   - after_paragraph_N  (1-based)
     *
     * @param  array $config  see example below
     * @return string
     */
    public function injectBlocks(array $config): string
    {
        $this->injectedPositions = [];

        if (empty($config))
        {
            return implode('', $this->paragraphChunks);
        }

        $total = count($this->paragraphChunks);
        $map   = [];                // chunk-index  =>  array of codes (keeps order)

        // translate human positions → chunk indices
        foreach ($config as $item)
        {
            $pos  = strtolower($item['position'] ?? '');
            $code = $this->prepareSnippet($item['code'] ?? '');

            if (!$pos || !$code)
            {
                continue;
            }

            switch ($pos)
            {
                case 'before_content':
                    $map[-1][] = $code;
                    $this->injectedPositions[-1] = -1;
                    break;

                case 'after_content':
                    $map[$total][] = $code;
                    $this->injectedPositions[$total] = $total;
                    break;

                case 'middle':
                    $idx = max(0, (int) floor(($total - 1) / 2)); // after middle chunk
                    $map[$idx][] = $code;
                    $this->injectedPositions[$idx] = $idx;
                    break;

                case 'after_excerpt':
                    $idx = $this->excerptChunkIndex !== null ? $this->excerptChunkIndex : 0;
                    $map[$idx][] = $code;
                    $this->injectedPositions[$idx] = $idx;
                    break;

                default:
                    if (preg_match('/after_paragraph_(\d+)/', $pos, $m))
                    {
                        $n   = (int) $m[1];
                        $idx = min(max($n, 1) - 1, $total - 1);   // clamp
                        $map[$idx][] = $code;
                        $this->injectedPositions[$idx] = $idx;
                    }
                    break;
            }
        }

        // rebuild post_content with injections
        $out = '';

        // codes that go before everything
        if (isset($map[-1]))
        {
            $out .= implode("\n", $map[-1]) . "\n";
        }

        foreach ($this->paragraphChunks as $i => $chunk)
        {
            $out .= $chunk;

            if (isset($map[$i]))
            {
                $out .= "\n" . implode("\n", $map[$i]) . "\n";
            }
        }

        // codes that go after everything
        if (isset($map[$total]))
        {
            $out .= "\n" . implode("\n", $map[$total]) . "\n";
        }

        return $out;
    }

    /**
     * Wrap a raw shortcode in a Shortcode block if needed.
     */
    protected function prepareSnippet(string $code): string
    {
        $trim = trim($code);

        // Already a Gutenberg block comment → leave as-is
        if (preg_match('/<!--\s*wp:/i', $trim))
        {
            return $trim;
        }

        // Simple shortcode detector
        if (preg_match('/^\[.+\]$/s', $trim))
        {
            if ($this->isGutenberg)
            {
                return "<!-- wp:shortcode -->\n{$trim}\n<!-- /wp:shortcode -->";
            }
            // classic: keep plain but make sure it sits on its own line
            return "\n{$trim}\n";
        }

        /* Raw HTML or anything else */
        return $code;
    }

    /**
     * Return a list of indexes where snippets were injected.
     *
     *  - -1  → before_content
     *  - 0+ → after paragraph N (0-based)
     *  - lastIndex (=paragraphChunks count) → after_content
     */
    public function getInsertedPositions(): array
    {
        ksort($this->injectedPositions);
        return array_values($this->injectedPositions);
    }

    /**
     * Tidy up cosmetic whitespace _without_ breaking:
     */
    protected function normalizePostWhitespace(string $content): string
    {
        /* 0 — normalise all line endings to "\n" */
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        /* 1 — trim trailing spaces/tabs on every line (safe everywhere) */
        $content = preg_replace('/[ \t]+$/m', '', $content);

        /* 2 — block-aware handling */
        if ($this->isGutenberg)
        {
            /* a) remove indentation on otherwise-blank lines */
            $content = preg_replace('/^[ \t]+$/m', '', $content);

            /* b) collapse 3-or-more blank lines to exactly two */
            $content = preg_replace("/\n{3,}/", "\n\n", $content);

            /* c) full trim, re-add single trailing LF (core style) */
            $content = trim($content) . "\n";
        }
        else
        {
            /**
             * Classic editor:
             *  – keep double-LFs because wpautop() needs them
             *  – but we can reduce 4+ LFs to 2 to avoid giant gaps
             */
            $content = preg_replace("/\n{4,}/", "\n\n", $content);

            // rtrim _only_ spaces/tabs/newlines at the very end
            $content = rtrim($content) . "\n";
        }

        return $content;
    }
}
