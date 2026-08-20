<?php
/**
 * Flexagon Template Loader
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */

use _Flexagon\Libs\FileUtil;

class TemplateLoader {
	/**
	 * Resolve a relative path inside a base directory, refusing anything that
	 * escapes it.
	 *
	 * content() and entryDir() are driven straight from the request URI, so
	 * containment has to be enforced here rather than trusted to the web
	 * server's path normalisation.
	 *
	 * @param string|null $baseDir
	 * @param string|null $relativePath
	 * @return string|null absolute path, or null when it is out of bounds
	 */
	private static function resolvePath(?string $baseDir, ?string $relativePath): ?string {
		if ( empty($baseDir) || $relativePath === null || $relativePath === '' ) { return null; }
		if ( str_contains($relativePath, "\0") ) { return null; }

		$___base = realpath($baseDir);
		if ( $___base === false ) { return null; }

		$___target = realpath(FileUtil::getCurrentDirectorySeparator($___base . '/' . $relativePath));
		if ( $___target === false || !is_file($___target) ) { return null; }

		if ( !str_starts_with($___target, $___base . DIRECTORY_SEPARATOR) ) {
			if ( _Global::$DEBUG_ON ) {
				error_log(sprintf('[FLEXAGON] blocked path outside %s: %s', $___base, $relativePath));
			}
			return null;
		}

		return $___target;
	}

	/**
	 * @return string
	 */
	private static function pageExtension(): string {
		return _Global::$ENABLE_EXTENSION_PHP ? '' : '.php';
	}

	/**
	 * @return string|null
	 */
	private static function requestPath(): ?string {
		return is_object(_Global::$URL_PARAM) ? _Global::$URL_PARAM->filePath : null;
	}

	public static function show($templateName, $params=[]) : void {
		if ( empty($templateName) ) { return; }
		if ( !defined('TEMPLATE_ROOT') ) { return; }

		$___target = self::resolvePath(TEMPLATE_ROOT, trim((string)$templateName) . '.php');
		if ( $___target === null ) { return; }

		extract($params);
		include($___target);
	}

	public static function content($suffix='', $params=[]) : void {
		if ( !defined('PUBLIC_ROOT') ) { return; }

		$___path = (string)self::requestPath();
		$suffix = trim((string)$suffix);
		if ( $suffix !== '' ) { $___path .= '.' . $suffix; }

		$___target = self::resolvePath(PUBLIC_ROOT, $___path . self::pageExtension());

		if ( $___target === null ) {
			if ( $suffix === '' && defined('TEMPLATE_ROOT') ) {
				$___notFound = self::resolvePath(TEMPLATE_ROOT, 'error404.php');
				if ( $___notFound !== null ) {
					extract($params);
					include_once $___notFound;
				}
			}
			return;
		}

		extract($params);
		include_once $___target;
	}

	public static function entryDir($entryName, $params=[]) : void {
		if ( empty($entryName) ) { return; }
		if ( !defined('PUBLIC_ROOT') ) { return; }

		$___dir = dirname((string)self::requestPath());
		if ( $___dir === '.' || $___dir === DIRECTORY_SEPARATOR ) { $___dir = ''; }

		$___relative = ltrim($___dir . '/' . trim((string)$entryName), '/');
		$___target = self::resolvePath(PUBLIC_ROOT, $___relative . self::pageExtension());
		if ( $___target === null ) { return; }

		extract($params);
		include_once $___target;
	}

	public static function entryRoot($entryName, $params=[]) : void {
		if ( empty($entryName) ) { return; }
		if ( !defined('PUBLIC_ROOT') ) { return; }

		$___target = self::resolvePath(PUBLIC_ROOT, trim((string)$entryName) . self::pageExtension());
		if ( $___target === null ) { return; }

		extract($params);
		include_once $___target;
	}
}
