<?php

defined('\ABSPATH') || exit;

use ContentEgg\application\deals\DealNav;

/** @var string $context */
/** @var int $object_id */
$trail = DealNav::breadcrumbs((string) ($context ?? 'archive'), (int) ($object_id ?? 0));
if (!$trail)
    return;
?>
<nav class="cegg-deal-crumbs" aria-label="<?php \esc_attr_e('Breadcrumb', 'content-egg-tpl'); ?>">
    <ol>
        <?php foreach ($trail as $crumb) : ?>
            <li><?php if ($crumb['url'] !== '') : ?><a href="<?php echo \esc_url($crumb['url']); ?>"><?php echo \esc_html($crumb['name']); ?></a><?php else : ?><span aria-current="page"><?php echo \esc_html($crumb['name']); ?></span><?php endif; ?></li>
        <?php endforeach; ?>
    </ol>
</nav>
<?php if (DealNav::schemaEnabled()) : ?>
<script type="application/ld+json"><?php echo DealNav::schemaJson($trail); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
<?php endif; ?>
