<?php

namespace ContentEgg\application\admin\import;

defined('\ABSPATH') || exit;

/**
 * MetaFieldPresets
 *
 * Known post meta keys for popular SEO plugins, offered as a picker in the
 * import preset form so users do not have to know the exact key names.
 *
 * Only plugins that really store their data in POST META belong here. All in One
 * SEO is deliberately absent: since v4 it keeps titles and descriptions in its
 * own `wp_aioseo_posts` table, so writing those keys to post meta would silently
 * do nothing — exactly the failure this picker exists to prevent.
 *
 * Image alt text is absent for the same reason: it lives on the attachment
 * (`_wp_attachment_image_alt`), not on the imported post.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class MetaFieldPresets
{
    /**
     * Groups of known meta keys, active plugins first.
     *
     * @return array<int, array{label: string, active: bool, fields: array}>
     */
    public static function groups(): array
    {
        $groups = self::definitions();

        // Active plugins first; original order preserved within each bucket.
        $active = [];
        $rest   = [];

        foreach ($groups as $group)
        {
            if ($group['active'])
            {
                $active[] = $group;
            }
            else
            {
                $rest[] = $group;
            }
        }

        return array_merge($active, $rest);
    }

    /**
     * Flat list of every known key, for validation and tests.
     *
     * @return string[]
     */
    public static function allKeys(): array
    {
        $keys = [];

        foreach (self::definitions() as $group)
        {
            foreach ($group['fields'] as $field)
            {
                $keys[] = $field['key'];
            }
        }

        return $keys;
    }

    private static function definitions(): array
    {
        return [
            [
                'label'  => 'Yoast SEO',
                'active' => \defined('WPSEO_VERSION') || \class_exists('WPSEO_Options'),
                'fields' => [
                    ['key' => '_yoast_wpseo_title',    'label' => __('SEO title', 'content-egg')],
                    ['key' => '_yoast_wpseo_metadesc', 'label' => __('Meta description', 'content-egg')],
                    ['key' => '_yoast_wpseo_focuskw',  'label' => __('Focus keyphrase', 'content-egg')],
                ],
            ],
            [
                'label'  => 'Rank Math',
                'active' => \class_exists('RankMath') || \defined('RANK_MATH_VERSION'),
                'fields' => [
                    ['key' => 'rank_math_title',         'label' => __('SEO title', 'content-egg')],
                    ['key' => 'rank_math_description',   'label' => __('Meta description', 'content-egg')],
                    ['key' => 'rank_math_focus_keyword', 'label' => __('Focus keyword', 'content-egg')],
                ],
            ],
            [
                'label'  => 'SEOPress',
                'active' => \defined('SEOPRESS_VERSION') || \function_exists('seopress_get_service'),
                'fields' => [
                    ['key' => '_seopress_titles_title',        'label' => __('SEO title', 'content-egg')],
                    ['key' => '_seopress_titles_desc',         'label' => __('Meta description', 'content-egg')],
                    ['key' => '_seopress_analysis_target_kw',  'label' => __('Target keyword', 'content-egg')],
                ],
            ],
            [
                'label'  => 'The SEO Framework',
                'active' => \defined('THE_SEO_FRAMEWORK_VERSION') || \function_exists('the_seo_framework'),
                'fields' => [
                    ['key' => '_genesis_title',       'label' => __('SEO title', 'content-egg')],
                    ['key' => '_genesis_description', 'label' => __('Meta description', 'content-egg')],
                ],
            ],
        ];
    }
}
