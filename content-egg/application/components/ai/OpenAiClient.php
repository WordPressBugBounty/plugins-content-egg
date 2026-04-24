<?php

namespace ContentEgg\application\components\ai;;

defined('\ABSPATH') || exit;

/**
 * OpenAiClient class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

class OpenAiClient extends AiClient
{
	//@link: https://platform.openai.com/docs/pricing
	const PRICE_INPUT_5_NANO = 0.05;
	const PRICE_OUTPUT_5_NANO = 0.40;
	const PRICE_INPUT_4O_MINI = 0.150;
	const PRICE_OUTPUT_4O_MINI = 0.600;
	const PRICE_INPUT_4O = 2.50;
	const PRICE_OUTPUT_4O = 10.00;
	const PRICE_INPUT_5_1 = 1.25;
	const PRICE_OUTPUT_5_1 = 10.00;
	const PRICE_INPUT_5_2 = 1.75;
	const PRICE_OUTPUT_5_2 = 14.00;
	const PRICE_INPUT_5_2_PRO = 21.00;
	const PRICE_OUTPUT_5_2_PRO = 84.00;

	public function getChatUrl()
	{
		return 'https://api.openai.com/v1/chat/completions';
	}

	public function getHeaders()
	{
		return array(
			'Content-Type: application/json',
			'Authorization: Bearer ' . $this->api_key,
		);
	}

	public function getAiModelPrices()
	{
		return [
			'gpt-5-nano' => [
				'input'  => apply_filters('cegg_price_input_5_nano', self::PRICE_INPUT_5_NANO),
				'output' => apply_filters('cegg_price_output_5_nano', self::PRICE_OUTPUT_5_NANO),
			],
			'gpt-4o-mini' => [
				'input'  => apply_filters('cegg_price_input_4o_mini', self::PRICE_INPUT_4O_MINI),
				'output' => apply_filters('cegg_price_output_4o_mini', self::PRICE_OUTPUT_4O_MINI),
			],
			'gpt-4o' => [
				'input'  => apply_filters('cegg_price_input_4o', self::PRICE_INPUT_4O),
				'output' => apply_filters('cegg_price_output_4o', self::PRICE_OUTPUT_4O),
			],
			'gpt-5.1' => [
				'input'  => apply_filters('cegg_price_input_5_1', self::PRICE_INPUT_5_1),
				'output' => apply_filters('cegg_price_output_5_1', self::PRICE_OUTPUT_5_1),
			],
			'gpt-5.2' => [
				'input'  => apply_filters('cegg_price_input_5_2', self::PRICE_INPUT_5_2),
				'output' => apply_filters('cegg_price_output_5_2', self::PRICE_OUTPUT_5_2),
			],
			'gpt-5.2-pro' => [
				'input'  => apply_filters('cegg_price_input_5_2_pro', self::PRICE_INPUT_5_2_PRO),
				'output' => apply_filters('cegg_price_output_5_2_pro', self::PRICE_OUTPUT_5_2_PRO),
			],
		];
	}

	public function getPayload($prompt, $system = '', $params = array())
	{
		$messages = array();

		if ($system)
		{
			$message = array(
				'role' => 'system',
				'content' => $system,
			);

			$messages[] = $message;
		}

		$message = array(
			'role' => 'user',
			'content' => $prompt,
		);

		$messages[] = $message;

		$payload = array(
			'messages' => $messages,
		);

		$payload = array_merge($params, $payload);

		return $payload;
	}

	public function getContent($response)
	{
		if (!$data = json_decode($response, true))
			throw new \Exception('Invalid JSON formatting.');

		if (isset($data['error']['message']))
		{
			$errorMessage = 'AI API error: ' . $data['error']['message'];
			if (isset($data['error']['code']))
				$errorMessage .= ' | Error code: ' . $data['error']['code'];

			if (isset($data['error']['metadata']['raw']))
				$errorMessage .= ' | Raw metadata: ' . $data['error']['metadata']['raw'];

			throw new \Exception(esc_html($errorMessage));
		}

		if (!isset($data['choices'][0]['message']['content']))
			throw new \Exception('No content message in the AI response.');

		$content = $data['choices'][0]['message']['content'];

		if (isset($data['usage']))
			$this->last_usage = $data['usage'];
		else
			$this->last_usage = array();

		return $content;
	}

	public function getLastUsagePrice()
	{
		if (!$this->last_usage)
			return 0;

		$price = $this->last_usage['prompt_tokens'] / 1000000 * $this->getLastUsedModelPriceInput();
		$price += $this->last_usage['completion_tokens'] / 1000000 * $this->getLastUsedModelPriceOutput();

		return $price;
	}

	public function getLastUsedModelPriceInput()
	{
		$prices = $this->getAiModelPrices();
		return $prices[$this->last_used_model]['input'];
	}

	public function getLastUsedModelPriceOutput()
	{
		$prices = self::getAiModelPrices();
		return $prices[$this->last_used_model]['output'];
	}
}
