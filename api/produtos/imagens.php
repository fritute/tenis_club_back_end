<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Tratar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/ProdutoImagemController.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathArray = explode('/', trim($path, '/'));

// Remove 'api', 'produtos', 'imagens' do array
array_shift($pathArray); // remove 'api'
array_shift($pathArray); // remove 'produtos'
array_shift($pathArray); // remove 'imagens'

$controller = new ProdutoImagemController();

try {
    switch ($method) {
        case 'GET':
            if (empty($pathArray) || empty($pathArray[0])) {
                // GET /api/produtos/imagens - Precisa de produto_id como query param
                if (!isset($_GET['produto_id'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'produto_id é obrigatório']);
                    exit();
                }
                $controller->listarPorProduto($_GET['produto_id']);
            } elseif (is_numeric($pathArray[0])) {
                // GET /api/produtos/imagens/{id} - Obter imagem específica
                $controller->obter($pathArray[0]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint não encontrado']);
            }
            break;
            
        case 'POST':
            if (empty($pathArray) || empty($pathArray[0])) {
                // POST /api/produtos/imagens - Upload de imagem
                $controller->upload();
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint não encontrado']);
            }
            break;
            
        case 'PUT':
            if (!empty($pathArray) && is_numeric($pathArray[0])) {
                if (isset($pathArray[1])) {
                    switch ($pathArray[1]) {
                        case 'principal':
                            // PUT /api/produtos/imagens/{id}/principal - Definir como principal
                            $controller->definirPrincipal($pathArray[0]);
                            break;
                        case 'ordem':
                            // PUT /api/produtos/imagens/{id}/ordem - Alterar ordem
                            $controller->alterarOrdem($pathArray[0]);
                            break;
                        default:
                            http_response_code(404);
                            echo json_encode(['error' => 'Endpoint não encontrado']);
                    }
                } else {
                    // PUT /api/produtos/imagens/{id} - Atualizar metadados
                    $controller->atualizar($pathArray[0]);
                }
            } elseif (!empty($pathArray) && $pathArray[0] === 'reordenar') {
                // PUT /api/produtos/imagens/reordenar - Reordenar imagens
                $controller->reordenar();
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint não encontrado']);
            }
            break;
            
        case 'DELETE':
            if (!empty($pathArray) && is_numeric($pathArray[0])) {
                // DELETE /api/produtos/imagens/{id} - Deletar imagem
                $controller->deletar($pathArray[0]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint não encontrado']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro interno do servidor',
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}