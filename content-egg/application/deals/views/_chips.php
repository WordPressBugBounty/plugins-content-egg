<?php

defined('\ABSPATH') || exit;

use ContentEgg\application\deals\DealNav;

/** @var int $parent */
/** @var int $current */
$chips = DealNav::categoryChips((int) ($parent ?? 0));
if (!$chips)
    return;
$current = (int) ($current ?? 0);
?>
<nav class="cegg-deal-chips" aria-label="<?php \esc_attr_e('Categories', 'content-egg-tpl'); ?>">
    <?php foreach ($chips as $term) : $link = \get_term_link($term); if (\is_wp_error($link)) continue; ?>
        <a class="cegg-deal-chip<?php echo $term->term_id === $current ? ' is-current' : ''; ?>" href="<?php echo \esc_url($link); ?>"><?php echo \esc_html($term->name); ?></a>
    <?php endforeach; ?>
</nav>
