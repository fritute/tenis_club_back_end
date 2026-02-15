<?php
/**
 * Model para Fornecedores
 * Virtual Market System
 */

require_once __DIR__ . '/BaseModel.php';

class FornecedorModel extends BaseModel {
    
    public function __construct() {
        parent::__construct();
        $this->table_name = 'fornecedores';
        $this->primary_key = 'id';
    }

    /**
     * Validar dados do fornecedor
     * @param array $data
     * @return array Array com 'valid' (bool) e 'errors' (array)
     */
    public function validate($data) {
        $errors = [];
        
        // Validar nome
        if (empty($data['nome']) || strlen(trim($data['nome'])) < 2) {
            $errors['nome'] = 'Nome é obrigatório e deve ter pelo menos 2 caracteres';
        }
        
        // Validar CNPJ
        if (empty($data['cnpj'])) {
            $errors['cnpj'] = 'CNPJ é obrigatório';
        } else {
            $cnpj = preg_replace('/[^0-9]/', '', $data['cnpj']);
            if (strlen($cnpj) != 14) {
                $errors['cnpj'] = 'CNPJ deve ter 14 dígitos';
            }
        }
        
        // Validar email
        if (empty($data['email'])) {
            $errors['email'] = 'E-mail é obrigatório';
        } else if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-mail inválido';
        }
        
        // Validar status
        if (!in_array($data['status'], ['Ativo', 'Inativo'])) {
            $errors['status'] = 'Status deve ser Ativo ou Inativo';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Buscar fornecedores ativos
     * @return array
     */
    public function findAtivos() {
        return $this->findAll("status = 'Ativo'", [], 'nome ASC');
    }

    /**
     * Verificar se CNPJ já existe (para outro fornecedor)
     * @param string $cnpj
     * @param int $excludeId ID para excluir da verificação (para edição)
     * @return bool
     */
    public function cnpjExists($cnpj, $excludeId = null) {
        try {
            $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
            
            $sql = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE cnpj = :cnpj";
            $params = [':cnpj' => $cnpj];
            
            if ($excludeId) {
                $sql .= " AND " . $this->primary_key . " != :id";
                $params[':id'] = $excludeId;
            }
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetch();
            return (int) $result['total'] > 0;
            
        } catch (PDOException $e) {
            error_log("Erro em cnpjExists: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar se email já existe (para outro fornecedor)
     * @param string $email
     * @param int $excludeId ID para excluir da verificação (para edição)
     * @return bool
     */
    public function emailExists($email, $excludeId = null) {
        try {
            $sql = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE email = :email";
            $params = [':email' => $email];
            
            if ($excludeId) {
                $sql .= " AND " . $this->primary_key . " != :id";
                $params[':id'] = $excludeId;
            }
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetch();
            return (int) $result['total'] > 0;
            
        } catch (PDOException $e) {
            error_log("Erro em emailExists: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Buscar fornecedores com filtros
     * @param array $filters
     * @return array
     */
    public function search($filters = []) {
        $where = [];
        $params = [];
        
        if (!empty($filters['nome'])) {
            $where[] = "nome LIKE :nome";
            $params[':nome'] = '%' . $filters['nome'] . '%';
        }
        
        if (!empty($filters['status'])) {
            $where[] = "status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['cnpj'])) {
            $cnpj = preg_replace('/[^0-9]/', '', $filters['cnpj']);
            $where[] = "cnpj LIKE :cnpj";
            $params[':cnpj'] = '%' . $cnpj . '%';
        }
        
        $whereClause = !empty($where) ? implode(' AND ', $where) : '';
        
        return $this->findAll($whereClause, $params, 'nome ASC');
    }

    /**
     * Contar produtos vinculados a um fornecedor
     * @param int $id_fornecedor
     * @return int
     */
    public function countProdutos($id_fornecedor) {
        try {
            $sql = "SELECT COUNT(*) as total FROM produto_fornecedor WHERE id_fornecedor = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id_fornecedor);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return (int) $result['total'];
            
        } catch (PDOException $e) {
            error_log("Erro em countProdutos: " . $e->getMessage());
            return 0;
        }
    }
}