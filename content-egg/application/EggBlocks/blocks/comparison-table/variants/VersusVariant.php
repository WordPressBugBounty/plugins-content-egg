<?php

namespace ContentEgg\application\EggBlocks\blocks\comparisontable\variants;

use ContentEgg\application\EggBlocks\blocks\comparisontable\ComparisonTableRenderer;
use ContentEgg\application\EggBlocks\shared\EggbIcons;
use ContentEgg\application\helpers\TemplateHelper;

defined('ABSPATH') || exit;

class VersusVariant
{
    public static function render(array $payload, string $theme_class, string $data_theme): void
    {
        $items = array_slice($payload['items'], 0, 2);
        if (count($items) < 2)
        {
            return;
        }

        $has_footer = $payload['footer_note'] !== '' || $payload['amazon_update_html'] !== '';
        ?>
        <div class="eggb-block eggb-card eggb-ct eggb-ct--versus<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
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
                <table class="eggb-ct-table table mb-0">
                    <colgroup>
                        <col class="eggb-ct-col-a">
                        <col class="eggb-ct-col-spine">
                        <col class="eggb-ct-col-b">
                    </colgroup>
                    <thead>
                        <tr class="border-bottom">
                            <th class="px-3 pt-3 pb-2 eggb-ct-col-a">
                                <?php self::renderHeaderCell($items[0], true); ?>
                            </th>
                            <th class="eggb-ct-spine px-1 py-3">
                                <span class="eggb-ct-vs-hero">VS</span>
                            </th>
                            <th class="px-3 pt-3 pb-2 eggb-ct-col-b">
                                <?php self::renderHeaderCell($items[1], false); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payload['criteria'] as $criterion) : ?>
                            <?php $winner_index = self::resolveWinnerIndex($criterion, $items); ?>
                            <tr>
                                <?php foreach ($items as $index => $item) : ?>
                                    <?php
                                    $cell_class = $index === 0 ? 'eggb-ct-col-a' : 'eggb-ct-col-b';
                                    if ($winner_index === $index)
                                    {
                                        $cell_class .= ' eggb-ct-winner';
                                    }
                                    ?>
                                    <td class="<?php echo esc_attr($cell_class); ?>">
                                        <div class="eggb-ct-val">
                                            <?php self::renderCriterionValue($criterion, $item, $index, $winner_index === $index); ?>
                                        </div>
                                    </td>

