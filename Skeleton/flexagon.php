<?php
/**
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
ini_set('error_reporting', E_ALL);
ini_set('display_errors','Off');

if ( !defined('PROJECT_ROOT') ) define('PROJECT_ROOT', __DIR__);
if ( !defined('PUBLIC_ROOT') ) define('PUBLIC_ROOT', dirname($_SERVER['SCRIPT_FILENAME']));

/*
 * Bring the Composer autoloader up before the framework so that its "files"
 * entries win the race against Bootstrap's own eager includes.
 */
if ( is_file(PROJECT_ROOT . '/vendor/autoload.php') ) {
    require_once PROJECT_ROOT . '/vendor/autoload.php';
}

/*
 * Locate the framework: a Composer install first, then a built phar, then a
 * plain source checkout.
 */
if ( is_file(PROJECT_ROOT . '/vendor/flexagon/framework/Bootstrap.php') ) {
    require_once PROJECT_ROOT . '/vendor/flexagon/framework/Bootstrap.php';
} elseif ( is_file(PROJECT_ROOT . '/application/_Flexagon.phar') ) {
    require_once PROJECT_ROOT . '/application/_Flexagon.phar';
} elseif ( is_file(PROJECT_ROOT . '/application/_Flexagon.phar/Bootstrap.php') ) {
    require_once PROJECT_ROOT . '/application/_Flexagon.phar/Bootstrap.php';
} else {
    throw new RuntimeException(
        '[FLEXAGON] Framework not found under ' . PROJECT_ROOT . '.'
        . ' Run "composer install", or build the phar with "ant build".'
    );
}
