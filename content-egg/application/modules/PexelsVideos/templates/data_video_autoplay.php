<?php

use ContentEgg\application\helpers\TemplateHelper;

defined('\ABSPATH') || exit;
/*
  Name: Video (autoplay, muted)
 */
?>

<div class="container px-0 mb-5 mt-1" <?php $this->colorMode(); ?>>
    <?php foreach ($items as $i => $item): ?>
        <?php $this->setItem($item, $i); ?>
        <div class="row mb-4">
            <div class="col text-body">

                <?php if ($this->isVisible('title') && !empty($item['title'])): ?>
                    <?php TemplateHelper::title($item, 'card-title h4 fw-normal mb-3', 'h4', $params); ?>
                <?php endif; ?>

                <?php if (!empty($item['extra']['video_url'])): ?>
                    <div class="ratio ratio-16x9">
                        <video autoplay muted loop playsinline preload="metadata" poster="<?php echo esc_url($item['img']); ?>">
                            <source src="<?php echo esc_url($item['extra']['video_url']); ?>" type="video/mp4">
                        </video>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    <?php endforeach; ?>
</div>
