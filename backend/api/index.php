<?php

try {
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

    // Handle Vercel's pathing
    if (isset($_GET['__path'])) {
         $_SERVER['REQUEST_URI'] = $_GET['__path'];
    }

    require __DIR__.'/../public/index.php';
} catch (\Throwable $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => 'Fatal Error during bootstrap',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
