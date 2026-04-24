<?php

namespace ContentEgg\application\EggBlocks\blocks\toc\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;
use ContentEgg\application\EggBlocks\blocks\toc\ProductNavSection;

defined('ABSPATH') || exit;

class NumberedVariant
{
    public static function render(string $label, array $items, bool $collapsible, bool $default_open, string $theme_class, string $data_theme, array $products = []): void
    {
        ?>
        <nav class="eggb-block eggb-card eggb-toc eggb-toc--numbered <?php echo esc_attr($theme_class); ?>" aria-label="<?php esc_attr_e('Table of contents', 'content-egg-tpl'); ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
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
        $use_cols = self::shouldUseTwoColumns($items);
        $list_class = $use_cols
            ? 'eggb-toc-list mt-3 eggb-toc-list--cols'
            : 'eggb-toc-list d-flex flex-column gap-1 mt-3';
        ?>
        <ol class="<?php echo esc_attr($list_class); ?>">
            <?php foreach ($items as $item) : ?>
                <li class="eggb-toc-item d-flex flex-column eggb-toc-level-<?php echo esc_attr((string) ($item['level'] ?? 2)); ?>">
                    <?php self::renderLink($item); ?>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php
    }

    private static function shouldUseTwoColumns(array $items): bool
    {
        if (count($items) < 4)
        {
            return false;
        }

        foreach ($items as $item)
        {
            if (mb_strlen($item['label'] ?? '') > 40)
            {
                return false;
            }
        }

        return true;
    }

    private static function renderLink(array $item): void
    {
        $classes = 'eggb-toc-link d-flex align-items-center gap-2 fw-medium';
        if ($item['anchor'] !== '') :
            ?>
            <a href="<?php echo esc_attr($item['anchor']); ?>" class="<?php echo esc_attr($classes); ?>">
                <span class="eggb-toc-text"><?php echo esc_html($item['label']); ?></span>
            </a>
        <?php else : ?>
            <span class="<?php echo esc_attr($classes); ?>">
                <span class="eggb-toc-text"><?php echo esc_html($item['label']); ?></span>
            </span>
            <?php
        endif;
    }
}
