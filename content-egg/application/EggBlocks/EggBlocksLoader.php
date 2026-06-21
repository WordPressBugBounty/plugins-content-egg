<?php

namespace ContentEgg\application\EggBlocks;

use ContentEgg\application\admin\GeneralConfig;
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
    ];

    public static function initAction(): void
    {
        // Must be added BEFORE register_block_type() so each eggb/* block
        // gets the active theme CSS attached at registration time. This is
        // the only style-loading path that reaches the iframed editor canvas
        // in WP 6.3+ without depending on legacy non-iframe behavior.
        add_filter('register_block_type_args', [self::class, 'attachThemeStyles'], 10, 2);

        self::registerBlocks();
        EggbSchemaCollector::init();
        PriceFormatBridge::register();
        add_filter('block_categories_all', [self::class, 'registerBlockCategory']);
        add_filter('render_block', [self::class, 'maybeBlockOutput'], 999, 2);
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

        // Append to style_handles (the modern WP 6.1+ property used for loading).
        // Assigning to the legacy 'style' key would trigger WP_Block_Type::__set()
        // and OVERWRITE the file-based handle WP already resolved from block.json.
        if (!isset($args['style_handles']) || !is_array($args['style_handles'])) {
            $args['style_handles'] = [];
        }

        foreach ($handles as $handle) {
            if (!in_array($handle, $args['style_handles'], true)) {
                $args['style_handles'][] = $handle;
            }
        }

        return $args;
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
