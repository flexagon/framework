<?php
/**
 * NetUtil
 *
 * Flexagon : PHP Development Framework
 *
 * @author        Younghwan Yong <young@phpk.org>
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Libs;

/**
 * CNET : Network Library using Curl
 */
class NetUtil {
	const METHOD_GET = 1;
	const METHOD_POST = 2;
	const METHOD_PUT = 3;
	const METHOD_DELETE = 4;

    /**
     * @var string
     */
	private static string $errorMsg = '';
    /**
     * @var int the error number or 0 (zero) if no error
     */
	private static int $errorNo = 0;
	
	/**
	 * @param string $url
	 * @param int $method
	 * @param array $paramsArray
	 * @param string|null $userAgent
	 * @return array|boolean
	 */
	public static function getHttpData(string $url, int $method = self::METHOD_GET, array $paramsArray = [], ?string $userAgent = null): array|false {
		if ( !($method == self::METHOD_GET || $method == self::METHOD_POST)) {
			return false;
		}
		if (is_null($userAgent)) {
			$userAgent = 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; Flexagon;)';
		}
		
		$timeoutConnection = 20;
		$timeout = 60;

		$postMethod = true;
		if ( $method == self::METHOD_GET ) {
		    $postMethod = false;
        }

		if ( !$postMethod && !empty($paramsArray) ) {
			$url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($paramsArray);
		}

		 
		$hasUpload = false;
		foreach ( $paramsArray as $paramValue ) {
			if ( $paramValue instanceof \CURLFile ) { $hasUpload = true; break; }
		}

		$options = [
		  CURLOPT_URL => $url,
		  CURLOPT_FAILONERROR => true,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_AUTOREFERER => true,      
		  CURLOPT_FOLLOWLOCATION => true,      
		  CURLOPT_HEADER => false,     
		  CURLOPT_MAXREDIRS => 10,        
		  CURLOPT_CONNECTTIMEOUT => $timeoutConnection,
		  CURLOPT_TIMEOUT => $timeout,
		  CURLOPT_USERAGENT => $userAgent,
		];

		if ( $postMethod ) {
			$options[CURLOPT_POST] = true;
			$options[CURLOPT_POSTFIELDS] = $hasUpload ? $paramsArray : http_build_query($paramsArray);
		} else {
			$options[CURLOPT_HTTPGET] = true;
		}
		
		$ch = curl_init();
		curl_setopt_array($ch, $options);
		$content = curl_exec($ch);
		$errorNo = curl_errno($ch);
		$errorMessage = curl_error($ch);
		$header = curl_getinfo($ch);
		
		if (0 == curl_errno($ch)) {
			curl_close($ch);

			self::$errorMsg = '';
			self::$errorNo = 0;

			$data['content'] = $content;
			$data['url'] = $header['url'];
			$data['header'] = $header;
			return $data;
		}

		self::$errorMsg = $errorMessage;
		self::$errorNo = $errorNo;

		curl_close($ch);
		return false;
	}

	/**
	 * Why the last call failed, or '' when it succeeded.
	 *
	 * The state was recorded but had no accessor, so callers had no way of
	 * telling a failure apart from an empty response.
	 *
	 * @return string
	 */
	public static function getErrorMessage(): string
	{
		return self::$errorMsg;
	}

	/**
	 * @return int curl error number, 0 when the last call succeeded
	 */
	public static function getErrorNo(): int
	{
		return self::$errorNo;
	}

    /**
     * @param $url
     * @param int $method
     * @param array $paramsArray
     * @param null $userAgent
     * @return string|null
     */
	/**
	 * @param string $url
	 * @param int $method
	 * @param array $paramsArray
	 * @param string|null $userAgent
	 * @return string|null null when the request failed; see getErrorMessage()
	 */
	public static function getHttpContent(string $url, int $method = self::METHOD_GET, array $paramsArray = [], ?string $userAgent = null): ?string
    {
		$responseData = self::getHttpData($url, $method, $paramsArray, $userAgent);
		if ( $responseData === false ) {
			return null;
		}

		return $responseData['content'];
	}
	
	public static function getHttpContentViaGet(string $url, array $paramsArray = []): ?string
    {
		return self::getHttpContent($url, self::METHOD_GET, $paramsArray);
	}
	
	public static function getHttpContentViaPost(string $url, array $paramsArray = []): ?string
    {
		return self::getHttpContent($url, self::METHOD_POST, $paramsArray);
	}

    /**
     * @param string $url
     * @return mixed
     */
	public static function getArrayFromJson(string $url): mixed
    {
		$content = self::getHttpContent($url);
        return json_decode($content, true);
	}

    /**
     * @param string $url
     * @param string $filePath
     * @param array $paramsArray
     * @return string|null
     */
	/**
	 * Upload a file with a multipart POST.
	 *
	 * @param string $url
	 * @param string $filePath
	 * @param array $paramsArray extra fields sent alongside the file
	 * @param string $fieldName form field the file is sent under
	 * @return string|null null when the file is unreadable or the request failed
	 */
	public static function sendFileViaPost(string $url, string $filePath, array $paramsArray = [], string $fieldName = 'FileData' ): ?string
    {
		if ( !is_file($filePath) || !is_readable($filePath) ) {
			self::$errorMsg = 'Cannot read ' . $filePath;
			self::$errorNo = 0;
			return null;
		}

		$paramsArray[$fieldName] = new \CURLFile($filePath);

		return self::getHttpContentViaPost($url, $paramsArray);
	}
}