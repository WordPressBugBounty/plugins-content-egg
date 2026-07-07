<?php

namespace ContentEgg\application\EggBlocks\blocks\quickpicks\variants;

use ContentEgg\application\EggBlocks\blocks\quickpicks\QuickPicksRenderer;

defined('ABSPATH') || exit;

class ShelvesVariant
{
    public static function render(array $payload, string $theme_class, string $data_theme): void
    {
        ?>
        <div class="eggb-block eggb-quick-picks eggb-quick-picks--grouped<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ($payload['section_label'] !== '' || $payload['title'] !== '') : ?>
                <div class="d-flex flex-column gap-1 mb-3">
                    <?php if ($payload['section_label'] !== '') : ?>
                        <div class="eggb-section-title eggb-section-title--muted mb-0"><?php echo esc_html($payload['section_label']); ?></div>
                    <?php endif; ?>
                    <?php if ($payload['title'] !== '') :
                        $ht = $payload['heading_tag']; ?>
                        <<?php echo esc_attr($ht); ?> class="eggb-block-title eggb-block-title--sm eggb-qp-heading"><?php echo esc_html($payload['title']); ?></<?php echo esc_attr($ht); ?>>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <section class="eggb-qp-group">
                <div class="eggb-qp-group-list">
                    <?php foreach ($payload['items'] as $item) : ?>
                        <article class="eggb-qp-group-item">
                            <?php if ($payload['has_any_image']) : ?>
                                <?php self::renderGroupThumb($item); ?>
                            <?php endif; ?>

                            <div class="eggb-qp-group-product d-flex flex-column gap-2">
                                <?php if ($item['badge'] !== '') : ?>
                                    <span class="eggb-qp-group-badge eggb-section-title mb-0"><?php echo esc_html($item['badge']); ?></span>
                                <?php endif; ?>

                                <?php DefaultVariant::renderTitle($item, 'eggb-qp-group-name'); ?>

                                <?php if ($item['subtitle'] !== '') : ?>
                                    <div class="eggb-qp-group-fit"><?php echo esc_html($item['subtitle']); ?></div>
                                <?php elseif ($item['description'] !== '') : ?>
                                    <div class="eggb-qp-group-fit"><?php echo esc_html($item['description']); ?></div>
                                <?php endif; ?>

                                <?php if ($item['subtitle'] !== '' && $item['description'] !== '') : ?>
                                    <div class="eggb-qp-group-note"><?php echo esc_html($item['description']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="eggb-qp-group-rail">
                                <?php if ($item['score'] !== '') : ?>
                                    <div class="eggb-qp-group-score" role="img" aria-label="<?php echo esc_attr(trim($item['score'] . ' / 10')); ?>">
                                        <span class="eggb-score-num"><?php echo esc_html($item['score']); ?></span>
                                        <span class="eggb-score-denom">/ 10</span>
                                    </div>
                                <?php endif; ?>

                                <?php DefaultVariant::renderPrice($item, 'eggb-price eggb-price--md eggb-qp-group-price'); ?>

                                <?php DefaultVariant::renderCta($item, QuickPicksRenderer::resolveCtaLabel($payload, $item['product_item']), 'eggb-btn eggb-btn--filled eggb-qp-group-link'); ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <?php if ($payload['amazon_update_html'] !== '') : ?>
                <div class="eggb-price-meta eggb-qp-group-update"><?php echo $payload['amazon_update_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function renderGroupThumb(array $item): void
    {
        if ($item['has_link']) : ?>
            <?php \ContentEgg\application\helpers\TemplateHelper::openATag($item['product_item'], ['title' => $item['title']], ['class' => 'eggb-qp-group-thumb']); ?>
                <?php DefaultVariant::renderImage($item); ?>
            <?php \ContentEgg\application\helpers\TemplateHelper::closeATag(); ?>
        <?php else : ?>
            <div class="eggb-qp-group-thumb">
                <?php DefaultVariant::renderImage($item); ?>
            </div>
        <?php
        endif;
    }
}
