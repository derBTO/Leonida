<?php

/*
 * Leonida – Startdatei im Webverzeichnis des Hetzner-Webhostings.
 * Wird von scripts/deploy.sh erzeugt. Der Code liegt außerhalb des Webverzeichnisses
 * unter __LEONIDA_APP__ (Symlink auf die aktive Release).
 */

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$base = '__LEONIDA_APP__';

if (file_exists($maintenance = $base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $base.'/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once $base.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);
$app->handleRequest(Request::capture());
