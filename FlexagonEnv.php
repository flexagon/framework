<?php
/**
 * FlexagonEnv
 *
 * Runtime state settled during bootstrap.
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */
final class FLEXAGON_ENV {
    public static ?string $_RUNTIME_ENV = null;
	public static string $_CONFIG_FILE_DIR = APPLICATION_ROOT;
}