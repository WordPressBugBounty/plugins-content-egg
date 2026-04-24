<?php

namespace ContentEgg\application\EggBlocks\shared;

defined('ABSPATH') || exit;

class EggbTocRegistry
{
    private const BLOCKS = [
        'eggb/section-header' => ['default_level' => 2, 'heading_attr' => 'title'],
        'eggb/intro' => ['default_level' => 2, 'heading_attr' => 'title', 'label_attr' => 'section_label'],
        'eggb/conclusion' => ['default_level' => 2, 'heading_attr' => 'title', 'label_attr' => 'section_label'],
        'eggb/faq' => ['default_level' => 2, 'heading_attr' => 'title', 'label_attr' => 'section_label'],
        'eggb/methodology' => ['default_level' => 2, 'heading_attr' => 'title', 'label_attr' => 'section_label'],
        'eggb/step-list' => ['default_level' => 2, 'heading_attr' => 'title', 'label_attr' => 'section_label'],
        'eggb/key-takeaways' => ['default_level' => 2, 'heading_attr' => 'title', 'label_attr' => 'section_label'],
        'eggb/criteria' => ['default_level' => 2, 'heading_attr' => 'title', 'label_attr' => 'section_label'],
        'eggb/definitions' => ['default_level' => 2, 'heading_attr' => 'title', 'label_attr' => 'section_label'],
        'eggb/myth-fact' => ['default_level' => 2, 'heading_attr' => 'title', 'label_attr' => 'section_label'],
        'eggb/specifications' => ['default_level' => 2, 'heading_attr' => 'title', 'label_attr' => 'section_label'],
        'eggb/quick-picks' => ['default_level' => 2, 'heading_attr' => 'title', 'label_attr' => 'section_label'],
        'eggb/where-to-buy' => ['default_level' => 2, 'heading_attr' => 'title', 'label_attr' => 'section_label'],
        'eggb/product-card' => ['default_level' => 2, 'heading_attr' => 'block_title', 'label_attr' => 'section_label'],
        'eggb/comparison-table' => ['default_level' => 2, 'heading_attr' => 'heading_title', 'label_attr' => 'heading_label'],
    ];

    public static function all(): array
    {
        return self::BLOCKS;
    }

    public static function isSupportedBlock(string $name): bool
    {
        return isset(self::BLOCKS[$name]);
    }

    public static function defaultLevel(string $name): int
    {
        return (int) (self::BLOCKS[$name]['default_level'] ?? 2);
    }

    public static function headingAttr(string $name): ?string
    {
        return self::BLOCKS[$name]['heading_attr'] ?? null;
    }

    public static function labelAttr(string $name): ?string
    {
        return self::BLOCKS[$name]['label_attr'] ?? null;
    }
}
