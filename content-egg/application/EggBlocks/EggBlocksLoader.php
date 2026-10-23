<?php

namespace ContentEgg\application\EggBlocks;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\EggBlocks\shared\EggbBlockGuard;
use ContentEgg\application\EggBlocks\shared\EggbSchemaCollector;
use ContentEgg\application\EggBlocks\shared\PriceFormatBridge;

defined('ABSPATH') || exit;

class EggBlocksLoader
{
    private const BLOCKS_DIR = __DIR__ . '/blocks/';

    private const BLOCKS = [
        'pros-cons',
        'verdict',
        'product-card',
        'editorial-product',
        'quick-picks',
        'comparison-table',
        'where-to-buy',
        'specifications',
        'callout',
        'intro',
        'conclusion',
        'faq',
        'definitions',
        'myth-fact',
        'methodology',
        'criteria',
        'key-takeaways',
        'step-list',
        'section-header',
        'rating-breakdown',
        'toc',
        'related-posts',
        'contextual-cta',
        'pricing',
        'testimonial',
        'trust-signals',
        'variant-switcher',
    ];

    public static function initAction(): void
    {
        // Refuses writes that would corrupt eggb/* block markup. Registered
        // first so it is active regardless of what happens below.
        EggbBlockGuard::initAction();

        // Must be added BEFORE register_block_type() so each eggb/* block
        // gets the active theme CSS attached at registration time, as
        // view + editor style handles (see attachThemeStyles()).
        add_filter('register_block_type_args', [self::class, 'attachThemeStyles'], 10, 2);

        // Also before register_block_type(): WordPress takes each block
        // stylesheet's cache-busting ?ver= straight from block.json's
        // "version", which every block here pins at 1.0.0. Left alone, a
        // released CSS change never invalidates a browser or CDN copy, and
        // visitors keep the stale file with nothing to signal it. Stamping the
        // plugin version makes the URL move with each release.
        add_filter('block_type_metadata', [self::class, 'stampBlockVersion']);

        self::registerBlocks();
        EggbSchemaCollector::init();
        PriceFormatBridge::register();
        add_filter('block_categories_all', [self::class, 'registerBlockCategory']);
        add_filter('render_block', [self::class, 'maybeBlockOutput'], 999, 2);
        add_action('wp_enqueue_scripts', [self::class, 'preloadContentStyles'], 9);
    }

    public static function maybeBlockOutput($block_content, $block)
    {
        $name = (is_array($block) && isset($block['blockName'])) ? (string) $block['blockName'] : '';
        if ($name === '' || strpos($name, 'eggb/') !== 0)
        {
            return $block_content;
        }

        if (!\file_exists(\ContentEgg\PLUGIN_PATH . 'application/Autoupdate.php'))
        {
            return $block_content;
        }

        $locked = (bool) \get_option('cegg_locked', false);
        $at     = (int) \get_option('cegg_locked_at', 0);
        if ($locked && $at > 0 && (time() - $at) >= 259200)
        {
            return '';
        }

        return $block_content;
    }

    public static function registerBlocks(): void
    {
        EggBlocksAssets::register();

        foreach (self::BLOCKS as $block)
        {
            $blockDir = self::BLOCKS_DIR . $block;
            if (is_dir($blockDir))
            {
                register_block_type($blockDir);
            }
        }
    }

    public static function attachThemeStyles(array $args, string $name): array
    {
        if (strpos($name, 'eggb/') !== 0) {
            return $args;
        }

        $theme = (string) GeneralConfig::getInstance()->option('eggb_default_theme', 'default');
        if ($theme === '') {
            $theme = 'default';
        }
        $theme = sanitize_html_class($theme);

        $handles = [
            'eggb-theme-' . $theme,
            'eggb-theme-' . $theme . '-dark',
        ];

        // Work on the resolved *_handles arrays. Assigning to the legacy 'style'
        // key would trigger WP_Block_Type::__set() and OVERWRITE the file-based
        // handle WP already resolved from block.json.
        $styles = isset($args['style_handles']) && is_array($args['style_handles']) ? $args['style_handles'] : [];
        foreach ($handles as $handle) {
            if (!in_array($handle, $styles, true)) {
                $styles[] = $handle;
            }
        }

        return self::scopeStyles($args, $styles);
    }

