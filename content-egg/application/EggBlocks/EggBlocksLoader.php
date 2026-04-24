<?php

namespace ContentEgg\application\EggBlocks;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\EggBlocks\shared\EggbSchemaCollector;

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
    ];

    public static function initAction(): void
    {
        self::registerBlocks();
        EggbSchemaCollector::init();
        add_action('enqueue_block_editor_assets', [self::class, 'enqueueEditorAssets']);
        add_filter('block_categories_all', [self::class, 'registerBlockCategory']);
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

    public static function enqueueEditorAssets(): void
    {
        wp_enqueue_style('cegg-bootstrap5');
        wp_enqueue_style('eggb-base');

        $theme = (string) GeneralConfig::getInstance()->option('eggb_default_theme', 'default');
        if ($theme === '') {
            $theme = 'default';
        }

        $handle = 'eggb-theme-' . sanitize_html_class($theme);
        wp_enqueue_style($handle);
        wp_enqueue_style($handle . '-dark');
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
