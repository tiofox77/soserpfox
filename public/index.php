<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// O restauro de emergência corre ANTES do Laravel (e antes do modo de
// manutenção): é para quando um deploy o deixou sem arrancar.
if (str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/__restauro/')
    && is_file($restauro = __DIR__.'/../bootstrap/restauro-de-emergencia.php')) {
    require $restauro;

    return;
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
