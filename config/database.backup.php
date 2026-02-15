<?php
/**
 * Configuração de Conexão com o Banco de Dados
 * Tenis Club System - Versão JSON (sem PDO)
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'tenis_club';
    private $username = 'root';
    private $password = 'bcd@127';
    private $charset = 'utf8mb4';
    private $dataPath;
    private $lastId = 0;
    public $conn;

    public function __construct() {
        $this->dataPath = __DIR__ . '/../data/';
        if (!is_dir($this->dataPath)) {
            mkdir($this->dataPath, 0777, true);
        }
        $this->initializeData();
    }

    /**
     * Conectar ao banco de dados (usando arquivos JSON)
     * @return Database
     */
    public function getConnection() {
        // Retorna a própria instância como "conexão"
        $this->conn = $this;
        return $this->conn;
    }

    /**
     * Executar query simulada
     */
    public function prepare($sql) {
        return new DatabaseStatement($this, $sql);
    }

    /**
     * Método para compatibilidade PDO
     */
    public function exec($sql) {
        // Implementação básica para CREATE/INSERT
        return true;
    }

    /**
     * Ler dados de uma tabela
     */
    public function select($table, $conditions = []) {
        $file = $this->dataPath . $table . '.json';
        if (!file_exists($file)) {
            return [];
        }
        
        $data = json_decode(file_get_contents($file), true) ?: [];
        
        // Aplicar filtros simples
        if (!empty($conditions)) {
            $data = array_filter($data, function($row) use ($conditions) {
                foreach ($conditions as $key => $value) {
                    if (!isset($row[$key]) || $row[$key] != $value) {
                        return false;
                    }
                }
                return true;
            });
        }
        
        return array_values($data);
    }

    /**
     * Inserir dados
     */
    public function insert($table, $data) {
        $file = $this->dataPath . $table . '.json';
        $existing = [];
        
        if (file_exists($file)) {
            $existing = json_decode(file_get_contents($file), true) ?: [];
        }
        
        // Gerar ID automático
        $maxId = 0;
        foreach ($existing as $row) {
            if (isset($row['id']) && $row['id'] > $maxId) {
                $maxId = $row['id'];
            }
        }
        
        $data['id'] = $maxId + 1;
        $this->lastId = $data['id']; // Armazenar último ID
        
        if (!isset($data['data_cadastro'])) {
            $data['data_cadastro'] = date('Y-m-d H:i:s');
        }
        
        $existing[] = $data;
        
        return file_put_contents($file, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Atualizar dados
     */
    public function update($table, $data, $id) {
        $file = $this->dataPath . $table . '.json';
        if (!file_exists($file)) {
            return false;
        }
        
        $existing = json_decode(file_get_contents($file), true) ?: [];
        
        foreach ($existing as &$row) {
            if ($row['id'] == $id) {
                $row = array_merge($row, $data);
                break;
            }
        }
        
        return file_put_contents($file, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Deletar dados
     */
    public function delete($table, $id) {
        $file = $this->dataPath . $table . '.json';
        if (!file_exists($file)) {
            return false;
        }
        
        $existing = json_decode(file_get_contents($file), true) ?: [];
        $existing = array_filter($existing, function($row) use ($id) {
            return $row['id'] != $id;
        });
        
        return file_put_contents($file, json_encode(array_values($existing), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Fechar conexão
     */
    public function closeConnection() {
        $this->conn = null;
    }

    /**
     * Testar conexão
     * @return bool
     */
    public function testConnection() {
        return is_dir($this->dataPath) && is_writable($this->dataPath);
    }

    /**
     * Inicializar dados de exemplo
     */
    private function initializeData() {
        // Criar categorias
        $categorias = [
            ['id' => 1, 'nome' => 'Tênis Esportivo', 'descricao' => 'Tênis para práticas esportivas', 'status' => 'Ativo', 'data_criacao' => date('Y-m-d H:i:s')],
            ['id' => 2, 'nome' => 'Tênis Casual', 'descricao' => 'Tênis para uso casual', 'status' => 'Ativo', 'data_criacao' => date('Y-m-d H:i:s')],
            ['id' => 3, 'nome' => 'Tênis Social', 'descricao' => 'Tênis para ocasiões sociais', 'status' => 'Ativo', 'data_criacao' => date('Y-m-d H:i:s')]
        ];
        
        $categoriasFile = $this->dataPath . 'categorias.json';
        if (!file_exists($categoriasFile)) {
            file_put_contents($categoriasFile, json_encode($categorias, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        // Criar fornecedores
        $fornecedores = [
            ['id' => 1, 'nome' => 'Nike Brasil', 'email' => 'contato@nike.com.br', 'cnpj' => '12.345.678/0001-90', 'telefone' => '(11) 1234-5678', 'status' => 'Ativo', 'data_cadastro' => date('Y-m-d H:i:s')],
            ['id' => 2, 'nome' => 'Adidas Sports', 'email' => 'vendas@adidas.com.br', 'cnpj' => '98.765.432/0001-10', 'telefone' => '(11) 8765-4321', 'status' => 'Ativo', 'data_cadastro' => date('Y-m-d H:i:s')]
        ];
        
        $fornecedoresFile = $this->dataPath . 'fornecedores.json';
        if (!file_exists($fornecedoresFile)) {
            file_put_contents($fornecedoresFile, json_encode($fornecedores, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        // Criar produtos
        $produtos = [
            ['id' => 1, 'nome' => 'Nike Air Max', 'descricao' => 'Tênis esportivo com tecnologia Air Max', 'categoria_id' => 1, 'preco_base' => 299.99, 'status' => 'Ativo', 'data_cadastro' => date('Y-m-d H:i:s')],
            ['id' => 2, 'nome' => 'Adidas Ultraboost', 'descricao' => 'Tênis de corrida com tecnologia Boost', 'categoria_id' => 1, 'preco_base' => 399.99, 'status' => 'Ativo', 'data_cadastro' => date('Y-m-d H:i:s')]
        ];
        
        $produtosFile = $this->dataPath . 'produtos.json';
        if (!file_exists($produtosFile)) {
            file_put_contents($produtosFile, json_encode($produtos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        // Criar vínculos
        $vinculos = [
            ['id' => 1, 'produto_id' => 1, 'fornecedor_id' => 1, 'preco_fornecedor' => 299.99, 'status' => 'Ativo', 'data_vinculo' => date('Y-m-d H:i:s')],
            ['id' => 2, 'produto_id' => 2, 'fornecedor_id' => 2, 'preco_fornecedor' => 399.99, 'status' => 'Ativo', 'data_vinculo' => date('Y-m-d H:i:s')]
        ];
        
        $vinculosFile = $this->dataPath . 'produto_fornecedor.json';
        if (!file_exists($vinculosFile)) {
            file_put_contents($vinculosFile, json_encode($vinculos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
    
    /**
     * Obter último ID inserido
     * @return int
     */
    public function lastInsertId() {
        return $this->lastId ?? 0;
    }
}

/**
 * Classe para simular PDO Statement
 */
class DatabaseStatement {
    private $db;
    private $sql;
    private $params = [];
    private $boundParams = [];
    
    public function __construct($db, $sql) {
        $this->db = $db;
        $this->sql = $sql;
    }
    
    public function bindParam($parameter, $value, $data_type = null) {
        $this->boundParams[$parameter] = $value;
        return true;
    }
    
    public function bindValue($parameter, $value, $data_type = null) {
        $this->boundParams[$parameter] = $value;
        return true;
    }
    
    public function execute($params = []) {
        // Combinar parâmetros bound e parâmetros do execute
        $allParams = array_merge($this->boundParams, $params);
        $this->params = $allParams;
        
        // Análise da SQL para operações
        $sql = strtoupper(trim($this->sql));
        
        if (strpos($sql, 'SELECT') === 0) {
            return $this->executeSelect($allParams);
        } elseif (strpos($sql, 'INSERT') === 0) {
            return $this->executeInsert($allParams);
        } elseif (strpos($sql, 'UPDATE') === 0) {
            return $this->executeUpdate($allParams);
        } elseif (strpos($sql, 'DELETE') === 0) {
            return $this->executeDelete($allParams);
        }
        
        return true;
    }
    
    private function executeSelect($params) {
        $sql = strtoupper(trim($this->sql));
        
        // Determinar tabela
        $table = '';
        if (strpos($sql, 'FROM FORNECEDORES') !== false) {
            $table = 'fornecedores';
        } elseif (strpos($sql, 'FROM PRODUTOS') !== false) {
            $table = 'produtos';
        } elseif (strpos($sql, 'FROM CATEGORIAS') !== false) {
            $table = 'categorias';
        } elseif (strpos($sql, 'FROM PRODUTO_FORNECEDOR') !== false) {
            $table = 'produto_fornecedor';
        }
        
        if (!$table) return [];
        
        $data = $this->db->select($table);
        
        // Tratar COUNT() queries
        if (strpos($sql, 'COUNT(*)') !== false) {
            $count = count($data);
            
            // Se tem WHERE com parâmetros, filtrar primeiro
            if (strpos($sql, 'WHERE') !== false && !empty($params)) {
                
                // Tratamento especial para vinculoExists (id_produto AND id_fornecedor)
                if (isset($params[':id_produto']) && isset($params[':id_fornecedor'])) {
                    $id_produto = $params[':id_produto'];
                    $id_fornecedor = $params[':id_fornecedor'];
                    $data = array_filter($data, function($row) use ($id_produto, $id_fornecedor) {
                        return isset($row['produto_id']) && $row['produto_id'] == $id_produto 
                            && isset($row['fornecedor_id']) && $row['fornecedor_id'] == $id_fornecedor;
                    });
                } else {
                    // Tratamento normal para um parâmetro
                    foreach ($params as $key => $value) {
                        $cleanKey = ltrim($key, ':');
                    if ($cleanKey === 'codigo' || $cleanKey === 'codigo_interno') {
                        $data = array_filter($data, function($row) use ($value) {
                            return isset($row['codigo_interno']) && $row['codigo_interno'] == $value;
                        });
                    } elseif ($cleanKey === 'cnpj') {
                        $data = array_filter($data, function($row) use ($value) {
                            // Limpar CNPJ para comparação (remover formatação)
                            $cnpj_banco = preg_replace('/[^0-9]/', '', $row['cnpj'] ?? '');
                            $cnpj_busca = preg_replace('/[^0-9]/', '', $value);
                            return $cnpj_banco == $cnpj_busca;
                        });
                    } elseif ($cleanKey === 'email') {
                        $data = array_filter($data, function($row) use ($value) {
                            return isset($row['email']) && $row['email'] == $value;
                        });
                    } elseif ($cleanKey === 'nome') {
                        $data = array_filter($data, function($row) use ($value) {
                            return isset($row['nome']) && $row['nome'] == $value;
                        });
                    } elseif ($cleanKey === 'id') {
                        // Para queries comuns WHERE id = X
                        if (strpos($sql, 'WHERE ID =') !== false || strpos($sql, 'WHERE ID=') !== false) {
                            $data = array_filter($data, function($row) use ($value) {
                                return $row['id'] == $value;
                            });
                        }
                        // Para queries WHERE id_produto = X (vínculos)
                        elseif (strpos($sql, 'WHERE ID_PRODUTO =') !== false || strpos($sql, 'WHERE ID_PRODUTO=') !== false) {
                            $data = array_filter($data, function($row) use ($value) {
                                return isset($row['id_produto']) && $row['id_produto'] == $value;
                            });
                        }
                        // Para queries WHERE id_fornecedor = X (vínculos)
                        elseif (strpos($sql, 'WHERE ID_FORNECEDOR =') !== false || strpos($sql, 'WHERE ID_FORNECEDOR=') !== false
                               || strpos($sql, 'WHERE id_fornecedor =') !== false || strpos($sql, 'WHERE id_fornecedor=') !== false) {
                            $data = array_filter($data, function($row) use ($value) {
                                return isset($row['id_fornecedor']) && $row['id_fornecedor'] == $value;
                            });
                        }
                        // Para queries WHERE id_categoria = X (produtos por categoria)
                        elseif (strpos($sql, 'WHERE ID_CATEGORIA =') !== false || strpos($sql, 'WHERE ID_CATEGORIA=') !== false
                               || strpos($sql, 'WHERE id_categoria =') !== false || strpos($sql, 'WHERE id_categoria=') !== false) {
                            $data = array_filter($data, function($row) use ($value) {
                                return isset($row['id_categoria']) && $row['id_categoria'] == $value;
                            });
                        }
                        // Para exclusão WHERE id != X
                        else {
                            $data = array_filter($data, function($row) use ($value) {
                                return $row['id'] != $value;
                            });
                        }
                    }
                } // fim do else do tratamento especial
            }
            
            return [['total' => count($data)]];
        }
        
        // Se tem WHERE com ID, filtrar
        if (strpos($sql, 'WHERE') !== false && isset($params[':id'])) {
            $id = $params[':id'];
            $data = array_filter($data, function($row) use ($id) {
                return $row['id'] == $id;
            });
            $data = array_values($data);
        }
        
        return $data;
    } // fim do método execute()
    
    private function executeInsert($params) {
        // Implementar INSERT simples
        $sql = strtoupper(trim($this->sql));
        
        $table = '';
        if (strpos($sql, 'INTO FORNECEDORES') !== false) {
            $table = 'fornecedores';
        } elseif (strpos($sql, 'INTO PRODUTOS') !== false) {
            $table = 'produtos';
        } elseif (strpos($sql, 'INTO CATEGORIAS') !== false) {
            $table = 'categorias';
        } elseif (strpos($sql, 'INTO PRODUTO_FORNECEDOR') !== false) {
            $table = 'produto_fornecedor';
        }
        
        if ($table && !empty($params)) {
            // Remover : dos parâmetros
            $cleanParams = [];
            foreach ($params as $key => $value) {
                $cleanKey = ltrim($key, ':');
                $cleanParams[$cleanKey] = $value;
            }
            return $this->db->insert($table, $cleanParams);
        }
        
        return false;
    }
    
    private function executeUpdate($params) {
        // Implementar UPDATE simples
        return true;
    }
    
    private function executeDelete($params) {
        $sql = strtoupper(trim($this->sql));
        
        // Determinar tabela
        $table = '';
        if (strpos($sql, 'FROM FORNECEDORES') !== false) {
            $table = 'fornecedores';
        } elseif (strpos($sql, 'FROM PRODUTOS') !== false) {
            $table = 'produtos';
        } elseif (strpos($sql, 'FROM CATEGORIAS') !== false) {
            $table = 'categorias';
        } elseif (strpos($sql, 'FROM PRODUTO_FORNECEDOR') !== false) {
            $table = 'produto_fornecedor';
        }
        
        if (!$table) return false;
        
        // Se tem WHERE com ID, deletar
        if (strpos($sql, 'WHERE') !== false && isset($params[':id'])) {
            $id = $params[':id'];
            return $this->db->delete($table, $id);
        }
        
        return false;
    }
    
    public function fetchAll($mode = null) {
        // Executar se ainda não foi executado
        $results = $this->executeSelect($this->params);
        return $results ?: [];
    }
    
    public function fetch($mode = null) {
        $results = $this->fetchAll();
        return $results ? $results[0] : false;
    }
    
    public function rowCount() {
        $results = $this->fetchAll();
        return count($results);
    }
}