<?php

namespace ContentEgg\application\EggBlocks\blocks\keytakeaways\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;

defined('ABSPATH') || exit;

class CardsVariant
{
    public static function render(array $takeaways, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <section class="eggb-block eggb-key-takeaways eggb-key-takeaways--cards<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ($takeaways['section_label'] !== '' || $takeaways['title'] !== ''): ?>
                <div class="d-flex flex-column gap-1 mb-3">
                    <?php if ($takeaways['section_label'] !== ''): ?>
                        <span class="eggb-section-title eggb-section-title--muted mb-0"><?php echo esc_html($takeaways['section_label']); ?></span>
                    <?php endif; ?>
                    <?php if ($takeaways['title'] !== ''):
                        $ht = $takeaways['heading_tag']; ?>
                        <<?php echo esc_attr($ht); ?> class="eggb-block-title eggb-block-title--sm eggb-kt-heading"><?php echo esc_html($takeaways['title']); ?></<?php echo esc_attr($ht); ?>>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="row g-2">
                <?php foreach ($takeaways['items'] as $item): ?>
                    <div class="col-12 col-md-6">
                        <article class="eggb-kt-card eggb-card h-100 p-3 d-flex align-items-start gap-2">
                            <span class="eggb-kt-card-icon" aria-hidden="true">
                                <?php echo EggbIcons::get('check2'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </span>
                            <div class="d-flex flex-column gap-1">
                                <?php if ($item['title'] !== ''): ?>
                                    <div class="eggb-kt-card-title"><?php echo esc_html($item['title']); ?></div>
                                <?php endif; ?>
                                <?php if ($item['text'] !== ''): ?>
                                    <div><?php echo $item['text']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                                <?php endif; ?>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($takeaways['note'] !== ''): ?>
                <div class="eggb-kt-note mt-3"><?php echo $takeaways['note']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php endif; ?>
        </section>
        <?php
    }
}
