<?php

namespace ContentEgg\application\EggBlocks\blocks\quickpicks\variants;

use ContentEgg\application\EggBlocks\blocks\quickpicks\QuickPicksRenderer;
use ContentEgg\application\EggBlocks\shared\EggbIcons;
use ContentEgg\application\helpers\TemplateHelper;

defined('ABSPATH') || exit;

class DefaultVariant
{
    public static function render(array $payload, string $theme_class, string $data_theme): void
    {
        ?>
        <div class="eggb-block eggb-block--panel eggb-quick-picks eggb-quick-picks--default<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php self::renderHeader($payload, 'eggb-qp-head', 'eggb-qp-updated'); ?>

            <div class="eggb-qp-body">
                <div class="eggb-qp-items">
                    <?php foreach ($payload['items'] as $item) : ?>
                        <article class="eggb-qp-item">
                            <div class="eggb-qp-rank-wrap" aria-hidden="true">
                                <span class="eggb-qp-rank"><?php echo esc_html($item['rank_display']); ?></span>
                            </div>

                            <?php if ($payload['has_any_image']) : ?>
                                <?php self::renderThumb($item); ?>
                            <?php endif; ?>

                            <div class="eggb-qp-main d-flex flex-column gap-2">
                                <div class="eggb-qp-topline d-flex flex-wrap align-items-center gap-2" data-rank="<?php echo esc_attr($item['rank_display'] . ' Pick'); ?>">
                                    <?php self::renderTitle($item, 'eggb-qp-name'); ?>
                                    <?php if ($item['badge'] !== '') : ?>
                                        <span class="eggb-award">
                                            <?php echo EggbIcons::get('award-fill', 'eggb-award-icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            <?php echo esc_html($item['badge']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($item['subtitle'] !== '') : ?>
                                    <div class="eggb-qp-fit"><?php echo esc_html($item['subtitle']); ?></div>
                                <?php endif; ?>

                                <?php self::renderChips($item['chips']); ?>

                                <?php if ($item['description'] !== '') : ?>
                                    <div class="eggb-qp-note"><?php echo esc_html($item['description']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="eggb-qp-rail d-flex flex-column gap-2">
                                <?php if ($item['score'] !== '') : ?>
                                    <div class="eggb-qp-score" role="img" aria-label="<?php echo esc_attr(trim($item['score'] . ' / 10')); ?>">
                                        <span class="eggb-score-num"><?php echo esc_html($item['score']); ?></span>
                                        <span class="eggb-score-denom">/ 10</span>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex flex-column">
                                    <?php self::renderPrice($item, 'eggb-price eggb-price--md eggb-qp-price'); ?>
                                    <?php self::renderMerchant($item, 'eggb-qp-store'); ?>
                                </div>

                                <?php self::renderCta($item, QuickPicksRenderer::resolveCtaLabel($payload, $item['product_item']), 'eggb-btn eggb-btn--filled eggb-qp-cta'); ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($payload['section_label'] === '' && $payload['title'] === '' && $payload['amazon_update_html'] !== '') : ?>
                <div class="eggb-qp-foot">
                    <div class="eggb-qp-foot-note"><?php echo $payload['amazon_update_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function renderHeader(array $payload, string $headerClass, string $metaClass): void
    {
        $has_section_label = $payload['section_label'] !== '';
        $has_title = $payload['title'] !== '';
        $has_amazon = $payload['amazon_update_html'] !== '';

        if (!$has_section_label && !$has_title && !$has_amazon) {
            return;
        }
        ?>
        <div class="<?php echo esc_attr($headerClass); ?> d-flex flex-wrap align-items-end justify-content-between gap-2">
            <div class="d-flex flex-column gap-1">
                <?php if ($has_section_label) : ?>
                    <div class="eggb-section-title eggb-section-title--muted mb-0"><?php echo esc_html($payload['section_label']); ?></div>
                <?php endif; ?>
                <?php if ($has_title) :
                    $ht = $payload['heading_tag']; ?>
                    <<?php echo esc_attr($ht); ?> class="eggb-block-title eggb-block-title--sm eggb-qp-heading"><?php echo esc_html($payload['title']); ?></<?php echo esc_attr($ht); ?>>
                <?php endif; ?>
            </div>

            <?php if (($has_section_label || $has_title) && $has_amazon) : ?>
                <span class="<?php echo esc_attr($metaClass); ?>"><?php echo $payload['amazon_update_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function renderPlaceholder(array $payload, string $theme_class, string $data_theme, string $label): void
    {
        ?>
        <div class="eggb-block eggb-card eggb-quick-picks eggb-quick-picks--<?php echo esc_attr($payload['variant']); ?><?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ($payload['section_label'] !== '' || $payload['title'] !== '') : ?>
                <div class="eggb-qp-placeholder-head">
                    <?php if ($payload['section_label'] !== '') : ?>
                        <div class="eggb-section-title eggb-section-title--muted mb-0"><?php echo esc_html($payload['section_label']); ?></div>
                    <?php endif; ?>
                    <?php if ($payload['title'] !== '') :
                        $ht = $payload['heading_tag']; ?>
                        <<?php echo esc_attr($ht); ?> class="eggb-block-title eggb-block-title--sm eggb-qp-heading"><?php echo esc_html($payload['title']); ?></<?php echo esc_attr($ht); ?>>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="eggb-qp-placeholder-body">
                <div class="eggb-qp-placeholder-meta">
                    <span class="eggb-award"><?php echo esc_html($label); ?> Placeholder</span>
                    <span class="eggb-qp-placeholder-count"><?php echo esc_html(count($payload['items'])); ?> items</span>
                </div>

                <ol class="eggb-qp-placeholder-list">
                    <?php foreach ($payload['items'] as $item) : ?>
                        <li class="eggb-qp-placeholder-item">
                            <span class="eggb-qp-placeholder-rank"><?php echo esc_html($item['rank_display']); ?></span>
                            <span class="eggb-qp-placeholder-title"><?php echo esc_html($item['title']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </div>
        <?php
    }

    public static function renderChips(array $chips): void
    {
        if (empty($chips)) {
            return;
        }
        ?>
        <div class="d-flex flex-wrap gap-1">
            <?php foreach ($chips as $chip) : ?>
                <span class="eggb-chip"><?php echo esc_html($chip); ?></span>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public static function renderThumb(array $item): void
    {
        ?>
        <?php if ($item['has_link']) : ?>
            <?php TemplateHelper::openATag($item['product_item'], ['title' => $item['title']], ['class' => 'eggb-qp-thumb-link']); ?>
                <?php self::renderImage($item); ?>
            <?php TemplateHelper::closeATag(); ?>
        <?php else : ?>
            <div class="eggb-qp-thumb-link">
                <?php self::renderImage($item); ?>
            </div>
        <?php endif; ?>
        <?php
    }

    public static function renderImage(array $item): void
    {
        $image_html = self::captureOutput(static function () use ($item): void {
            TemplateHelper::displayImage($item['product_item'], 350, 350, ['class' => 'eggb-qp-thumb']);
        });

        if ($image_html !== '') {
            echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        echo '<span class="eggb-qp-thumb eggb-qp-thumb--empty" aria-hidden="true"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public static function renderTitle(array $item, string $class): void
    {
        if ($item['title'] === '') {
            return;
        }

        if ($item['has_link']) : ?>
            <?php TemplateHelper::openATag($item['product_item'], ['title' => $item['title']], ['class' => $class]); ?>
                <?php echo esc_html($item['title']); ?>
            <?php TemplateHelper::closeATag(); ?>
        <?php else : ?>
            <div class="<?php echo esc_attr($class); ?>" title="<?php echo esc_attr($item['title']); ?>"><?php echo esc_html($item['title']); ?></div>
        <?php
        endif;
    }

    public static function renderPrice(array $item, string $class): void
    {
        $price_html = self::captureOutput(static function () use ($item): void {
            TemplateHelper::price($item['product_item']);
        });

        if ($price_html !== '') : ?>
            <span class="<?php echo esc_attr($class); ?>"><?php echo $price_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        <?php
        endif;
    }

    public static function renderMerchant(array $item, string $class): void
    {
        $merchant_html = $item['merchant'] !== ''
            ? esc_html($item['merchant'])
            : self::captureOutput(static function () use ($item): void {
                TemplateHelper::merchant($item['product_item']);
            });

        if ($merchant_html === '') {
            return;
        }
        ?>
        <span class="<?php echo esc_attr($class); ?>"><?php echo $merchant_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        <?php
    }

    public static function renderCta(array $item, string $label, string $class): void
    {
        if (!$item['has_link']) {
            return;
        }
        ?>
        <?php TemplateHelper::openATag($item['product_item'], [], ['class' => $class]); ?>
            <?php echo esc_html($label); ?>
            <?php echo EggbIcons::get('arrow-right-short', 'eggb-qp-cta-icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php TemplateHelper::closeATag(); ?>
        <?php
    }

    public static function captureOutput(callable $callback): string
    {
        ob_start();
        $callback();
        return trim((string) ob_get_clean());
    }
}
