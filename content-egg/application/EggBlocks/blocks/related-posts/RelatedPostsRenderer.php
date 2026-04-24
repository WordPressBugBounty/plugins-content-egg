<?php

namespace ContentEgg\application\EggBlocks\blocks\relatedposts;

use ContentEgg\application\EggBlocks\blocks\relatedposts\variants\CompactVariant;
use ContentEgg\application\EggBlocks\blocks\relatedposts\variants\MediaListVariant;
use ContentEgg\application\EggBlocks\blocks\relatedposts\variants\CardsVariant;
use ContentEgg\application\EggBlocks\blocks\relatedposts\variants\FeaturedVariant;
use ContentEgg\application\EggBlocks\shared\traits\RendersWithTheme;

defined('ABSPATH') || exit;

class RelatedPostsRenderer
{
    use RendersWithTheme;

    public static function render(array $attributes): string
    {
        $variant = (string) ($attributes['variant'] ?? 'compact');
        if (!in_array($variant, ['compact', 'media-list', 'cards', 'featured'], true))
        {
            $variant = 'compact';
        }

        $items = self::normalizeItems($attributes['items'] ?? []);
        if (empty($items))
        {
            return '';
        }

        $data = [
            'section_label' => trim((string) ($attributes['section_label'] ?? __('Related Articles', 'content-egg-tpl'))),
            'items' => $items,
        ];

        $theme_class = self::resolveThemeClass('auto');
        $color_scheme = self::resolveColorScheme($attributes['color_scheme'] ?? 'auto', $theme_class);
        $data_theme = ($color_scheme === 'light' || $color_scheme === 'dark')
            ? ' data-theme="' . esc_attr($color_scheme) . '"' . ' data-bs-theme="' . esc_attr($color_scheme) . '"'
            : '';

        ob_start();

        switch ($variant)
        {
            case 'media-list':
                MediaListVariant::render($data, $theme_class, $data_theme);
                break;
            case 'cards':
                CardsVariant::render($data, $theme_class, $data_theme);
                break;
            case 'featured':
                FeaturedVariant::render($data, $theme_class, $data_theme);
                break;
            default:
                CompactVariant::render($data, $theme_class, $data_theme);
                break;
        }

        return (string) ob_get_clean();
    }

    private static function normalizeItems($items): array
    {
        if (!is_array($items))
        {
            return [];
        }

        $can_see_scheduled = current_user_can('manage_options');

        $normalized = [];
        foreach ($items as $item)
        {
            if (!is_array($item))
            {
                continue;
            }

            $post_id_raw = trim((string) ($item['post_id'] ?? ''));

            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $post_id_raw))
            {
                if (!class_exists('\TMN\Services\ContentEgg\TmnResolver'))
                {
                    continue;
                }
                $post_id = \TMN\Services\ContentEgg\TmnResolver::getPostIdByNodeUuid($post_id_raw);
                if ($post_id === null)
                {
                    continue;
                }
            }
            else
            {
                $post_id = (int) $post_id_raw;
                if ($post_id <= 0)
                {
                    continue;
                }
            }

            $post = get_post($post_id);
            if (!$post || !in_array($post->post_status, ['publish', 'future'], true))
            {
                continue;
            }

            $is_published = $post->post_status === 'publish';
            $show_link = $is_published || $can_see_scheduled;

            $title = trim((string) ($item['title'] ?? ''));
            if ($title === '')
            {
                $title = get_the_title($post_id);
            }

            if ($title === '')
            {
                continue;
            }

            $url = $show_link ? get_permalink($post_id) : '';

            $badge = trim((string) ($item['badge'] ?? ''));
            if ($badge === '')
            {
                $categories = get_the_category($post_id);
                if (!empty($categories))
                {
                    $badge = $categories[0]->name;
                }
            }

            $snippet = trim((string) ($item['snippet'] ?? ''));
            if ($snippet === '')
            {
                $excerpt = get_the_excerpt($post_id);
                if ($excerpt !== '')
                {
                    $snippet = wp_trim_words($excerpt, 20, '...');
                }
            }

            $thumbnail = get_the_post_thumbnail_url($post_id, 'medium');

            $normalized[] = [
                'url'       => $url,
                'title'     => $title,
                'thumbnail' => $thumbnail ?: '',
                'badge'     => $badge,
                'snippet'   => $snippet,
                'linked'    => $show_link,
            ];
        }

        return $normalized;
    }
}
