<?php
/**
 * Debug endpoint - Mostra todas as variáveis de roteamento
 */

header('Content-Type: application/json; charset=utf-8');

$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));

// Remover 'api' se existir
if (isset($segments[0]) && $segments[0] === 'api') {
    array_shift($segments);
}

$resource = $segments[0] ?? '';
$action = $segments[1] ?? '';
$id = $segments[2] ?? '';

$debug = [
    'REQUEST_URI' => $_SERVER['REQUEST_URI'],
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
    'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'],
    'PATH_INFO' => $_SERVER['PATH_INFO'] ?? 'not set',
    'parsed_path' => $path,
    'segments_raw' => explode('/', trim($path, '/')),
    'segments_after_api_removed' => $segments,
    'resource' => $resource,
    'action' => $action,
    'id' => $id,
    'GET_params' => $_GET
];

echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
