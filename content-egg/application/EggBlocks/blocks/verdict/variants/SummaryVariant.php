<?php

namespace ContentEgg\application\EggBlocks\blocks\verdict\variants;

defined('ABSPATH') || exit;

class SummaryVariant
{
    public static function render(array $data, string $theme_class, string $data_theme): void
    {
        $has_score = $data['score'] !== '';
        $has_body  = $data['verdict_text'] !== '' || $has_score || $data['cta_label'] !== '';
        ?>
        <div class="eggb-block eggb-card eggb-verdict eggb-verdict--summary <?php echo esc_attr($theme_class); ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ($data['band_label'] !== '') : ?>
                <div class="eggb-vd-band d-flex align-items-center gap-2"><?php echo esc_html($data['band_label']); ?></div>
            <?php endif; ?>

            <?php if ($has_body) : ?>
                <div class="d-flex flex-column p-3 gap-3">
                    <?php if ($data['verdict_text'] !== '' || $has_score) : ?>
                        <div class="d-flex align-items-start gap-3">
                            <?php if ($data['verdict_text'] !== '') : ?>
                                <div class="flex-grow-1 eggb-vd-text min-w-0"><?php echo $data['verdict_text']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                            <?php endif; ?>

                            <?php if ($has_score) : ?>
                                <div class="eggb-vd-score-col d-flex flex-column align-items-center gap-1" aria-label="<?php echo esc_attr(trim($data['score'] . ' ' . $data['score_denom'])); ?>">
                                    <?php if ($data['score_label'] !== '') : ?>
                                        <span class="eggb-vd-score-label"><?php echo esc_html($data['score_label']); ?></span>
                                    <?php endif; ?>
                                    <span class="eggb-score-num"><?php echo esc_html($data['score']); ?></span>
                                    <?php if ($data['score_denom'] !== '') : ?>
                                        <span class="eggb-score-denom eggb-label"><?php echo esc_html($data['score_denom']); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($data['cta_label'] !== '') : ?>
                        <?php DefaultVariant::renderCta($data, 'filled'); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($data['title'] !== '' || $data['award_label'] !== '' || !empty($data['chips'])) : ?>
                <div class="eggb-vd-meta d-flex flex-column gap-2">
                    <?php DefaultVariant::renderMetaHeader($data); ?>
                    <?php DefaultVariant::renderChips($data['chips']); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
