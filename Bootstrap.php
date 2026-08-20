<?php
/**
 * Bootstrap File
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
if ( !defined('PROJECT_ROOT') ) {
    $___flexagonDir = strlen(Phar::running(false)) > 0 ? dirname(Phar::running(false)) : __DIR__;
    $___flexagonProjectRoot = null;
    for ( $___flexagonDepth = 0; $___flexagonDepth < 6; $___flexagonDepth++ ) {
        if ( is_file($___flexagonDir . '/application/_Global.php') ) { $___flexagonProjectRoot = $___flexagonDir; break; }
        $___flexagonParent = dirname($___flexagonDir);
        if ( $___flexagonParent === $___flexagonDir ) { break; }
        $___flexagonDir = $___flexagonParent;
    }
    define('PROJECT_ROOT', $___flexagonProjectRoot ?? dirname(__FILE__, 3));
    unset($___flexagonDir, $___flexagonProjectRoot, $___flexagonDepth, $___flexagonParent);
}
if ( !defined( 'PUBLIC_ROOT') ) { define('PUBLIC_ROOT',dirname($_SERVER["SCRIPT_FILENAME"])); }
const APPLICATION_ROOT = PROJECT_ROOT . '/application';
if ( !defined('_FLEXAGON_ROOT') ) { if ( strlen(Phar::running()) > 0 ) { define('_FLEXAGON_ROOT',Phar::running()); } else { define('_FLEXAGON_ROOT',__DIR__); }}
const LIBS_ROOT = PROJECT_ROOT . '/libs';

if (PHP_MAJOR_VERSION >= 7) {
    set_error_handler(function ($errno, $errstr) {
        return str_starts_with($errstr, 'Declaration of');
    }, E_WARNING);
}

include_once _FLEXAGON_ROOT . '/FlexagonConst.php';
include_once _FLEXAGON_ROOT . '/FlexagonEnv.php';

ini_set('include_path', get_include_path() . PATH_SEPARATOR . PROJECT_ROOT . PATH_SEPARATOR . APPLICATION_ROOT . PATH_SEPARATOR . LIBS_ROOT);
spl_autoload_register(function ($className) {
    $className = preg_replace(/** @lang text */ '/^application\\\/', '', $className);
    $___tmpPathPos = strrpos($className, "_Flexagon");
    if ($___tmpPathPos > 0) {
        $className = substr($className, $___tmpPathPos);
    }

    if (str_starts_with($className, "_Flexagon\\")) {
		include_once _FLEXAGON_ROOT . str_replace('\\', DIRECTORY_SEPARATOR, substr($className, strlen('_Flexagon')) . '.php');
    } else {
		if ( !@include_once APPLICATION_ROOT. DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php' ) {
            include_once str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';
        };
    }
});

$_PARAMS = [];

if (PHP_SAPI === 'cli') {
    FLEXAGON_ENV::$_RUNTIME_ENV = FLEXAGON_CONST::RUNTIME_ENV_SCRIPT;
    $argvCount = isset($_SERVER['argv']) && is_array($_SERVER['argv']) ? count($_SERVER['argv']) : 0;
    for ($tmpCount = 1; $tmpCount < $argvCount; $tmpCount++) {
        $tmpPos = strpos($_SERVER['argv'][$tmpCount], '=');
        if ($tmpPos > 0) {
            $tmpKey = substr($_SERVER['argv'][$tmpCount], 0, $tmpPos);
            $tmpValue = substr($_SERVER['argv'][$tmpCount], $tmpPos + 1);
            $_PARAMS[$tmpKey] = $tmpValue;
            $_REQUEST[$tmpKey] = $tmpValue;
        } else {
            if (isset($argv)) {
                $_PARAMS[] = $argv[$tmpCount];
            }
        }
    }

	if ( !empty($_PARAMS['runtimeStage']) ) {
		FLEXAGON_ENV::$_CONFIG_FILE_DIR = APPLICATION_ROOT.'/_Config/'.$_PARAMS['runtimeStage'];
	}
} else {
    FLEXAGON_ENV::$_RUNTIME_ENV = FLEXAGON_CONST::RUNTIME_ENV_WEB;
};

if (!defined('TEMPLATE_ROOT')) {
	if (defined('PUBLIC_ROOT')) define('TEMPLATE_ROOT', PUBLIC_ROOT . '/_Template');
}

if (!defined('ASSETS_ROOT')) {
	if (defined('PUBLIC_ROOT')) define('ASSETS_URL', '/assets');
}

