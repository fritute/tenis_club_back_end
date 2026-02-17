<?php
/**
 * API Endpoints para Usuários
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

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/UsuarioController.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathArray = explode('/', trim($path, '/'));

// Remover 'api' e 'usuarios' do array
array_shift($pathArray); // remove 'api'
array_shift($pathArray); // remove 'usuarios'

try {
    $controller = new UsuarioController();
    switch ($method) {
        case 'GET':
            if (empty($pathArray) || empty($pathArray[0])) {
                // GET /api/usuarios - Listar usuários (apenas executivo)
                $controller->listar();
            } elseif ($pathArray[0] === 'perfil') {
                // GET /api/usuarios/perfil - Perfil do usuário logado
                $controller->perfil();
            } elseif (is_numeric($pathArray[0])) {
                // GET /api/usuarios/{id} - Obter usuário específico
                $controller->buscarPorId($pathArray[0]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint não encontrado']);
            }
            break;
            
        case 'POST':
            if (empty($pathArray) || empty($pathArray[0])) {
                // POST /api/usuarios - Criar usuário
                $controller->criar();
            } elseif ($pathArray[0] === 'login') {
                // POST /api/usuarios/login - Login
                $controller->login();
            } elseif ($pathArray[0] === 'logout') {
                // POST /api/usuarios/logout - Logout (TODO: implementar)
                http_response_code(501);
                echo json_encode(['error' => 'Funcionalidade não implementada']);
            } elseif ($pathArray[0] === 'validar-token') {
                // POST /api/usuarios/validar-token - Validar token
                $controller->validarToken();
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint não encontrado']);
            }
            break;
            
        case 'PUT':
            if (!empty($pathArray) && is_numeric($pathArray[0])) {
                // PUT /api/usuarios/{id} - Atualizar usuário
                $controller->atualizar($pathArray[0]);
            } elseif (!empty($pathArray) && $pathArray[0] === 'perfil') {
                // PUT /api/usuarios/perfil - Atualizar perfil próprio (TODO: implementar)
                http_response_code(501);
                echo json_encode(['error' => 'Funcionalidade não implementada']);
            } elseif (!empty($pathArray) && $pathArray[0] === 'senha') {
                // PUT /api/usuarios/senha - Alterar senha própria (TODO: implementar)
                http_response_code(501);
                echo json_encode(['error' => 'Funcionalidade não implementada']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint não encontrado']);
            }
            break;
            
        case 'DELETE':
            if (!empty($pathArray) && is_numeric($pathArray[0])) {
                // DELETE /api/usuarios/{id} - Deletar usuário
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
} catch (PDOException $e) {
    error_log("Erro PDO no endpoint usuarios: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro ao conectar ao banco de dados',
        'message' => $e->getMessage(),
        'code' => 500
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("Erro no endpoint usuarios: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro interno do servidor',
        'message' => $e->getMessage(),
        'code' => 500,
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
}