                                    <?php if ($index === 0) : ?>
                                        <td class="eggb-ct-spine">
                                            <?php if ($criterion['label'] !== '') : ?>
                                                <span class="eggb-chip eggb-ct-spine-label"><?php echo esc_html($criterion['label']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>

                        <tr>
                            <td class="eggb-ct-col-a px-3 py-3 text-end">
                                <?php self::renderCta($items[0], ComparisonTableRenderer::resolveCtaLabel($payload, $items[0]['product_item']), 'eggb-btn eggb-btn--filled'); ?>
                            </td>
                            <td class="eggb-ct-spine"></td>
                            <td class="eggb-ct-col-b px-3 py-3 text-start">
                                <?php self::renderCta($items[1], ComparisonTableRenderer::resolveCtaLabel($payload, $items[1]['product_item']), 'eggb-btn'); ?>
                            </td>
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

    private static function renderHeaderCell(array $item, bool $reverse): void
    {
        $direction_class = $reverse ? 'flex-row-reverse' : 'flex-row';
        $align_class = $reverse ? 'text-end' : 'text-start';
        $image_html = self::getHeaderImageHtml($item);
        ?>
        <div class="d-flex <?php echo esc_attr($direction_class); ?> align-items-center gap-2">
            <?php if ($image_html !== '') : ?>
                <?php if (!empty($item['has_link'])) : ?>
                    <?php TemplateHelper::openATag($item['product_item'], ['title' => $item['title']], ['class' => 'eggb-ct-img-wrap eggb-ct-header-img flex-shrink-0']); ?>
                        <?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php TemplateHelper::closeATag(); ?>
                <?php else : ?>
                    <div class="eggb-ct-img-wrap eggb-ct-header-img flex-shrink-0">
                        <?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <div class="<?php echo esc_attr($align_class); ?> eggb-ct-header-text">
                <?php if ($item['role_label'] !== '') : ?>
                    <div class="eggb-ct-label mb-1"><?php echo esc_html($item['role_label']); ?></div>
                <?php endif; ?>
                <div class="lh-sm eggb-ct-header-name"><?php echo esc_html($item['title']); ?></div>
            </div>
        </div>
        <?php
    }

    private static function getHeaderImageHtml(array $item): string
    {
        if (empty($item['product_item']['img'])) {
            return '';
        }

        return self::captureOutput(static function () use ($item): void {
            TemplateHelper::displayImage($item['product_item'], 300, 300, ['class' => 'eggb-ct-img']);
        });
    }

    private static function resolveWinnerIndex(array $criterion, array $items): ?int
    {
        $type = (string) ($criterion['type'] ?? 'text');
        if (!in_array($type, ['price', 'score', 'star', 'text'], true))
        {
            return null;
        }

        if (count($items) < 2)
        {
            return null;
        }

        switch ($type)
        {
            case 'price':
                $left = self::extractPriceValue($items[0]);
                $right = self::extractPriceValue($items[1]);
                return self::compareValues($left, $right, true);
            case 'score':
                $left = self::extractScoreValue($criterion['values'][0] ?? '');
                $right = self::extractScoreValue($criterion['values'][1] ?? '');
                return self::compareValues($left, $right, false);
            case 'star':
                $left = self::resolveStarLevel($criterion['values'][0] ?? '');
                $right = self::resolveStarLevel($criterion['values'][1] ?? '');
                return self::compareValues($left, $right, false);
            case 'text':
                return self::resolveTextWinnerIndex(
                    $criterion['values'][0] ?? '',
                    $criterion['values'][1] ?? ''
                );
            default:
                return null;
        }
    }

    private static function renderCriterionValue(array $criterion, array $item, int $index, bool $isWinner): void
    {
        $type = (string) ($criterion['type'] ?? 'text');
        $value = $criterion['values'][$index] ?? '';

        if ($type === 'price')
        {
            self::renderPrice($item, $isWinner);
            return;
        }

        switch ($type)
        {
            case 'score':
                self::renderScoreValue($value, $isWinner);
                return;
            case 'star':
                self::renderStarValue($value, $isWinner);
                return;
            case 'boolean':
                self::renderBooleanValue($value, $isWinner);
                return;
            default:
                self::renderTextValue($value, $isWinner);
                return;
        }
    }

    private static function renderPrice(array $item, bool $isWinner): void
    {
        $price_html = self::captureOutput(static function () use ($item): void {
            TemplateHelper::price($item['product_item']);
        });

        if ($price_html === '')
        {
            self::renderDash();
            return;
        }

        self::renderValueInner($price_html, $isWinner);
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

    private static function renderScoreValue($value, bool $isWinner): void
    {
        $score = trim((string) $value);
        if ($score === '')
        {
            self::renderDash();
            return;
        }

        ob_start();
        if (strpos($score, '/') !== false)
        {
            echo esc_html($score);
        }
        else
        {
            ?>
            <span class="eggb-ct-score"><?php echo esc_html($score); ?></span><span class="eggb-ct-label">/10</span>
            <?php
        }

        self::renderValueInner(trim((string) ob_get_clean()), $isWinner);
    }

    private static function extractScoreValue($value): ?float
    {
        if (is_numeric($value))
        {
            return (float) $value;
        }

        $text = trim((string) $value);
        if ($text === '')
        {
            return null;
        }

        if (preg_match('/-?\d+(?:[.,]\d+)?/', $text, $matches))
        {
            $normalized = str_replace(',', '.', $matches[0]);
            if (is_numeric($normalized))
            {
                return (float) $normalized;
            }
        }

        return null;
    }

    private static function renderStarValue($value, bool $isWinner): void
    {
        $level = self::resolveStarLevel($value);
        if ($level === null)
        {
            self::renderDash();
            return;
        }

        ob_start();
        ?>
        <span class="eggb-ct-stars" aria-hidden="true">
            <?php for ($i = 0; $i < $level; $i++) : ?>
                <?php echo EggbIcons::get('star-fill', 'eggb-ct-star'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endfor; ?>
        </span>
        <span class="visually-hidden"><?php echo esc_html(trim((string) $value)); ?></span>
        <?php
        self::renderValueInner(trim((string) ob_get_clean()), $isWinner);
    }

    private static function renderBooleanValue($value, bool $isWinner): void
    {
        $state = self::resolveBooleanState($value);
        if ($state === null)
        {
            self::renderDash();
            return;
        }

        ob_start();
        if ($state)
        {
            echo EggbIcons::get('check-circle', 'eggb-ct-check'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<span class="visually-hidden">' . esc_html__('Yes', 'content-egg') . '</span>';
        }
        else
        {
            echo EggbIcons::get('x-circle', 'eggb-ct-no'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<span class="visually-hidden">' . esc_html__('No', 'content-egg') . '</span>';
        }

        self::renderValueInner(trim((string) ob_get_clean()), $isWinner);
    }

    private static function renderTextValue($value, bool $isWinner): void
    {
        $text = trim((string) $value);
        if ($text === '')
        {
            self::renderDash();
            return;
        }

        self::renderValueInner(esc_html($text), $isWinner);
    }

    private static function renderValueInner(string $html, bool $isWinner): void
    {
        if ($isWinner)
        {
            echo '<span class="eggb-ct-val-inner">' . $html . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private static function renderDash(): void
    {
        echo '<span class="eggb-ct-dash">&ndash;</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

    private static function compareValues($left, $right, bool $lowerWins): ?int
    {
        if ($left === null || $right === null)
        {
            return null;
        }

        if ((float) $left === (float) $right)
        {
            return null;
        }

        if ($lowerWins)
        {
            return ((float) $left < (float) $right) ? 0 : 1;
        }

        return ((float) $left > (float) $right) ? 0 : 1;
    }

    private static function resolveTextWinnerIndex($left, $right): ?int
    {
        $left_has_value = self::hasMeaningfulTextValue($left);
        $right_has_value = self::hasMeaningfulTextValue($right);

        if ($left_has_value === $right_has_value)
        {
            return null;
        }

        return $left_has_value ? 0 : 1;
    }

    private static function hasMeaningfulTextValue($value): bool
    {
        $text = trim((string) $value);

        if ($text === '')
        {
            return false;
        }

        return !in_array($text, ['—', '-', '–'], true);
    }

    private static function captureOutput(callable $callback): string
    {
        ob_start();
        $callback();
        return trim((string) ob_get_clean());
    }
}
