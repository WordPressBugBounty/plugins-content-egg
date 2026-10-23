<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\import\PresetPostType;
use ContentEgg\application\admin\import\PresetRepository;

/**
 * DealPreset class file
 *
 * The import preset behind Deals → Add deals: one deal per product link,
 * drafted so there is time to write a line about it.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealPreset
{
    const OPTION = 'cegg_deals_preset_id';

    public static function id(): int
    {
        return (int) \get_option(self::OPTION, 0);
    }

    /**
     * PresetRepository::get() returns an array with a title even for a deleted
     * preset, so existence is checked on the post itself.
     */
    public static function exists(int $id): bool
    {
        return $id > 0 && \get_post_type($id) === PresetPostType::POST_TYPE && \get_post_status($id) === 'publish';
    }

    public static function ensure(): int
    {
        $id = self::id();
        if (self::exists($id))
            return $id;

        // A deal preset the user made themselves is just as good.
        foreach (PresetRepository::all() as $preset_id => $meta)
        {
            if (($meta['post_type'] ?? '') === DealPostType::POST_TYPE)
            {
                \update_option(self::OPTION, (int) $preset_id, false);
                return (int) $preset_id;
            }
        }

        $id = PresetRepository::installPreset(self::data());
        if ($id)
            \update_option(self::OPTION, $id, false);

        return $id;
    }

    public static function data(): array
    {
        return array(
            'title' => __('Deal', 'content-egg'),
            'pro' => false,
            'data' => array(
                'post_type' => DealPostType::POST_TYPE,
                'product_type' => 'external',
                'dynamic_categories' => 'none',
                'post_status' => 'draft',
                'tags' => '',
                'price_comparison' => 'disabled',
                'title_tpl' => '%PRODUCT.title%',
                'body_tpl' => '%PRODUCT.description%',
                'woo_short_desc_tpl' => '',
                'ai_product_content' => array(),
                'ai_title' => '',
                'ai_content' => '',
                'ai_short_desc' => '',
                'prompt1' => '',
                'prompt2' => '',
                'prompt3' => '',
                'avoid_duplicates' => true,
                'use_default' => false,
            ),
        );
    }
}
