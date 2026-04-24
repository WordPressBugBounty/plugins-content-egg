<?php

namespace ContentEgg\application\EggBlocks\blocks\keytakeaways\variants;

defined('ABSPATH') || exit;

class InlineVariant
{
    public static function render(array $takeaways, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <section class="eggb-block eggb-key-takeaways eggb-key-takeaways--inline<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="d-flex flex-column gap-2">
                <?php if ($takeaways['section_label'] !== '' || $takeaways['title'] !== ''): ?>
                    <div class="d-flex flex-column gap-1">
                        <?php if ($takeaways['section_label'] !== ''): ?>
                            <span class="eggb-section-title eggb-section-title--muted mb-0"><?php echo esc_html($takeaways['section_label']); ?></span>
                        <?php endif; ?>
                        <?php if ($takeaways['title'] !== ''):
                            $ht = $takeaways['heading_tag']; ?>
                            <<?php echo esc_attr($ht); ?> class="eggb-block-title eggb-block-title--sm eggb-kt-heading"><?php echo esc_html($takeaways['title']); ?></<?php echo esc_attr($ht); ?>>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <ul class="eggb-kt-inline-list d-flex flex-column gap-2 mb-0">
                    <?php foreach ($takeaways['items'] as $item): ?>
                        <li class="eggb-kt-inline-item d-flex flex-column gap-1">
                            <?php if ($item['title'] !== ''): ?>
                                <span class="eggb-kt-item-title"><?php echo esc_html($item['title']); ?></span>
                            <?php endif; ?>
                            <?php if ($item['text'] !== ''): ?>
                                <span><?php echo $item['text']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($takeaways['note'] !== ''): ?>
                    <div class="eggb-kt-note"><?php echo $takeaways['note']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php endif; ?>
            </div>
        </section>
        <?php
    }
}
