<?php
class Database {
    private $conn;

    public function __construct() {
        $connections = [
            'mysql' => [
                'driver' => 'mysql',
                'host' => $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost',
                'port' => $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306',
                'database' => $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'tenis_club',
                'username' => $_ENV['DB_USERNAME'] ?? $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root',
                'password' => $_ENV['DB_PASSWORD'] ?? $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '',
                'charset' => $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ],
            ],
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => __DIR__ . '/../storage/database.sqlite',
                'foreign_key_constraints' => true,
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ],
            ],
        ];
        $defaultRaw = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION') ?: 'mysql';
        $default = strtolower(trim($defaultRaw));
        if (!isset($connections[$default])) {
            if (stripos($defaultRaw, 'mysql') !== false) {
                $default = 'mysql';
            } else {
                $default = 'sqlite';
            }
        }
        $cfg = $connections[$default];

        if ($cfg['driver'] === 'mysql') {
            $host = $cfg['host'];
            $is_local = in_array($host, ['localhost', '127.0.0.1']);
            if (!$is_local) {
                throw new RuntimeException('Conexões remotas não são permitidas. Use host=localhost ou 127.0.0.1');
            }
            if (empty($cfg['host']) || empty($cfg['port']) || empty($cfg['database']) || $cfg['username'] === null || $cfg['password'] === null) {
                throw new RuntimeException('Variáveis de ambiente do banco ausentes');
            }
            $dsnEnv = $_ENV['DB_DSN'] ?? getenv('DB_DSN');
            $dsn = $dsnEnv ?: "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}";
            try {
                $this->conn = new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options']);
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if (stripos($msg, 'could not find driver') !== false) {
                    throw new RuntimeException('Driver pdo_mysql ausente ou não carregado');
                }
                throw new PDOException($msg, (int)$e->getCode());
            }
        } else {
            $dsn = "sqlite:{$cfg['database']}";
            try {
                $this->conn = new PDO($dsn, null, null, $cfg['options']);
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if (stripos($msg, 'could not find driver') !== false) {
                    throw new RuntimeException('Driver pdo_sqlite ausente ou não carregado');
                }
                throw new PDOException($msg, (int)$e->getCode());
            }
        }
    }

    public function getConnection() {
        return $this->conn;
    }
}
