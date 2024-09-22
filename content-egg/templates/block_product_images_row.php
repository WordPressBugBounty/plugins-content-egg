<?php
/*
 * Name: Product images row
 * Modules:
 * Module Types: PRODUCT
 *
 */

use ContentEgg\application\helpers\TemplateHelper;

if (empty($cols) || $cols > 12)
    $cols = 2;

$col_size_md = ceil(12 / $cols);

if ($col_size_md >= 6)
    $col_size_xs = $col_size_md;
else
    $col_size_xs = 6;

if (!$images = TemplateHelper::getGallery($data, $cols))
    return;
?>

<div class="egg-container egg-gallery">
    <?php if ($title) : ?>
        <h3><?php echo esc_html($title); ?></h3>
    <?php endif; ?>

    <div class="row">
        <?php foreach ($images as $image) : ?>
            <div class="col-md-<?php echo esc_attr($col_size_md); ?> col-xs-<?php echo esc_attr($col_size_xs); ?> text-center">
                <a<?php TemplateHelper::printRel(); ?> target="_blank" href="<?php echo esc_url_raw($image['url']); ?>">
                    <img src="<?php echo esc_attr($image['uri']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" class="img-thumbnail" style="max-height:400px;" />
                    </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>