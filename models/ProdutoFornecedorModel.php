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
        $this->primary_key = 'id';
    }

    /**
     * Definir fornecedor principal para um produto
     * @param int $id_produto
     * @param int $id_fornecedor
     * @return bool
     */
    public function setPrincipal($id_produto, $id_fornecedor) {
        try {
            $this->conn->beginTransaction();
            
            // 1. Resetar todos os vínculos deste produto para não principal
            $sqlReset = "UPDATE " . $this->table_name . " 
                         SET is_principal = 0 
                         WHERE produto_id = :id_produto OR id_produto = :id_produto2";
            $stmtReset = $this->conn->prepare($sqlReset);
            $stmtReset->bindParam(':id_produto', $id_produto);
            $stmtReset->bindParam(':id_produto2', $id_produto);
            $stmtReset->execute();
            
            // 2. Definir o novo principal
            $sqlSet = "UPDATE " . $this->table_name . " 
                       SET is_principal = 1 
                       WHERE (produto_id = :id_produto OR id_produto = :id_produto2)
                       AND (fornecedor_id = :id_fornecedor OR id_fornecedor = :id_fornecedor2)";
            $stmtSet = $this->conn->prepare($sqlSet);
            $stmtSet->bindParam(':id_produto', $id_produto);
            $stmtSet->bindParam(':id_produto2', $id_produto);
            $stmtSet->bindParam(':id_fornecedor', $id_fornecedor);
            $stmtSet->bindParam(':id_fornecedor2', $id_fornecedor);
            $stmtSet->execute();
            
            $this->conn->commit();
            return true;
            
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Erro em setPrincipal: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Buscar histórico simples de vínculos (ordenado por data de criação)
     * @param int $id_produto (Opcional)
     * @return array
     */
    public function getHistorico($id_produto = null) {
        try {
            $sql = "
                SELECT 
                    pf.id,
                    pf.produto_id,
                    pf.fornecedor_id,
                    pf.status,
                    pf.is_principal,
                    pf.data_vinculo,
                    p.nome as produto_nome,
                    f.nome as fornecedor_nome
                FROM produto_fornecedor pf
                INNER JOIN produtos p ON (pf.produto_id = p.id OR pf.id_produto = p.id)
                INNER JOIN fornecedores f ON (pf.fornecedor_id = f.id OR pf.id_fornecedor = f.id)
            ";
            
            $params = [];
            if ($id_produto) {
                $sql .= " WHERE pf.produto_id = :id_produto OR pf.id_produto = :id_produto2";
                $params[':id_produto'] = $id_produto;
                $params[':id_produto2'] = $id_produto;
            }
            
            $sql .= " ORDER BY pf.data_vinculo DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Erro em getHistorico: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Criar vínculo entre produto e fornecedor
     * @param int $id_produto
     * @param int $id_fornecedor
     * @param array $dadosExtras Dados adicionais opcionais (preco_fornecedor, status, etc.)
     * @return bool
     */
    public function criarVinculo($id_produto, $id_fornecedor, $dadosExtras = []) {
        try {
            // Verificar se o vínculo já existe
            if ($this->vinculoExists($id_produto, $id_fornecedor)) {
                return false; // Já existe
            }
            
            // O schema tem tanto produto_id quanto id_produto (e fornecedor_id/id_fornecedor)
            // Precisamos preencher AMBOS para evitar erro de campo NOT NULL sem valor
            $data = array_merge([
                'produto_id' => $id_produto,
                'id_produto' => $id_produto,       // Legado obrigatório
                'fornecedor_id' => $id_fornecedor,
                'id_fornecedor' => $id_fornecedor, // Legado obrigatório
                'status' => 'Ativo'
            ], $dadosExtras);
            
            return $this->create($data) > 0;
            
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
            $sql = "DELETE FROM " . $this->table_name . " 
                    WHERE produto_id = :id_produto 
                    AND fornecedor_id = :id_fornecedor";
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
            $sql = "DELETE FROM " . $this->table_name . " WHERE produto_id = :id_produto OR id_produto = :id_produto2";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id_produto', $id_produto);
            $stmt->bindParam(':id_produto2', $id_produto);
            
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
            
            foreach ($vinculos as $vinculo) {
                if (isset($vinculo['id_produto'], $vinculo['id_fornecedor'])) {
                    $this->removerVinculo($vinculo['id_produto'], $vinculo['id_fornecedor']);
                }
            }
            
            $this->conn->commit();
            return true;
            
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
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
            $sql = "SELECT COUNT(*) as total FROM " . $this->table_name . " 
                    WHERE (produto_id = :id_produto OR id_produto = :id_produto2) 
                    AND (fornecedor_id = :id_fornecedor OR id_fornecedor = :id_fornecedor2)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id_produto', $id_produto);
            $stmt->bindParam(':id_produto2', $id_produto);
            $stmt->bindParam(':id_fornecedor', $id_fornecedor);
            $stmt->bindParam(':id_fornecedor2', $id_fornecedor);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return (int) ($result['total'] ?? 0) > 0;
            
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
                    f.id,
                    f.nome,
                    f.cnpj,
                    f.email,
                    f.telefone,
                    f.status,
                    pf.data_vinculo as vinculo_created_at
                FROM fornecedores f
                INNER JOIN produto_fornecedor pf ON f.id = pf.fornecedor_id
                WHERE pf.produto_id = :id_produto
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
                    p.id,
                    p.nome,
                    p.descricao,
                    p.codigo_interno,
                    p.status,
                    pf.data_vinculo as vinculo_created_at
                FROM produtos p
                INNER JOIN produto_fornecedor pf ON p.id = pf.produto_id
                WHERE pf.fornecedor_id = :id_fornecedor
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
                    pf.id,
                    pf.produto_id as id_produto,
                    pf.fornecedor_id as id_fornecedor,
                    pf.preco_fornecedor,
                    pf.status,
                    pf.data_vinculo,
                    p.nome as produto_nome,
                    p.codigo_interno,
                    p.status as produto_status,
                    f.nome as fornecedor_nome,
                    f.cnpj,
                    f.email,
                    f.status as fornecedor_status
                FROM produto_fornecedor pf
                INNER JOIN produtos p ON (pf.produto_id = p.id OR pf.id_produto = p.id)
                INNER JOIN fornecedores f ON (pf.fornecedor_id = f.id OR pf.id_fornecedor = f.id)
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
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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
                    p.id,
                    p.nome,
                    p.descricao,
                    p.codigo_interno,
                    p.status
                FROM produtos p
                LEFT JOIN produto_fornecedor pf ON p.id = pf.produto_id
                WHERE pf.produto_id IS NULL
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
                    f.id,
                    f.nome,
                    f.cnpj,
                    f.email,
                    f.telefone,
                    f.status
                FROM fornecedores f
                LEFT JOIN produto_fornecedor pf ON f.id = pf.fornecedor_id
                WHERE pf.fornecedor_id IS NULL
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
                    COUNT(DISTINCT COALESCE(pf.produto_id, pf.id_produto)) as produtos_com_fornecedor,
                    COUNT(DISTINCT COALESCE(pf.fornecedor_id, pf.id_fornecedor)) as fornecedores_com_produto,
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


}