<?php
/**
 * API Endpoints para Vínculos Produto-Fornecedor
 * Virtual Market System
 */

require_once __DIR__ . '/../controllers/ProdutoFornecedorController.php';

$controller = new ProdutoFornecedorController();

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
            if ($action === 'produto' && !empty($id)) {
                // GET /vinculos/produto/{id} - Fornecedores de um produto
                $result = $controller->getFornecedoresPorProduto($id);
                
            } else if ($action === 'fornecedor' && !empty($id)) {
                // GET /vinculos/fornecedor/{id} - Produtos de um fornecedor
                $result = $controller->getProdutosPorFornecedor($id);
                
            } else {
                // GET /vinculos ou /vinculos?filtros
                $filters = [
                    'produto_nome' => $_GET['produto_nome'] ?? '',
                    'fornecedor_nome' => $_GET['fornecedor_nome'] ?? '',
                    'produto_status' => $_GET['produto_status'] ?? '',
                    'fornecedor_status' => $_GET['fornecedor_status'] ?? ''
                ];
                
                $result = $controller->index($filters);
            }
            break;
            
        case 'POST':
            if ($action === 'multiplos') {
                // POST /vinculos/multiplos - Criar vínculos múltiplos
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $controller->storeMultiple($data);
                
            } else {
                // POST /vinculos - Criar vínculo simples
                $data = json_decode(file_get_contents('php://input'), true);
                
                // Se não veio JSON, pegar do POST normal
                if (!$data) {
                    $data = $_POST;
                }
                
                $result = $controller->store($data);
            }
            break;
            
        case 'DELETE':
            if ($action === 'multiplos') {
                // DELETE /vinculos/multiplos - Remover múltiplos vínculos
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $controller->deleteMultiple($data);
                
            } else if ($action === 'produto' && !empty($id)) {
                // DELETE /vinculos/produto/{id} - Remover todos os vínculos de um produto
                $result = $controller->deleteAllByProduto($id);
                
            } else if (!empty($id) && is_numeric($id) && !isset($_GET['fornecedor'])) {
                // DELETE /vinculos/{vinculo_id} - Deletar vínculo por ID
                $result = $controller->deleteById($id);
                
            } else if (!empty($id) && isset($_GET['fornecedor'])) {
                // DELETE /vinculos/{produto_id}?fornecedor={fornecedor_id}
                $id_fornecedor = $_GET['fornecedor'];
                $result = $controller->delete($id, $id_fornecedor);
                
            } else {
                // Formato: DELETE com dados no body
                $data = json_decode(file_get_contents('php://input'), true);
                if (isset($data['id_produto']) && isset($data['id_fornecedor'])) {
                    $result = $controller->delete($data['id_produto'], $data['id_fornecedor']);
                } else {
                    $result = [
                        'success' => false,
                        'message' => 'IDs do produto e fornecedor são obrigatórios',
                        'code' => 400
                    ];
                }
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
    error_log("Erro no endpoint vínculos: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'code' => 500
    ], JSON_UNESCAPED_UNICODE);
}