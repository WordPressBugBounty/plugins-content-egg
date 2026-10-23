<?php

defined('\ABSPATH') || exit;

use ContentEgg\application\deals\DealTemplates;

/**
 * The deals archive. Override by copying to yourtheme/archive-cegg_deal.php.
 */

DealTemplates::header();
echo DealTemplates::part('_listing', array('title' => __('Deals', 'content-egg-tpl'), 'description' => '', 'context' => 'archive', 'term' => null)); // phpcs:ignore
DealTemplates::footer();
