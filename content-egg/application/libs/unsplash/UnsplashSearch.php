<?php

namespace ContentEgg\application\libs\unsplash;

defined('\ABSPATH') || exit;

use ContentEgg\application\libs\RestClient;

/**
 * UnsplashSearch class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 *
 * @link: https://unsplash.com/documentation#search-photos
 *
 */
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'RestClient.php';

class UnsplashSearch extends RestClient
{

	const API_URI_BASE = 'https://api.unsplash.com';

	protected static $timeout = 30; //sec

	private $accessKey = null;

	/**
	 * @var array Response Format Types
	 */
	protected $_responseTypes = array(
		'json'
	);

	/**
	 * Constructor
	 */
	public function __construct($accessKey, $responseType = 'json')
	{
		$this->setAccessKey($accessKey);
		$this->setResponseType($responseType);
		$this->setUri(self::API_URI_BASE);
	}

	public function setAccessKey($accessKey)
	{
		$this->accessKey = $accessKey;
	}

	public function getAccessKey()
	{
		return $this->accessKey;
	}

	public function search($query, array $params = array())
	{
		$params['query']     = $query;
		$params['client_id'] = $this->getAccessKey();

		$response = $this->restGet('/search/photos', $params);

		return $this->_decodeResponse($response);
	}
}
