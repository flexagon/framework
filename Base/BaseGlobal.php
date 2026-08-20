<?php
/**
 * BaseGlobal
 *
 * Framework-wide configuration. Applications extend this as _Global.
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
namespace _Flexagon\Base;


use _Flexagon\Models\UrlParamsModel;

abstract class BaseGlobal {
	/**
	 * @var string
	 */
	public static string $SITE_TITLE = 'Flexagon';

	/**
	 * @var boolean
	 */
	public static bool $DEBUG_ON = false;

    public static bool $DEBUG_QUERY_BREAKPOINT = false;

	/**
	 * FLEXAGON_CONST::RUNNING_MODE_PRODUCTION
	 * FLEXAGON_CONST::RUNNING_MODE_DEV
	 * FLEXAGON_CONST::RUNNING_MODE_TEST
	 * @var string
	 */
	public static string $RUNNING_MODE = \FLEXAGON_CONST::RUNNING_MODE_PRODUCTION;

    /**
     * @var array
     */
	public static array $SITE_CONFIG = [];

    /**
     * @var bool
     */
    public static bool $ENABLE_EXTENSION_PHP = false;

    /**
     * @var UrlParamsModel|null
     */
	public static ?UrlParamsModel $URL_PARAM = null;

    /**
     * @var bool
     */
    public static bool $SESSION_AUTO_START = false;

    /**
     * @var string
     */
    public static string $SESSION_NAME = 'session_data';

    /**
     * Timeout Seconds
     * @var int
     */
    public static int $SESSION_TIMEOUT_SECONDS = 86400;

    /**
     * @var string
     */
	public static string $SESSION_ENCRYPTION_STRING = '';

    /**
     * Reject session cookies that carry no expiry.
     *
     * Cookies issued before expiry was recorded inside the payload have no
     * server-side deadline at all. Leave this off until every live session has
     * had time to be reissued, then turn it on to retire them.
     *
     * @var bool
     */
    public static bool $SESSION_REQUIRE_EXPIRY = false;

    /**
     * Reject every session issued before this Unix timestamp.
     *
     * A stateless kill switch: set it to time() to sign everyone out at once,
     * for instance after the encryption key has been rotated.
     *
     * @var int
     */
    public static int $SESSION_NOT_BEFORE = 0;

    /**
     * @var string
     * www.domain.com or .domain.com
     */
    public static String $SESSION_DOMAIN = '';

    /**
     * Secure flag on the session cookie.
     * null lets the current request decide: on for HTTPS, off for plain HTTP.
     * @var bool|null
     */
    public static ?bool $SESSION_COOKIE_SECURE = null;

    /**
     * HttpOnly flag on the session cookie. Keep this on unless client-side
     * script genuinely has to read the session.
     * @var bool
     */
    public static bool $SESSION_COOKIE_HTTPONLY = true;

    /**
     * SameSite policy: 'Lax', 'Strict' or 'None'.
     * 'None' forces the Secure flag on, since browsers reject it otherwise.
     * @var string
     */
    public static string $SESSION_COOKIE_SAMESITE = 'Lax';

	/**
	 * Any DAO extending BaseSessionDAO. Typed against the framework's own base
	 * class rather than an application class, so the framework does not depend
	 * on a particular namespace existing in the host project.
	 *
	 * @var null|BaseSessionDAO
	 */
	public static ?BaseSessionDAO $SESSION_DAO = null;

	/**
	 * Class instantiated when $SESSION_AUTO_START is on and $SESSION_DAO has
	 * not been set explicitly.
	 *
	 * @var string
	 */
	public static string $SESSION_DAO_CLASS = '\\Session\\SessionDAO';

    /**
     * @var null|object
     */
	public static ?object $SESSION_MODEL = null;

    /**
     * Class names the session cookie is allowed to instantiate.
     *
     * The cookie names the class to rebuild, so an attacker holding the
     * encryption key could otherwise pick any class in the project. Leaving
     * this empty keeps the previous behaviour of accepting any BaseModel
     * subclass; listing classes narrows it further.
     *
     * @var string[]
     */
    public static array $SESSION_MODEL_CLASSES = [];

    /**
     * @var string
     */
	public static string $TIMEZONE = 'Asia/Seoul';

    /**
     * @var string
     */
	public static string $DATA_SOURCE_ID = 'default';
    /**
     * @var array
     */
	public static array $DATA_SOURCES = [];

    /**
     * @var bool
     */
    public static bool $USE_COMPOSER = true;

    /**
     * @var bool
     */
    public static bool $USE_OUTPUT_BUFFER = true;

    /**
     * How many prepared statements each connection keeps around.
     *
     * Re-preparing the same SQL costs a server round trip every time, which
     * dominates short queries against a remote database. 0 disables the cache
     * and restores the previous behaviour of preparing on every call.
     *
     * @var int
     */
    public static int $DB_STATEMENT_CACHE_SIZE = 64;

    /**
     * Stop the request when a data source cannot be reached.
     *
     * True keeps the long-standing behaviour of halting immediately. Set it to
     * false to have connect() return false instead and let the application
     * decide; either way the reason goes to the error log, never to the
     * response body.
     *
     * @var bool
     */
    public static bool $DB_CONNECT_FAILURE_FATAL = true;

    /**
     * Warn in the error log when a SQL Server data source connects through
     * pdo_dblib with prepared statements on.
     *
     * That combination writes anything outside the server codepage into
     * NVARCHAR columns as '?' and reports no error, so the loss only shows up
     * when someone reads the column back. One line per data source per process.
     *
     * @var bool
     */
    public static bool $DB_WARN_DBLIB_UNICODE = true;

    /**
     * @var bool
     */
    public static bool $USE_AUTO_CREATED_AT_TIMESTAMP = true;

    /**
     * @var bool
     */
    public static bool $USE_AUTO_UPDATED_AT_TIMESTAMP = true;

    /**
     * @var string
     */
    public static string $CLASS_PROPERTY_ENCRYPTION_PASSPHRASE = '';

    /**
     * Optional: only set when the host project actually uses Composer.
     *
     * @var \Composer\Autoload\ClassLoader|null
     */
    public static $COMPOSER_AUTOLOADER = null;
}