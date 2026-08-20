<?php
/**
 * HttpUtil
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Libs;


use _Flexagon\Models\UrlParamsModel;

class HttpUtil
{
    /**
     * @param string $url
     * @return string[]
     */
    public static function parseUrl(string $url): array
    {
        $urlArray = [0=>'',1=>'',2=>'',3=>'','scheme'=>'','host'=>'','path'=>'','query'=>''];
        $parsedUrl = parse_url($url);
        if ( isset($parsedUrl['scheme']) ) {
            $urlArray['scheme'] = $parsedUrl['scheme'];
            $urlArray['0'] = $parsedUrl['scheme'];
        }
        if ( isset($parsedUrl['host']) ) {
            $urlArray['host'] = $parsedUrl['host'];
            $urlArray['1'] = $parsedUrl['host'];
        }
        if ( isset($parsedUrl['path']) ) {
            $urlArray['path'] = $parsedUrl['path'];
            $urlArray['2'] = $parsedUrl['path'];
        }
        if ( isset($parsedUrl['query']) ) {
            $urlArray['query'] = $parsedUrl['query'];
            $urlArray['3'] = $parsedUrl['query'];
        }
        return $urlArray;
    }

    /**
     * @param string $url
     * @return string
     */
    public static function getSchemeAndHost(string $url): string
    {
        $url = trim((string)$url);
        if ( empty($url) ) return '';
        $tmpArray = parse_url($url);
        return $tmpArray['scheme'].'://'.$tmpArray['host'];
    }


    /**
     * $return['filepath'] = string<br />
     * $return['filepath_array'] = array<br />
     * $return['params] = string<br />
     * @param string $uri
     * @return UrlParamsModel
     */
    static function getParamsFromUrl(string $uri = '' ): UrlParamsModel
    {
        if ( \FLEXAGON_ENV::$_RUNTIME_ENV !== \FLEXAGON_CONST::RUNTIME_ENV_SCRIPT && empty($uri)) {
            if ( isset($_SERVER['REQUEST_URI']) ) {
                $uri = $_SERVER['REQUEST_URI'];
                if ( strpos($uri, '/__flexagon.php') !== false ) {
                    $url = substr($uri, 0, strpos($uri,'/__flexagon.php')).substr($uri,strpos($uri, '/__flexagon.php')+strlen('/__flexagon.php')).'/';
                }
            }
        }
        $separatorPos = strpos($uri, '?',0);
        if ( $separatorPos === false ) {
            $separatorPos = strlen($uri);
        }
        $filePath = substr($uri,1,$separatorPos-1);
        if ( trim((string)$filePath) == '' || substr($filePath, -1) =='/'  ) $filePath .= 'index';

        $filePath = self::normalizePath($filePath);
        if ( $filePath === '' ) { $filePath = 'index'; }

        $filePathArray = explode('/',$filePath );
        $params = substr($uri,$separatorPos);

        return new UrlParamsModel($filePath, $filePathArray, $params);
    }

    /**
     * Reduce a request path to a plain relative path.
     *
     * Drops NUL bytes, folds backslashes onto '/', and removes empty, '.' and
     * '..' segments so the result can never climb above the directory it is
     * later resolved against.
     *
     * @param string $path
     * @return string
     */
    public static function normalizePath(string $path): string
    {
        $path = str_replace(["\0", '\\'], ['', '/'], $path);

        $segments = [];
        foreach ( explode('/', $path) as $segment ) {
            if ( $segment === '' || $segment === '.' ) { continue; }
            if ( $segment === '..' ) { array_pop($segments); continue; }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }


    /**
     * The URI without its http:// or https:// prefix.
     *
     * @param string $uri
     * @return string
     */
    public static function stripHttpProtocol(string $uri): string
    {
        $disallowed = ['http://', 'https://'];
        foreach ($disallowed as $d) {
            if (strpos($uri, $d) === 0) {
                return substr($uri, strlen($d));
            }
        }
        return $uri;
    }

    /**
     * The URI without its query string.
     *
     * @param $uri
     * @return string
     */
    public static function getUrlWithoutQueryString($uri): string
    {
        $uriArray = explode('?', $uri, 2);
        if ( is_string($uriArray[0])) {
            return $uriArray[0];
        }
        return '';
    }
}