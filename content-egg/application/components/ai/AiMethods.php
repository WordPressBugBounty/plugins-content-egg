<?php

namespace ContentEgg\application\components\ai;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;

/**
 * AiMethods class file
 *
 * Single source of the AI title/description/smart-group method → label lists
 * for the editor Product Manager. Values are the exact keys AiProcessor
 * dispatches on; labels mirror the classic metabox menus. Custom prompts 1-4
 * are included only when their GeneralConfig option is set.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class AiMethods
{
    /**
     * Prepend the configured custom prompts (promptN with a non-empty option
     * value) ahead of $methods, in numeric order. Pure.
     *
     * @param array $methods       list of array('value' => .., 'label' => ..)
     * @param array $promptOptions array('prompt1' => optValue, .. 'prompt4' => ..)
     */
    public static function withCustomPrompts(array $methods, array $promptOptions): array
    {
        $custom = array();
        for ($n = 1; $n <= 4; $n++)
        {
            $key = 'prompt' . $n;
            if (!empty($promptOptions[$key]))
            {
                $custom[] = array(
                    'value' => $key,
                    'label' => \sprintf(\__('Custom prompt #%d', 'content-egg'), $n),
                );
            }
        }

        return array_merge($custom, $methods);
    }

    private static function promptOptions(): array
    {
        $config = GeneralConfig::getInstance();

        return array(
            'prompt1' => $config->option('prompt1'),
            'prompt2' => $config->option('prompt2'),
            'prompt3' => $config->option('prompt3'),
            'prompt4' => $config->option('prompt4'),
        );
    }

    public static function titleMethods(): array
    {
        $methods = array(
            array('value' => 'shorten',              'label' => \__('Shorten', 'content-egg')),
            array('value' => 'rephrase',             'label' => \__('Rephrase', 'content-egg')),
            array('value' => 'translate',            'label' => \__('Translate', 'content-egg')),
            array('value' => 'subtitle_perfect_for', 'label' => \__('Generate subtitle', 'content-egg')),
        );

        return self::withCustomPrompts($methods, self::promptOptions());
    }

    public static function descriptionMethods(): array
    {
        $methods = array(
            array('value' => 'rewrite',               'label' => \__('Rewrite', 'content-egg')),
            array('value' => 'paraphrase',            'label' => \__('Paraphrase', 'content-egg')),
            array('value' => 'translate',             'label' => \__('Translate', 'content-egg')),
            array('value' => 'summarize',             'label' => \__('Summarize', 'content-egg')),
            array('value' => 'bullet_points',         'label' => \__('Bullet points', 'content-egg')),
            array('value' => 'bullet_points_compact', 'label' => \__('Bullet points (concise)', 'content-egg')),
            array('value' => 'write_review',          'label' => \__('Write a review', 'content-egg')),
            array('value' => 'write_article',         'label' => \__('Write an article', 'content-egg')),
            array('value' => 'write_buyers_guide',    'label' => \__('Write a buyer\'s guide', 'content-egg')),
            array('value' => 'write_paragraphs',      'label' => \__('Write a few paragraphs', 'content-egg')),
            array('value' => 'craft_description',     'label' => \__('Craft a product description', 'content-egg')),
            array('value' => 'write_how_to_use',      'label' => \__('Write a how to use instruction', 'content-egg')),
            array('value' => 'turn_into_advertising', 'label' => \__('Turn into advertising', 'content-egg')),
            array('value' => 'cta_text',              'label' => \__('Generate CTA text', 'content-egg')),
        );

        return self::withCustomPrompts($methods, self::promptOptions());
    }

    public static function smartGroupMethods(): array
    {
        return array(
            array('value' => 'auto',                 'label' => \__('Auto-Groups', 'content-egg')),
            array('value' => 'price_comparison',     'label' => \__('Price Comparison', 'content-egg')),
            array('value' => 'product_category',     'label' => \__('By Shopping Category', 'content-egg')),
            array('value' => 'features',             'label' => \__('By Features', 'content-egg')),
            array('value' => 'brand',                'label' => \__('By Brand', 'content-egg')),
            array('value' => 'price_range',          'label' => \__('By Price Range', 'content-egg')),
            array('value' => 'by_usage',             'label' => \__('By Usage', 'content-egg')),
            array('value' => 'age_group',            'label' => \__('By Age Group', 'content-egg')),
            array('value' => 'material_ingredients', 'label' => \__('By Material or Ingredients', 'content-egg')),
            array('value' => 'size_volume',          'label' => \__('By Size or Volume', 'content-egg')),
        );
    }
}
