<?php

namespace ContentEgg\application\EggBlocks\blocks\criteria\variants;

defined('ABSPATH') || exit;

class DefaultVariant
{
    public static function render(array $header, array $items, array $labels = [], string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <section class="eggb-block eggb-block--panel eggb-block--accented eggb-criteria eggb-criteria--default d-flex flex-column gap-3<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ($header['section_label'] !== '' || $header['title'] !== ''): ?>
                <div class="d-flex flex-column gap-1">
                    <?php if ($header['section_label'] !== ''): ?>
                        <span class="eggb-section-title eggb-section-title--muted mb-0"><?php echo esc_html($header['section_label']); ?></span>
                    <?php endif; ?>
                    <?php if ($header['title'] !== ''): ?>
                        <<?php echo esc_attr($header['heading_tag']); ?> class="eggb-block-title eggb-block-title--sm eggb-cr-heading"><?php echo esc_html($header['title']); ?></<?php echo esc_attr($header['heading_tag']); ?>>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="eggb-cr-grid">
                <?php foreach ($items as $index => $item): ?>
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <span class="eggb-cr-num"><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></span>
                            <span class="eggb-cr-importance eggb-cr-importance--<?php echo esc_attr($item['importance']); ?>" role="img" aria-label="<?php echo esc_attr($item['importance_label']); ?>"><?php foreach ($item['importance_dots'] as $on): ?><span class="eggb-cr-dot<?php echo $on ? ' eggb-cr-dot--on' : ''; ?>"></span><?php endforeach; ?></span>
                        </div>
                        <?php if ($item['title'] !== ''): ?>
                            <div class="eggb-cr-title"><?php echo esc_html($item['title']); ?></div>
                        <?php endif; ?>
                        <?php if ($item['description'] !== ''): ?>
                            <div class="eggb-cr-desc"><?php echo $item['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        <?php endif; ?>
                        <?php if ($item['look_for'] !== ''): ?>
                            <div class="d-flex flex-column gap-1 mt-auto pt-1">
                                <span class="eggb-section-title eggb-cr-sub-label--positive mb-0"><?php echo esc_html($labels['look_for']); ?></span>
                                <div class="eggb-cr-sub-text"><?php echo esc_html($item['look_for']); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($item['avoid'] !== ''): ?>
                            <div class="d-flex flex-column gap-1">
                                <span class="eggb-section-title eggb-cr-sub-label--negative mb-0"><?php echo esc_html($labels['avoid']); ?></span>
                                <div class="eggb-cr-sub-text"><?php echo esc_html($item['avoid']); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }
}
