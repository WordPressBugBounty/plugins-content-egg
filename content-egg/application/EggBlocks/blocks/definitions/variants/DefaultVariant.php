<?php

namespace ContentEgg\application\EggBlocks\blocks\definitions\variants;

defined('ABSPATH') || exit;

class DefaultVariant
{
    public static function render(array $definitions, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <section class="eggb-block eggb-definitions eggb-definitions--default<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
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

                <dl class="d-flex flex-column gap-2 mb-0">
                    <?php foreach ($definitions['items'] as $item): ?>
                        <div class="eggb-df-row eggb-card d-flex flex-column flex-md-row gap-1 gap-md-3 p-2 p-md-3">
                            <dt class="eggb-df-term mb-0"><?php echo esc_html($item['term']); ?></dt>
                            <dd class="mb-0"><?php echo $item['definition']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </div>
        </section>
        <?php
    }
}
