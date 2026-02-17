<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/database.php';

$result = [
    'ok' => false,
    'driver' => null,
    'database' => null,
    'tables' => [],
    'errors' => [],
    'extensions' => [],
    'extension_dir' => null,
    'php_ini' => null,
];

try {
    $result['extensions'] = get_loaded_extensions();
    $result['extension_dir'] = ini_get('extension_dir');
    $result['php_ini'] = function_exists('php_ini_loaded_file') ? php_ini_loaded_file() : 'n/a';
    $db = new Database();
    $pdo = $db->getConnection();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $result['driver'] = $driver;

    if ($driver === 'mysql') {
        $stmt = $pdo->query('SELECT DATABASE() AS db');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $result['database'] = $row['db'] ?? null;

        $tables = [];
        $q = $pdo->query('SHOW TABLES');
        while ($r = $q->fetch(PDO::FETCH_NUM)) {
            $tables[] = $r[0];
        }
        $result['tables'] = $tables;

        $pdo->query('SELECT 1');
    } elseif ($driver === 'sqlite') {
        $stmt = $pdo->query("PRAGMA database_list");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $result['database'] = $row['file'] ?? null;

        $tables = [];
        $q = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
        while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
            $tables[] = $r['name'];
        }
        $result['tables'] = $tables;

        $pdo->query('SELECT 1');
    } else {
        $result['errors'][] = 'Driver PDO não suportado';
    }
} catch (Throwable $e) {
    $result['errors'][] = $e->getMessage();
}

// Tentativa MySQLi apenas local
try {
    if (in_array('mysqli', get_loaded_extensions())) {
        $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
        $is_local = in_array($host, ['localhost', '127.0.0.1']);
        if ($is_local) {
            $user = $_ENV['DB_USERNAME'] ?? $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root';
            $pass = $_ENV['DB_PASSWORD'] ?? $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';
            $dbn  = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'tenis_club';
            $port = (int)($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306);
            $mysqli = @mysqli_connect($host, $user, $pass, $dbn, $port);
            if ($mysqli) {
                if (!$result['driver']) { $result['driver'] = 'mysqli'; }
                $result['database'] = $dbn;
                if (empty($result['tables'])) {
                    $tables = [];
                    if ($res = mysqli_query($mysqli, 'SHOW TABLES')) {
                        while ($row = mysqli_fetch_row($res)) {
                            $tables[] = $row[0];
                        }
                        mysqli_free_result($res);
                    }
                    $result['tables'] = $tables;
                }
                mysqli_close($mysqli);
                $result['ok'] = true;
            } else {
                $result['errors'][] = 'Falha ao conectar via MySQLi: ' . (mysqli_connect_error() ?: 'erro desconhecido');
            }
        } else {
            $result['errors'][] = 'MySQLi: conexões remotas não são permitidas';
        }
    } else {
        $result['errors'][] = 'Extensão mysqli não carregada';
    }
} catch (Throwable $e) {
    $result['errors'][] = 'Diagnóstico MySQLi: ' . $e->getMessage();
}

$result['ok'] = $result['ok'] || empty($result['errors']);
http_response_code($result['ok'] ? 200 : 500);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
