<?php

defined('\ABSPATH') || exit;

use ContentEgg\application\deals\DealTemplates;

DealTemplates::header();
echo DealTemplates::part('_listing', array('title' => \single_term_title('', false), 'description' => \term_description(), 'context' => 'category', 'term' => \get_queried_object())); // phpcs:ignore
DealTemplates::footer();
