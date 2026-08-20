<?php
/**
 * ValidUtil
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Libs;

class ValidUtil
{
    /**
     * @param string $str
     * @return bool
     */
    public static function isEmail(string $str): bool
    {
        return filter_var($str, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @param $val
     * @return bool
     */
    public static function isInteger($val): bool
    {
        return filter_var($val, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * @param $val
     * @return bool
     */
    public static function isFloat($val): bool
    {
        return filter_var($val, FILTER_VALIDATE_FLOAT) !== false;
    }

    /**
     * @param $str
     * @return bool
     */
    public static function isAlphabetAndNumeric($str): bool
    {
        return preg_match('/^[a-z0-9]+\z/i', (string)$str) === 1;
    }

    /**
     * @param $str
     * @return bool
     */
    public static function isUsernameLetters($str): bool
    {
        return preg_match('/^[a-z0-9_]+\z/i', (string)$str) === 1;
    }

    /**
     * @param $str
     * @return bool
     */
    public static function isEmailLettersExceptAt($str): bool
    {
        return preg_match('/^[a-z0-9-]+\z/i', (string)$str) === 1;
    }
}