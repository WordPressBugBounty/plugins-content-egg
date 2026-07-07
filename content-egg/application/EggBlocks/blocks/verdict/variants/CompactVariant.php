<?php

namespace ContentEgg\application\EggBlocks\blocks\verdict\variants;

defined('ABSPATH') || exit;

class CompactVariant
{
    public static function render(array $data, string $theme_class, string $data_theme): void
    {
        $has_score = $data['score'] !== '';
        ?>
        <div class="eggb-block eggb-verdict eggb-verdict--compact <?php echo esc_attr($theme_class); ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="d-flex align-items-start gap-3">
                <?php if ($has_score) : ?>
                    <div class="eggb-vd-score-col d-flex flex-column align-items-center gap-1" role="img" aria-label="<?php echo esc_attr(trim($data['score'] . ' ' . $data['score_denom'])); ?>">
                        <span class="eggb-score-num"><?php echo esc_html($data['score']); ?></span>
                        <?php if ($data['score_denom'] !== '') : ?>
                            <span class="eggb-score-denom eggb-label"><?php echo esc_html($data['score_denom']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="eggb-divider-v align-self-stretch" aria-hidden="true"></div>
                <?php endif; ?>

                <div class="flex-grow-1 d-flex flex-column gap-2 min-w-0">
                    <?php DefaultVariant::renderMetaHeader($data); ?>
                    <?php DefaultVariant::renderChips($data['chips']); ?>
                    <?php if ($data['verdict_text'] !== '') : ?>
                        <div class="eggb-vd-text"><?php echo $data['verdict_text']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <?php endif; ?>
                    <?php DefaultVariant::renderCta($data, ''); ?>
                </div>
            </div>
        </div>
        <?php
    }
}
