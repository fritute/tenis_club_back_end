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
        
        // Definir status padrão se não informado
        if (!isset($data['status']) || empty($data['status'])) {
            $data['status'] = 'Ativo';
        }
        
        // Validar status
        if (!in_array($data['status'], ['Ativo', 'Inativo'])) {
            $errors['status'] = 'Status deve ser Ativo ou Inativo';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $data // Retornar dados modificados com valores padrão
        ];
    }

    /**
     * Buscar produtos disponíveis para vínculo (que o fornecedor ainda não tem)
     * @param int $fornecedor_id
     * @return array
     */
    public function findDisponiveisParaVinculo($fornecedor_id) {
        try {
            $sql = "
                SELECT p.* 
                FROM produtos p
                WHERE p.status = 'Ativo'
                AND (p.fornecedor_id IS NULL OR p.fornecedor_id != :meu_id)
                AND NOT EXISTS (
                    SELECT 1 FROM produto_fornecedor pf 
                    WHERE (pf.produto_id = p.id OR pf.id_produto = p.id) 
                    AND (pf.fornecedor_id = :meu_id2 OR pf.id_fornecedor = :meu_id3)
                )
                ORDER BY p.nome
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':meu_id', $fornecedor_id);
            $stmt->bindParam(':meu_id2', $fornecedor_id);
            $stmt->bindParam(':meu_id3', $fornecedor_id);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Erro em findDisponiveisParaVinculo: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar produtos ativos
     * @return array
     */
    public function findAtivos() {
        if (!$this->conn) {
            $rows = $this->findAll('', [], 'nome ASC');
            $out = [];
            foreach ($rows as $r) {
                $st = $r['status'] ?? '';
                if (strcasecmp($st, 'Ativo') === 0 || strcasecmp($st, 'ativo') === 0) {
                    $out[] = $r;
                }
            }
            return $out;
        }
        return $this->findAll("status = 'Ativo'", [], 'nome ASC');
    }

    /**
     * Verificar se código interno já existe (para outro produto)
     * @param string $codigo_interno
     * @param int $excludeId ID para excluir da verificação (para edição)
     * @return bool
     */
    public function codigoInternoExists($codigo_interno, $excludeId = null) {
        if (!$this->conn) {
            $rows = $this->findAll();
            foreach ($rows as $r) {
                $cid = $r['codigo_interno'] ?? '';
                if ($cid === $codigo_interno) {
                    if ($excludeId && (int)($r[$this->primary_key] ?? 0) === (int)$excludeId) {
                        continue;
                    }
                    return true;
                }
            }
            return false;
        }
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
        if (!$this->conn) {
            $rows = $this->findAll('', [], 'nome ASC');
            $fn = function($val) { return is_string($val) ? mb_strtolower($val) : $val; };
            $f = [];
            foreach ($rows as $row) {
                $ok = true;
                if (!empty($filters['nome'])) {
                    $ok = $ok && strpos($fn($row['nome'] ?? ''), mb_strtolower($filters['nome'])) !== false;
                }
                if (!empty($filters['status'])) {
                    $statusRow = $row['status'] ?? '';
                    $ok = $ok && (strcasecmp($statusRow, $filters['status']) === 0);
                }
                if (!empty($filters['codigo_interno'])) {
                    $ok = $ok && strpos($fn($row['codigo_interno'] ?? ''), mb_strtolower($filters['codigo_interno'])) !== false;
                }
                if (!empty($filters['descricao'])) {
                    $ok = $ok && strpos($fn($row['descricao'] ?? ''), mb_strtolower($filters['descricao'])) !== false;
                }
                if (!empty($filters['fornecedor_id'])) {
                    $ok = $ok && (intval($row['fornecedor_id'] ?? 0) === intval($filters['fornecedor_id']));
                }
                if (!empty($filters['categoria_id'])) {
                    $cid = $row['categoria_id'] ?? $row['id_categoria'] ?? null;
                    $ok = $ok && (intval($cid) === intval($filters['categoria_id']));
                }
                if ($ok) $f[] = $row;
            }
            return $f;
        }
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
        if (!empty($filters['fornecedor_id'])) {
            $where[] = "fornecedor_id = :fornecedor_id";
            $params[':fornecedor_id'] = (int) $filters['fornecedor_id'];
        }
        if (!empty($filters['categoria_id'])) {
            $where[] = "categoria_id = :categoria_id";
            $params[':categoria_id'] = (int) $filters['categoria_id'];
        }
        $whereClause = !empty($where) ? implode(' AND ', $where) : '';
        return $this->findAll($whereClause, $params, 'nome ASC');
    }
    
    /**
     * Buscar produtos de um fornecedor específico (incluindo vínculos)
     * @param int $fornecedor_id
     * @return array
     */
    public function findByFornecedor($fornecedor_id) {
        if (!$this->conn) {
            // Fallback para JSON (não suporta UNION)
            return $this->search(['fornecedor_id' => $fornecedor_id]);
        }
        
        try {
            // Unir produtos próprios com produtos vinculados
            $sql = "
                SELECT DISTINCT 
                    p.id, 
                    p.nome, 
                    p.descricao, 
                    p.codigo_interno, 
                    p.categoria_id, 
                    p.preco_base, 
                    CASE 
                        WHEN p.fornecedor_id = :fid1 THEN p.fornecedor_id 
                        ELSE pf.fornecedor_id 
                    END as fornecedor_id,
                    p.status, 
                    p.data_cadastro, 
                    p.updated_at
                FROM produtos p
                LEFT JOIN produto_fornecedor pf ON (p.id = pf.produto_id OR p.id = pf.id_produto)
                WHERE (p.fornecedor_id = :fid2)
                   OR (pf.fornecedor_id = :fid3 AND (pf.status = 'Ativo' OR pf.status = 'ativo'))
                ORDER BY p.nome
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':fid1', $fornecedor_id);
            $stmt->bindParam(':fid2', $fornecedor_id);
            $stmt->bindParam(':fid3', $fornecedor_id);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Erro em findByFornecedor: " . $e->getMessage());
            // Fallback em caso de erro
            return $this->search(['fornecedor_id' => $fornecedor_id]);
        }
    }

    /**
     * Contar fornecedores vinculados a um produto
     * @param int $id_produto
     * @return int
     */
    public function countFornecedores($id_produto) {
        try {
            $sql = "SELECT COUNT(*) as total FROM produto_fornecedor 
                    WHERE produto_id = :id";
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
        if (!$this->conn) {
            $produtos = $this->findAll('', [], 'nome ASC');
            $pf_path = __DIR__ . '/../data/produto_fornecedor.json';
            $for_path = __DIR__ . '/../data/fornecedores.json';
            $pf = file_exists($pf_path) ? (json_decode(file_get_contents($pf_path), true) ?: []) : [];
            $fornecedores = file_exists($for_path) ? (json_decode(file_get_contents($for_path), true) ?: []) : [];
            $for_by_id = [];
            foreach ($fornecedores as $f) {
                $for_by_id[(int)($f['id'] ?? 0)] = $f;
            }
            $out = [];
            foreach ($produtos as $p) {
                if ($id_produto && (int)($p['id'] ?? 0) !== (int)$id_produto) continue;
                $pid = (int)($p['id'] ?? 0);
                $matches = [];
                foreach ($pf as $link) {
                    $lp = (int)($link['produto_id'] ?? 0);
                    if ($lp === $pid) $matches[] = $link;
                }
                if (empty($matches)) {
                    $out[] = [
                        'id' => $p['id'] ?? null,
                        'produto_nome' => $p['nome'] ?? null,
                        'descricao' => $p['descricao'] ?? null,
                        'codigo_interno' => $p['codigo_interno'] ?? null,
                        'produto_status' => $p['status'] ?? null,
                        'produto_created_at' => $p['data_cadastro'] ?? null,
                        'fornecedor_id' => null,
                        'fornecedor_nome' => null,
                        'cnpj' => null,
                        'email' => null,
                        'telefone' => null,
                        'fornecedor_status' => null
                    ];
                } else {
                    foreach ($matches as $m) {
                        $fid = (int)($m['fornecedor_id'] ?? 0);
                        $f = $for_by_id[$fid] ?? [];
                        $out[] = [
                            'id' => $p['id'] ?? null,
                            'produto_nome' => $p['nome'] ?? null,
                            'descricao' => $p['descricao'] ?? null,
                            'codigo_interno' => $p['codigo_interno'] ?? null,
                            'produto_status' => $p['status'] ?? null,
                            'produto_created_at' => $p['data_cadastro'] ?? null,
                            'fornecedor_id' => $f['id'] ?? null,
                            'fornecedor_nome' => $f['nome'] ?? null,
                            'cnpj' => $f['cnpj'] ?? null,
                            'email' => $f['email'] ?? null,
                            'telefone' => $f['telefone'] ?? null,
                            'fornecedor_status' => $f['status'] ?? null
                        ];
                    }
                }
            }
            return $out;
        }
        try {
            $sql = "
                SELECT 
                    p.id,
                    p.nome as produto_nome,
                    p.descricao,
                    p.codigo_interno,
                    p.status as produto_status,
                    p.data_cadastro as produto_created_at,
                    f.id as fornecedor_id,
                    f.nome as fornecedor_nome,
                    f.cnpj,
                    f.email,
                    f.telefone,
                    f.status as fornecedor_status
                FROM produtos p
                LEFT JOIN produto_fornecedor pf ON p.id = pf.produto_id
                LEFT JOIN fornecedores f ON pf.fornecedor_id = f.id
            ";
            
            $params = [];
            if ($id_produto) {
                $sql .= " WHERE p.id = :id_produto";
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
        if (!$this->conn) {
            $produtos = $this->findAll('', [], 'nome ASC');
            $pf_path = __DIR__ . '/../data/produto_fornecedor.json';
            $pf = file_exists($pf_path) ? (json_decode(file_get_contents($pf_path), true) ?: []) : [];
            $linked = [];
            foreach ($pf as $link) {
                $pid = (int)($link['produto_id'] ?? $link['id_produto'] ?? 0);
                if ($pid) { $linked[$pid] = true; }
            }
            $out = [];
            foreach ($produtos as $p) {
                $pid = (int)($p['id'] ?? 0);
                if (!$pid) continue;
                if (!isset($linked[$pid])) {
                    $out[] = $p;
                }
            }
            return $out;
        }
        try {
            $sql = "
                SELECT p.*
                FROM produtos p
                LEFT JOIN produto_fornecedor pf ON (p.id = pf.produto_id OR p.id = pf.id_produto)
                WHERE pf.produto_id IS NULL AND pf.id_produto IS NULL
                ORDER BY p.nome
            ";
            
            return $this->executeQuery($sql);
            
        } catch (PDOException $e) {
            error_log("Erro em findSemFornecedores: " . $e->getMessage());
            return [];
        }
    }
}
