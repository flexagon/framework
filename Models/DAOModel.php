<?php
/**
 * DAOModel
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Models;

class DAOModel {
	/**
	 * Override in a DAO that has to read a specific data source. Leaving it
	 * alone follows _Global::$DATA_SOURCE_ID.
	 */
	const DATA_SOURCE_ID = 'default';

	protected DataSourceModel $_DATA_SOURCE_MODEL;

	/**
	 * @throws \RuntimeException when the DAO names a data source that has not
	 *         been configured. Leaving the typed property unset instead only
	 *         surfaced later as "must not be accessed before initialization",
	 *         which says nothing about the actual mistake.
	 */
	function __construct() {
		$sourceId = $this->_resolveDataSourceId();

		if ( !array_key_exists($sourceId, \_Global::$DATA_SOURCES) ) {
			throw new \RuntimeException(sprintf(
				'[FLEXAGON] %s uses data source "%s", which is not configured in _Global::$DATA_SOURCES. Configured: %s',
				static::class,
				$sourceId,
				empty(\_Global::$DATA_SOURCES) ? '(none)' : implode(', ', array_keys(\_Global::$DATA_SOURCES))
			));
		}

		$this->_DATA_SOURCE_MODEL = \_Global::$DATA_SOURCES[$sourceId];
	}

	/** @var array<string,bool> class name => does it declare DATA_SOURCE_ID itself */
	private static array $declaresDataSourceId = [];

	/**
	 * A DAO that declares its own DATA_SOURCE_ID means it, so that wins.
	 * Otherwise the application-wide default applies.
	 *
	 * Asked by declaration rather than by value: a DAO pinning itself to
	 * 'default' on purpose reads the same as one that never said anything, and
	 * only the former should ignore the global.
	 */

	protected function _resolveDataSourceId(): string
	{
		$className = static::class;

		if ( !array_key_exists($className, self::$declaresDataSourceId) ) {
			try {
				$declaringClass = (new \ReflectionClassConstant($className, 'DATA_SOURCE_ID'))->getDeclaringClass()->getName();
			} catch ( \ReflectionException ) {
				$declaringClass = self::class;
			}
			self::$declaresDataSourceId[$className] = $declaringClass !== self::class;
		}

		if ( self::$declaresDataSourceId[$className] ) {
			return static::DATA_SOURCE_ID;
		}
		return \_Global::$DATA_SOURCE_ID;
	}
}
