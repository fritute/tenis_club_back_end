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

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }

    /**
     * Buscar todos os registros
     * @param string $where Condição WHERE (opcional)
     * @param array $params Parâmetros para prepared statement
     * @param string $order_by Ordenação (opcional)
     * @return array
     */
    public function findAll($where = '', $params = [], $order_by = '') {
        try {
            $sql = "SELECT * FROM " . $this->table_name;
            
            if (!empty($where)) {
                $sql .= " WHERE " . $where;
            }
            
            if (!empty($order_by)) {
                $sql .= " ORDER BY " . $order_by;
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
     * Inserir novo registro
     * @param array $data
     * @return int ID do registro inserido ou 0 se falhou
     */
    public function create($data) {
        try {
            $columns = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            
            $sql = "INSERT INTO " . $this->table_name . " (" . $columns . ") VALUES (" . $placeholders . ")";
            
            $stmt = $this->conn->prepare($sql);
            
            foreach ($data as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
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
        try {
            $set_clause = '';
            foreach (array_keys($data) as $column) {
                $set_clause .= $column . ' = :' . $column . ', ';
            }
            $set_clause = rtrim($set_clause, ', ');
            
            $sql = "UPDATE " . $this->table_name . " SET " . $set_clause . " WHERE " . $this->primary_key . " = :id";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id);
            
            foreach ($data as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
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
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro em executeQuery: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Destructor - fechar conexão
     */
    public function __destruct() {
        if ($this->db) {
            $this->db->closeConnection();
        }
    }
}