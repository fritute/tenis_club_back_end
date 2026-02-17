<?php
/**
 * Model para Categorias
 * Virtual Market System
 */

require_once __DIR__ . '/BaseModel.php';

class CategoriaModel extends BaseModel {
    
    public function __construct() {
        parent::__construct();
        $this->table_name = 'categorias';
        $this->primary_key = 'id';
    }

    /**
     * Validar dados da categoria
     * @param array $data
     * @return array Array com 'valid' (bool) e 'errors' (array)
     */
    public function validate($data) {
        $errors = [];
        
        // Validar nome
        if (empty($data['nome']) || strlen(trim($data['nome'])) < 2) {
            $errors['nome'] = 'Nome é obrigatório e deve ter pelo menos 2 caracteres';
        }
        
        // Validar status
        if (isset($data['status']) && !in_array($data['status'], ['Ativo', 'Inativo'])) {
            $errors['status'] = 'Status deve ser Ativo ou Inativo';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Buscar categorias ativas
     * @return array
     */
    public function findAtivas() {
        return $this->findAll("status = 'Ativo'", [], 'nome ASC');
    }

    /**
     * Verificar se nome já existe (para outra categoria)
     * @param string $nome
     * @param int $excludeId ID para excluir da verificação (para edição)
     * @return bool
     */
    public function nomeExists($nome, $excludeId = null) {
        try {
            $sql = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE nome = :nome";
            $params = [':nome' => $nome];
            
            if ($excludeId) {
                $sql .= " AND " . $this->primary_key . " != :id";
                $params[':id'] = $excludeId;
            }
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetch();
            return (int) $result['total'] > 0;
            
        } catch (PDOException $e) {
            error_log("Erro em nomeExists: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Contar produtos de uma categoria
     * @param int $id_categoria
     * @return int
     */
    public function countProdutos($id_categoria) {
        try {
            $sql = "SELECT COUNT(*) as total FROM produtos WHERE categoria_id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id_categoria);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return (int) $result['total'];
            
        } catch (PDOException $e) {
            error_log("Erro em countProdutos: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Buscar categorias com contagem de produtos
     * @return array
     */
    public function findWithProdutoCount() {
        try {
            $sql = "
                SELECT 
                    c.id,
                    c.nome,
                    c.descricao,
                    c.status,
                    c.data_criacao,
                    COUNT(p.id) as total_produtos,
                    COUNT(CASE WHEN p.status = 'Ativo' THEN 1 END) as produtos_ativos
                FROM categorias c
                LEFT JOIN produtos p ON c.id = p.categoria_id
                GROUP BY c.id
                ORDER BY c.nome
            ";
            
            return $this->executeQuery($sql);
            
        } catch (PDOException $e) {
            error_log("Erro em findWithProdutoCount: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Relatório de categorias mais usadas
     * @param int $limit
     * @return array
     */
    public function getCategoriasPopulares($limit = 10) {
        try {
            $sql = "
                SELECT 
                    c.id,
                    c.nome,
                    c.descricao,
                    COUNT(p.id) as total_produtos,
                    COUNT(CASE WHEN p.status = 'Ativo' THEN 1 END) as produtos_ativos,
                    COUNT(DISTINCT pf.fornecedor_id) as total_fornecedores
                FROM categorias c
                LEFT JOIN produtos p ON c.id = p.categoria_id
                LEFT JOIN produto_fornecedor pf ON (p.id = pf.produto_id OR p.id = pf.id_produto)
                WHERE c.status = 'Ativo'
                GROUP BY c.id
                HAVING total_produtos > 0
                ORDER BY total_produtos DESC, produtos_ativos DESC
                LIMIT :limit
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro em getCategoriasPopulares: " . $e->getMessage());
            return [];
        }
    }
}