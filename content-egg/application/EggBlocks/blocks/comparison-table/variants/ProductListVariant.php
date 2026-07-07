<?php

namespace ContentEgg\application\EggBlocks\blocks\comparisontable\variants;

use ContentEgg\application\EggBlocks\blocks\comparisontable\ComparisonTableRenderer;
use ContentEgg\application\helpers\TemplateHelper;

defined('ABSPATH') || exit;

class ProductListVariant
{
    public static function render(array $payload, string $theme_class, string $data_theme): void
    {
        $winner_index = self::getWinnerIndex($payload['items']);
        ?>
        <div class="eggb-block eggb-ct eggb-ct--product-list<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
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
                <table class="eggb-ct-table table mb-0 align-middle table-sm">
                    <thead>
                        <tr>
                            <th></th>
                            <?php foreach ($payload['criteria'] as $criterion) : ?>
                                <th class="eggb-ct-col-attr fw-semibold text-center">
                                    <span class="eggb-ct-label"><?php echo esc_html($criterion['label']); ?></span>
                                </th>
                            <?php endforeach; ?>
                            <th class="eggb-ct-col-cta"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payload['items'] as $index => $item) : ?>
                            <?php $is_winner = ($winner_index === $index); ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php self::renderThumb($item); ?>
                                        <div class="min-w-0">
                                            <?php self::renderTitle($item); ?>
                                            <?php self::renderSecondary($item); ?>
                                        </div>
                                    </div>
                                </td>

                                <?php foreach ($payload['criteria'] as $criterion) : ?>
                                    <td class="eggb-ct-pl-value text-center">
                                        <?php self::renderCriterionValue($criterion, $item, $index, self::getLowestPrice($payload['items'], $criterion)); ?>
                                    </td>
                                <?php endforeach; ?>

                                <td class="text-center">
                                    <?php
                                    $button_class = 'eggb-btn text-nowrap';
                                    if ($is_winner)
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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($payload['footer_note'] !== '' || $payload['amazon_update_html'] !== '') : ?>
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

    private static function renderThumb(array $item): void
    {
        if (empty($item['product_item']['img'])) {
            return;
        }

        $image_html = self::captureOutput(static function () use ($item): void {
            TemplateHelper::displayImage($item['product_item'], 160, 160, ['class' => 'eggb-ct-thumb-img']);
        });

        if ($image_html === '')
        {
            return;
        }

        if ($item['has_link']) : ?>
            <?php TemplateHelper::openATag($item['product_item'], ['title' => $item['title']], ['class' => 'eggb-ct-thumb']); ?>
                <?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php TemplateHelper::closeATag(); ?>
        <?php else : ?>
            <div class="eggb-ct-thumb">
                <?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        <?php
        endif;
    }

    private static function renderTitle(array $item): void
    {
        if ($item['title'] === '')
        {
            return;
        }

        if ($item['has_link']) : ?>
            <?php TemplateHelper::openATag($item['product_item'], ['title' => $item['title']], ['class' => 'eggb-ct-pl-title']); ?>
                <?php echo esc_html($item['title']); ?>
            <?php TemplateHelper::closeATag(); ?>
        <?php else : ?>
            <div class="eggb-ct-pl-title"><?php echo esc_html($item['title']); ?></div>
        <?php
        endif;
    }

    private static function renderSecondary(array $item): void
    {
        $parts = [];
        if ($item['role_label'] !== '')
        {
            $parts[] = $item['role_label'];
        }

        if (empty($parts))
        {
            return;
        }
        ?>
        <div class="eggb-ct-sub"><?php echo esc_html(implode(' · ', $parts)); ?></div>
        <?php
    }

    private static function renderCriterionValue(array $criterion, array $item, int $index, ?float $lowestPrice = null): void
    {
        $type = (string) ($criterion['type'] ?? 'text');
        $value = $criterion['values'][$index] ?? '';

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
            case 'price':
                self::renderPrice($item, $lowestPrice);
                return;
            default:
                self::renderTextValue($value);
                return;
        }
    }

    private static function renderPrice(array $item, ?float $lowestPrice = null): void
    {
        $price_html = self::captureOutput(static function () use ($item): void {
            TemplateHelper::price($item['product_item']);
        });

        if ($price_html === '')
        {
            echo '<span class="eggb-ct-dash">&ndash;</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        $price_value = self::extractPriceValue($item);
        $is_lowest_price = $lowestPrice !== null && $price_value !== null && abs($price_value - $lowestPrice) < 0.0001;

        if ($is_lowest_price)
        {
            echo '<span class="fw-semibold">' . $price_html . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        echo $price_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private static function renderScoreValue($value): void
    {
        $score = trim((string) $value);
        if ($score === '')
        {
            echo '<span class="eggb-ct-dash">&ndash;</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        if (strpos($score, '/') !== false)
        {
            echo esc_html($score);
            return;
        }
        ?>
        <span class="eggb-ct-score"><?php echo esc_html($score); ?></span><span class="eggb-ct-label">/10</span>
        <?php
    }

    private static function renderStarValue($value): void
    {
        $level = self::resolveStarLevel($value);
        if ($level === null)
        {
            echo '<span class="eggb-ct-dash">&ndash;</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }
        ?>
        <span class="eggb-ct-stars" aria-hidden="true">
            <?php for ($i = 0; $i < $level; $i++) : ?>
                <?php echo \ContentEgg\application\EggBlocks\shared\EggbIcons::get('star-fill', 'eggb-ct-star'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endfor; ?>
        </span>
        <?php
    }

    private static function renderBooleanValue($value): void
    {
        $state = self::resolveBooleanState($value);
        if ($state === null)
        {
            echo '<span class="eggb-ct-dash">&ndash;</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        if ($state)
        {
            echo \ContentEgg\application\EggBlocks\shared\EggbIcons::get('check-circle', 'eggb-ct-check'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        echo \ContentEgg\application\EggBlocks\shared\EggbIcons::get('x-circle', 'eggb-ct-no'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private static function renderTextValue($value): void
    {
        $text = trim((string) $value);
        if ($text === '')
        {
            echo '<span class="eggb-ct-dash">&ndash;</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        echo '<span class="eggb-ct-pl-text">' . esc_html($text) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

    private static function getLowestPrice(array $items, array $criterion): ?float
    {
        if (($criterion['type'] ?? '') !== 'price')
        {
            return null;
        }

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

    private static function captureOutput(callable $callback): string
    {
        ob_start();
        $callback();
        return trim((string) ob_get_clean());
    }
}
