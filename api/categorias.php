<?php
/**
 * API Endpoints para Categorias
 * Virtual Market System
 */

require_once __DIR__ . '/../controllers/CategoriaController.php';

$controller = new CategoriaController();

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

// Se action é numérico, é na verdade um ID
if (is_numeric($action)) {
    $id = $action;
    $action = '';
}

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if ($action === 'ativas') {
                // GET /categorias/ativas
                $result = $controller->ativas();
                
            } else if ($action === 'com-contagem') {
                // GET /categorias/com-contagem
                $result = $controller->comContagem();
                
            } else if ($action === 'populares') {
                // GET /categorias/populares ou /categorias/populares/{limit}
                $limit = !empty($id) ? (int)$id : 10;
                $result = $controller->populares($limit);
                
            } else if (!empty($id)) {
                // GET /categorias/{id}
                $result = $controller->show($id);
                
            } else {
                // GET /categorias ou /categorias?filtros
                $filters = [
                    'nome' => $_GET['nome'] ?? '',
                    'status' => $_GET['status'] ?? ''
                ];
                
                $result = $controller->index($filters);
            }
            break;
            
        case 'POST':
            if ($action === 'status' && !empty($id)) {
                // POST /categorias/{id}/status
                $data = json_decode(file_get_contents('php://input'), true);
                $status = $data['status'] ?? '';
                $result = $controller->alterarStatus($id, $status);
                
            } else {
                // POST /categorias
                $data = json_decode(file_get_contents('php://input'), true);
                
                // Se não veio JSON, pegar do POST normal
                if (!$data) {
                    $data = $_POST;
                }
                
                $result = $controller->store($data);
            }
            break;
            
        case 'PUT':
            if (!empty($id)) {
                // PUT /categorias/{id}
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $controller->update($id, $data);
                
            } else {
                $result = [
                    'success' => false,
                    'message' => 'ID da categoria é obrigatório para atualização',
                    'code' => 400
                ];
            }
            break;
            
        case 'DELETE':
            if (!empty($id)) {
                // DELETE /categorias/{id}
                $result = $controller->delete($id);
                
            } else {
                $result = [
                    'success' => false,
                    'message' => 'ID da categoria é obrigatório para exclusão',
                    'code' => 400
                ];
            }
            break;
            
        default:
            $result = [
                'success' => false,
                'message' => 'Método não permitido',
                'code' => 405
            ];
            break;
    }
    
    // Definir código de resposta HTTP
    http_response_code($result['code'] ?? 200);
    
    // Retornar resposta JSON
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro no endpoint categorias: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'code' => 500
    ], JSON_UNESCAPED_UNICODE);
}