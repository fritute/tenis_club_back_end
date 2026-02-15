<?php
/**
 * Model para Vínculos Produto-Fornecedor
 * Virtual Market System
 */

require_once __DIR__ . '/BaseModel.php';

class ProdutoFornecedorModel extends BaseModel {
    
    public function __construct() {
        parent::__construct();
        $this->table_name = 'produto_fornecedor';
        // Esta tabela tem chave composta, então não usaremos primary_key único
    }

    /**
     * Criar vínculo entre produto e fornecedor
     * @param int $id_produto
     * @param int $id_fornecedor
     * @return bool
     */
    public function criarVinculo($id_produto, $id_fornecedor) {
        try {
            // Verificar se o vínculo já existe
            if ($this->vinculoExists($id_produto, $id_fornecedor)) {
                return false; // Já existe
            }
            
            $sql = "INSERT INTO produto_fornecedor (id_produto, id_fornecedor) VALUES (:id_produto, :id_fornecedor)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id_produto', $id_produto);
            $stmt->bindParam(':id_fornecedor', $id_fornecedor);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Erro em criarVinculo: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remover vínculo específico
     * @param int $id_produto
     * @param int $id_fornecedor
     * @return bool
     */
    public function removerVinculo($id_produto, $id_fornecedor) {
        try {
            $sql = "DELETE FROM produto_fornecedor WHERE id_produto = :id_produto AND id_fornecedor = :id_fornecedor";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id_produto', $id_produto);
            $stmt->bindParam(':id_fornecedor', $id_fornecedor);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Erro em removerVinculo: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remover todos os vínculos de um produto
     * @param int $id_produto
     * @return bool
     */
    public function removerTodosVinculosProduto($id_produto) {
        try {
            $sql = "DELETE FROM produto_fornecedor WHERE id_produto = :id_produto";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id_produto', $id_produto);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Erro em removerTodosVinculosProduto: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remover vínculos em massa (por array de IDs)
     * @param array $vinculos Array de ['id_produto' => x, 'id_fornecedor' => y]
     * @return bool
     */
    public function removerVinculosEmMassa($vinculos) {
        try {
            $this->conn->beginTransaction();
            
            $sql = "DELETE FROM produto_fornecedor WHERE id_produto = :id_produto AND id_fornecedor = :id_fornecedor";
            $stmt = $this->conn->prepare($sql);
            
            foreach ($vinculos as $vinculo) {
                $stmt->bindParam(':id_produto', $vinculo['id_produto']);
                $stmt->bindParam(':id_fornecedor', $vinculo['id_fornecedor']);
                $stmt->execute();
            }
            
            $this->conn->commit();
            return true;
            
        } catch (PDOException $e) {
            $this->conn->rollback();
            error_log("Erro em removerVinculosEmMassa: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar se vínculo já existe
     * @param int $id_produto
     * @param int $id_fornecedor
     * @return bool
     */
    public function vinculoExists($id_produto, $id_fornecedor) {
        try {
            $sql = "SELECT COUNT(*) as total FROM produto_fornecedor WHERE id_produto = :id_produto AND id_fornecedor = :id_fornecedor";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id_produto', $id_produto);
            $stmt->bindParam(':id_fornecedor', $id_fornecedor);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return (int) $result['total'] > 0;
            
        } catch (PDOException $e) {
            error_log("Erro em vinculoExists: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Buscar fornecedores de um produto (JOIN)
     * @param int $id_produto
     * @return array
     */
    public function getFornecedoresPorProduto($id_produto) {
        try {
            $sql = "
                SELECT 
                    f.id_fornecedor,
                    f.nome,
                    f.cnpj,
                    f.email,
                    f.telefone,
                    f.status,
                    pf.created_at as vinculo_created_at
                FROM fornecedores f
                INNER JOIN produto_fornecedor pf ON f.id_fornecedor = pf.id_fornecedor
                WHERE pf.id_produto = :id_produto
                ORDER BY f.nome
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id_produto', $id_produto);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro em getFornecedoresPorProduto: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar produtos de um fornecedor (JOIN)
     * @param int $id_fornecedor
     * @return array
     */
    public function getProdutosPorFornecedor($id_fornecedor) {
        try {
            $sql = "
                SELECT 
                    p.id_produto,
                    p.nome,
                    p.descricao,
                    p.codigo_interno,
                    p.status,
                    pf.created_at as vinculo_created_at
                FROM produtos p
                INNER JOIN produto_fornecedor pf ON p.id_produto = pf.id_produto
                WHERE pf.id_fornecedor = :id_fornecedor
                ORDER BY p.nome
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id_fornecedor', $id_fornecedor);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro em getProdutosPorFornecedor: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar todos os vínculos com dados completos (JOIN complexo)
     * @param array $filters Filtros opcionais
     * @return array
     */
    public function getAllVinculos($filters = []) {
        try {
            $sql = "
                SELECT 
                    p.id_produto,
                    p.nome as produto_nome,
                    p.codigo_interno,
                    p.status as produto_status,
                    f.id_fornecedor,
                    f.nome as fornecedor_nome,
                    f.cnpj,
                    f.email,
                    f.status as fornecedor_status,
                    pf.created_at as vinculo_created_at
                FROM produto_fornecedor pf
                INNER JOIN produtos p ON pf.id_produto = p.id_produto
                INNER JOIN fornecedores f ON pf.id_fornecedor = f.id_fornecedor
            ";
            
            $where = [];
            $params = [];
            
            // Aplicar filtros
            if (!empty($filters['produto_nome'])) {
                $where[] = "p.nome LIKE :produto_nome";
                $params[':produto_nome'] = '%' . $filters['produto_nome'] . '%';
            }
            
            if (!empty($filters['fornecedor_nome'])) {
                $where[] = "f.nome LIKE :fornecedor_nome";
                $params[':fornecedor_nome'] = '%' . $filters['fornecedor_nome'] . '%';
            }
            
            if (!empty($filters['produto_status'])) {
                $where[] = "p.status = :produto_status";
                $params[':produto_status'] = $filters['produto_status'];
            }
            
            if (!empty($filters['fornecedor_status'])) {
                $where[] = "f.status = :fornecedor_status";
                $params[':fornecedor_status'] = $filters['fornecedor_status'];
            }
            
            if (!empty($where)) {
                $sql .= " WHERE " . implode(' AND ', $where);
            }
            
            $sql .= " ORDER BY p.nome, f.nome";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro em getAllVinculos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Relatório de produtos sem fornecedores
     * @return array
     */
    public function getProdutosSemFornecedores() {
        try {
            $sql = "
                SELECT 
                    p.id_produto,
                    p.nome,
                    p.descricao,
                    p.codigo_interno,
                    p.status
                FROM produtos p
                LEFT JOIN produto_fornecedor pf ON p.id_produto = pf.id_produto
                WHERE pf.id_produto IS NULL
                ORDER BY p.nome
            ";
            
            return $this->executeQuery($sql);
            
        } catch (PDOException $e) {
            error_log("Erro em getProdutosSemFornecedores: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Relatório de fornecedores sem produtos
     * @return array
     */
    public function getFornecedoresSemProdutos() {
        try {
            $sql = "
                SELECT 
                    f.id_fornecedor,
                    f.nome,
                    f.cnpj,
                    f.email,
                    f.telefone,
                    f.status
                FROM fornecedores f
                LEFT JOIN produto_fornecedor pf ON f.id_fornecedor = pf.id_fornecedor
                WHERE pf.id_fornecedor IS NULL
                ORDER BY f.nome
            ";
            
            return $this->executeQuery($sql);
            
        } catch (PDOException $e) {
            error_log("Erro em getFornecedoresSemProdutos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Estatísticas dos vínculos
     * @return array
     */
    public function getEstatisticas() {
        try {
            $sql = "
                SELECT 
                    COUNT(*) as total_vinculos,
                    COUNT(DISTINCT pf.id_produto) as produtos_com_fornecedor,
                    COUNT(DISTINCT pf.id_fornecedor) as fornecedores_com_produto,
                    (SELECT COUNT(*) FROM produtos) as total_produtos,
                    (SELECT COUNT(*) FROM fornecedores) as total_fornecedores
                FROM produto_fornecedor pf
            ";
            
            $result = $this->executeQuery($sql);
            return $result[0] ?? [];
            
        } catch (PDOException $e) {
            error_log("Erro em getEstatisticas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar vínculo por ID
     * @param int $id
     * @return array|null
     */
    public function findById($id) {
        try {
            $sql = "SELECT * FROM produto_fornecedor WHERE id = :id";
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
     * Deletar vínculo por ID
     * @param int $id
     * @return bool
     */
    public function deleteById($id) {
        try {
            $sql = "DELETE FROM produto_fornecedor WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Erro em deleteById: " . $e->getMessage());
            return false;
        }
    }
}