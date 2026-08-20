<?php
/**
 * ArrayUtil
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Libs;


class ArrayUtil
{
    /**
     * @param array $array
     * @return array
     */
    public static function convertArrayKeyToCamelcase(array $array): array
    {
        $newArray = [];
        foreach($array as $tmpKey => $tmpValue)
        {
            $newArray[StringUtil::convertToCamelcase($tmpKey)]=$tmpValue;
        }
        return $newArray;
    }

    /**
     * @param array $leftArray
     * @param array $rightArray
     * @return array
     */
    public static function diff(array $leftArray, array $rightArray): array
    {
        return array_diff(array_merge($leftArray, $rightArray), array_intersect($leftArray, $rightArray));
    }

    /**
     * Find where $needle sits in a nested array.
     *
     * @param mixed $needle value to look for
     * @param array $haystack
     * @return array|false the key path to the value, or false when absent
     */
    public static function findAllByKey(mixed $needle, array $haystack): array|false {
        foreach($haystack as $first_level_key=>$value) {
            if ($needle === $value) {
                return [$first_level_key];
            } elseif (is_array($value)) {
                $callback = ArrayUtil::findAllByKey($needle, $value);
                if ($callback) {
                    return array_merge([$first_level_key], $callback);
                }
            }
        }
        return false;
    }

    /**
     * Collect every value stored under $needle, at any depth.
     *
     * @param mixed $needle key to look for
     * @param array $haystack
     * @return mixed the value when there is one, an array when there are
     *               several, null when there are none
     */
    public static function getAllValuesByKey(mixed $needle, array $haystack): mixed {
        $val = [];
        array_walk_recursive($haystack, function($v, $k) use($needle, &$val){
            if($k == $needle) { $val[] = $v; }
        });
        return count($val) > 1 ? $val : array_pop($val);
    }
}