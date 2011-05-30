<?php

/**
 * Class for easy requesting Gowalla's API.
 * Based on EpiFoursquare class written by Jaisen Mathai <jaisen@jmathai.com>.
 */
class EpiGowalla
{
	protected $apiKey, $apiSecret, $accessToken;
	protected $requestTokenUrl = 'https://gowalla.com/api/oauth/new';
	protected $accessTokenUrl = 'https://api.gowalla.com/api/oauth/token';
	protected $apiUrl = 'https://api.gowalla.com';
	protected $userAgent = 'EpiGowalla (http://github.com/detonator/php-gowalla-oauth/tree/)';
	protected $apiVersion = 'v2';
	protected $isAsynchronous = false;
	protected $followLocation = false;
	protected $maxRedirectsDepth = 5;
	protected $connectionTimeout = 5;
	protected $requestTimeout = 30;
	protected $debug = false;

	/**
	 * @param string $apiKey
	 * @param string $apiSecret
	 * @param string $accessToken
	 */
	public function __construct($apiKey = null, $apiSecret = null, $accessToken = null)
	{
		$this->apiKey = $apiKey;
		$this->apiSecret = $apiSecret;
		$this->accessToken = $accessToken;
	}

	/**
	 * Gets access token. After it access token can be stored in DB and used in future for
	 * offline accessing to API.
	 * @param string $code
	 * @param string $redirectUri
	 * @return EpiGowallaJson
	 */
	public function getAccessToken($code, $redirectUri)
	{
		$params = array(
			'client_id' => $this->apiKey,
			'client_secret' => $this->apiSecret,
			'grant_type' => 'authorization_code',
			'redirect_uri' => $redirectUri,
			'code' => $code,
		);
		return $this->request('POST', "{$this->accessTokenUrl}", $params); //line to change
	}

	/**
	 * Gets access token. After it access token can be stored in DB and used in future for
	 * offline accessing to API.
	 * @param string $refreshToken
	 * @param string $redirectUri
	 * @return EpiGowallaJson
	 */
	public function getRefreshedAccessToken($refreshToken)
	{
		$params = array(
			'client_id' => $this->apiKey,
			'client_secret' => $this->apiSecret,
			'grant_type' => 'refresh_token',
			'refresh_token' => $refreshToken,
		);
		return $this->request('POST', "{$this->accessTokenUrl}", $params); //line to change
	}

	/**
	 * Gets authorize URL where user must be redirected for granting application.
	 * @param string $redirectUri
	 * @return string
	 */
	public function getAuthorizeUrl($redirectUri)
	{
		$params = array(
			'client_id' => $this->apiKey,
			'response_type' => 'code',
			'redirect_uri' => $redirectUri,
		);
		$qs = http_build_query($params);
		return "{$this->requestTokenUrl}?{$qs}";
	}

	/**
	 * Sets access token to perform API requests in future.
	 * @param string $accessToken
	 * @return EpiGowalla
	 */
	public function setAccessToken($accessToken)
	{
		$this->accessToken = $accessToken;
		return $this;
	}

	/**
	 * @param null $requestTimeout
	 * @param null $connectionTimeout
	 * @return void
	 */
	public function setTimeout($requestTimeout = null, $connectionTimeout = null)
	{
		if ($requestTimeout !== null) {
			$this->requestTimeout = floatval($requestTimeout);
		}
		if ($connectionTimeout !== null) {
			$this->connectionTimeout = floatval($connectionTimeout);
		}
	}

	/**
	 * Sets API version. For example: "v2".
	 * @param string $version
	 * @return void
	 */
	public function useApiVersion($version = null)
	{
		$this->apiVersion = $version;
	}

	/**
	 * Sets asynchronous mode.
	 * @param bool $async
	 * @return void
	 */
	public function useAsynchronous($async = true)
	{
		$this->isAsynchronous = (bool)$async;
	}

	/**
	 * @param string $spot
	 * @param array $params
	 * @return EpiGowallaJson
	 */
	public function delete($spot, $params = null)
	{
		return $this->request('DELETE', $spot, $params);
	}

	/**
	 * @param string $spot
	 * @param array $params
	 * @return EpiGowallaJson
	 */
	public function get($spot, $params = null)
	{
		return $this->request('GET', $spot, $params);
	}

	/**
	 * @param string $spot
	 * @param array $params
	 * @return EpiGowallaJson
	 */
	public function post($spot, $params = null)
	{
		return $this->request('POST', $spot, $params);
	}

	/**
	 * @param string $spot
	 * @return string
	 */
	private function getApiUrl($spot)
	{
		return "{$this->apiUrl}{$spot}";
	}

