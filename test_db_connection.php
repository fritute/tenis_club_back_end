<?php
/**
 * Teste de conexão com banco de dados
 */

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$result = [
    'success' => false,
    'tests' => []
];

try {
    require_once __DIR__ . '/config/database.php';
    $result['tests']['database_class'] = 'Carregado com sucesso';
    
    try {
        $db = new Database();
        $result['tests']['database_connection'] = 'Conectado com sucesso';
        
        $conn = $db->getConnection();
        $result['tests']['pdo_object'] = 'PDO criado com sucesso';
        
        // Testar query simples
        $stmt = $conn->query("SELECT DATABASE() as db_name");
        $db_info = $stmt->fetch();
        $result['tests']['current_database'] = $db_info['db_name'] ?? 'Não identificado';
        
        // Verificar se tabelas existem
        $stmt = $conn->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $result['tests']['tables_found'] = count($tables);
        $result['tests']['tables_list'] = $tables;
        
        // Testar query em categorias
        if (in_array('categorias', $tables)) {
            $stmt = $conn->query("SELECT COUNT(*) as total FROM categorias");
            $count = $stmt->fetch();
            $result['tests']['categorias_count'] = $count['total'];
        } else {
            $result['tests']['categorias_count'] = 'Tabela não existe';
        }
        
        // Testar query em produtos
        if (in_array('produtos', $tables)) {
            $stmt = $conn->query("SELECT COUNT(*) as total FROM produtos");
            $count = $stmt->fetch();
            $result['tests']['produtos_count'] = $count['total'];
        } else {
            $result['tests']['produtos_count'] = 'Tabela não existe';
        }
        
        // Testar query em fornecedores
        if (in_array('fornecedores', $tables)) {
            $stmt = $conn->query("SELECT COUNT(*) as total FROM fornecedores");
            $count = $stmt->fetch();
            $result['tests']['fornecedores_count'] = $count['total'];
        } else {
            $result['tests']['fornecedores_count'] = 'Tabela não existe';
        }
        
        $result['success'] = true;
        $result['message'] = 'Todos os testes passaram';
        
    } catch (PDOException $e) {
        $result['tests']['database_connection'] = 'ERRO: ' . $e->getMessage();
        $result['tests']['pdo_error_code'] = $e->getCode();
        $result['message'] = 'Erro ao conectar ao banco de dados';
    }
    
} catch (Exception $e) {
    $result['tests']['error'] = $e->getMessage();
    $result['message'] = 'Erro ao carregar configuração';
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
