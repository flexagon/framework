<?php
/**
 * Global helper functions.
 *
 * Loaded eagerly by Bootstrap.php and also registered as a Composer "files"
 * autoload entry, so the declarations are guarded against a double load.
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */

if ( !function_exists('_t') ) {
    /**
     * @param string|null $str
     * @param array|null $arr
     * @param bool $print
     * @return string
     */
    function _echo(?string $str, ?array $arr = null, bool $print = true ): string {
        $str = _t($str, $arr);
        if ( $print ) { echo $str; }
        return $str;
    }

    /**
     * @param string|null $str
     * @param array|null $arr
     * @return string
     */
    function _t(?string $str, ?array $arr = null): string {
        if ( $str === null ) { $str = ''; }

        if ( function_exists('__') ) {
            $str = __($str);
        } elseif ( function_exists( '_' )) {
            $str = _($str);
        }

        if (!empty($arr)) {
            $matchCount = preg_match_all('/\{([\S]+?)\}/', $str, $matches);
            if ( $matchCount > 0 && isset($matches[0]) && isset($matches[1])) {
                for ( $i=0; $i < count($matches[0]); $i++ ) {
                    if ( key_exists($matches[1][$i], $arr) ) {
                        $str = str_replace($matches[0][$i], $arr[$matches[1][$i]], $str);
                    }
                }
            }
        }

        return $str;
    }

    /**
     * @param string|null $str
     * @param array|null $arr
     * @return string
     */
    function ___(?string $str, ?array $arr = null): string {
        return _t($str, $arr);
    }
}