	/**
	 * Requests API calling.
	 * @param string $method
	 * @param string $spot the URL to make API call.
	 * @param null $params
	 * @return EpiGowallaJson
	 */
	private function request($method, $spot, $params = null)
	{
		if (preg_match('#^https?://#', $spot)) {
			$url = $spot;
		} else {
			$url = $this->getApiUrl($spot);
		}

		if ($this->accessToken) {
			$params['oauth_token'] = $this->accessToken;
		} else {
			$params['client_id'] = $this->apiKey;
			$params['client_secret'] = $this->apiSecret;
		}

		if ($method === 'GET') {
			$url .= is_null($params) ? '' : '?' . http_build_query($params, '', '&');
		}
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->requestTimeout);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		if (isset($_SERVER ['SERVER_ADDR']) && !empty($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] != '127.0.0.1') {
			curl_setopt($ch, CURLOPT_INTERFACE, $_SERVER ['SERVER_ADDR']);
		}
		if ($method === 'POST' && $params !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
		}

		$response = EpiCurl::getInstance()->addCurl($ch);
		$responseJson = new EpiGowallaJson($response, $this->debug);
		if (!$this->isAsynchronous) {
			$responseJson->responseText;
		}

		return $responseJson;
	}
}

/**
 * Class represents JSON-response data as object.
 */
class EpiGowallaJson implements ArrayAccess, Countable, IteratorAggregate
{
	private $debug;
	private $__resp;

	/**
	 * @param  $response
	 * @param bool $debug
	 */
	public function __construct($response, $debug = false)
	{
		$this->__resp = $response;
		$this->debug = $debug;
	}

	// ensure that calls complete by blocking for results, NOOP if already returned
	public function __destruct()
	{
		$this->responseText;
	}

	/**
	 * Implementation of the IteratorAggregate::getIterator() to support foreach ($this as $...)
	 * @return ArrayIterator
	 */
	public function getIterator()
	{
		if ($this->__obj) {
			return new ArrayIterator($this->__obj);
		} else {
			return new ArrayIterator($this->response);
		}
	}

	/**
	 * Implementation of Countable::count() to support count($this)
	 * @return int
	 */
	public function count()
	{
		return count($this->response);
	}

	/**
	 * @param  $offset
	 * @param  $value
	 * @return void
	 */
	public function offsetSet($offset, $value)
	{
		$this->response[$offset] = $value;
	}

	/**
	 * @param  $offset
	 * @return bool
	 */
	public function offsetExists($offset)
	{
		return isset($this->response[$offset]);
	}

	/**
	 * @param  $offset
	 * @return void
	 */
	public function offsetUnset($offset)
	{
		unset($this->response[$offset]);
	}

	/**
	 * @param  $offset
	 * @return null
	 */
	public function offsetGet($offset)
	{
		return isset($this->response[$offset]) ? $this->response[$offset] : null;
	}

	/**
	 * @param  $name
	 * @return null
	 */
	public function __get($name)
	{
		$accessible = array(
			'responseText' => 1,
		    'headers' => 1,
		    'code' => 1,
		);
		$this->responseText = $this->__resp->data;
		$this->headers = $this->__resp->headers;
		$this->code = $this->__resp->code;
		if (isset($accessible[$name]) && $accessible[$name]) {
			return $this->$name;
		} elseif (($this->code < 200 || $this->code >= 400) && !isset($accessible[$name])) {
			EpiGowallaException::raise($this->__resp, $this->debug);
		}

		// Call appears ok so we can fill in the response
		$this->response = json_decode($this->responseText, 1);
		$this->__obj = json_decode($this->responseText);

		if (gettype($this->__obj) === 'object') {
			foreach ($this->__obj as $k => $v) {
				$this->$k = $v;
			}
		}

		if (property_exists($this, $name)) {
			return $this->$name;
		}
		return null;
	}

	public function __isset($name)
	{
		return !empty($name);
	}
}

class EpiGowallaException extends Exception
{
	public static function raise($response, $debug)
	{
		$message = $response->data;

		switch ($response->code)
		{
			case 400:
				throw new EpiGowallaBadRequestException($message, $response->code);
			case 401:
				throw new EpiGowallaNotAuthorizedException($message, $response->code);
			case 403:
				throw new EpiGowallaForbiddenException($message, $response->code);
			case 404:
				throw new EpiGowallaNotFoundException($message, $response->code);
			default:
				throw new EpiGowallaException($message, $response->code);
		}
	}
}

class EpiGowallaBadRequestException extends EpiGowallaException
{
}

class EpiGowallaNotAuthorizedException extends EpiGowallaException
{
}

class EpiGowallaForbiddenException extends EpiGowallaException
{
}

class EpiGowallaNotFoundException extends EpiGowallaException
{
}
