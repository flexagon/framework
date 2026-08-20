<?php
/**
 * Project side entry point for the framework's command line tool.
 *
 *   php script/flexagon.php check
 *
 * A Composer install also exposes it as vendor/bin/flexagon. This exists for
 * the other shapes: the framework packed into a phar cannot be handed to the
 * PHP binary as a script path, and a source checkout has no vendor/bin at all.
 *
 * Flexagon : PHP Development Framework
 *
 * @copyright     Copyright (c) 2010-2026 Younghwan Yong
 * @link          https://flexagon.org
 * @license       MIT License (https://opensource.org/licenses/MIT)
 */

require_once __DIR__ . '/../flexagon.php';

$flexagonCli = _FLEXAGON_ROOT . '/bin/flexagon';

if ( !file_exists($flexagonCli) ) {
    fwrite(STDERR, "flexagon: the command line tool is not in this installation.\n");
    fwrite(STDERR, "          looked in " . _FLEXAGON_ROOT . "/bin/\n");
    exit(1);
}

include $flexagonCli;
