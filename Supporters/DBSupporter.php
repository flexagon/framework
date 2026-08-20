<?php
/**
 * DBSupporter
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Supporters;

use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use ReflectionNamedType;

/**
 * Shared row-to-model mapping for the concrete drivers.
 *
 * getResultAsObject() leans on getAllResultAsObject(), which lives on the
 * driver rather than here -- it is declared by the RDB interface that Mysql and
 * Mssql implement.
 *
 * @method array getAllResultAsObject(object $object)
 */

abstract class DBSupporter extends ModelSupporter {
    /**
     * @param object $object
     * @param ?iterable $resultArray rows as fetched by the driver, null when the fetch failed
     * @return array
     */
    protected function getAllResultAsObjectFromArray(object $object, ?iterable $resultArray): array
    {
        if ( $resultArray === null ) { return []; }

        $setterList = $this->getSetters($object);
        $className = get_class($object);

        $callableSetters = [];
        foreach ( $setterList as $columnName => $setterName ) {
            if ( method_exists($className, $setterName) ) { $callableSetters[$columnName] = $setterName; }
        }
        $hasDecrypt = method_exists($className, 'decrypt');

        $valueObjectList = [];
        $isFirstRow = true;

        foreach ( $resultArray as $row ) {
            if ( $isFirstRow && count(array_intersect_key($row,$setterList)) <1 ) break;
            $isFirstRow = false;

            $valueObject = new $className;

            foreach ( $row as $key => $value ) {
                if ( !is_string($key) ) continue;

                $setterName = $callableSetters[strtolower($key)] ?? null;
                if ( $setterName === null ) continue;

                try {
                    $valueObject->$setterName($value);
                } catch ( \TypeError ) {
                    $valueObject->$setterName($this->resolveEnumValue($className, $setterName, $value));
                }
            }

            if ( $hasDecrypt ) { $valueObject->decrypt(); }

            $valueObjectList[] = $valueObject;
        };

        return $valueObjectList;
    }

    /**
     * A setter typed against an enum rejects the raw column value, so translate
     * the value into the matching case and let the caller retry. Anything that
     * is not an enum-typed setter, and any value with no matching case, comes
     * back untouched so the retry raises the original TypeError.
     *
     * @param string $className
     * @param string $setterName
     * @param $value
     * @return mixed
     */
    private function resolveEnumValue(string $className, string $setterName, $value): mixed
    {
        try {
            $reflectionParams = (new ReflectionClass($className))->getMethod($setterName)->getParameters();
        } catch ( ReflectionException ) {
            return $value;
        }

        if ( empty($reflectionParams) ) { return $value; }

         
        $paramType = $reflectionParams[0]->getType();
        if ( !$paramType instanceof ReflectionNamedType || $paramType->isBuiltin() ) { return $value; }

        try {
            $reflectionEnum = new ReflectionEnum($paramType->getName());
             
             
            if ( !$reflectionEnum->isBacked() ) { return $value; }

            foreach ( $reflectionEnum->getCases() as $enumCase ) {
                if ( $enumCase->getBackingValue() == $value ) { return $enumCase->getValue(); }
            }
        } catch ( ReflectionException ) {
        }

        return $value;
    }

    /**
     * @param object $object
     * @return object|null null when the query matched nothing
     */
    protected function getResultAsObject(object $object): ?object
    {
        $resultList = $this->getAllResultAsObject($object);
        return array_shift($resultList);
    }
}
