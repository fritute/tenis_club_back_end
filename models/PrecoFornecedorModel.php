<?php
/**
 * Model para Preços de Fornecedores
 * Virtual Market System
 */

require_once __DIR__ . '/BaseModel.php';

class PrecoFornecedorModel extends BaseModel {
    
    public function __construct() {
        parent::__construct();
        $this->table_name = 'precos_fornecedor';
        $this->primary_key = 'id_preco';
    }

    /**
     * Validar dados do preço
     * @param array $data
     * @return array Array com 'valid' (bool) e 'errors' (array)
     */
    public function validate($data) {
        $errors = [];
        
        // Validar IDs
        if (empty($data['id_produto']) || !is_numeric($data['id_produto'])) {
            $errors['id_produto'] = 'ID do produto é obrigatório';
        }
        
        if (empty($data['id_fornecedor']) || !is_numeric($data['id_fornecedor'])) {
            $errors['id_fornecedor'] = 'ID do fornecedor é obrigatório';
        }
        
        // Validar preço
        if (empty($data['preco_unitario']) || !is_numeric($data['preco_unitario']) || $data['preco_unitario'] <= 0) {
            $errors['preco_unitario'] = 'Preço unitário é obrigatório e deve ser maior que zero';
        }
        
        // Validar data de início
        if (empty($data['data_vigencia_inicio'])) {
            $errors['data_vigencia_inicio'] = 'Data de início da vigência é obrigatória';
        }
        
        // Validar datas (fim deve ser maior que início se fornecida)
        if (!empty($data['data_vigencia_inicio']) && !empty($data['data_vigencia_fim'])) {
            if (strtotime($data['data_vigencia_fim']) <= strtotime($data['data_vigencia_inicio'])) {
                $errors['data_vigencia_fim'] = 'Data de fim deve ser posterior à data de início';
            }
        }
        
        // Validar quantidade mínima
        if (isset($data['quantidade_minima']) && (!is_numeric($data['quantidade_minima']) || $data['quantidade_minima'] < 1)) {
            $errors['quantidade_minima'] = 'Quantidade mínima deve ser um número maior que zero';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Buscar preços vigentes por produto
     * @param int $id_produto
     * @return array
     */
    public function getPrecosPorProduto($id_produto) {
        try {
            $sql = "
                SELECT 
                    pf.*,
                    f.nome as fornecedor_nome,
                    f.cnpj,
                    f.status as fornecedor_status,
                    AVG(av.nota_geral) as media_avaliacao
                FROM " . $this->table_name . " pf
                INNER JOIN fornecedores f ON pf.id_fornecedor = f.id_fornecedor
                LEFT JOIN avaliacoes_fornecedor av ON f.id_fornecedor = av.id_fornecedor AND av.id_produto = pf.id_produto
                WHERE pf.id_produto = :id_produto 
                AND pf.status = 'Ativo'
                AND (pf.data_vigencia_fim IS NULL OR pf.data_vigencia_fim >= CURDATE())
                GROUP BY pf.id_preco
                ORDER BY pf.preco_unitario ASC
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id_produto', $id_produto);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro em getPrecosPorProduto: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar preços por fornecedor
     * @param int $id_fornecedor
     * @return array
     */
    public function getPrecosPorFornecedor($id_fornecedor) {
        try {
            $sql = "
                SELECT 
                    pf.*,
                    p.nome as produto_nome,
                    p.codigo_interno,
                    p.status as produto_status,
                    c.nome as categoria_nome
                FROM " . $this->table_name . " pf
                INNER JOIN produtos p ON pf.id_produto = p.id_produto
                LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
                WHERE pf.id_fornecedor = :id_fornecedor 
                AND pf.status = 'Ativo'
                ORDER BY p.nome
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id_fornecedor', $id_fornecedor);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro em getPrecosPorFornecedor: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar melhor preço para um produto
     * @param int $id_produto
     * @param int $quantidade
     * @return array|null
     */
    public function getMelhorPreco($id_produto, $quantidade = 1) {
        try {
            $sql = "
                SELECT 
                    pf.*,
                    f.nome as fornecedor_nome,
                    f.cnpj,
                    AVG(av.nota_geral) as media_avaliacao
                FROM " . $this->table_name . " pf
                INNER JOIN fornecedores f ON pf.id_fornecedor = f.id_fornecedor
                LEFT JOIN avaliacoes_fornecedor av ON f.id_fornecedor = av.id_fornecedor
                WHERE pf.id_produto = :id_produto 
                AND pf.status = 'Ativo'
                AND pf.quantidade_minima <= :quantidade
                AND (pf.data_vigencia_fim IS NULL OR pf.data_vigencia_fim >= CURDATE())
                AND f.status = 'Ativo'
                GROUP BY pf.id_preco
                ORDER BY pf.preco_unitario ASC
                LIMIT 1
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id_produto', $id_produto);
            $stmt->bindParam(':quantidade', $quantidade);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("Erro em getMelhorPreco: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Comparativo de preços entre fornecedores
     * @param int $id_produto
     * @return array
     */
    public function getComparativoPrecos($id_produto) {
        try {
            $sql = "
                SELECT 
                    p.nome as produto_nome,
                    p.codigo_interno,
                    f.nome as fornecedor_nome,
                    pf.preco_unitario,
                    pf.quantidade_minima,
                    pf.prazo_entrega_dias,
                    pf.data_vigencia_inicio,
                    pf.data_vigencia_fim,
                    AVG(av.nota_geral) as media_avaliacao,
                    RANK() OVER (ORDER BY pf.preco_unitario ASC) as ranking_preco,
                    CASE 
                        WHEN pf.preco_unitario = MIN(pf.preco_unitario) OVER () THEN 'MELHOR_PRECO'
                        WHEN AVG(av.nota_geral) >= 4.0 THEN 'BOA_AVALIACAO'
                        ELSE 'PADRAO'
                    END as categoria_recomendacao
                FROM " . $this->table_name . " pf
                INNER JOIN produtos p ON pf.id_produto = p.id_produto
                INNER JOIN fornecedores f ON pf.id_fornecedor = f.id_fornecedor
                LEFT JOIN avaliacoes_fornecedor av ON f.id_fornecedor = av.id_fornecedor AND av.id_produto = p.id_produto
                WHERE pf.id_produto = :id_produto 
                AND pf.status = 'Ativo'
                AND (pf.data_vigencia_fim IS NULL OR pf.data_vigencia_fim >= CURDATE())
                AND f.status = 'Ativo'
                GROUP BY pf.id_preco
                ORDER BY pf.preco_unitario ASC
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id_produto', $id_produto);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro em getComparativoPrecos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Atualizar preços expirados automaticamente
     * @return int Número de preços atualizados
     */
    public function atualizarPrecosExpirados() {
        try {
            $sql = "UPDATE " . $this->table_name . " 
                    SET status = 'Expirado' 
                    WHERE status = 'Ativo' 
                    AND data_vigencia_fim IS NOT NULL 
                    AND data_vigencia_fim < CURDATE()";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            
            return $stmt->rowCount();
            
        } catch (PDOException $e) {
            error_log("Erro em atualizarPrecosExpirados: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Histórico de preços de um produto/fornecedor
     * @param int $id_produto
     * @param int $id_fornecedor
     * @param int $limit
     * @return array
     */
    public function getHistoricoPrecos($id_produto, $id_fornecedor = null, $limit = 20) {
        try {
            $sql = "
                SELECT 
                    pf.*,
                    p.nome as produto_nome,
                    f.nome as fornecedor_nome
                FROM " . $this->table_name . " pf
                INNER JOIN produtos p ON pf.id_produto = p.id_produto
                INNER JOIN fornecedores f ON pf.id_fornecedor = f.id_fornecedor
                WHERE pf.id_produto = :id_produto
            ";
            
            $params = [':id_produto' => $id_produto];
            
            if ($id_fornecedor) {
                $sql .= " AND pf.id_fornecedor = :id_fornecedor";
                $params[':id_fornecedor'] = $id_fornecedor;
            }
            
            $sql .= " ORDER BY pf.data_vigencia_inicio DESC LIMIT :limit";
            
            $stmt = $this->conn->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro em getHistoricoPrecos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verificar se existe conflito de vigência
     * @param array $data
     * @param int $excludeId
     * @return bool
     */
    public function verificarConflitoVigencia($data, $excludeId = null) {
        try {
            $sql = "
                SELECT COUNT(*) as total 
                FROM " . $this->table_name . " 
                WHERE id_produto = :id_produto 
                AND id_fornecedor = :id_fornecedor
                AND status = 'Ativo'
                AND (
                    (data_vigencia_fim IS NULL AND :data_inicio <= CURDATE())
                    OR 
                    (data_vigencia_fim IS NOT NULL AND :data_inicio <= data_vigencia_fim AND (:data_fim IS NULL OR :data_fim >= data_vigencia_inicio))
                )
            ";
            
            $params = [
                ':id_produto' => $data['id_produto'],
                ':id_fornecedor' => $data['id_fornecedor'],
                ':data_inicio' => $data['data_vigencia_inicio'],
                ':data_fim' => $data['data_vigencia_fim'] ?? null
            ];
            
            if ($excludeId) {
                $sql .= " AND id_preco != :exclude_id";
                $params[':exclude_id'] = $excludeId;
            }
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetch();
            return (int) $result['total'] > 0;
            
        } catch (PDOException $e) {
            error_log("Erro em verificarConflitoVigencia: " . $e->getMessage());
            return false;
        }
    }
}