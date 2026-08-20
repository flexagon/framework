<?php
/**
 * StringUtil
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Libs;

class StringUtil
{
    /**
     * @param string|null $text
     * @return string
     */
    public static function escapeSpaces(?string $text): string
    {
        if ( is_null($text)) { $text = ''; }
        return str_replace(" ","",$text);
    }

    /**
     * @param string|null $text
     * @return string
     */
    /**
     * Drop byte sequences that are not valid UTF-8, keeping everything else
     * untouched.
     *
     * Escaping for output is a separate concern: use htmlspecialchars() with
     * ENT_QUOTES for that.
     *
     * Implemented with PCRE so no mbstring or iconv dependency is introduced.
     *
     * @param string|null $text
     * @return string
     */

    public static function stripNonUTF8(?string $text): string
    {
        if ( is_null($text) || $text === '' ) { return ''; }

        $pattern = '/(
              [\x09\x0A\x0D\x20-\x7E]            # ASCII, printable and common whitespace
            | [\xC2-\xDF][\x80-\xBF]              # 2 byte
            | \xE0[\xA0-\xBF][\x80-\xBF]         # 3 byte, excluding overlong
            | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}
            | \xED[\x80-\x9F][\x80-\xBF]         # excluding surrogates
            | \xF0[\x90-\xBF][\x80-\xBF]{2}      # 4 byte, excluding overlong
            | [\xF1-\xF3][\x80-\xBF]{3}
            | \xF4[\x80-\x8F][\x80-\xBF]{2}      # excluding above U+10FFFF
        )/x';

        preg_match_all($pattern, $text, $matches);
        return implode('', $matches[0]);
    }

    /**
     * @param string|null $text
     * @return array|string|string[]|null
     */
    public static function getAlphabetAndNumberOnly(?string $text): string {
        if ( is_null($text)) { return ''; }

        return (string)preg_replace("/[^a-zA-Z0-9]+/", "", $text);
    }

    /**
     * @param $string
     * @return string
     */
    public static function convertToHex($string): string
    {
        $hex='';
        for ($i=0; $i < strlen($string); $i++){ $hex .= dechex(ord($string[$i])); }
        return $hex;
    }

    /**
     * @param $string
     * @return string|null
     */
    public static function convertToCamelcase($string): ?string
    {
        return lcfirst(str_replace('_','',ucwords($string,'_')));
    }

    /**
     * @param string $str
     * @return string
     */
    public static function convertTextToHexString(string $str): string
    {
        return bin2hex($str);
    }

    /**
     * @param string $hexString
     * @return string
     */
    public static function convertHexStringToText(string $hexString): string
    {
        return pack("H*",bin2hex($hexString));
    }

    /**
     * @param int $length
     * @return string
     */
    public static function generateRandomString(int $length = 10): string
    {
        if ( $length < 0 ) {
            $length = 0;
        }
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * Whether $text begins with $startString.
     * @param $startString
     * @param $text
     * @return bool
     */
    public static function isStartedWith($startString, $text): bool
    {
        $len = strlen($startString);
        return (substr($text, 0, $len) === $startString);
    }
}