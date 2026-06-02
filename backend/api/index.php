<?php

$storagePath = '/tmp/laravel-storage';

foreach ([
    $storagePath,
    $storagePath.'/app/public',
    $storagePath.'/framework/cache',
    $storagePath.'/framework/sessions',
    $storagePath.'/framework/views',
    $storagePath.'/logs',
] as $path) {
    if (! is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

$_ENV['LARAVEL_STORAGE_PATH'] = $storagePath;
$_SERVER['LARAVEL_STORAGE_PATH'] = $storagePath;

if (isset($_GET['__path']) && is_string($_GET['__path'])) {
    $path = '/'.ltrim($_GET['__path'], '/');

    unset($_GET['__path'], $_REQUEST['__path']);

    $query = $_GET === [] ? '' : '?'.http_build_query($_GET);

    $_SERVER['REQUEST_URI'] = $path.$query;
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
}

require __DIR__.'/../public/index.php';
