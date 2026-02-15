<?php
/**
 * API Router
 * Virtual Market System
 */

// Incluir configurações
require_once __DIR__ . '/../config/config.php';

// Definir que é uma resposta JSON
header('Content-Type: application/json; charset=utf-8');

// Tratar CORS se necessário
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Responder OPTIONS para CORS preflight
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Processar a rota
    $request_uri = $_SERVER['REQUEST_URI'];
    $script_name = $_SERVER['SCRIPT_NAME'];
    
    // Remover o nome do script da URI
    $path = str_replace(dirname($script_name), '', $request_uri);
    $path = ltrim($path, '/');
    
    // Remover query string
    if (($pos = strpos($path, '?')) !== false) {
        $path = substr($path, 0, $pos);
    }
    
    // Quebrar o path em segmentos
    $segments = explode('/', $path);
    
    // Remover segmento 'api' se existir
    if (isset($segments[0]) && $segments[0] === 'api') {
        array_shift($segments);
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
            
        case 'test':
            // Endpoint de teste da conexão
            require_once __DIR__ . '/../config/database.php';
            $db = new Database();
            if ($db->testConnection()) {
                jsonResponse(['message' => 'Conexão com banco OK', 'timestamp' => date('Y-m-d H:i:s')]);
            } else {
                jsonResponse(['message' => 'Erro na conexão com banco'], 500);
            }
            break;
            
        case 'debug':
            require_once __DIR__ . '/debug.php';
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
                    'test'
                ]
            ], 404);
            break;
    }
    
} catch (Exception $e) {
    error_log("Erro no router API: " . $e->getMessage());
    jsonResponse(['error' => 'Erro interno do servidor'], 500);
}