<?php
/**
 * ModelSupporter
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Supporters;

abstract class ModelSupporter {
	public const string ACCESSOR_GET = 'get';
	public const string ACCESSOR_SET = 'set';

	private const array NON_COLUMN_ACCESSORS = [
		'getArray'                   => true,
		'getJson'                    => true,
		'setByArray'                 => true,
		'getCreatedAtDatetimeString' => true,
		'getUpdatedAtDatetimeString' => true,
	];

	/** @var array<string,array<string,array<string,string>>> class name => 'get'|'set' => column name => method name */
	private static array $accessorCache = [];

	/**
	 * Column name => getter name, for every column-shaped getter on the model.
	 *
	 * @param object|string $obj model instance or class name
	 */
	protected function getGetters(object|string $obj): array
    { return $this->getGSetters($obj, self::ACCESSOR_GET); }

	/**
	 * Column name => setter name, for every column-shaped setter on the model.
	 *
	 * @param object|string $obj model instance or class name
	 */
	protected function getSetters(object|string $obj): array
    { return $this->getGSetters($obj, self::ACCESSOR_SET); }

	/**
	 * @param object|string $obj model instance or class name
	 * @param string $gs self::ACCESSOR_GET or self::ACCESSOR_SET; anything else yields an empty map
	 */
	protected function getGSetters(object|string $obj, string $gs): array
    {
		if ( $gs !== self::ACCESSOR_SET && $gs !== self::ACCESSOR_GET ) return [];

		$className = is_object($obj) ? get_class($obj) : $obj;
		if ( isset(self::$accessorCache[$className][$gs]) ) return self::$accessorCache[$className][$gs];

		$gsList = [];

		$pattern = sprintf('/^%s([A-Z0-9].*)/',$gs);

		$methodList = get_class_methods($className);
		foreach ( $methodList as $method ) {
			if ( isset(self::NON_COLUMN_ACCESSORS[$method]) ) continue;

			if ( preg_match($pattern,$method,$matches) ) {
				$columnName = strtolower(implode('_',preg_split('#([A-Z][^A-Z]*)#', $matches[1], 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY)));
				$gsList[$columnName] = $matches[0];
			}
		};

		self::$accessorCache[$className][$gs] = $gsList;
		return $gsList;
	}

	/**
	 * The map only depends on the class, so it is held for the life of the
	 * request. Drop it when a test defines classes on the fly.
	 */
	public static function clearAccessorCache(): void
	{ self::$accessorCache = []; }
}
