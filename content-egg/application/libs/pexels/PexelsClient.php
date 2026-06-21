<?php

namespace ContentEgg\application\libs\pexels;

defined('\ABSPATH') || exit;

use ContentEgg\application\libs\RestClient;

/**
 * PexelsClient class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 *
 * @link: https://www.pexels.com/api/documentation/
 *
 */
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'RestClient.php';

class PexelsClient extends RestClient
{

	const API_URI_BASE = 'https://api.pexels.com';

	private $apiKey = null;

	/**
	 * @var array Response Format Types
	 */
	protected $_responseTypes = array(
		'json'
	);

	/**
	 * Constructor
	 */
	public function __construct($apiKey, $responseType = 'json')
	{
		$this->apiKey = $apiKey;
		$this->setResponseType($responseType);
		$this->setUri(self::API_URI_BASE);
		$this->addCustomHeaders(array('Authorization' => $apiKey));
	}

	public function searchPhotos($query, array $params = array())
	{
		$params['query'] = $query;

		$response = $this->restGet('/v1/search', $params);

		return $this->_decodeResponse($response);
	}

	public function searchVideos($query, array $params = array())
	{
		$params['query'] = $query;

		$response = $this->restGet('/videos/search', $params);

		return $this->_decodeResponse($response);
	}
}
