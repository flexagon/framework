<?php
/**
 * ClassUtil
 *
 * Flexagon : PHP Development Framework
 *
 * @author        Younghwan Yong <young@phpk.org>
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Libs;


use Exception;
use ReflectionClass;
use ReflectionException;

class ClassUtil
{
    /**
     * @param object $object
     * @return string
     */
    public static function getClassName(object $object): string {
        $classNameOnly = '';
        if ( $object ) {
            $className = get_class($object);
            $className = trim((string)$className);
            if ( !empty($className) ) {
                $classNameOnly = str_replace("\\", "", substr($className, strrpos($className, "\\")));
            }
        }
        return $classNameOnly;
    }

    /**
     * Return namespace and class name.
     * @param object $object
     * @return string
     */
    public static function getClassPath(object $object): string
    {
        $className = '';
        if ( $object ) {
            try {
                $className = get_class($object);
                $className = trim((string)$className);
            } catch ( Exception $e ) {
            }
        }
        return $className;
    }

    /**
     * Reflected member names per class.
     *
     * A class does not gain members while the process runs, and these are read
     * on every annotation lookup, so the reflection is done once.
     *
     * @var array<string, array<string, string[]>>
     */
    private static array $memberCache = [];

    /**
     * Drop everything cached so far. Only needed when classes are defined at
     * runtime with the same name, which in practice means test suites.
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$memberCache = [];
    }

    /**
     * @param object $object
     * @param string $kind 'properties' or 'methods'
     * @return string[]
     */
    private static function getMemberNames(object $object, string $kind): array
    {
        $className = self::getClassPath($object);

        if ( isset(self::$memberCache[$className][$kind]) ) {
            return self::$memberCache[$className][$kind];
        }

        $names = [];

        try {
            $reflectionClass = new ReflectionClass($className);
            $members = $kind === 'methods'
                ? $reflectionClass->getMethods()
                : $reflectionClass->getProperties();

            foreach ($members as $member) {
                $names[] = $member->getName();
            }
        } catch ( Exception|ReflectionException $e ) {
        }

        self::$memberCache[$className][$kind] = $names;

        return $names;
    }

    /**
     * @param object $object
     * @return string[]
     */
    public static function getProperties(object $object): array
    {
        return self::getMemberNames($object, 'properties');
    }

    /**
     * @param object $object
     * @return string[]
     */
    public static function getMethods(object $object): array
    {
        return self::getMemberNames($object, 'methods');
    }

    /**
     * @return string|null
     */
    public static function getCallingClassName(): ?string {
        $callingClass = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);

        $callingClassName = null;
        if ( count($callingClass) >1 ) {
            $callingClassName = $callingClass[1]['class'];
        }

        return $callingClassName;
    }
}