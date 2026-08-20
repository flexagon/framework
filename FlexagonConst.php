<?php
/**
 * FlexagonConst
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */

final class FLEXAGON_CONST {
    /**
     * Stamped from build/build.properties at build time.
     */
    const string FLEXAGON_VERSION = '3.5.0';
    const string FLEXAGON_COPYRIGHT = 'Copyright (c) 2010-2026 Younghwan Yong';

    /**
     * Values for _Global::$RUNNING_MODE. Read by the application, not by the
     * framework itself.
     */
    const string RUNNING_MODE_AUTO = 'auto';
    const string RUNNING_MODE_PRODUCTION = 'prod';
    const string RUNNING_MODE_DEV  = 'dev';
    const string RUNNING_MODE_TEST = 'test';

    /**
     * Values for FLEXAGON_ENV::$_RUNTIME_ENV, set by Bootstrap from the SAPI.
     *
     * Strings rather than opaque numbers, matching RUNNING_MODE_* above and the
     * type the property has always been declared with.
     */
    const string RUNTIME_ENV_WEB = 'web';
    const string RUNTIME_ENV_SCRIPT = 'script';

    /**
     * Shortest passphrase accepted for session and @encrypt keys, in bytes.
     */
    const int CIPHER_PASSPHRASE_MIN_LENGTH = 10;
    const string CIPHER_METHOD = 'AES-256-CBC';

    const string ANNOTATION_TAG_CLASS_PROPERTY_ENCRYPTION = 'encrypt';
    const string ANNOTATION_TAG_CLASS_PROPERTY_DB_AUTO_TIMESTAMP = 'db_auto_timestamp';
    const string ANNOTATION_TAG_CLASS_EXCLUDE_FROM_GET_ARRAY = 'exclude_from_get_array';

    /**
     * Which statement _getQueryAndParams() should build.
     */
    const int QUERY_TYPE_INSERT = 1;
    const int QUERY_TYPE_UPDATE = 2;
    const int QUERY_TYPE_DELETE = 3;
    const int QUERY_TYPE_TRUNCATE = 4;
    const int QUERY_TYPE_SELECT = 5;
    const int QUERY_TYPE_SELECT_LIST = 6;
    const int QUERY_TYPE_SELECT_COUNT = 7;
}
