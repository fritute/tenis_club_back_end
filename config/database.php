<?php
/**
 * Database JSON - Virtual Market
 */
class Database {
    private $dataPath;
    private $lastId = 0;
    private $conn;

    public function __construct() {
        $this->dataPath = __DIR__ . '/../data/';
        if (!is_dir($this->dataPath)) {
            mkdir($this->dataPath, 0777, true);
        }
        $this->conn = $this; // Para compatibilidade
    }

    public function getConnection() {
        return $this;
    }

    // Compatibilidade PDO
    public function prepare($sql) {
        return new DatabaseStatement($this, $sql);
    }

    public function closeConnection() {
        // Não faz nada, só para compatibilidade
        return true;
    }

    public function fetchAll($table) {
        $file = $this->dataPath . $table . '.json';
        if (!file_exists($file)) return [];
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    public function insert($table, $row) {
        $data = $this->fetchAll($table);
        $row['id'] = $this->getNextId($data);
        $this->lastId = $row['id'];
        $data[] = $row;
        file_put_contents($this->dataPath . $table . '.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $row['id'];
    }

    public function update($table, $id, $row) {
        $data = $this->fetchAll($table);
        foreach ($data as &$item) {
            if ($item['id'] == $id) {
                $item = array_merge($item, $row);
            }
        }
        file_put_contents($this->dataPath . $table . '.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return true;
    }

    public function delete($table, $id) {
        $data = $this->fetchAll($table);
        $data = array_filter($data, function($item) use ($id) {
            return $item['id'] != $id;
        });
        file_put_contents($this->dataPath . $table . '.json', json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return true;
    }

    public function lastInsertId() {
        return $this->lastId;
    }

    private function getNextId($data) {
        $max = 0;
        foreach ($data as $item) {
            if (isset($item['id']) && $item['id'] > $max) {
                $max = $item['id'];
            }
        }
        return $max + 1;
    }
}

// Classe de compatibilidade para statements
class DatabaseStatement {
    private $db;
    private $sql;
    private $params = [];

    public function __construct($db, $sql) {
        $this->db = $db;
        $this->sql = $sql;
    }
    public function execute($params = []) {
        $this->params = array_merge($this->params, $params);
        
        // Processar INSERT
        if (preg_match('/INSERT INTO ([a-z_]+)/i', $this->sql, $matches)) {
            $table = $matches[1];
            $data = [];
            
            // Extrair valores dos parâmetros
            foreach ($this->params as $key => $value) {
                $cleanKey = ltrim($key, ':');
                $data[$cleanKey] = $value;
            }
            
            return $this->db->insert($table, $data);
        }
        
        // Processar UPDATE
        if (preg_match('/UPDATE ([a-z_]+)/i', $this->sql, $matches)) {
            $table = $matches[1];
            
            // Extrair ID dos parâmetros ou do WHERE
            $id = null;
            if (isset($this->params[':id'])) {
                $id = $this->params[':id'];
            } elseif (preg_match('/WHERE.*?=\s*:?([0-9]+)/i', $this->sql, $whereMatches)) {
                $id = $whereMatches[1];
            }
            
            if ($id) {
                $data = [];
                foreach ($this->params as $key => $value) {
                    $cleanKey = ltrim($key, ':');
                    if ($cleanKey !== 'id') {  // Não incluir o ID nos dados de atualização
                        $data[$cleanKey] = $value;
                    }
                }
                return $this->db->update($table, $id, $data);
            }
        }
        
        // Processar DELETE
        if (preg_match('/DELETE FROM ([a-z_]+)/i', $this->sql, $matches)) {
            $table = $matches[1];
            if (preg_match('/WHERE.*?=\s*:?([0-9]+)/i', $this->sql, $whereMatches)) {
                $id = $whereMatches[1];
                return $this->db->delete($table, $id);
            }
        }
        
        // Outros casos (SELECT etc)
        return true;
    }
    public function fetchAll() {
        // Extrair nome da tabela
        if (preg_match('/FROM ([a-z_]+)/i', $this->sql, $matches)) {
            $table = $matches[1];
            $data = $this->db->fetchAll($table);
            
            // Aplicar filtros WHERE se houver
            if (preg_match('/WHERE\s+([a-z_]+)\s*=\s*:([a-z_]+)/i', $this->sql, $whereMatches)) {
                $field = $whereMatches[1];
                $paramName = ':' . $whereMatches[2];
                
                if (isset($this->params[$paramName])) {
                    $value = $this->params[$paramName];
                    $data = array_filter($data, function($item) use ($field, $value) {
                        return isset($item[$field]) && $item[$field] == $value;
                    });
                }
            }
            
            return array_values($data);
        }
        return [];
    }
    public function fetch() {
        $all = $this->fetchAll();
        return $all ? $all[0] : false;
    }
    public function rowCount() {
        $all = $this->fetchAll();
        return count($all);
    }
    public function bindParam($param, &$var) {
        // Compatibilidade fake
        $this->params[$param] = $var;
        return true;
    }
    public function bindValue($param, $value) {
        $this->params[$param] = $value;
        return true;
    }
}