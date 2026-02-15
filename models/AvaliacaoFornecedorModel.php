<?php
/**
 * Model para Avaliações de Fornecedores
 * Virtual Market System
 */

require_once __DIR__ . '/BaseModel.php';

class AvaliacaoFornecedorModel extends BaseModel {
    
    public function __construct() {
        parent::__construct();
        $this->table_name = 'avaliacoes_fornecedor';
        $this->primary_key = 'id_avaliacao';
    }

    /**
     * Validar dados da avaliação
     * @param array $data
     * @return array Array com 'valid' (bool) e 'errors' (array)
     */
    public function validate($data) {
        $errors = [];
        
        // Validar ID do fornecedor
        if (empty($data['id_fornecedor']) || !is_numeric($data['id_fornecedor'])) {
            $errors['id_fornecedor'] = 'ID do fornecedor é obrigatório';
        }
        
        // Validar notas (1-5)
        $notas = ['nota_qualidade', 'nota_preco', 'nota_prazo', 'nota_atendimento'];
        foreach ($notas as $nota) {
            if (isset($data[$nota])) {
                if (!is_numeric($data[$nota]) || $data[$nota] < 1 || $data[$nota] > 5) {
                    $errors[$nota] = 'Nota deve estar entre 1 e 5';
                }
            }
        }
        
        // Pelo menos uma nota deve ser informada
        $temNota = false;
        foreach ($notas as $nota) {
            if (!empty($data[$nota])) {
                $temNota = true;
                break;
            }
        }
        if (!$temNota) {
            $errors['notas'] = 'Pelo menos uma nota deve ser informada';
        }
        
        // Validar data da avaliação
        if (empty($data['data_avaliacao'])) {
            $errors['data_avaliacao'] = 'Data da avaliação é obrigatória';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Buscar avaliações de um fornecedor
     * @param int $id_fornecedor
     * @param int $id_produto (opcional)
     * @return array
     */
    public function getByFornecedor($id_fornecedor, $id_produto = null) {
        try {
            $sql = "
                SELECT 
                    av.*,
                    p.nome as produto_nome,
                    p.codigo_interno
                FROM " . $this->table_name . " av
                LEFT JOIN produtos p ON av.id_produto = p.id_produto
                WHERE av.id_fornecedor = :id_fornecedor
            ";
            
            $params = [':id_fornecedor' => $id_fornecedor];
            
            if ($id_produto) {
                $sql .= " AND av.id_produto = :id_produto";
                $params[':id_produto'] = $id_produto;
            }
            
            $sql .= " ORDER BY av.data_avaliacao DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro em getByFornecedor: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar média de avaliações de um fornecedor
     * @param int $id_fornecedor
     * @param int $id_produto (opcional)
     * @return array|null
     */
    public function getMediaAvaliacoes($id_fornecedor, $id_produto = null) {
        try {
            $sql = "
                SELECT 
                    COUNT(*) as total_avaliacoes,
                    AVG(nota_qualidade) as media_qualidade,
                    AVG(nota_preco) as media_preco,
                    AVG(nota_prazo) as media_prazo,
                    AVG(nota_atendimento) as media_atendimento,
                    AVG(nota_geral) as media_geral,
                    MIN(data_avaliacao) as primeira_avaliacao,
                    MAX(data_avaliacao) as ultima_avaliacao
                FROM " . $this->table_name . "
                WHERE id_fornecedor = :id_fornecedor
            ";
            
            $params = [':id_fornecedor' => $id_fornecedor];
            
            if ($id_produto) {
                $sql .= " AND id_produto = :id_produto";
                $params[':id_produto'] = $id_produto;
            }
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetch();
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("Erro em getMediaAvaliacoes: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Ranking de fornecedores por avaliação
     * @param int $limite
     * @param string $tipoNota ('geral', 'qualidade', 'preco', 'prazo', 'atendimento')
     * @return array
     */
    public function getRankingFornecedores($limite = 10, $tipoNota = 'geral') {
        try {
            $colunaNota = $tipoNota === 'geral' ? 'nota_geral' : 'nota_' . $tipoNota;
            
            $sql = "
                SELECT 
                    f.id_fornecedor,
                    f.nome as fornecedor_nome,
                    f.cnpj,
                    f.status,
                    COUNT(av.id_avaliacao) as total_avaliacoes,
                    AVG(av.{$colunaNota}) as media_nota,
                    AVG(av.nota_geral) as media_geral,
                    MAX(av.data_avaliacao) as ultima_avaliacao,
                    COUNT(DISTINCT av.id_produto) as produtos_avaliados
                FROM fornecedores f
                INNER JOIN " . $this->table_name . " av ON f.id_fornecedor = av.id_fornecedor
                WHERE f.status = 'Ativo'
                GROUP BY f.id_fornecedor
                HAVING COUNT(av.id_avaliacao) >= 1
                ORDER BY media_nota DESC, total_avaliacoes DESC
                LIMIT :limite
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro em getRankingFornecedores: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Relatório de avaliações por período
     * @param string $dataInicio (Y-m-d)
     * @param string $dataFim (Y-m-d)
     * @return array
     */
    public function getRelatoriorPorPeriodo($dataInicio, $dataFim) {
        try {
            $sql = "
                SELECT 
                    f.nome as fornecedor_nome,
                    p.nome as produto_nome,
                    av.nota_qualidade,
                    av.nota_preco,
                    av.nota_prazo,
                    av.nota_atendimento,
                    av.nota_geral,
                    av.comentarios,
                    av.data_avaliacao,
                    av.avaliado_por
                FROM " . $this->table_name . " av
                INNER JOIN fornecedores f ON av.id_fornecedor = f.id_fornecedor
                LEFT JOIN produtos p ON av.id_produto = p.id_produto
                WHERE av.data_avaliacao BETWEEN :data_inicio AND :data_fim
                ORDER BY av.data_avaliacao DESC, f.nome
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':data_inicio', $dataInicio);
            $stmt->bindParam(':data_fim', $dataFim);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro em getRelatoriorPorPeriodo: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Análise de tendência de avaliações
     * @param int $id_fornecedor
     * @param int $meses (quantos meses atrás analisar)
     * @return array
     */
    public function getTendenciaAvaliacoes($id_fornecedor, $meses = 6) {
        try {
            $sql = "
                SELECT 
                    DATE_FORMAT(data_avaliacao, '%Y-%m') as mes_ano,
                    COUNT(*) as total_avaliacoes,
                    AVG(nota_geral) as media_mensal,
                    AVG(nota_qualidade) as media_qualidade,
                    AVG(nota_preco) as media_preco,
                    AVG(nota_prazo) as media_prazo,
                    AVG(nota_atendimento) as media_atendimento
                FROM " . $this->table_name . "
                WHERE id_fornecedor = :id_fornecedor
                AND data_avaliacao >= DATE_SUB(CURDATE(), INTERVAL :meses MONTH)
                GROUP BY DATE_FORMAT(data_avaliacao, '%Y-%m')
                ORDER BY mes_ano DESC
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id_fornecedor', $id_fornecedor);
            $stmt->bindParam(':meses', $meses);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro em getTendenciaAvaliacoes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fornecedores que precisam de avaliação
     * @param int $diasSemAvaliacao
     * @return array
     */
    public function getFornecedoresSemAvaliacao($diasSemAvaliacao = 90) {
        try {
            $sql = "
                SELECT 
                    f.id_fornecedor,
                    f.nome as fornecedor_nome,
                    f.cnpj,
                    COUNT(pf.id_produto) as produtos_vinculados,
                    MAX(av.data_avaliacao) as ultima_avaliacao,
                    DATEDIFF(CURDATE(), MAX(av.data_avaliacao)) as dias_sem_avaliacao
                FROM fornecedores f
                INNER JOIN produto_fornecedor pf ON f.id_fornecedor = pf.id_fornecedor
                LEFT JOIN " . $this->table_name . " av ON f.id_fornecedor = av.id_fornecedor
                WHERE f.status = 'Ativo' AND pf.status_vinculo = 'Ativo'
                GROUP BY f.id_fornecedor
                HAVING ultima_avaliacao IS NULL 
                OR dias_sem_avaliacao > :dias
                ORDER BY dias_sem_avaliacao DESC NULLS FIRST
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':dias', $diasSemAvaliacao);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro em getFornecedoresSemAvaliacao: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar avaliações com comentários
     * @param int $limite
     * @param bool $apenasPositivas
     * @return array
     */
    public function getAvaliacoesComComentarios($limite = 20, $apenasPositivas = false) {
        try {
            $sql = "
                SELECT 
                    av.*,
                    f.nome as fornecedor_nome,
                    p.nome as produto_nome,
                    p.codigo_interno
                FROM " . $this->table_name . " av
                INNER JOIN fornecedores f ON av.id_fornecedor = f.id_fornecedor
                LEFT JOIN produtos p ON av.id_produto = p.id_produto
                WHERE av.comentarios IS NOT NULL 
                AND av.comentarios != ''
            ";
            
            if ($apenasPositivas) {
                $sql .= " AND av.nota_geral >= 4.0";
            }
            
            $sql .= " ORDER BY av.data_avaliacao DESC LIMIT :limite";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro em getAvaliacoesComComentarios: " . $e->getMessage());
            return [];
        }
    }
}