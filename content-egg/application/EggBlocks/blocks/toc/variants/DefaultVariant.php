<?php

namespace ContentEgg\application\EggBlocks\blocks\toc\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;
use ContentEgg\application\EggBlocks\blocks\toc\ProductNavSection;

defined('ABSPATH') || exit;

class DefaultVariant
{
    public static function render(string $label, array $items, bool $collapsible, bool $default_open, string $theme_class, string $data_theme, array $products = []): void
    {
        ?>
        <nav class="eggb-block eggb-card eggb-toc eggb-toc--default <?php echo esc_attr($theme_class); ?>" aria-label="<?php esc_attr_e('Table of contents', 'content-egg-tpl'); ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ($collapsible) : ?>
                <details<?php echo $default_open ? ' open' : ''; ?>>
                    <summary class="d-flex align-items-center justify-content-between gap-2">
                        <?php self::renderLabel($label, true); ?>
                        <?php echo EggbIcons::get('chevron-down', 'eggb-toc-chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </summary>
                    <?php self::renderList($items, true, false); ?>
                    <?php ProductNavSection::render($products); ?>
                </details>
            <?php else : ?>
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <?php self::renderLabel($label, true); ?>
                </div>
                <?php self::renderList($items, true, false); ?>
                <?php ProductNavSection::render($products); ?>
            <?php endif; ?>
        </nav>
        <?php
    }

    private static function renderLabel(string $label, bool $with_icon): void
    {
        if ($label === '') {
            return;
        }
        ?>
        <p class="eggb-toc-label mb-0">
            <?php if ($with_icon) : ?>
                <?php echo EggbIcons::get('list-ul', 'eggb-toc-label-icon me-1'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
            <?php echo esc_html($label); ?>
        </p>
        <?php
    }

    private static function renderList(array $items, bool $with_sub_items, bool $numbered): void
    {
        $use_cols = self::shouldUseTwoColumns($items);
        $list_class = $use_cols
            ? 'eggb-toc-list mt-3 eggb-toc-list--cols'
            : 'eggb-toc-list d-flex flex-column gap-1 mt-3';
        ?>
        <ol class="<?php echo esc_attr($list_class); ?>">
            <?php foreach ($items as $item) : ?>
                <li class="eggb-toc-item d-flex flex-column eggb-toc-level-<?php echo esc_attr((string) ($item['level'] ?? 2)); ?>">
                    <?php self::renderLink($item, $numbered, true); ?>
                    <?php if ($with_sub_items && !empty($item['sub_items'])) : ?>
                        <ul class="eggb-toc-sub d-flex flex-column ps-3 mt-1 mb-1">
                            <?php foreach ($item['sub_items'] as $sub_item) : ?>
                                <li>
                                    <?php self::renderLink($sub_item, false, false); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
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
            if (!empty($item['sub_items']))
            {
                return false;
            }

            if (mb_strlen($item['label'] ?? '') > 40)
            {
                return false;
            }
        }

        return true;
    }

    private static function renderLink(array $item, bool $numbered, bool $is_top_level): void
    {
        $classes = 'eggb-toc-link d-flex align-items-center ' . ($numbered ? 'gap-2' : 'gap-1');
        if ($is_top_level) {
            $classes .= ' fw-medium';
        }
        if ($item['anchor'] !== '') :
            ?>
            <a href="<?php echo esc_attr($item['anchor']); ?>" class="<?php echo esc_attr($classes); ?>">
                <?php if ($numbered) : ?>
                    <span class="eggb-toc-text"><?php echo esc_html($item['label']); ?></span>
                <?php else : ?>
                    <?php echo esc_html($item['label']); ?>
                <?php endif; ?>
            </a>
        <?php else : ?>
            <span class="<?php echo esc_attr($classes); ?>">
                <?php if ($numbered) : ?>
                    <span class="eggb-toc-text"><?php echo esc_html($item['label']); ?></span>
                <?php else : ?>
                    <?php echo esc_html($item['label']); ?>
                <?php endif; ?>
            </span>
            <?php
        endif;
    }
}
