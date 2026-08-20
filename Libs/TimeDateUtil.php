<?php
/**
 * TimeDateUtil
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Libs;


class TimeDateUtil
{
    /**
     * @param string $dateString
     * @return false|int
     */
    public static function getTimestampFromDateString(string $dateString): int|false {
        return strtotime($dateString);
    }

    /**
     * @param int|null $timestamp
     * @return false|string
     */
    public static function getDateTimeFromTimestamp(?int $timestamp): string|false {
        if ( is_null($timestamp) || $timestamp < 0 ) {
            return false;
        }
        return date("Y-m-d H:i:s",$timestamp);
    }


    /**
     * UTC offset of a timezone, in hours.
     *
     * Was a hand written table of 34 zones holding values like 5.30 to mean
     * "5 hours 30 minutes", which an ?int return then truncated to 5 while
     * raising a deprecation notice. Anything outside the table returned null,
     * and daylight saving was never applied. PHP knows all of this already.
     *
     * @param string $timezoneString e.g. 'Asia/Seoul'
     * @param int|null $atTimestamp point in time to resolve DST against, now by default
     * @return float|null null when the timezone is unknown
     */
    public static function getTimezoneOffset(string $timezoneString, ?int $atTimestamp = null): ?float
    {
        $timezoneString = trim($timezoneString);
        if ( $timezoneString === '' ) {
            return null;
        }

        try {
            $timezone = new \DateTimeZone($timezoneString);
        } catch ( \Exception $e ) {
            return null;
        }

        $moment = new \DateTime('@' . ($atTimestamp ?? time()));

        return $timezone->getOffset($moment) / 3600;
    }

    /**
     *
     * @param $timestamp
     * @return String
     */
    public static function getTimeSince($timestamp): string
    {
        $chunks = [
            [60 * 60 * 24 * 365 , 'year'],
            [60 * 60 * 24 * 30 , 'month'],
            [60 * 60 * 24 * 7, 'week'],
            [60 * 60 * 24 , 'day'],
            [60 * 60 , 'hour'],
            [60 , 'minute'],
        ];

        $today = time();  
        $since = $today - $timestamp;

        $print = '';
        if ( $since < 60 ) {
            if ( $since == 0 ) return "now";
            $print  = $since.' second';
            if ( $since > 1 ) $print =$print.'s';
            return $print;
        }

        if ( $print =='' ) {
            for ($i = 0, $j = count($chunks); $i < $j; $i++) {
                $seconds = $chunks[$i][0];
                $name = $chunks[$i][1];

                 
                if (($count = floor($since / $seconds)) != 0) {
                    break;
                }
            }

            $print = ($count == 1) ? '1 '.$name : "$count {$name}s";

            if ($i + 1 < $j) {
                $seconds2 = $chunks[$i + 1][0];
                $name2 = $chunks[$i + 1][1];

                 
                if (($count2 = floor(($since - ($seconds * $count)) / $seconds2)) != 0) {
                    $print .= ($count2 == 1) ? ', 1 '.$name2 : ", $count2 {$name2}s";
                }
            }
        }
        return $print.' ago';
    }
}