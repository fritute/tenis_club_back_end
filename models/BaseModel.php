<?php
/**
 * Classe Base para Models
 * Virtual Market System
 */

require_once __DIR__ . '/../config/database.php';

abstract class BaseModel {
    protected $db;
    protected $conn;
    protected $table_name;
    protected $primary_key;
    private $data_dir;

    public function __construct() {
        $forceDb = in_array(strtolower(getenv('DB_REQUIRE') ?: ''), ['1','true','yes']);
        try {
            $this->db = new Database();
            $this->conn = $this->db->getConnection();
        } catch (Throwable $e) {
            if ($forceDb) {
                throw $e;
            }
            $this->db = null;
            $this->conn = null;
        }
        $this->data_dir = __DIR__ . '/../data';
    }

    /**
     * Buscar todos os registros
     * @param string $where Condição WHERE (opcional)
     * @param array $params Parâmetros para prepared statement
     * @param string $order_by Ordenação (opcional)
     * @return array
     */
    public function findAll($where = '', $params = [], $order_by = '') {
        if (!$this->conn) {
            $rows = $this->readDataFile();
            if (!empty($order_by)) {
                $parts = preg_split('/\s+/', $order_by);
                $col = $parts[0] ?? null;
                $dir = strtoupper($parts[1] ?? 'ASC');
                if ($col) {
                    usort($rows, function($a, $b) use ($col, $dir) {
                        $va = $a[$col] ?? null;
                        $vb = $b[$col] ?? null;
                        if ($va == $vb) return 0;
                        $cmp = ($va < $vb) ? -1 : 1;
                        return $dir === 'DESC' ? -$cmp : $cmp;
                    });
                }
            }
            return $rows;
        }
        try {
            $sql = "SELECT * FROM " . $this->table_name;
            
            if (!empty($where)) {
                $sql .= " WHERE " . $where;
            }
            
            if (!empty($order_by)) {
                // Validar ORDER BY para prevenir SQL Injection
                // Permite apenas letras, números, underscore, espaços, vírgulas e pontos
                if (!preg_match('/^[a-zA-Z0-9_\s,.]+$/', $order_by)) {
                    error_log("Tentativa de SQL Injection em ORDER BY: " . $order_by);
                    $order_by = ''; // Ignorar ordenação inválida
                } else {
                    $sql .= " ORDER BY " . $order_by;
                }
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro em findAll: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar um registro por ID
     * @param int $id
     * @return array|null
     */
    public function findById($id) {
        if (!$this->conn) {
            $rows = $this->readDataFile();
            foreach ($rows as $row) {
                if ((string)($row[$this->primary_key] ?? null) === (string)$id) {
                    return $row;
                }
            }
            return null;
        }
        try {
            $sql = "SELECT * FROM " . $this->table_name . " WHERE " . $this->primary_key . " = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("Erro em findById: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Sanitizar identificador SQL (nome de tabela, coluna)
     * Permite apenas letras, números e underscore
     */
    protected function sanitizeIdentifier($identifier) {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $identifier);
    }

    /**
     * Inserir novo registro
     * @param array $data
     * @return int ID do registro inserido ou 0 se falhou
     */
    public function create($data) {
        if (!$this->conn) {
            $rows = $this->readDataFile();
            $maxId = 0;
            foreach ($rows as $r) {
                $rid = (int)($r[$this->primary_key] ?? 0);
                if ($rid > $maxId) $maxId = $rid;
            }
            $newId = $maxId + 1;
            $data[$this->primary_key] = $newId;
            $rows[] = $data;
            $this->writeDataFile($rows);
            return $newId;
        }
        try {
            $columns = [];
            $placeholders = [];
            
            foreach ($data as $key => $value) {
                // Sanitizar nome da coluna para evitar SQL Injection
                $cleanKey = $this->sanitizeIdentifier($key);
                if ($cleanKey !== $key) {
                    continue; // Ignorar chaves inválidas
                }
                $columns[] = $cleanKey;
                $placeholders[] = ':' . $cleanKey;
            }
            
            $sql = "INSERT INTO " . $this->table_name . " (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            
            $stmt = $this->conn->prepare($sql);
            
            foreach ($data as $key => $value) {
                $cleanKey = $this->sanitizeIdentifier($key);
                if ($cleanKey === $key) {
                    $stmt->bindValue(':' . $cleanKey, $value);
                }
            }
            
            if ($stmt->execute()) {
                return $this->conn->lastInsertId();
            }
            
            return 0;
            
        } catch (PDOException $e) {
            error_log("Erro em create: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Atualizar registro existente
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data) {
        if (!$this->conn) {
            $rows = $this->readDataFile();
            $updated = false;
            foreach ($rows as $i => $row) {
                if ((string)($row[$this->primary_key] ?? null) === (string)$id) {
                    $rows[$i] = array_merge($row, $data);
                    $updated = true;
                    break;
                }
            }
            if ($updated) {
                $this->writeDataFile($rows);
            }
            return $updated;
        }
        try {
            $set_clause = '';
            foreach ($data as $key => $value) {
                // Sanitizar nome da coluna
                $cleanKey = $this->sanitizeIdentifier($key);
                if ($cleanKey === $key) {
                    $set_clause .= $cleanKey . ' = :' . $cleanKey . ', ';
                }
            }
            $set_clause = rtrim($set_clause, ', ');
            
            if (empty($set_clause)) {
                return false;
            }
            
            $sql = "UPDATE " . $this->table_name . " SET " . $set_clause . " WHERE " . $this->primary_key . " = :id";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id);
            
            foreach ($data as $key => $value) {
                $cleanKey = $this->sanitizeIdentifier($key);
                if ($cleanKey === $key) {
                    $stmt->bindValue(':' . $cleanKey, $value);
                }
            }
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Erro em update: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Excluir registro
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        if (!$this->conn) {
            $rows = $this->readDataFile();
            $new = [];
            $deleted = false;
            foreach ($rows as $row) {
                if ((string)($row[$this->primary_key] ?? null) === (string)$id) {
                    $deleted = true;
                    continue;
                }
                $new[] = $row;
            }
            if ($deleted) {
                $this->writeDataFile($new);
            }
            return $deleted;
        }
        try {
            $sql = "DELETE FROM " . $this->table_name . " WHERE " . $this->primary_key . " = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Erro em delete: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Contar registros
     * @param string $where Condição WHERE (opcional)
     * @param array $params Parâmetros para prepared statement
     * @return int
     */
    public function count($where = '', $params = []) {
        if (!$this->conn) {
            $rows = $this->readDataFile();
            return count($rows);
        }
        try {
            $sql = "SELECT COUNT(*) as total FROM " . $this->table_name;
            
            if (!empty($where)) {
                $sql .= " WHERE " . $where;
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetch();
            return (int) $result['total'];
            
        } catch (PDOException $e) {
            error_log("Erro em count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Executar query customizada
     * @param string $sql
     * @param array $params
     * @return array
     */
    protected function executeQuery($sql, $params = []) {
        if (!$this->conn) {
            return [];
        }
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro em executeQuery: " . $e->getMessage());
            return [];
        }
    }

    private function getDataFilePath() {
        $file = $this->data_dir . '/' . $this->table_name . '.json';
        return $file;
    }

    private function readDataFile() {
        $file = $this->getDataFilePath();
        if (!file_exists($file)) {
            return [];
        }
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
        }

    private function writeDataFile($rows) {
        $file = $this->getDataFilePath();
        $json = json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        file_put_contents($file, $json);
    }

    /**
     * Destructor - fechar conexão
     */
    public function __destruct() {
        // PDO fecha automaticamente a conexão quando o objeto é destruído
        $this->conn = null;
        $this->db = null;
    }
}
