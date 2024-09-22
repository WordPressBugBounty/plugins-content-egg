<?php

namespace ContentEgg\application\components\ai;

use ContentEgg\application\admin\GeneralConfig;

use function ContentEgg\prn;
use function ContentEgg\prnx;

defined('\ABSPATH') || exit;

/**
 * AiProcessor class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2024 keywordrush.com
 */

class AiProcessor
{

    public static function applayAiItems(array $items, $title_method = '', $description_method = '')
    {
        $is_selected = self::isSelected($items);
        foreach ($items as $i => $item)
        {
            if ($is_selected && empty($item['_selected']))
                continue;

            $items[$i] = self::applayAiItem($item, $title_method, $description_method);
        }

        return $items;
    }

    public static function isSelected(array $items)
    {
        foreach ($items as $item)
        {
            if (isset($item) && $item['_selected'])
                return true;
        }

        return false;
    }

    public static function applayAiItem(array $item, $title_method = '', $description_method = '')
    {
        if (!$api_key = GeneralConfig::getInstance()->option('ai_key'))
            return $item;

        if (!$title_method && !$description_method)
            return $item;

        $lang = GeneralConfig::getInstance()->option('ai_language');
        $temperature = GeneralConfig::getInstance()->option('ai_temperature');
        $model = GeneralConfig::getInstance()->option('ai_model');

        $api_key = explode(',', $api_key);
        $api_key = trim($api_key[array_rand($api_key)]);

        $prompt = new ProductPrompt($api_key, $model);

        $prompt->setProduct($item);
        $prompt->setProductNew($item);
        $prompt->setLang($lang);
        $prompt->setTemperature($temperature);

        if (\ContentEgg\application\Plugin::isDevEnvironment())
            mt_srand(12345678);

        $title_methods = array(
            'rephrase' => 'rephraseProductTitle',
            'translate' => 'translateProductTitle',
            'shorten' => 'shortenProductTitle',
            'prompt1' => 'customPromptTitle2',
            'prompt2' => 'customPromptTitle2',
            'prompt3' => 'customPromptTitle3',
            'prompt4' => 'customPromptTitle4',
        );

        if ($title_method && isset($title_methods[$title_method]))
        {
            $method = $title_methods[$title_method];
            if (method_exists($prompt, $method))
            {
                try
                {
                    $item['title'] = $prompt->$method();
                }
                catch (\Exception $e)
                {
                    throw new \Exception('AI: Title generation error: ' . $e->getMessage());
                }
            }
        }

        $description_methods = array(
            'rewrite' => 'rewriteProductDescription',
            'paraphrase' => 'paraphraseProductDescription',
            'translate' => 'translateProductDescription',
            'summarize' => 'summarizeProductDescription',
            'bullet_points' => 'bulletPointsProductDescription',
            'turn_into_advertising' => 'turnIntoAdvertisingProductDescription',
            'cta_text' => 'ctaTextProductDescription',
            'write_paragraphs' => 'writeParagraphsProductDescription',
            'craft_description' => 'craftProductDescription',
            'write_article' => 'writeArticleProductDescription',
            'write_buyers_guide' => 'writeBuyersGuideProductDescription',
            'write_review' => 'writeReviewProductDescription',
            'write_how_to_use' => 'writeHowToUseProductDescription',
            'prompt1' => 'customPromptDescription1',
            'prompt2' => 'customPromptDescription2',
            'prompt3' => 'customPromptDescription3',
            'prompt4' => 'customPromptDescription4',
        );

        if ($description_method && isset($description_methods[$description_method]))
        {
            $method = $description_methods[$description_method];
            if (method_exists($prompt, $method))
            {
                try
                {
                    $item['description'] = trim($prompt->$method());
                }
                catch (\Exception $e)
                {
                    throw new \Exception('AI: Description generation error: ' . $e->getMessage());
                }
            }
        }

        return $item;
    }
}