    /**
     * Move block CSS from style_handles to view_style_handles + editor_style_handles.
     *
     * style_handles are enqueued on EVERY front-end page unless WordPress loads
     * block assets on demand, which is off on classic themes before WP 6.9 or
     * when a theme/plugin opts out (e.g. Rehub). view_style_handles are only
     * enqueued when the block actually renders; editor_style_handles are
     * enqueued into the iframed editor canvas by _wp_get_iframed_editor_assets().
     *
     * @param array    $args    Block type args.
     * @param string[] $handles Style handles the block needs on front end and in the editor.
     * @return array
     */
    public static function scopeStyles(array $args, array $handles): array
    {
        foreach (['view_style_handles', 'editor_style_handles'] as $key) {
            $current = isset($args[$key]) && is_array($args[$key]) ? $args[$key] : [];
            $args[$key] = array_values(array_unique(array_merge($current, $handles)));
        }
        $args['style_handles'] = [];

        return $args;
    }

    /**
     * Enqueue the CSS of eggb/* and content-egg/* blocks (and CE product
     * shortcodes) found in the current singular post's content, so it prints
     * in <head>. Without this, on sites that don't hoist on-demand block
     * styles, it would print in the footer after the block and shift the
     * layout. Anything outside post_content (widgets, templates, reusable
     * blocks, auto-embedded modules) still loads its CSS on render.
     */
    public static function preloadContentStyles(): void
    {
        if (!is_singular()) {
            return;
        }

        $post = get_queried_object();
        if (!$post instanceof \WP_Post) {
            return;
        }

        $content = (string) $post->post_content;

        // [content-egg] / [content-egg-block] render through TemplateManager,
        // which enqueues these same handles at render time.
        if (preg_match('/\[content-egg(?:-block)?[\s\]]/', $content)) {
            wp_enqueue_style('cegg-bootstrap5');
            wp_enqueue_style('cegg-products');
        }

        if (strpos($content, '<!-- wp:eggb/') === false && strpos($content, '<!-- wp:content-egg/') === false) {
            return;
        }

        $registry = \WP_Block_Type_Registry::get_instance();
        foreach (self::collectBlockNames(parse_blocks($content)) as $name) {
            $type = $registry->get_registered($name);
            if (!$type) {
                continue;
            }
            foreach ($type->view_style_handles as $handle) {
                wp_enqueue_style($handle);
            }
        }
    }

    /**
     * @param array $blocks Parsed blocks.
     * @return string[] Unique eggb/* and content-egg/* block names, nested blocks included.
     */
    private static function collectBlockNames(array $blocks): array
    {
        $names = [];
        foreach ($blocks as $block) {
            $name = isset($block['blockName']) ? (string) $block['blockName'] : '';
            if (strpos($name, 'eggb/') === 0 || strpos($name, 'content-egg/') === 0) {
                $names[$name] = true;
            }
            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                foreach (self::collectBlockNames($block['innerBlocks']) as $inner) {
                    $names[$inner] = true;
                }
            }
        }

        return array_keys($names);
    }

    /**
     * Stamp the plugin version onto eggb/* block metadata, so every block
     * stylesheet is served as ?ver=<plugin version> rather than the 1.0.0
     * frozen into block.json. Runs before the style handles are registered,
     * which is where WordPress reads this value.
     *
     * @param array $metadata Parsed block.json.
     * @return array
     */
    public static function stampBlockVersion($metadata)
    {
        if (!is_array($metadata) || !isset($metadata['name']))
        {
            return $metadata;
        }

        if (strpos((string) $metadata['name'], 'eggb/') !== 0)
        {
            return $metadata;
        }

        $metadata['version'] = \ContentEgg\application\Plugin::version();

        return $metadata;
    }

    public static function registerBlockCategory(array $categories): array
    {
        array_unshift($categories, [
            'slug'  => 'cegg-blocks',
            'title' => 'Egg Blocks',
            'icon'  => null,
        ]);
        return $categories;
    }
}
