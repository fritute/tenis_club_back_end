<?php
/**
 * Model para Contatos de Fornecedores
 * Virtual Market System
 */

require_once __DIR__ . '/BaseModel.php';

class ContatoFornecedorModel extends BaseModel {
    
    public function __construct() {
        parent::__construct();
        $this->table_name = 'contatos_fornecedor';
        $this->primary_key = 'id_contato';
    }

    /**
     * Validar dados do contato
     * @param array $data
     * @return array Array com 'valid' (bool) e 'errors' (array)
     */
    public function validate($data) {
        $errors = [];
        
        // Validar ID do fornecedor
        if (empty($data['id_fornecedor']) || !is_numeric($data['id_fornecedor'])) {
            $errors['id_fornecedor'] = 'ID do fornecedor é obrigatório';
        }
        
        // Validar nome
        if (empty($data['nome']) || strlen(trim($data['nome'])) < 2) {
            $errors['nome'] = 'Nome é obrigatório e deve ter pelo menos 2 caracteres';
        }
        
        // Validar email se fornecido
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-mail inválido';
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
     * Buscar contatos de um fornecedor
     * @param int $id_fornecedor
     * @param bool $apenasAtivos
     * @return array
     */
    public function getByFornecedor($id_fornecedor, $apenasAtivos = true) {
        try {
            $where = "id_fornecedor = :id_fornecedor";
            $params = [':id_fornecedor' => $id_fornecedor];
            
            if ($apenasAtivos) {
                $where .= " AND status = 'Ativo'";
            }
            
            return $this->findAll($where, $params, 'is_principal DESC, nome ASC');
            
        } catch (PDOException $e) {
            error_log("Erro em getByFornecedor: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar contato principal de um fornecedor
     * @param int $id_fornecedor
     * @return array|null
     */
    public function getPrincipalByFornecedor($id_fornecedor) {
        try {
            $sql = "SELECT * FROM " . $this->table_name . " 
                    WHERE id_fornecedor = :id_fornecedor 
                    AND is_principal = 1 
                    AND status = 'Ativo'
                    LIMIT 1";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id_fornecedor', $id_fornecedor);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("Erro em getPrincipalByFornecedor: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Definir um contato como principal (remove principal de outros)
     * @param int $id_contato
     * @param int $id_fornecedor
     * @return bool
     */
    public function setPrincipal($id_contato, $id_fornecedor) {
        try {
            $this->conn->beginTransaction();
            
            // Remover principal de todos os contatos do fornecedor
            $sql1 = "UPDATE " . $this->table_name . " 
                     SET is_principal = 0 
                     WHERE id_fornecedor = :id_fornecedor";
            
            $stmt1 = $this->conn->prepare($sql1);
            $stmt1->bindParam(':id_fornecedor', $id_fornecedor);
            $stmt1->execute();
            
            // Definir o novo principal
            $sql2 = "UPDATE " . $this->table_name . " 
                     SET is_principal = 1 
                     WHERE id_contato = :id_contato AND id_fornecedor = :id_fornecedor";
            
            $stmt2 = $this->conn->prepare($sql2);
            $stmt2->bindParam(':id_contato', $id_contato);
            $stmt2->bindParam(':id_fornecedor', $id_fornecedor);
            $stmt2->execute();
            
            $this->conn->commit();
            return true;
            
        } catch (PDOException $e) {
            $this->conn->rollback();
            error_log("Erro em setPrincipal: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Buscar contatos com dados do fornecedor
     * @param array $filters
     * @return array
     */
    public function findWithFornecedor($filters = []) {
        try {
            $sql = "
                SELECT 
                    c.id_contato,
                    c.nome as contato_nome,
                    c.cargo,
                    c.telefone,
                    c.email,
                    c.whatsapp,
                    c.is_principal,
                    c.status as contato_status,
                    c.created_at,
                    f.id_fornecedor,
                    f.nome as fornecedor_nome,
                    f.cnpj,
                    f.status as fornecedor_status
                FROM contatos_fornecedor c
                INNER JOIN fornecedores f ON c.id_fornecedor = f.id_fornecedor
            ";
            
            $where = [];
            $params = [];
            
            if (!empty($filters['fornecedor_nome'])) {
                $where[] = "f.nome LIKE :fornecedor_nome";
                $params[':fornecedor_nome'] = '%' . $filters['fornecedor_nome'] . '%';
            }
            
            if (!empty($filters['contato_nome'])) {
                $where[] = "c.nome LIKE :contato_nome";
                $params[':contato_nome'] = '%' . $filters['contato_nome'] . '%';
            }
            
            if (!empty($filters['cargo'])) {
                $where[] = "c.cargo LIKE :cargo";
                $params[':cargo'] = '%' . $filters['cargo'] . '%';
            }
            
            if (!empty($filters['status'])) {
                $where[] = "c.status = :status";
                $params[':status'] = $filters['status'];
            }
            
            if (isset($filters['is_principal'])) {
                $where[] = "c.is_principal = :is_principal";
                $params[':is_principal'] = $filters['is_principal'];
            }
            
            if (!empty($where)) {
                $sql .= " WHERE " . implode(' AND ', $where);
            }
            
            $sql .= " ORDER BY f.nome, c.is_principal DESC, c.nome";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro em findWithFornecedor: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verificar se email já existe para outro contato
     * @param string $email
     * @param int $excludeId
     * @return bool
     */
    public function emailExists($email, $excludeId = null) {
        try {
            if (empty($email)) return false;
            
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
     * Contar contatos por fornecedor
     * @param int $id_fornecedor
     * @return int
     */
    public function countByFornecedor($id_fornecedor) {
        try {
            $sql = "SELECT COUNT(*) as total FROM " . $this->table_name . " 
                    WHERE id_fornecedor = :id_fornecedor AND status = 'Ativo'";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id_fornecedor', $id_fornecedor);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return (int) $result['total'];
            
        } catch (PDOException $e) {
            error_log("Erro em countByFornecedor: " . $e->getMessage());
            return 0;
        }
    }
}