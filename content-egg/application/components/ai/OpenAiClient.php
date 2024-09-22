<?php

namespace ContentEgg\application\components\ai;

use function ContentEgg\prnx;

defined('\ABSPATH') || exit;

/**
 * OpenAiClient class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2024 keywordrush.com
 */

class OpenAiClient extends AiClient
{
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

		if (!isset($data['choices'][0]['message']['content']))
			throw new \Exception('No content message in the openai response.');

		$content = $data['choices'][0]['message']['content'];

		if (isset($data['usage']))
			$this->last_usage = $data['usage'];
		else
			$this->last_usage = array();

		return $content;
	}
}
