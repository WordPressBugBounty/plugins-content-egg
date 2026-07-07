<?php

namespace ContentEgg\application\EggBlocks\blocks\relatedposts\variants;

use ContentEgg\application\EggBlocks\shared\EggbThumbnail;

defined('ABSPATH') || exit;

class FeaturedVariant
{
    public static function render(array $data, string $theme_class = '', string $data_theme = ''): void
    {
        $hero = $data['items'][0];
        $list_items = array_slice($data['items'], 1);
        ?>
        <section class="eggb-block eggb-related-posts eggb-related-posts--featured<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ($data['section_label'] !== ''): ?>
                <div class="mb-3">
                    <span class="eggb-section-title eggb-section-title--muted mb-0"><?php echo esc_html($data['section_label']); ?></span>
                </div>
            <?php endif; ?>

            <div class="eggb-rp-featured-layout d-grid gap-3" style="grid-template-columns: 1fr 1fr;">

                <article class="eggb-rp-featured-hero eggb-card d-flex flex-column">
                    <?php if ($hero['thumbnail'] !== ''): ?>
                        <?php if ($hero['linked']): ?>
                            <a href="<?php echo esc_url($hero['url']); ?>" class="eggb-rp-thumb">
                                <?php echo EggbThumbnail::render($hero['post_id'], 'large', '(max-width: 767.98px) 100vw, 50vw'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </a>
                        <?php else: ?>
                            <span class="eggb-rp-thumb">
                                <?php echo EggbThumbnail::render($hero['post_id'], 'large', '(max-width: 767.98px) 100vw, 50vw'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div class="eggb-rp-featured-body d-flex flex-column gap-2 flex-grow-1">
                        <?php if ($hero['badge'] !== ''): ?>
                            <span class="eggb-rp-badge"><?php echo esc_html($hero['badge']); ?></span>
                        <?php endif; ?>
                        <?php if ($hero['linked']): ?>
                            <a href="<?php echo esc_url($hero['url']); ?>" class="eggb-rp-title"><?php echo esc_html($hero['title']); ?></a>
                        <?php else: ?>
                            <span class="eggb-rp-title"><?php echo esc_html($hero['title']); ?></span>
                        <?php endif; ?>
                        <?php if ($hero['snippet'] !== ''): ?>
                            <span class="eggb-rp-excerpt mb-0"><?php echo esc_html($hero['snippet']); ?></span>
                        <?php endif; ?>
                    </div>
                </article>

                <?php if (!empty($list_items)): ?>
                    <div class="d-flex flex-column">
                        <?php foreach ($list_items as $item): ?>
                            <article class="eggb-rp-featured-list-item">
                                <?php if ($item['badge'] !== ''): ?>
                                    <span class="eggb-rp-badge"><?php echo esc_html($item['badge']); ?></span>
                                <?php endif; ?>
                                <div class="eggb-rp-featured-list-main">
                                    <?php if ($item['thumbnail'] !== ''): ?>
                                        <?php if ($item['linked']): ?>
                                            <a href="<?php echo esc_url($item['url']); ?>" class="eggb-rp-thumb">
                                                <?php echo EggbThumbnail::render($item['post_id'], 'medium', '72px'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="eggb-rp-thumb">
                                                <?php echo EggbThumbnail::render($item['post_id'], 'medium', '72px'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <div class="d-flex flex-column gap-1 min-w-0">
                                        <?php if ($item['linked']): ?>
                                            <a href="<?php echo esc_url($item['url']); ?>" class="eggb-rp-title"><?php echo esc_html($item['title']); ?></a>
                                        <?php else: ?>
                                            <span class="eggb-rp-title"><?php echo esc_html($item['title']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($item['snippet'] !== ''): ?>
                                            <span class="eggb-rp-snippet"><?php echo esc_html($item['snippet']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </section>
        <?php
    }
}
