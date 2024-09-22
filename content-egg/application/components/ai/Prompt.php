<?php

namespace ContentEgg\application\components\ai;

use function ContentEgg\prn;
use function ContentEgg\prnx;

defined('\ABSPATH') || exit;

/**
 * Prompt class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2024 keywordrush.com
 */

class Prompt
{
    protected $lang;
    protected $temperature;
    protected $client;

    public function __construct($api_key, $model)
    {
        $this->client = AiClient::createClient($api_key, $model);
    }

    public function setLang($lang)
    {
        $this->lang = $lang;
    }

    public function setTemperature($temperature)
    {
        $this->temperature = (float) $temperature;
    }

    protected function query($prompt, array $params = array(), array $ai_params = array())
    {
        $params = $this->prepareParams($params, $prompt);
        $prompt = PromptHelper::build($prompt, $params);
        if ($this->lang)
            $system = sprintf('Make sure you answer in %s!', $this->lang);

        if ($this->temperature && !isset($ai_params['temperature']))
            $ai_params['temperature'] = $this->temperature;

        $content = $this->client->query($prompt, $system, $ai_params);

        $content = ContentHelper::fixAiResponse($content);
        if (ContentHelper::isAiGenerated($content))
            return '';

        return $content;
    }

    protected function prepareParams(array $params, $prompt = '')
    {
        return $params;
    }
}
