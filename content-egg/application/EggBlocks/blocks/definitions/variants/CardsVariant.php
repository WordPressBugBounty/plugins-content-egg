<?php

namespace ContentEgg\application\EggBlocks\blocks\definitions\variants;

defined('ABSPATH') || exit;

class CardsVariant
{
    public static function render(array $definitions, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <section class="eggb-block eggb-definitions eggb-definitions--cards<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="d-flex flex-column gap-3">
                <?php if ($definitions['section_label'] !== '' || $definitions['title'] !== ''): ?>
                    <div class="d-flex flex-column gap-1">
                        <?php if ($definitions['section_label'] !== ''): ?>
                            <span class="eggb-section-title eggb-section-title--muted mb-0"><?php echo esc_html($definitions['section_label']); ?></span>
                        <?php endif; ?>
                        <?php if ($definitions['title'] !== ''):
                            $ht = $definitions['heading_tag']; ?>
                            <<?php echo esc_attr($ht); ?> class="eggb-block-title eggb-block-title--sm eggb-df-heading"><?php echo esc_html($definitions['title']); ?></<?php echo esc_attr($ht); ?>>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="row g-2">
                    <?php foreach ($definitions['items'] as $item): ?>
                        <div class="col-12 col-md-6">
                            <article class="eggb-df-card eggb-card h-100 p-3">
                                <p class="eggb-df-card-term mb-1"><?php echo esc_html($item['term']); ?></p>
                                <p class="eggb-df-card-def mb-0"><?php echo $item['definition']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php
    }
}
