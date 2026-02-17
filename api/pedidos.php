<?php
/**
 * API Endpoints para Pedidos
 * Virtual Market System
 */

// Configurar CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

// Tratar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../controllers/PedidoController.php';

$controller = new PedidoController();

// Definir variáveis de roteamento
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
$subaction = $segments[3] ?? '';

// Se action é numérico, é na verdade um ID
if (is_numeric($action)) {
    $id = $action;
    $action = '';
    if (isset($segments[2])) {
        $subaction = $segments[2];
    }
}

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if ($action === 'meus') {
                // GET /api/pedidos/meus - Meus pedidos (usuário logado)
                $result = $controller->meusPedidos();
                
            } else if ($action === 'recebidos') {
                // GET /api/pedidos/recebidos - Pedidos recebidos (fornecedor logado)
                $result = $controller->pedidosRecebidos();
                
            } else if ($action === 'estatisticas') {
                // GET /api/pedidos/estatisticas - Estatísticas (fornecedor logado)
                $result = $controller->estatisticas();
                
            } else if (!empty($id)) {
                // GET /api/pedidos/{id} - Buscar pedido específico
                $result = $controller->buscarPorId($id);
                
            } else {
                // GET /api/pedidos - Listar todos (apenas executivo)
                $result = $controller->index();
            }
            break;

        case 'POST':
            // POST /api/pedidos - Criar novo pedido
            $result = $controller->criar();
            break;

        case 'PUT':
            if ($subaction === 'status') {
                // PUT /api/pedidos/{id}/status - Atualizar status
                $result = $controller->atualizarStatus($id);
                
            } else if ($subaction === 'cancelar') {
                // PUT /api/pedidos/{id}/cancelar - Cancelar pedido
                $result = $controller->cancelar($id);
                
            } else {
                $result = [
                    'success' => false,
                    'error' => 'Ação não implementada'
                ];
                http_response_code(400);
            }
            break;

        case 'DELETE':
            $result = [
                'success' => false,
                'error' => 'Pedidos não podem ser deletados, apenas cancelados'
            ];
            http_response_code(400);
            break;

        default:
            $result = [
                'success' => false,
                'error' => 'Método não permitido'
            ];
            http_response_code(405);
            break;
    }

    // Definir código de resposta HTTP baseado no resultado
    $httpCode = $result['code'] ?? ($result['success'] ? 200 : 400);
    http_response_code($httpCode);
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    // Log do erro
    error_log("Erro na API de pedidos: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'error' => $e->getMessage(),
        'code' => 500
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
