<?php
/**
 * PhpDocUtil
 *
 * Flexagon : PHP Development Framework
 *
 * @author        Younghwan Yong <young@phpk.org>
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Libs;

use ReflectionClass;
use ReflectionException;

class PhpDocUtil
{
    /**
     * Doc blocks per class name.
     *
     * Annotations are part of the class definition and cannot change while the
     * process runs, so the reflection work is done once per class. Without this
     * a single findProperties() call reflected the whole class once per
     * property, and DAO result mapping paid that cost for every row.
     *
     * @var array<string, array>
     */
    private static array $blockCache = [];

    /**
     * Parsed tags per class name.
     *
     * @var array<string, array>
     */
    private static array $tagCache = [];

    /**
     * Attributes per class name, already shaped like doc block tags.
     *
     * @var array<string, array>
     */
    private static array $attributeCache = [];

    /**
     * findProperties()/findMethods() results per class name and query.
     *
     * @var array<string, array>
     */
    private static array $lookupCache = [];

    /**
     * Drop everything cached so far.
     *
     * Only needed when classes are defined at runtime with the same name, which
     * in practice means test suites.
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$blockCache = [];
        self::$tagCache = [];
        self::$attributeCache = [];
        self::$lookupCache = [];
    }

    /**
     * @param object $object
     * @return array
     */
    public static function getAllBlocks(object $object): array
    {
        $className = ClassUtil::getClassPath($object);
        if ( isset(self::$blockCache[$className]) ) {
            return self::$blockCache[$className];
        }

        $propertyArray = [];
        $methodArray = [];

        try {
            $reflectionClass = new ReflectionClass($className);
            foreach ($reflectionClass->getProperties() as $property) {
                $propertyArray[$property->getName()] = $property->getDocComment();
            }

            foreach ($reflectionClass->getMethods() as $method) {
                $methodArray[$method->getName()] = $method->getDocComment();
            }
        } catch ( ReflectionException $e ) {
        }

        self::$blockCache[$className] = ['properties'=>$propertyArray, 'methods'=>$methodArray];
        return self::$blockCache[$className];
    }

    /**
     * @param object $object
     * @param string $propertyName
     * @return string
     */
    public static function getPropertyDocBlock(object $object, string $propertyName ): string
    {
        $docBlock = '';

        $blockArray = self::getAllBlocks($object);
        $docBlocks = $blockArray['properties'];
        if (!empty($docBlocks)) {
            if (isset($docBlocks[$propertyName])) {
                $docBlock = $docBlocks[$propertyName];
            }
        }

        return $docBlock;
    }

    /**
     * @param object $object
     * @param string $methodName
     * @return string
     */
    public static function getMethodDocBlock(object $object, string $methodName ): string
    {
        $docBlock = '';

        $blockArray = self::getAllBlocks($object);
        $docBlocks = $blockArray['methods'];
        if (!empty($docBlocks)) {
            if (isset($docBlocks[$methodName])) {
                $docBlock = $docBlocks[$methodName];
            }
        }

        return $docBlock;
    }

    /**
     * Attributes read as though they were doc block tags.
     *
     * #[DbAutoTimestamp('insert', 'update')] comes back as
     * ['DbAutoTimestamp' => ['insert', 'update']]. The tag name is the
     * attribute's short class name, which normalizeTagName() already treats as
     * the same thing as db_auto_timestamp, so every lookup below works on
     * either spelling without knowing which one was used.
     *
     * @param object $object
     * @return array
     */
    public static function getAllAttributes(object $object): array
    {
        $className = ClassUtil::getClassPath($object);
        if ( isset(self::$attributeCache[$className]) ) {
            return self::$attributeCache[$className];
        }

        $propertyArray = [];
        $methodArray = [];

        try {
            $reflectionClass = new ReflectionClass($className);
            foreach ( $reflectionClass->getProperties() as $property ) {
                $tags = self::readAttributes($property->getAttributes());
                if ( !empty($tags) ) { $propertyArray[$property->getName()] = $tags; }
            }
            foreach ( $reflectionClass->getMethods() as $method ) {
                $tags = self::readAttributes($method->getAttributes());
                if ( !empty($tags) ) { $methodArray[$method->getName()] = $tags; }
            }
        } catch ( ReflectionException ) {
        }

        self::$attributeCache[$className] = ['properties' => $propertyArray, 'methods' => $methodArray];
        return self::$attributeCache[$className];
    }

    /**
     * @param \ReflectionAttribute[] $attributes
     * @return array tag name => list of values
     */
    private static function readAttributes(array $attributes): array
    {
        $tags = [];

        foreach ( $attributes as $attribute ) {
            $shortName = $attribute->getName();
            $separator = strrpos($shortName, '\\');
            if ( $separator !== false ) { $shortName = substr($shortName, $separator + 1); }

            $values = [];
            foreach ( $attribute->getArguments() as $argument ) {
                foreach ( (array)$argument as $item ) {
                    if ( is_scalar($item) ) {
                        foreach ( explode('|', (string)$item) as $piece ) { $values[] = trim($piece); }
                    }
                }
            }

            $tags[$shortName] = $values;
        }

        return $tags;
    }

    /**
     * @param object $object
     * @param string $propertyName
     * @return array
     */
    public static function getPropertyTags(object $object, string $propertyName): array
    {
        $className = ClassUtil::getClassPath($object);
        if ( isset(self::$tagCache[$className]['properties'][$propertyName]) ) {
            return self::$tagCache[$className]['properties'][$propertyName];
        }

        $tags = self::parseTags(self::getPropertyDocBlock($object, $propertyName ));
        $tags = array_merge($tags, self::getAllAttributes($object)['properties'][$propertyName] ?? []);

        self::$tagCache[$className]['properties'][$propertyName] = $tags;
        return $tags;
    }

    public static function getMethodTags(object $object, string $methodName): array
    {
        $className = ClassUtil::getClassPath($object);
        if ( isset(self::$tagCache[$className]['methods'][$methodName]) ) {
            return self::$tagCache[$className]['methods'][$methodName];
        }

        $tags = self::parseTags(self::getMethodDocBlock($object, $methodName ));
        $tags = array_merge($tags, self::getAllAttributes($object)['methods'][$methodName] ?? []);

        self::$tagCache[$className]['methods'][$methodName] = $tags;
        return $tags;
    }

    /**
     * Pull "@tag value" pairs out of a doc block.
     *
     * @param string $docBlock
     * @return array tag name as written => list of values
     */
    private static function parseTags(string $docBlock): array
    {
        $tagsArray = [];

        preg_match_all("/\@([a-zA-Z-_]+)([^\n^\t.]*)/", $docBlock, $matches);
        if ( count($matches) == 3 ) {
            for ( $i=0; $i < count($matches[0]); $i++ ) {
                $rawValue = preg_replace('/\*+\/\s*$/', '', $matches[2][$i]);
                $tagsArray[$matches[1][$i]] = array_map('trim', explode('|', trim((string)$rawValue)));
            }
        }

        return $tagsArray;
    }

    /**
     * Reduce a tag name to its comparable form: lower case, with word
     * separators removed.
     *
     * The canonical spelling is snake_case, but capitalisation and separators
     * are not significant, so db_auto_timestamp, DbAutoTimestamp,
     * DB_AUTO_TIMESTAMP and dbautotimestamp are one and the same tag.
     *
     * @param string $tag
     * @return string
     */
    private static function normalizeTagName(string $tag): string
    {
        return strtolower(str_replace(['_', '-'], '', trim($tag)));
    }

    /**
     * Locate a tag however it happens to be spelled in the doc block.
     *
     * Returns the key as it was actually written, keeping the arrays returned
     * by getPropertyTags()/getMethodTags() untouched for callers that read them
     * directly.
     *
     * @param array $tagsArray
     * @param string $tag
     * @return string|null
     */
    private static function matchTagKey(array $tagsArray, string $tag): ?string
    {
        if ( array_key_exists($tag, $tagsArray) ) {
            return $tag;
        }

        $normalizedTag = self::normalizeTagName($tag);
        foreach ( array_keys($tagsArray) as $key ) {
            if ( self::normalizeTagName((string)$key) === $normalizedTag ) {
                return (string)$key;
            }
        }

        return null;
    }

    /**
     * @param array $values
     * @param string $valueName
     * @return bool
     */
    private static function containsTagValue(array $values, string $valueName): bool
    {
        foreach ( $values as $value ) {
            if ( strcasecmp(trim((string)$value), trim($valueName)) === 0 ) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param object $object
     * @param string $propertyName
     * @param string $tag
     * @return array
     */
    public static function getPropertyTagValues(object $object, string $propertyName, string $tag ): array {
        $tagsArray = self::getPropertyTags($object, $propertyName );

        $matchedKey = self::matchTagKey($tagsArray, $tag);
        if ( !is_null($matchedKey) ) {
            return $tagsArray[$matchedKey];
        }
        return [];
    }

    public static function getMethodTagValues(object $object, string $methodName, string $tag ): array {
        $tagsArray = self::getMethodTags($object, $methodName );

        $matchedKey = self::matchTagKey($tagsArray, $tag);
        if ( !is_null($matchedKey) ) {
            return $tagsArray[$matchedKey];
        }
        return [];
    }

    /**
     * @param object $object
     * @param string $propertyName
     * @param string $tag
     * @param string $valueName
     * @return bool
     */
    public static function existsPropertyTagAndValue(object $object, string $propertyName, string $tag ,string $valueName ): bool {
        return self::containsTagValue(self::getPropertyTagValues($object, $propertyName, $tag ), $valueName);
    }

    public static function existsMethodTagAndValue(object $object, string $methodName, string $tag ,string $valueName ): bool {
        return self::containsTagValue(self::getMethodTagValues($object, $methodName, $tag ), $valueName);
    }

    /**
     * @param string $tag
     * @param string $propertyName
     * @param object $object
     * @return bool
     */
    public static function existsPropertyTag(object $object, string $propertyName, string $tag ): bool {
        return !is_null(self::matchTagKey(self::getPropertyTags($object, $propertyName ), $tag));
    }

    public static function existsMethodTag(object $object, string $methodName, string $tag ): bool {
        return !is_null(self::matchTagKey(self::getMethodTags($object, $methodName ), $tag));
    }

    /**
     * @param object $object
     * @param string $tag
     * @param string $valueName
     * @return string[]
     */
    public static function findProperties(object $object, string $tag, string $valueName = '' ): array {
        $className = ClassUtil::getClassPath($object);
        $cacheKey = 'p|' . $tag . '|' . $valueName;
        if ( isset(self::$lookupCache[$className][$cacheKey]) ) {
            return self::$lookupCache[$className][$cacheKey];
        }

        $propertyNames = [];

        $properties = ClassUtil::getProperties($object);
        foreach ( $properties as $property ) {
            $result = null;
            if ( $valueName == '' ) {
                $result = self::existsPropertyTag($object, $property, $tag );
            } else {
                $result = self::existsPropertyTagAndValue($object, $property, $tag, $valueName );
            }
            if ( $result ) {
                $propertyNames[] = $property;
            }
        }

        self::$lookupCache[$className][$cacheKey] = $propertyNames;
        return $propertyNames;
    }

    /**
     * @param object $object
     * @param string $tag
     * @param string $valueName
     * @return string[]
     */
    public static function findMethods(object $object, string $tag, string $valueName = '' ): array {
        $className = ClassUtil::getClassPath($object);
        $cacheKey = 'm|' . $tag . '|' . $valueName;
        if ( isset(self::$lookupCache[$className][$cacheKey]) ) {
            return self::$lookupCache[$className][$cacheKey];
        }

        $methodNames = [];

        $methods = ClassUtil::getMethods($object);
        foreach ( $methods as $method ) {
            $result = null;
            if ( $valueName == '' ) {
                $result = self::existsMethodTag($object, $method, $tag );
            } else {
                $result = self::existsMethodTagAndValue($object, $method, $tag, $valueName );
            }
            if ( $result ) {
                $methodNames[] = $method;
            }
        }

        self::$lookupCache[$className][$cacheKey] = $methodNames;
        return $methodNames;
    }
}