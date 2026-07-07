<?php

namespace ContentEgg\application\EggBlocks\shared;

defined('ABSPATH') || exit;

class EggbThumbnail
{
    /**
     * Render a responsive post thumbnail <img> with auto srcset and a sizes hint.
     *
     * Using get_the_post_thumbnail() (rather than a single resized URL) lets WP emit
     * the srcset so the browser picks a resolution that matches the display width and
     * device pixel ratio — avoiding upscaled / blurry thumbnails on larger layouts and
     * HiDPI screens.
     *
     * @param int    $post_id Post whose featured image to render.
     * @param string $size    Largest registered size the layout may need (srcset source).
     * @param string $sizes   CSS sizes attribute describing the rendered width per breakpoint.
     *
     * @return string The <img> markup, or '' when the post has no featured image.
     */
    public static function render(int $post_id, string $size, string $sizes): string
    {
        if ($post_id <= 0 || !has_post_thumbnail($post_id))
        {
            return '';
        }

        return get_the_post_thumbnail($post_id, $size, [
            'alt'     => '',
            'loading' => 'lazy',
            'sizes'   => $sizes,
        ]);
    }
}
