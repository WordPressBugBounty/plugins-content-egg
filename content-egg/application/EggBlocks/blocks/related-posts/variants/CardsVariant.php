<?php

namespace ContentEgg\application\EggBlocks\blocks\relatedposts\variants;

defined('ABSPATH') || exit;

class CardsVariant
{
    public static function render(array $data, string $theme_class = '', string $data_theme = ''): void
    {
        ?>
        <section class="eggb-block eggb-related-posts eggb-related-posts--cards<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>"<?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ($data['section_label'] !== ''): ?>
                <div class="mb-3">
                    <span class="eggb-section-title eggb-section-title--muted mb-0"><?php echo esc_html($data['section_label']); ?></span>
                </div>
            <?php endif; ?>

            <div class="row g-3">
                <?php foreach ($data['items'] as $item): ?>
                    <div class="col-12 col-md-6">
                        <article class="eggb-rp-card eggb-card h-100 d-flex flex-column">
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
                            <div class="eggb-rp-card-body d-flex flex-column gap-2 flex-grow-1">
                                <?php if ($item['badge'] !== ''): ?>
                                    <span class="eggb-rp-badge"><?php echo esc_html($item['badge']); ?></span>
                                <?php endif; ?>
                                <?php if ($item['linked']): ?>
                                    <a href="<?php echo esc_url($item['url']); ?>" class="eggb-rp-title"><?php echo esc_html($item['title']); ?></a>
                                <?php else: ?>
                                    <span class="eggb-rp-title"><?php echo esc_html($item['title']); ?></span>
                                <?php endif; ?>
                                <?php if ($item['snippet'] !== ''): ?>
                                    <span class="eggb-rp-excerpt mb-0"><?php echo esc_html($item['snippet']); ?></span>
                                <?php endif; ?>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }
}
