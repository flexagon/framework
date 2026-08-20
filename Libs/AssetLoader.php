<?php
/**
 * AssetLoader
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */

namespace _Flexagon\Libs;

/**
 * Collects the CSS and JS a page needs, then prints them where the template
 * asks for them.
 *
 * One print method per kind of tag; where each one goes is the template's
 * decision:
 *
 *   <head>   printPreloads() · printStyles()
 *   </body>  printScripts()
 *
 * Preload hints have their own bucket because they belong in <head> even when
 * the script they point at is printed at the end of the document. A preload
 * only pays for itself when it closes a gap between discovery and use; emitted
 * next to the tag it refers to, it does nothing the browser's preload scanner
 * was not already doing.
 */
class AssetLoader
{
	private static string $version = '';
	private static string $assetsRootPath = '/assets/';
	private static array $preloads = [];
	private static array $styles = [];
	private static array $scripts = [];

	public static function setAssetRootPath(string $assetRootPath): void
	{
		self::$assetsRootPath = $assetRootPath;
	}

	public static function setVersion(string $version): void
	{
		self::$version = $version;
	}

	/**
	 * @param string $url
	 * @return string
	 */
	public static function getAutoVersion(string $url): string {
		if ( !empty(self::$version)) {
			return $url . (parse_url($url, PHP_URL_QUERY) ? "&" : "?") . "_=" . self::$version;
		}
		return $url;
	}

	/**
	 * Does the asset already point somewhere absolute?
	 *
	 * @param string $asset
	 * @return bool
	 */
	private static function isAbsoluteUrl(string $asset): bool
	{
		return preg_match('/^(https?:)?\/\//', $asset) === 1;
	}

	/**
	 * Turn an asset name into the URL it will be served from.
	 *
	 * @param string $asset
	 * @param string $dirPath sub directory under the assets root
	 * @return string
	 */
	private static function resolveAssetUrl(string $asset, string $dirPath): string
	{
		if ( self::isAbsoluteUrl($asset) ) {
			return self::getAutoVersion($asset);
		}

		return self::getAutoVersion(self::$assetsRootPath . $dirPath . '/' . $asset);
	}

	/**
	 * @param string $url
	 * @param string $as 'style' or 'script'
	 * @return void
	 */
	private static function addPreload(string $url, string $as): void
	{
		self::$preloads[] = '<link rel="preload" href="' . $url . '" as="' . $as . '">';
	}

	/**
	 * @param string $assetCss
	 * @param bool $preload
	 * @param string $dirPath
	 * @return void
	 */
	public static function setCss(string $assetCss, bool $preload = true, string $dirPath = 'css'): void {
		$url = self::resolveAssetUrl($assetCss, $dirPath);

		if ( $preload && !self::isAbsoluteUrl($assetCss) ) {
			self::addPreload($url, 'style');
		}

		self::$styles[] = '<link rel="stylesheet" href="' . $url . '">';
	}

	/**
	 * @param string $assetJs
	 * @param bool $isModule
	 * @param bool $preload
	 * @param string $dirPath
	 * @return void
	 */
	public static function setJs(string $assetJs, bool $isModule = false, bool $preload = false, string $dirPath = 'js'): void {
		$url = self::resolveAssetUrl($assetJs, $dirPath);

		if ( $preload && !self::isAbsoluteUrl($assetJs) ) {
			self::addPreload($url, 'script');
		}

		self::$scripts[] = '<script' . ($isModule ? ' type="module"' : '') . ' src="' . $url . '"></script>';
	}

	/**
	 * Remove a script registered earlier, along with its preload hint.
	 *
	 * Compares the resolved src, so it removes exactly the entry setJs() added.
	 * A plain str_contains() over the whole tag meant unsetJs('app.js') also
	 * dropped 'vendor/app.js.map.js' and 'admin/app.js'.
	 *
	 * @param string $assetJs the same value passed to setJs()
	 * @param string $dirPath the same value passed to setJs()
	 * @return void
	 */
	public static function unsetJs(string $assetJs, string $dirPath = 'js'): void {
		$assetJs = trim($assetJs);
		if ( $assetJs === '' ) {
			return;
		}

		$target = self::isAbsoluteUrl($assetJs)
			? $assetJs
			: self::$assetsRootPath . $dirPath . '/' . $assetJs;

		$matchesTarget = function (string $tag) use ($target): bool {
			if ( !preg_match('/\s(?:src|href)="([^"]*)"/i', $tag, $matches) ) {
				return false;
			}

			 
			$url = preg_replace('/[?&]_=[^&]*/', '', $matches[1]);

			return $url === $target;
		};

		self::$scripts = array_values(array_filter(self::$scripts, fn($tag) => !$matchesTarget($tag)));
		self::$preloads = array_values(array_filter(self::$preloads, fn($tag) => !$matchesTarget($tag)));
	}

	/**
	 * @param array $tags
	 * @param string $indent
	 * @return void
	 */
	private static function printTags(array $tags, string $indent): void
	{
		if ( empty($tags) ) {
			return;
		}

		echo $indent . implode(PHP_EOL . $indent, $tags) . PHP_EOL;
	}

	/**
	 * Preload hints.
	 *
	 * Belongs in <head>. The tags these point at are usually printed at the end
	 * of <body>, and that gap is the whole reason a preload is worth emitting.
	 *
	 * @param string $indent
	 * @return void
	 */
	public static function printPreloads(string $indent = ''): void {
		self::printTags(self::$preloads, $indent);
	}

	/**
	 * @param string $indent
	 * @return void
	 */
	public static function printStyles(string $indent = ''): void {
		self::printTags(self::$styles, $indent);
	}

	/**
	 * @param string $indent
	 * @return void
	 */
	public static function printScripts(string $indent = ''): void {
		self::printTags(self::$scripts, $indent);
	}

	/**
	 * @param string $assetImage
	 * @param string $dirPath
	 * @return string
	 */
	public static function getImageUrl(string $assetImage, string $dirPath = 'images'): string {
		return self::resolveAssetUrl($assetImage, $dirPath);
	}

	/**
	 * @param string $imagePath
	 * @param int|null $width
	 * @param int|null $height
	 * @param string $alt alt text; escaped, so it may come from user data
	 * @param string $dirPath
	 * @return string
	 */
	public static function getImageHtml(string $imagePath, ?int $width = null, ?int $height = null, string $alt = '' , string $dirPath = 'images'): string {
		$imageUrl = self::getImageUrl($imagePath, $dirPath);

		$imgHtml = '<img src="' . self::escapeAttribute($imageUrl) . '" alt="' . self::escapeAttribute($alt) . '"';
		if ($width) { $imgHtml .= ' width="' . (int)$width . '"'; }
		if ($height) { $imgHtml .= ' height="' . (int)$height . '"'; }
		$imgHtml .= '>';
		return $imgHtml;
	}

	/**
	 * @param string $value
	 * @return string
	 */
	private static function escapeAttribute(string $value): string
	{
		return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	/**
	 * @param string $asset path under the assets root, or an absolute URL
	 * @return string
	 */
	public static function getAssetUrl(string $asset ): string {
		if ( self::isAbsoluteUrl($asset) ) {
			return self::getAutoVersion($asset);
		}

		return self::getAutoVersion(self::$assetsRootPath . $asset);
	}
}
