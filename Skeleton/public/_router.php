<?php
/*
 * Dispatch. Runs last, after _entry.php and the session and _prepare.php.
 *
 * TemplateLoader::content() runs the page that matches the URL:
 * /user/profile executes public/user/profile.php. Replace or wrap this call
 * to route differently -- _Global::$URL_PARAM holds the parsed request.
 */

TemplateLoader::content();
