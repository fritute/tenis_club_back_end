<?php
/**
 * API Router
 * Virtual Market System
 */

$__is_dev = ((getenv('APP_ENV') ?: '') === 'dev') || (isset($_GET['debug']) && $_GET['debug']);
error_reporting(E_ALL);
ini_set('display_errors', $__is_dev ? '1' : '0');
ini_set('log_errors', '1');
$__log_dir = __DIR__ . '/../logs';
if (!is_dir($__log_dir)) { @mkdir($__log_dir, 0777, true); }
ini_set('error_log', $__log_dir . '/php_errors.log');

// Buffer de saída para evitar problemas com headers
ob_start();

// Incluir configurações
require_once __DIR__ . '/../config/config.php';

// Configurar CORS - permitir origens do frontend
$allowed_origins = [
    'http://localhost:3000',
    'http://localhost:3001',
    'http://127.0.0.1:3000',
    'http://localhost:5173',
    'http://127.0.0.1:5173'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header("Access-Control-Allow-Credentials: true");
} else {
    header("Access-Control-Allow-Origin: *");
    // Não enviar Allow-Credentials com wildcard (requisito do CORS)
}
header("Vary: Origin");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept");
header("Access-Control-Max-Age: 86400");
header('Content-Type: application/json; charset=utf-8');

// Responder OPTIONS para CORS preflight
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    if ($_GET['debug'] ?? false) {
        $segments_debug = [];
        $request_uri = $_SERVER['REQUEST_URI'];
        
        // Remover query string
        if (($pos = strpos($request_uri, '?')) !== false) {
            $request_uri = substr($request_uri, 0, $pos);
        }
        
        $segments = explode('/', trim($request_uri, '/'));
        $api_index = array_search('api', $segments);
        
        if ($api_index !== false) {
            $segments = array_slice($segments, $api_index + 1);
        }
        
        $debug = [
            'REQUEST_URI' => $_SERVER['REQUEST_URI'],
            'PROCESSED_URI' => $request_uri,
            'SEGMENTS' => $segments,
            'RESOURCE' => $segments[0] ?? '',
            'ACTION' => $segments[1] ?? '',
            'API_INDEX' => $api_index
        ];
        jsonResponse($debug);
    }

    // Processar a rota de forma mais simples
    $request_uri = $_SERVER['REQUEST_URI'];
    
    // Remover query string
    if (($pos = strpos($request_uri, '?')) !== false) {
        $request_uri = substr($request_uri, 0, $pos);
    }
    
    // Quebrar o path em segmentos
    $segments = explode('/', trim($request_uri, '/'));
    
    // Encontrar onde começa 'api'
    $api_index = array_search('api', $segments);
    if ($api_index !== false) {
        // Remover tudo até e incluindo 'api'
        $segments = array_slice($segments, $api_index + 1);
    }
    
    // Roteamento simples
    $resource = $segments[0] ?? '';
    $action = $segments[1] ?? '';
    $id = $segments[2] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Redirecionar para o endpoint apropriado
    switch ($resource) {
        case 'fornecedores':
            require_once __DIR__ . '/fornecedores.php';
            break;
            
        case 'produtos':
            require_once __DIR__ . '/produtos.php';
            break;
            
        case 'categorias':
            require_once __DIR__ . '/categorias.php';
            break;
            
        case 'vinculos':
        case 'produto-fornecedor':
            require_once __DIR__ . '/produto_fornecedor.php';
            break;
            
        case 'precos-avaliacoes':
        case 'precos_avaliacoes':
            require_once __DIR__ . '/precos_avaliacoes.php';
            break;
            
        case 'relatorios':
            require_once __DIR__ . '/relatorios.php';
            break;
            
        case 'usuarios':
            require_once __DIR__ . '/usuarios.php';
            break;
            
        case 'pedidos':
            require_once __DIR__ . '/pedidos.php';
            break;
            
        case 'test':
            // Endpoint de teste simples da conexão
            jsonResponse([
                'message' => 'API funcionando corretamente', 
                'timestamp' => date('Y-m-d H:i:s'),
                'resource' => $resource,
                'method' => $method
            ]);
            break;
            
        case 'debug':
            require_once __DIR__ . '/debug.php';
            break;
            
        case 'diagnostico':
            require_once __DIR__ . '/../diagnostico.php';
            break;
            
        default:
            // Rota não encontrada
            jsonResponse([
                'error' => 'Endpoint não encontrado', 
                'resource' => $resource,
                'available_endpoints' => [
                    'fornecedores',
                    'produtos',
                    'categorias', 
                    'vinculos',
                    'produto-fornecedor', 
                    'precos-avaliacoes',
                    'relatorios',
                    'usuarios',
                    'pedidos',
                    'test'
                ]
            ], 404);
            break;
    }
    
} catch (Exception $e) {
    error_log("Erro no router API: " . $e->getMessage());
    if ($__is_dev) {
        jsonResponse(['error' => 'Erro interno do servidor', 'message' => $e->getMessage()], 500);
    } else {
        jsonResponse(['error' => 'Erro interno do servidor'], 500);
    }
}
