<?php
/**
 * API Endpoints para Produtos
 * Virtual Market System
 */

// CORS já configurado no router.php, mas garantir aqui também
if (!headers_sent()) {
    $allowed_origins = ['http://localhost:3000', 'http://localhost:3001', 'http://127.0.0.1:3000', 'http://localhost:5173', 'http://127.0.0.1:5173'];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin && in_array($origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: {$origin}");
        header("Access-Control-Allow-Credentials: true");
    } else {
        header("Access-Control-Allow-Origin: *");
    }
    header("Vary: Origin");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept");
    header("Access-Control-Max-Age: 86400");
    header('Content-Type: application/json; charset=utf-8');
}

// Tratar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Debug simples
if (isset($_GET['debug'])) {
    echo json_encode([
        'message' => 'Endpoint produtos funcionando',
        'uri' => $_SERVER['REQUEST_URI'],
        'method' => $_SERVER['REQUEST_METHOD']
    ]);
    exit();
}

require_once __DIR__ . '/../controllers/ProdutoController.php';

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

// Roteamento para imagens de produtos
if ($action === 'imagens') {
    require_once __DIR__ . '/produtos/imagens.php';
    exit;
}

try {
    $controller = new ProdutoController();
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if ($action === 'ativos') {
                // GET /produtos/ativos
                $result = $controller->ativos();
                
            } else if ($action === 'disponiveis') {
                // GET /produtos/disponiveis (para fornecedores buscarem novos produtos)
                $result = $controller->disponiveisParaVinculo();
                
            } else if ($action === 'publicos') {
                // GET /produtos/publicos (produtos públicos para qualquer usuário)
                $result = $controller->publicos();
                
            } else if ($action === 'com-fornecedores') {
                // GET /produtos/com-fornecedores ou /produtos/com-fornecedores/{id}
                $result = $controller->showWithFornecedores($id);
                
            } else if ($action === 'sem-fornecedores') {
                // GET /produtos/sem-fornecedores
                $result = $controller->semFornecedores();
                
            } else if (!empty($id)) {
                // GET /produtos/{id}
                $result = $controller->show($id);
                
            } else if ($action === 'minha-empresa') {
                // GET /produtos/minha-empresa (produtos do fornecedor logado)
                $result = $controller->minhaEmpresa();
                
            } else {
                // GET /produtos ou /produtos?filtros
                $filters = [
                    'nome' => $_GET['nome'] ?? '',
                    'status' => $_GET['status'] ?? '',
                    'codigo_interno' => $_GET['codigo_interno'] ?? '',
                    'descricao' => $_GET['descricao'] ?? '',
                    'fornecedor_id' => $_GET['fornecedor_id'] ?? '',
                    'categoria_id' => $_GET['categoria_id'] ?? ''
                ];
                
                $result = $controller->index($filters);
            }
            break;
            
        case 'POST':
            if ($action === 'minha-loja') {
                // POST /produtos/minha-loja - Adicionar produto à minha loja (fornecedor)
                $data = json_decode(file_get_contents('php://input'), true);
                
                // Se não veio JSON, pegar do POST normal
                if (!$data) {
                    $data = $_POST;
                }
                
                $result = $controller->addToMyStore($data);
                
            } else if ($subaction === 'status' && !empty($id)) {
                // POST /produtos/{id}/status - Alterar status do produto
                $data = json_decode(file_get_contents('php://input'), true);
                $status = $data['status'] ?? '';
                $result = $controller->alterarStatusProduto($id, $status);
                
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
