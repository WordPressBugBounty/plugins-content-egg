<?php

namespace ContentEgg\application\EggBlocks\blocks\toc;

use ContentEgg\application\helpers\TemplateHelper;

defined('ABSPATH') || exit;

class ProductNavSection
{
    public static function render(array $products): void
    {
        if (empty($products))
        {
            return;
        }

        $has_any_image = !empty(array_filter($products, fn($p) => $p['img'] !== ''));
        ?>
        <div class="eggb-toc-products mt-3">
            <div class="eggb-toc-products-list<?php echo count($products) <= 3 ? ' eggb-toc-products-list--few' : ''; ?>">
                <?php foreach ($products as $index => $product) : ?>
                    <a href="#<?php echo esc_attr($product['anchor']); ?>" class="eggb-toc-product-item">
                        <?php if ($product['img'] !== '') : ?>
                            <img
                                src="<?php echo esc_url($product['img']); ?>"
                                alt="<?php echo esc_attr($product['label']); ?>"
                                class="eggb-toc-product-img"
                                loading="lazy"
                                decoding="async"
                            />
                        <?php elseif ($has_any_image) : ?>
                            <span class="eggb-toc-product-img-placeholder"></span>
                        <?php else : ?>
                            <span class="eggb-toc-product-rank" aria-hidden="true"><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></span>
                        <?php endif; ?>
                        <span class="eggb-toc-product-label"><?php echo esc_html($product['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}
