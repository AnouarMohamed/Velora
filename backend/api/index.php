<?php
echo json_encode([
    'status' => 'alive',
    'php' => PHP_VERSION,
    'query' => $_GET,
    'uri' => $_SERVER['REQUEST_URI']
]);
