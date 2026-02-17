<?php
/**
 * API Endpoints para Fornecedores
 * Virtual Market System
 */

// CORS já configurado no router.php, mas garantir aqui também
if (!headers_sent()) {
    $allowed_origins = ['http://localhost:3000', 'http://localhost:3001', 'http://127.0.0.1:3000'];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: {$origin}");
    } else {
        header("Access-Control-Allow-Origin: *");
    }
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 86400");
    header('Content-Type: application/json; charset=utf-8');
}

// Tratar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../controllers/FornecedorController.php';

$controller = new FornecedorController();

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

// Se action é numérico, é na verdade um ID e id é uma sub-ação
if (is_numeric($action)) {
    $subaction = $id;
    $id = $action;
    $action = '';
}

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if ($action === 'minha-loja') {
                // GET /fornecedores/minha-loja
                $result = $controller->minhaLoja();
                
            } else if ($action === 'ativos') {
                // GET /fornecedores/ativos
                $result = $controller->ativos();
                
            } else if (!empty($id)) {
                // GET /fornecedores/{id}
                $result = $controller->show($id);
                
            } else {
                // Verificar se é uma busca pela loja do usuário logado
                if (isset($_GET['minha_loja']) && $_GET['minha_loja'] === 'true') {
                    // GET /fornecedores?minha_loja=true
                    $result = $controller->minhaLoja();
                } else {
                    // GET /fornecedores ou /fornecedores?filtros
                    $filters = [
                        'nome' => $_GET['nome'] ?? '',
                        'status' => $_GET['status'] ?? '',
                        'cnpj' => $_GET['cnpj'] ?? ''
                    ];
                    
                    $result = $controller->index($filters);
                }
            }
            break;
            
        case 'POST':
            if ($action === 'minha-loja') {
                // POST /fornecedores/minha-loja (criar loja do fornecedor logado)
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (!$data) {
                    $data = $_POST;
                }
                
                $result = $controller->criarMinhaLoja($data);
                
            } else if ($subaction === 'status' && !empty($id)) {
                // POST /fornecedores/{id}/status - Alterar status da loja
                $data = json_decode(file_get_contents('php://input'), true);
                $status = $data['status'] ?? '';
                $result = $controller->alterarStatusLoja($id, $status);
                
            } else {
                // POST /fornecedores
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
                // PUT /fornecedores/{id}
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $controller->update($id, $data);
                
            } else {
                $result = [
                    'success' => false,
                    'message' => 'ID do fornecedor é obrigatório para atualização',
                    'code' => 400
                ];
            }
            break;
            
        case 'DELETE':
            if (!empty($id)) {
                // DELETE /fornecedores/{id}
                $result = $controller->delete($id);
                
            } else {
                $result = [
                    'success' => false,
                    'message' => 'ID do fornecedor é obrigatório para exclusão',
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
    
} catch (PDOException $e) {
    error_log("Erro PDO no endpoint fornecedores: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    error_log("SQL Error Info: " . print_r($e->errorInfo ?? [], true));
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao conectar ao banco de dados',
        'code' => 500,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("Erro no endpoint fornecedores: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'code' => 500,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}