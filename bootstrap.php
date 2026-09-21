<?php
declare(strict_types=1);

define('SC_ROOT', __DIR__);

$scConfig = require SC_ROOT . '/config/config.php';
$localConfig = SC_ROOT . '/config/local.php';
if (is_file($localConfig)) {
    $local = require $localConfig;
    if (is_array($local)) {
        $scConfig = array_replace($scConfig, $local);
    }
}
$GLOBALS['sc_config'] = $scConfig;
date_default_timezone_set((string) ($scConfig['timezone'] ?? 'Europe/Zagreb'));

require_once SC_ROOT . '/includes/functions.php';
require_once SC_ROOT . '/includes/catalog.php';
require_once SC_ROOT . '/includes/export.php';
