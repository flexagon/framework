<?php
/**
 * Common page header, rendered via TemplateLoader::show('head', [...]).
 * Any array passed as the second argument is extract()ed into this scope.
 */

use _Flexagon\Libs\AssetLoader;

?><!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? _Global::$SITE_TITLE, ENT_QUOTES) ?></title>
<?php AssetLoader::printPreloads('    ') ?>
<?php AssetLoader::printStyles('    ') ?>
</head>
<body>
<div class="container">
