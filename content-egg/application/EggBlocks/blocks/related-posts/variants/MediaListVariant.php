<?php

namespace ContentEgg\application\EggBlocks\blocks\relatedposts\variants;

defined('ABSPATH') || exit;

class MediaListVariant
{
    public static function render(array $data, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <section class="eggb-block eggb-related-posts eggb-related-posts--media-list<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <div class="d-flex flex-column">
                <?php if ($data['section_label'] !== ''): ?>
                    <span class="eggb-section-title eggb-section-title--muted mb-1"><?php echo esc_html($data['section_label']); ?></span>
                <?php endif; ?>

                <?php foreach ($data['items'] as $item): ?>
                    <article class="eggb-rp-media-item">
                        <?php if ($item['thumbnail'] !== ''): ?>
                            <?php if ($item['linked']): ?>
                                <a href="<?php echo esc_url($item['url']); ?>" class="eggb-rp-thumb">
                                    <img src="<?php echo esc_url($item['thumbnail']); ?>" alt="" loading="lazy">
                                </a>
                            <?php else: ?>
                                <span class="eggb-rp-thumb">
                                    <img src="<?php echo esc_url($item['thumbnail']); ?>" alt="" loading="lazy">
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="d-flex flex-column gap-1 min-w-0">
                            <?php if ($item['badge'] !== ''): ?>
                                <span class="eggb-rp-badge"><?php echo esc_html($item['badge']); ?></span>
                            <?php endif; ?>
                            <?php if ($item['linked']): ?>
                                <a href="<?php echo esc_url($item['url']); ?>" class="eggb-rp-title"><?php echo esc_html($item['title']); ?></a>
                            <?php else: ?>
                                <span class="eggb-rp-title"><?php echo esc_html($item['title']); ?></span>
                            <?php endif; ?>
                            <?php if ($item['snippet'] !== ''): ?>
                                <span class="eggb-rp-excerpt"><?php echo esc_html($item['snippet']); ?></span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }
}
