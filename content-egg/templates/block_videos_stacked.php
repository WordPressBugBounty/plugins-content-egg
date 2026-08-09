<?php

/*
 * Name: Videos stacked
 * Module Types: VIDEO
 */

defined('\ABSPATH') || exit;

use ContentEgg\application\helpers\TemplateHelper;

// Block-template counterpart of the per-module video partials. Rendered via
// [content-egg-block template=videos] → ModuleViewer::viewBlockData (merged
// $items across active VIDEO modules). Source detected per item:
//   extra.guid      → YouTube <iframe> embed
//   extra.video_url → HTML5 <video> with img poster (Pexels etc.)
//
// Each clip sits in a rounded, shadowed 16:9 frame (overflow-hidden so the embed
// corners round too), with the title above and description below. CSS is emitted
// once per page via a guarded helper.

?>

<div class="container px-0 mb-5 mt-1 cegg-videos" <?php $this->colorMode(); ?>>
    <?php foreach ($items as $i => $item): ?>
        <?php $this->setItem($item, $i); ?>
        <?php if (empty($item['extra']['guid']) && empty($item['extra']['video_url'])) continue; ?>
        <div class="cegg-video">

            <?php if ($this->isVisible('title') && !empty($item['title'])): ?>
                <?php TemplateHelper::title($item, 'cegg-video__title h4 fw-semibold mb-3', 'h4', $params); ?>
            <?php endif; ?>

            <div class="cegg-video__frame ratio ratio-16x9">
                <?php if (!empty($item['extra']['guid'])): ?>
                    <iframe loading="lazy" width="560" height="315" src="https://www.youtube.com/embed/<?php echo esc_attr($item['extra']['guid']); ?>?rel=0" frameborder="0" allowfullscreen></iframe>
                <?php else: ?>
                    <video controls preload="metadata" poster="<?php echo esc_url($item['img']); ?>">
                        <source src="<?php echo esc_url($item['extra']['video_url']); ?>" type="video/mp4">
                    </video>
                <?php endif; ?>
            </div>

            <?php if ($this->isVisible('description', false) && !empty($item['description'])): ?>
                <div class="cegg-video__desc cegg-desc-small small lh-sm mt-3"><?php TemplateHelper::description($item); ?></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php if (!function_exists('cegg_videos_stacked_css_enqueue')): ?>
    <?php function cegg_videos_stacked_css_enqueue()
    { ?>
        <style>
            .cegg-video {
                max-width: 860px;
                margin: 0 auto 2.25rem;
            }

            .cegg-video:last-child {
                margin-bottom: 0;
            }

            .cegg-video__frame {
                border-radius: .625rem;
                overflow: hidden;
                background: #000;
                box-shadow: 0 6px 22px rgba(0, 0, 0, .16);
            }

            .cegg-video__frame iframe,
            .cegg-video__frame video {
                border: 0;
            }

            .cegg-video__desc {
                color: var(--bs-secondary-color, #6c757d);
            }
        </style>
    <?php } ?>
    <?php cegg_videos_stacked_css_enqueue();
    ?>
<?php endif; ?>