if ( is_file(APPLICATION_ROOT . '/_Const.php') ) { include_once APPLICATION_ROOT . '/_Const.php'; }
if ( is_file(APPLICATION_ROOT . '/_Global.php') ) { include_once APPLICATION_ROOT . '/_Global.php'; }
if ( is_file(FLEXAGON_ENV::$_CONFIG_FILE_DIR . '/_Config.php') ) { include_once FLEXAGON_ENV::$_CONFIG_FILE_DIR . '/_Config.php'; }

if ( !class_exists('_Global', false) ) {
    class_alias(\_Flexagon\Base\BaseGlobal::class, '_Global');
}
include_once _FLEXAGON_ROOT . '/Libs/_Util.php';
include_once _FLEXAGON_ROOT . '/Libs/HttpUtil.php';
include_once _FLEXAGON_ROOT . '/Libs/ClassUtil.php';
include_once _FLEXAGON_ROOT . '/Libs/AssetLoader.php';
include_once _FLEXAGON_ROOT . '/Libs/TemplateLoader.php';
if (_Global::$USE_COMPOSER) {
    $___composerAutoload = PROJECT_ROOT . '/vendor/autoload.php';
    if ( is_file($___composerAutoload) ) {
        $___composerLoader = include_once $___composerAutoload;
    } else {
        $___composerLoader = @include_once 'vendor/autoload.php';
    }
    if ( $___composerLoader instanceof \Composer\Autoload\ClassLoader ) {
        _Global::$COMPOSER_AUTOLOADER = $___composerLoader;
    }
    unset($___composerLoader);

    if ( is_null(_Global::$COMPOSER_AUTOLOADER) && class_exists('\Composer\Autoload\ClassLoader', false) ) {
        $___composerLoaders = \Composer\Autoload\ClassLoader::getRegisteredLoaders();
        if ( !empty($___composerLoaders) ) { _Global::$COMPOSER_AUTOLOADER = reset($___composerLoaders); }
        unset($___composerLoaders);
    }
    unset($___composerAutoload);
}

if (isset(_Global::$TIMEZONE)) date_default_timezone_set(_Global::$TIMEZONE);

if (FLEXAGON_ENV::$_RUNTIME_ENV === FLEXAGON_CONST::RUNTIME_ENV_WEB) {
    if ( isset(_Global::$USE_OUTPUT_BUFFER) && _Global::$USE_OUTPUT_BUFFER ) {
        ob_start();
    }
    _Global::$URL_PARAM =  _Flexagon\Libs\HttpUtil::getParamsFromUrl();

    if (isset(_Global::$SITE_CONFIG['web']['header'])) {
        for ($tmpCount = 0; $tmpCount < count(_Global::$SITE_CONFIG['web']['header']); $tmpCount++)
            header(_Global::$SITE_CONFIG['web']['header'][$tmpCount]);
    }

    @include_once PUBLIC_ROOT . '/_entry.php';

    if (_Global::$SESSION_AUTO_START) {
        if (is_null(_Global::$SESSION_DAO)) {
            $___sessionDaoClass = _Global::$SESSION_DAO_CLASS;
            if (!empty($___sessionDaoClass) && class_exists($___sessionDaoClass)) {
                _Global::$SESSION_DAO = new $___sessionDaoClass();
            }
            unset($___sessionDaoClass);
        }
        try {
            if (!is_null(_Global::$SESSION_DAO)) {
                _Global::$SESSION_MODEL = _Global::$SESSION_DAO->getSessionModel();
            }
        } catch (Exception $e) {
        }

        if (is_object(_Global::$SESSION_MODEL)) {
            $tmpGlobalClassName = strtoupper(preg_replace("/(?<=[a-zA-Z])(?=[A-Z])/", "_", _Flexagon\Libs\ClassUtil::getClassName(_Global::$SESSION_MODEL)));
            $tmpRf = new ReflectionClass('_Global');
            $tmpRf->setStaticPropertyValue($tmpGlobalClassName, _Global::$SESSION_MODEL);
            $tmpGlobalClassName = null;
            $tmpRf = null;
        }
    }

    @include_once PUBLIC_ROOT . '/_prepare.php';
     
	include_once PUBLIC_ROOT . '/_router.php';

    if ( isset(_Global::$USE_OUTPUT_BUFFER) && _Global::$USE_OUTPUT_BUFFER ) {
        if ( ob_get_level() ) { ob_end_flush(); }
    }
} elseif ( FLEXAGON_ENV::$_RUNTIME_ENV === FLEXAGON_CONST::RUNTIME_ENV_SCRIPT ) {
}