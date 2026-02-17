<?php
/**
 * Router para PHP Built-in Server
 * Redireciona todas as requisições para api/index.php
 * Garante CORS mesmo em caso de erros fatais
 */

// Iniciar buffer de saída ANTES de qualquer coisa
ob_start();

// Registrar função para garantir CORS em caso de erro fatal
register_shutdown_function(function() {
    // Limpar qualquer output anterior
    $output = ob_get_clean();
    
    // Se não há headers enviados, enviar CORS
    if (!headers_sent()) {
        $allowed_origins = ['http://localhost:3000', 'http://localhost:3001', 'http://127.0.0.1:3000', 'http://localhost:5173', 'http://127.0.0.1:5173'];
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin && in_array($origin, $allowed_origins)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header("Access-Control-Allow-Credentials: true");
        } else {
            header("Access-Control-Allow-Origin: *");
        }
        $reqHeaders = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? 'Content-Type, Authorization, X-Requested-With, Accept';
        header("Vary: Origin");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
        header("Access-Control-Allow-Headers: {$reqHeaders}");
        header("Access-Control-Max-Age: 86400");
        header('Content-Type: application/json; charset=utf-8');
    }
    
    // Se houve erro fatal, retornar JSON de erro
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Erro interno do servidor',
            'message' => 'Erro fatal no servidor'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Se há output, enviá-lo
    if ($output) {
        echo $output;
    }
});

// Se é um arquivo estático, servir diretamente
if (preg_match('/\.(?:png|jpg|jpeg|gif|css|js|ico|svg|woff|woff2|ttf|eot)$/', $_SERVER["REQUEST_URI"])) {
    return false;
}

// Configurar CORS ANTES de qualquer output
$allowed_origins = ['http://localhost:3000', 'http://localhost:3001', 'http://127.0.0.1:3000', 'http://localhost:5173', 'http://127.0.0.1:5173' , '*'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin && in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header("Access-Control-Allow-Credentials: true");
} else {
    header("Access-Control-Allow-Origin: *");
}

header("Vary: Origin");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
$reqHeaders = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? 'Content-Type, Authorization, X-Requested-With, Accept';
header("Access-Control-Allow-Headers: {$reqHeaders}");
header("Access-Control-Max-Age: 86400");
header('Content-Type: application/json; charset=utf-8');

// Responder OPTIONS para CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    ob_end_flush();
    exit();
}

// Redirecionar para api/index.php com tratamento de erros
try {
    require __DIR__ . '/api/index.php';
    ob_end_flush();
} catch (Throwable $e) {
    // Limpar buffer
    ob_end_clean();
    
    // Garantir headers CORS
    if (!headers_sent()) {
        $allowed_origins = ['http://localhost:3000', 'http://localhost:3001', 'http://127.0.0.1:3000', 'http://localhost:5173', 'http://127.0.0.1:5173'];
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin && in_array($origin, $allowed_origins)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header("Access-Control-Allow-Credentials: true");
        } else {
            header("Access-Control-Allow-Origin: *");
        }
        $reqHeaders = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? 'Content-Type, Authorization, X-Requested-With, Accept';
        header("Vary: Origin");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
        header("Access-Control-Allow-Headers: {$reqHeaders}");
        header('Content-Type: application/json; charset=utf-8');
    }
    
    http_response_code(500);
    
    // Log do erro
    error_log("Erro fatal no router: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    // Retornar JSON de erro
    echo json_encode([
        'error' => 'Erro interno do servidor',
        'message' => 'Erro ao processar requisição'
    ], JSON_UNESCAPED_UNICODE);
    
    exit;
}
