<?php
/**
 * Model para Produtos
 * Virtual Market System
 */

require_once __DIR__ . '/BaseModel.php';

class ProdutoModel extends BaseModel {
    
    public function __construct() {
        parent::__construct();
        $this->table_name = 'produtos';
        $this->primary_key = 'id'; // Corrigido: JSON usa 'id', não 'id_produto'
    }

    /**
     * Validar dados do produto
     * @param array $data
     * @return array Array com 'valid' (bool) e 'errors' (array)
     */
    public function validate($data) {
        $errors = [];
        
        // Validar nome
        if (empty($data['nome']) || strlen(trim($data['nome'])) < 2) {
            $errors['nome'] = 'Nome é obrigatório e deve ter pelo menos 2 caracteres';
        }
        
        // Validar código interno (OPCIONAL)
        if (!empty($data['codigo_interno']) && strlen(trim($data['codigo_interno'])) < 3) {
            $errors['codigo_interno'] = 'Código interno deve ter pelo menos 3 caracteres quando informado';
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
     * Buscar produtos ativos
     * @return array
     */
    public function findAtivos() {
        return $this->findAll("status = 'Ativo'", [], 'nome ASC');
    }

    /**
     * Verificar se código interno já existe (para outro produto)
     * @param string $codigo_interno
     * @param int $excludeId ID para excluir da verificação (para edição)
     * @return bool
     */
    public function codigoInternoExists($codigo_interno, $excludeId = null) {
        try {
            $sql = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE codigo_interno = :codigo";
            $params = [':codigo' => $codigo_interno];
            
            if ($excludeId) {
                $sql .= " AND " . $this->primary_key . " != :id";
                $params[':id'] = $excludeId;
            }
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetch();
            return (int) $result['total'] > 0;
            
        } catch (PDOException $e) {
            error_log("Erro em codigoInternoExists: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Buscar produtos com filtros
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
        
        if (!empty($filters['codigo_interno'])) {
            $where[] = "codigo_interno LIKE :codigo";
            $params[':codigo'] = '%' . $filters['codigo_interno'] . '%';
        }
        
        if (!empty($filters['descricao'])) {
            $where[] = "descricao LIKE :descricao";
            $params[':descricao'] = '%' . $filters['descricao'] . '%';
        }
        
        $whereClause = !empty($where) ? implode(' AND ', $where) : '';
        
        return $this->findAll($whereClause, $params, 'nome ASC');
    }

    /**
     * Contar fornecedores vinculados a um produto
     * @param int $id_produto
     * @return int
     */
    public function countFornecedores($id_produto) {
        try {
            $sql = "SELECT COUNT(*) as total FROM produto_fornecedor WHERE id_produto = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id_produto);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return (int) $result['total'];
            
        } catch (PDOException $e) {
            error_log("Erro em countFornecedores: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Buscar produtos com seus fornecedores (JOIN)
     * @param int $id_produto ID específico ou null para todos
     * @return array
     */
    public function findWithFornecedores($id_produto = null) {
        try {
            $sql = "
                SELECT 
                    p.id_produto,
                    p.nome as produto_nome,
                    p.descricao,
                    p.codigo_interno,
                    p.status as produto_status,
                    p.created_at as produto_created_at,
                    f.id_fornecedor,
                    f.nome as fornecedor_nome,
                    f.cnpj,
                    f.email,
                    f.telefone,
                    f.status as fornecedor_status
                FROM produtos p
                LEFT JOIN produto_fornecedor pf ON p.id_produto = pf.id_produto
                LEFT JOIN fornecedores f ON pf.id_fornecedor = f.id_fornecedor
            ";
            
            $params = [];
            if ($id_produto) {
                $sql .= " WHERE p.id_produto = :id_produto";
                $params[':id_produto'] = $id_produto;
            }
            
            $sql .= " ORDER BY p.nome, f.nome";
            
            return $this->executeQuery($sql, $params);
            
        } catch (PDOException $e) {
            error_log("Erro em findWithFornecedores: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar produtos sem fornecedores
     * @return array
     */
    public function findSemFornecedores() {
        try {
            $sql = "
                SELECT p.*
                FROM produtos p
                LEFT JOIN produto_fornecedor pf ON p.id_produto = pf.id_produto
                WHERE pf.id_produto IS NULL
                ORDER BY p.nome
            ";
            
            return $this->executeQuery($sql);
            
        } catch (PDOException $e) {
            error_log("Erro em findSemFornecedores: " . $e->getMessage());
            return [];
        }
    }
}