<?php

namespace ContentEgg\application\EggBlocks\blocks\verdict\variants;

use ContentEgg\application\EggBlocks\shared\EggbIcons;
use ContentEgg\application\helpers\TemplateHelper;

defined('ABSPATH') || exit;

class DefaultVariant
{
    public static function render(array $data, string $theme_class, string $data_theme): void
    {
        $has_score = $data['score'] !== '';
        ?>
        <div class="eggb-block eggb-block--panel eggb-block--accented eggb-verdict eggb-verdict--default <?php echo esc_attr($theme_class); ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="d-flex align-items-start gap-3">
                <?php if ($has_score) : ?>
                    <div class="eggb-vd-score-col d-flex flex-column align-items-center gap-1" aria-label="<?php echo esc_attr(trim($data['score'] . ' ' . $data['score_denom'])); ?>">
                        <span class="eggb-score-num"><?php echo esc_html($data['score']); ?></span>
                        <?php if ($data['score_denom'] !== '') : ?>
                            <span class="eggb-score-denom eggb-label"><?php echo esc_html($data['score_denom']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="eggb-divider-v align-self-stretch" aria-hidden="true"></div>
                <?php endif; ?>

                <div class="flex-grow-1 d-flex flex-column gap-2 min-w-0">
                    <?php self::renderMetaHeader($data); ?>
                    <?php self::renderChips($data['chips']); ?>
                    <?php if ($data['verdict_text'] !== '') : ?>
                        <div class="eggb-vd-text"><?php echo $data['verdict_text']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <?php endif; ?>
                    <?php self::renderCta($data, 'filled'); ?>
                </div>
            </div>
        </div>
        <?php
    }

    public static function renderMetaHeader(array $data): void
    {
        if ($data['title'] === '' && $data['award_label'] === '') {
            return;
        }
        ?>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <?php if ($data['title'] !== '') : ?>
                <span class="eggb-vd-name"><?php echo esc_html($data['title']); ?></span>
            <?php endif; ?>
            <?php if ($data['award_label'] !== '') : ?>
                <span class="eggb-award">
                    <?php echo EggbIcons::get('award-fill', 'eggb-award-icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo esc_html($data['award_label']); ?>
                </span>
            <?php endif; ?>
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

    public static function renderCta(array $data, string $variant): void
    {
        if ($data['cta_label'] === '' || !$data['has_cta_target']) {
            return;
        }

        $classes = 'eggb-btn align-self-start';
        if ($variant !== '') {
            $classes .= ' eggb-btn--' . $variant;
        }

        if (!empty($data['product_item']) && is_array($data['product_item'])) :
            ?>
            <?php TemplateHelper::openATag($data['product_item'], [], ['class' => $classes]); ?>
                <?php echo esc_html($data['cta_label']); ?>
                <?php echo EggbIcons::get('arrow-right-short', 'eggb-vd-cta-icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php TemplateHelper::closeATag(); ?>
        <?php elseif ($data['cta_url'] !== '') : ?>
            <a href="<?php echo esc_url($data['cta_url']); ?>" class="<?php echo esc_attr($classes); ?>">
                <?php echo esc_html($data['cta_label']); ?>
                <?php echo EggbIcons::get('arrow-right-short', 'eggb-vd-cta-icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </a>
        <?php else : ?>
            <span class="<?php echo esc_attr($classes); ?>">
                <?php echo esc_html($data['cta_label']); ?>
                <?php echo EggbIcons::get('arrow-right-short', 'eggb-vd-cta-icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
            <?php
        endif;
    }
}
