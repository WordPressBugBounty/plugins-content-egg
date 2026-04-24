<?php

namespace ContentEgg\application\EggBlocks\blocks\comparisontable\variants;

use ContentEgg\application\EggBlocks\blocks\comparisontable\ComparisonTableRenderer;
use ContentEgg\application\EggBlocks\shared\EggbIcons;
use ContentEgg\application\helpers\TemplateHelper;

defined('ABSPATH') || exit;

class DefaultVariant
{
    public static function render(array $payload, string $theme_class, string $data_theme): void
    {
        $winner_index = self::getWinnerIndex($payload['items']);
        $has_images = self::hasAnyImages($payload['items']);
        $has_footer = $payload['footer_note'] !== '' || $payload['amazon_update_html'] !== '';
        ?>
        <div class="eggb-block eggb-card eggb-ct eggb-ct--default<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ($payload['heading_label'] !== '' || $payload['heading_title'] !== '') : ?>
                <div class="eggb-ct-header px-3 px-md-4 pt-3 pt-md-4 pb-3">
                    <?php if ($payload['heading_label'] !== '') : ?>
                        <div class="eggb-ct-label mb-1"><?php echo esc_html($payload['heading_label']); ?></div>
                    <?php endif; ?>
                    <?php if ($payload['heading_title'] !== '') : ?>
                        <?php $ht = $payload['heading_tag']; ?>
                        <<?php echo esc_attr($ht); ?> class="eggb-ct-header-title<?php echo $ht !== 'div' ? ' eggb-block-title' : ''; ?>"><?php echo esc_html($payload['heading_title']); ?></<?php echo esc_attr($ht); ?>>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="eggb-ct-table table table-bordered mb-0 align-middle">
                    <colgroup>
                        <col class="eggb-ct-criteria">
                        <?php foreach ($payload['items'] as $item) : ?>
                            <col class="eggb-ct-col-product">
                        <?php endforeach; ?>
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="eggb-ct-criteria eggb-ct-criteria-cell px-3 py-2"></th>
                            <?php foreach ($payload['items'] as $index => $item) : ?>
                                <?php
                                $header_classes = 'text-center px-3 py-2';
                                if ($winner_index === $index)
                                {
                                    $header_classes .= ' eggb-ct-col-winner';
                                }
                                ?>
                                <th class="<?php echo esc_attr($header_classes); ?>">
                                    <?php if ($item['role_label'] !== '') : ?>
                                        <div class="eggb-ct-label mb-1"><?php echo esc_html($item['role_label']); ?></div>
                                    <?php endif; ?>
                                    <div class="eggb-ct-header-name"><?php echo esc_html($item['title']); ?></div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($has_images) : ?>
                            <tr>
                                <td class="eggb-ct-criteria eggb-ct-criteria-cell px-3 py-2"></td>
                                <?php foreach ($payload['items'] as $item) : ?>
                                    <td class="text-center px-3 py-2">
                                        <?php self::renderImage($item); ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($payload['criteria'] as $criterion) : ?>
                            <?php $best_price = (($criterion['type'] ?? '') === 'price') ? self::getLowestPrice($payload['items']) : null; ?>
                            <tr>
                                <td class="eggb-ct-criteria eggb-ct-criteria-cell px-3 py-2">
                                    <?php if ($criterion['label'] !== '') : ?>
                                        <span class="eggb-ct-label"><?php echo esc_html($criterion['label']); ?></span>
                                    <?php endif; ?>
                                </td>

                                <?php foreach ($payload['items'] as $index => $item) : ?>
                                    <td class="text-center px-3 py-2">
                                        <?php self::renderCriterionValue($criterion, $item, $index, $best_price); ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>

                        <tr>
                            <td class="eggb-ct-criteria eggb-ct-criteria-cell px-3 py-2"></td>
                            <?php foreach ($payload['items'] as $index => $item) : ?>
                                <td class="text-center px-3 py-2">
                                    <?php
                                    $button_class = 'eggb-btn eggb-ct-cta';
                                    if ($winner_index === $index)
                                    {
                                        $button_class .= ' eggb-btn--filled';
                                    }

                                    self::renderCta(
                                        $item,
                                        ComparisonTableRenderer::resolveCtaLabel($payload, $item['product_item']),
                                        $button_class
                                    );
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php if ($has_footer) : ?>
                <div class="eggb-ct-footer px-3 px-md-4 py-2">
                    <?php if ($payload['footer_note'] !== '') : ?>
                        <div class="eggb-ct-note"><?php echo esc_html($payload['footer_note']); ?></div>
                    <?php endif; ?>
                    <?php if ($payload['amazon_update_html'] !== '') : ?>
                        <div class="eggb-ct-note"><?php echo $payload['amazon_update_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function renderPlaceholder(array $payload, string $theme_class, string $data_theme, string $label): void
    {
        ?>
        <div class="eggb-block eggb-card eggb-ct eggb-ct--<?php echo esc_attr($payload['variant']); ?> eggb-ct-placeholder<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ($payload['heading_label'] !== '' || $payload['heading_title'] !== '') : ?>
                <div class="eggb-ct-placeholder-head">
                    <?php if ($payload['heading_label'] !== '') : ?>
                        <div class="eggb-ct-placeholder-label"><?php echo esc_html($payload['heading_label']); ?></div>
                    <?php endif; ?>
                    <?php if ($payload['heading_title'] !== '') : ?>
                        <?php $ht = $payload['heading_tag']; ?>
                        <<?php echo esc_attr($ht); ?> class="eggb-ct-placeholder-title<?php echo $ht !== 'div' ? ' eggb-block-title' : ''; ?>"><?php echo esc_html($payload['heading_title']); ?></<?php echo esc_attr($ht); ?>>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="eggb-ct-placeholder-body">
                <div class="eggb-ct-placeholder-meta">
                    <span class="eggb-award"><?php echo esc_html($label); ?> Placeholder</span>
                    <span class="eggb-ct-placeholder-count"><?php echo esc_html(count($payload['items'])); ?> products</span>
                    <?php if (!empty($payload['criteria'])) : ?>
                        <span class="eggb-ct-placeholder-count"><?php echo esc_html(count($payload['criteria'])); ?> rows</span>
                    <?php endif; ?>
                </div>

                <ol class="eggb-ct-placeholder-list">
                    <?php foreach ($payload['items'] as $item) : ?>
                        <li class="eggb-ct-placeholder-item">
                            <span class="eggb-ct-placeholder-rank"><?php echo esc_html($item['rank_display']); ?></span>
                            <span class="eggb-ct-placeholder-title-text"><?php echo esc_html($item['title']); ?></span>
                            <?php if ($item['role_label'] !== '') : ?>
                                <span class="eggb-ct-placeholder-sub"><?php echo esc_html($item['role_label']); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>

            <?php if ($payload['footer_note'] !== '') : ?>
                <div class="eggb-ct-placeholder-foot"><?php echo esc_html($payload['footer_note']); ?></div>
            <?php elseif ($payload['amazon_update_html'] !== '') : ?>
                <div class="eggb-ct-placeholder-foot"><?php echo $payload['amazon_update_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function getWinnerIndex(array $items): ?int
    {
        foreach ($items as $index => $item)
        {
            if (!empty($item['is_winner']))
            {
                return $index;
            }
        }

        return null;
    }

    private static function hasAnyImages(array $items): bool
    {
        foreach ($items as $item)
        {
            if (!empty($item['product_item']['img']) || !empty($item['product_item']['img_large']))
            {
                return true;
            }
        }

        return false;
    }

    private static function renderImage(array $item): void
    {
        $image_html = self::captureOutput(static function () use ($item): void {
            TemplateHelper::displayImage($item['product_item'], 300, 300, ['class' => 'eggb-ct-img d-block w-100 mx-auto']);
        });

        if ($image_html === '')
        {
            echo '<span class="eggb-ct-img-placeholder" aria-hidden="true"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        if (!empty($item['has_link']))
        {
            TemplateHelper::openATag($item['product_item'], ['title' => $item['title']], ['class' => 'eggb-ct-img-link d-block']);
            echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            TemplateHelper::closeATag();
            return;
        }

        echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private static function renderCriterionValue(array $criterion, array $item, int $index, ?float $bestPrice = null): void
    {
        $type = (string) ($criterion['type'] ?? 'text');

        if ($type === 'price')
        {
            self::renderPrice($item, $bestPrice);
            return;
        }

        $value = self::getCriterionValue($criterion['values'] ?? [], $index);

        switch ($type)
        {
            case 'score':
                self::renderScoreValue($value);
                return;
            case 'star':
                self::renderStarValue($value);
                return;
            case 'boolean':
                self::renderBooleanValue($value);
                return;
            default:
                self::renderTextValue($value);
                return;
        }
    }

    private static function renderPrice(array $item, ?float $bestPrice): void
    {
        $price_html = self::captureOutput(static function () use ($item): void {
            TemplateHelper::price($item['product_item']);
        });

        if ($price_html === '')
        {
            self::renderDash();
            return;
        }

        $price_value = self::extractPriceValue($item);
        $is_best_price = $bestPrice !== null && $price_value !== null && abs($price_value - $bestPrice) < 0.0001;
        ?>
        <span<?php echo $is_best_price ? ' class="fw-semibold"' : ''; ?>><?php echo $price_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        <?php
    }

    private static function renderScoreValue($value): void
    {
        $score = trim((string) $value);
        if ($score === '')
        {
            self::renderDash();
            return;
        }

        if (strpos($score, '/') !== false)
        {
            echo esc_html($score);
            return;
        }
        ?>
        <span class="eggb-ct-score"><?php echo esc_html($score); ?></span>
        <span class="eggb-ct-label">/10</span>
        <?php
    }

    private static function renderStarValue($value): void
    {
        $level = self::resolveStarLevel($value);
        if ($level === null)
        {
            self::renderDash();
            return;
        }
        ?>
        <span class="eggb-ct-stars" aria-hidden="true">
            <?php for ($i = 0; $i < $level; $i++) : ?>
                <?php echo EggbIcons::get('star-fill', 'eggb-ct-star'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endfor; ?>
        </span>
        <span class="visually-hidden"><?php echo esc_html(trim((string) $value)); ?></span>
        <?php
    }

    private static function renderBooleanValue($value): void
    {
        $state = self::resolveBooleanState($value);

        if ($state === null)
        {
            self::renderDash();
            return;
        }

        if ($state) : ?>
            <?php echo EggbIcons::get('check-circle', 'eggb-ct-check'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <span class="visually-hidden"><?php echo esc_html__('Yes', 'content-egg'); ?></span>
        <?php else : ?>
            <?php echo EggbIcons::get('x-circle', 'eggb-ct-no'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <span class="visually-hidden"><?php echo esc_html__('No', 'content-egg'); ?></span>
        <?php
        endif;
    }

    private static function renderTextValue($value): void
    {
        $text = trim((string) $value);
        if ($text === '')
        {
            self::renderDash();
            return;
        }

        echo esc_html($text);
    }

    private static function renderDash(): void
    {
        echo '<span class="eggb-ct-dash">&ndash;</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private static function getCriterionValue(array $values, int $index)
    {
        return $values[$index] ?? '';
    }

    private static function getLowestPrice(array $items): ?float
    {
        $lowest = null;

        foreach ($items as $item)
        {
            $price = self::extractPriceValue($item);
            if ($price === null)
            {
                continue;
            }

            if ($lowest === null || $price < $lowest)
            {
                $lowest = $price;
            }
        }

        return $lowest;
    }

    private static function extractPriceValue(array $item): ?float
    {
        $raw_price = $item['product_item']['price'] ?? null;

        if (is_numeric($raw_price))
        {
            return (float) $raw_price;
        }

        if (is_string($raw_price))
        {
            $normalized = preg_replace('/[^0-9.,-]/', '', $raw_price);
            if ($normalized === null || $normalized === '')
            {
                return null;
            }

            $normalized = str_replace(',', '.', $normalized);
            if (is_numeric($normalized))
            {
                return (float) $normalized;
            }
        }

        return null;
    }

    private static function resolveStarLevel($value): ?int
    {
        if (is_numeric($value))
        {
            $numeric = (float) $value;

            if ($numeric >= 3)
            {
                return 3;
            }

            if ($numeric >= 2)
            {
                return 2;
            }

            if ($numeric > 0)
            {
                return 1;
            }

            return null;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '')
        {
            return null;
        }

        if (in_array($normalized, ['excellent', 'best', 'high', 'strong', 'full', 'filled', 'yes'], true))
        {
            return 3;
        }

        if (in_array($normalized, ['good', 'medium', 'mid', 'half', 'partial'], true))
        {
            return 2;
        }

        if (in_array($normalized, ['low', 'poor', 'weak', 'limited'], true))
        {
            return 1;
        }

        return null;
    }

    private static function resolveBooleanState($value): ?bool
    {
        if (is_bool($value))
        {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '')
        {
            return null;
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'y'], true))
        {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'n'], true))
        {
            return false;
        }

        return null;
    }

    private static function renderCta(array $item, string $label, string $class): void
    {
        if (!$item['has_link'])
        {
            echo '<span class="' . esc_attr($class) . '">' . esc_html($label) . '</span>';
            return;
        }
        ?>
        <?php TemplateHelper::openATag($item['product_item'], [], ['class' => $class]); ?>
            <?php echo esc_html($label); ?>
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
