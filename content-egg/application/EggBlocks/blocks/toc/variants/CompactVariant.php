<?php

namespace ContentEgg\application\EggBlocks\blocks\toc\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;
use ContentEgg\application\EggBlocks\blocks\toc\ProductNavSection;

defined('ABSPATH') || exit;

class CompactVariant
{
    public static function render(string $label, array $items, bool $collapsible, bool $default_open, string $theme_class, string $data_theme, array $products = []): void
    {
        ?>
        <nav class="eggb-block eggb-card eggb-toc eggb-toc--compact <?php echo esc_attr($theme_class); ?>" aria-label="<?php esc_attr_e('Table of contents', 'content-egg-tpl'); ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ($collapsible) : ?>
                <details<?php echo $default_open ? ' open' : ''; ?>>
                    <summary class="d-flex align-items-center justify-content-between gap-2">
                        <?php self::renderLabel($label); ?>
                        <?php echo EggbIcons::get('chevron-down', 'eggb-toc-chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </summary>
                    <?php self::renderList($items); ?>
                    <?php ProductNavSection::render($products); ?>
                </details>
            <?php else : ?>
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <?php self::renderLabel($label); ?>
                </div>
                <?php self::renderList($items); ?>
                <?php ProductNavSection::render($products); ?>
            <?php endif; ?>
        </nav>
        <?php
    }

    private static function renderLabel(string $label): void
    {
        if ($label === '') {
            return;
        }
        ?>
        <p class="eggb-toc-label mb-0"><?php echo esc_html($label); ?></p>
        <?php
    }

    private static function renderList(array $items): void
    {
        ?>
        <ul class="eggb-toc-list eggb-toc-cols mt-2">
            <?php foreach ($items as $item) : ?>
                <li class="eggb-toc-item eggb-toc-level-<?php echo esc_attr((string) ($item['level'] ?? 2)); ?>">
                    <?php self::renderLink($item); ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    private static function renderLink(array $item): void
    {
        $classes = 'eggb-toc-link d-flex align-items-center gap-1';
        if ($item['anchor'] !== '') :
            ?>
            <a href="<?php echo esc_attr($item['anchor']); ?>" class="<?php echo esc_attr($classes); ?>"><?php echo esc_html($item['label']); ?></a>
        <?php else : ?>
            <span class="<?php echo esc_attr($classes); ?>"><?php echo esc_html($item['label']); ?></span>
            <?php
        endif;
    }
}
