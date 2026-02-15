<?php
/**
 * API Endpoints para Produtos
 * Virtual Market System
 */

require_once __DIR__ . '/../controllers/ProdutoController.php';

$controller = new ProdutoController();

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

// Roteamento para imagens de produtos
if ($action === 'imagens') {
    require_once __DIR__ . '/produtos/imagens.php';
    exit;
}

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if ($action === 'ativos') {
                // GET /produtos/ativos
                $result = $controller->ativos();
                
            } else if ($action === 'com-fornecedores') {
                // GET /produtos/com-fornecedores ou /produtos/com-fornecedores/{id}
                $result = $controller->showWithFornecedores($id);
                
            } else if ($action === 'sem-fornecedores') {
                // GET /produtos/sem-fornecedores
                $result = $controller->semFornecedores();
                
            } else if (!empty($id)) {
                // GET /produtos/{id}
                $result = $controller->show($id);
                
            } else {
                // GET /produtos ou /produtos?filtros
                $filters = [
                    'nome' => $_GET['nome'] ?? '',
                    'status' => $_GET['status'] ?? '',
                    'codigo_interno' => $_GET['codigo_interno'] ?? '',
                    'descricao' => $_GET['descricao'] ?? ''
                ];
                
                $result = $controller->index($filters);
            }
            break;
            
        case 'POST':
            if ($action === 'status' && !empty($id)) {
                // POST /produtos/{id}/status
                $data = json_decode(file_get_contents('php://input'), true);
                $status = $data['status'] ?? '';
                $result = $controller->alterarStatus($id, $status);
                
            } else {
                // POST /produtos
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
                // PUT /produtos/{id}
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $controller->update($id, $data);
                
            } else {
                $result = [
                    'success' => false,
                    'message' => 'ID do produto é obrigatório para atualização',
                    'code' => 400
                ];
            }
            break;
            
        case 'DELETE':
            if (!empty($id)) {
                // DELETE /produtos/{id}
                $result = $controller->delete($id);
                
            } else {
                $result = [
                    'success' => false,
                    'message' => 'ID do produto é obrigatório para exclusão',
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
    error_log("Erro no endpoint produtos: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'code' => 500
    ], JSON_UNESCAPED_UNICODE);
}