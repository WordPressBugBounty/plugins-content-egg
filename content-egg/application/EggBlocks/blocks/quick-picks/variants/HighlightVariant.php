<?php

namespace ContentEgg\application\EggBlocks\blocks\quickpicks\variants;

use ContentEgg\application\EggBlocks\blocks\quickpicks\QuickPicksRenderer;
use ContentEgg\application\EggBlocks\shared\EggbIcons;

defined('ABSPATH') || exit;

class HighlightVariant
{
    public static function render(array $payload, string $theme_class, string $data_theme): void
    {
        if (empty($payload['items'])) {
            return;
        }

        $lead_item = $payload['items'][0];
        $secondary_items = array_slice($payload['items'], 1);
        ?>
        <div class="eggb-block eggb-quick-picks eggb-quick-picks--highlight d-flex flex-column gap-3<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ($payload['section_label'] !== '' || $payload['title'] !== '') : ?>
                <div class="d-flex flex-column gap-1">
                    <?php if ($payload['section_label'] !== '') : ?>
                        <div class="eggb-section-title eggb-section-title--muted mb-0"><?php echo esc_html($payload['section_label']); ?></div>
                    <?php endif; ?>
                    <?php if ($payload['title'] !== '') :
                        $ht = $payload['heading_tag']; ?>
                        <<?php echo esc_attr($ht); ?> class="eggb-block-title eggb-block-title--sm eggb-qp-heading"><?php echo esc_html($payload['title']); ?></<?php echo esc_attr($ht); ?>>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php self::renderLead($lead_item, $payload); ?>

            <?php foreach ($secondary_items as $item) : ?>
                <?php self::renderSecondary($item, $payload); ?>
            <?php endforeach; ?>

            <?php if ($payload['amazon_update_html'] !== '') : ?>
                <div class="eggb-price-meta eggb-qp-highlight-update"><?php echo $payload['amazon_update_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function renderLead(array $item, array $payload): void
    {
        ?>
        <article class="eggb-qp-highlight-card eggb-card eggb-qp-highlight-card--lead d-flex">
            <?php self::renderMedia($item, 'eggb-qp-highlight-media'); ?>

            <div class="p-3 d-flex flex-column gap-3 flex-grow-1 min-w-0">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <?php if ($item['badge'] !== '') : ?>
                            <span class="eggb-award">
                                <?php echo EggbIcons::get('award-fill', 'eggb-award-icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <?php echo esc_html($item['badge']); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($item['score'] !== '') : ?>
                        <div class="eggb-qp-highlight-score d-flex align-items-baseline gap-1" aria-label="<?php echo esc_attr(trim($item['score'] . ' / 10')); ?>">
                            <span class="eggb-score-num"><?php echo esc_html($item['score']); ?></span>
                            <span class="eggb-score-denom">/ 10</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex flex-column gap-2">
                    <div class="eggb-qp-highlight-title-row d-flex align-items-baseline gap-2">
                        <span class="eggb-qp-highlight-rank" aria-hidden="true"><?php echo esc_html($item['rank_display']); ?></span>
                        <?php DefaultVariant::renderTitle($item, 'eggb-qp-highlight-title'); ?>
                    </div>

                    <?php if ($item['subtitle'] !== '') : ?>
                        <div class="eggb-qp-fit"><?php echo esc_html($item['subtitle']); ?></div>
                    <?php endif; ?>

                    <?php DefaultVariant::renderChips($item['chips']); ?>
                </div>

                <?php if ($item['description'] !== '') : ?>
                    <div class="eggb-qp-highlight-copy"><?php echo esc_html($item['description']); ?></div>
                <?php endif; ?>

                <div class="eggb-qp-highlight-rule d-flex flex-wrap align-items-center gap-3 pt-2">
                    <div class="d-flex flex-column">
                        <?php DefaultVariant::renderPrice($item, 'eggb-price eggb-price--lg eggb-qp-highlight-price'); ?>
                        <?php DefaultVariant::renderMerchant($item, 'eggb-qp-store'); ?>
                    </div>

                    <?php DefaultVariant::renderCta($item, QuickPicksRenderer::resolveCtaLabel($payload, $item['product_item']), 'eggb-btn eggb-btn--filled eggb-qp-highlight-cta'); ?>
                </div>
            </div>
        </article>
        <?php
    }

    private static function renderSecondary(array $item, array $payload): void
    {
        ?>
        <article class="eggb-qp-highlight-alt">
            <?php self::renderMedia($item, 'eggb-qp-highlight-alt-media'); ?>

            <div class="eggb-qp-highlight-alt-body d-flex flex-column gap-2">
                <div class="d-flex flex-column gap-1">
                    <?php if ($item['badge'] !== '') : ?>
                        <span class="eggb-section-title eggb-qp-highlight-alt-kicker mb-1"><?php echo esc_html($item['badge']); ?></span>
                    <?php endif; ?>

                    <div class="eggb-qp-highlight-title-row d-flex align-items-baseline gap-2">
                        <span class="eggb-qp-highlight-rank" aria-hidden="true"><?php echo esc_html($item['rank_display']); ?></span>
                        <?php DefaultVariant::renderTitle($item, 'eggb-qp-highlight-title eggb-qp-highlight-alt-title'); ?>
                    </div>
                </div>

                <?php if ($item['subtitle'] !== '') : ?>
                    <div class="eggb-qp-fit"><?php echo esc_html($item['subtitle']); ?></div>
                <?php endif; ?>

                <?php if ($item['description'] !== '') : ?>
                    <div class="eggb-qp-highlight-alt-copy"><?php echo esc_html($item['description']); ?></div>
                <?php endif; ?>

                <?php DefaultVariant::renderChips($item['chips']); ?>
            </div>

            <div class="eggb-qp-highlight-alt-rail">
                <?php if ($item['score'] !== '') : ?>
                    <div class="eggb-qp-highlight-alt-score" aria-label="<?php echo esc_attr(trim($item['score'] . ' / 10')); ?>">
                        <span class="eggb-score-num"><?php echo esc_html($item['score']); ?></span>
                        <span class="eggb-score-denom">/ 10</span>
                    </div>
                <?php endif; ?>

                <div class="d-flex flex-column align-items-end">
                    <?php DefaultVariant::renderPrice($item, 'eggb-price eggb-price--lg eggb-qp-highlight-alt-price'); ?>
                    <?php DefaultVariant::renderMerchant($item, 'eggb-qp-store'); ?>
                </div>

                <?php DefaultVariant::renderCta($item, QuickPicksRenderer::resolveCtaLabel($payload, $item['product_item']), 'eggb-btn eggb-btn--filled eggb-qp-highlight-alt-cta'); ?>
            </div>
        </article>
        <?php
    }

    private static function renderMedia(array $item, string $class): void
    {
        if ($item['has_link']) : ?>
            <?php \ContentEgg\application\helpers\TemplateHelper::openATag($item['product_item'], ['title' => $item['title']], ['class' => $class]); ?>
                <?php DefaultVariant::renderImage($item); ?>
            <?php \ContentEgg\application\helpers\TemplateHelper::closeATag(); ?>
        <?php else : ?>
            <div class="<?php echo esc_attr($class); ?>">
                <?php DefaultVariant::renderImage($item); ?>
            </div>
        <?php
        endif;
    }
}
