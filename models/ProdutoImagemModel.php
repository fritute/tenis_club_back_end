<?php
/**
 * Model para Imagens de Produtos
 * Virtual Market System
 */

require_once __DIR__ . '/BaseModel.php';

class ProdutoImagemModel extends BaseModel {
    
    public function __construct() {
        parent::__construct();
        $this->table_name = 'produto_imagens';
        $this->primary_key = 'id';
    }

    /**
     * Validar dados da imagem
     */
    public function validate($data) {
        $errors = [];
        
        // Validar produto_id
        if (empty($data['produto_id'])) {
            $errors['produto_id'] = 'ID do produto é obrigatório';
        }
        
        // Validar nome do arquivo
        if (empty($data['nome_arquivo'])) {
            $errors['nome_arquivo'] = 'Nome do arquivo é obrigatório';
        }
        
        // Validar URL
        if (empty($data['url'])) {
            $errors['url'] = 'URL da imagem é obrigatória';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Adicionar imagem a um produto
     * @param array $dados Array com os dados da imagem
     * @return int ID da imagem criada
     */
    public function adicionarImagem($dados) {
        // Se veio no formato antigo (parâmetros separados)
        if (!is_array($dados)) {
            // Backward compatibility
            $args = func_get_args();
            $dados = [
                'produto_id' => $args[0] ?? null,
                'nome_arquivo' => $args[1] ?? null,
                'caminho' => $args[2] ?? null,
                'descricao' => $args[3] ?? null
            ];
        }
        
        // Garantir campos obrigatórios
        $data = [
            'produto_id' => $dados['produto_id'] ?? null,
            'nome_arquivo' => $dados['nome_arquivo'] ?? null,
            'caminho' => $dados['caminho'] ?? $dados['url'] ?? null,
            'descricao' => $dados['descricao'] ?? null,
            'alt_text' => $dados['alt_text'] ?? '',
            'tamanho' => $dados['tamanho'] ?? 0,
            'tipo_mime' => $dados['tipo_mime'] ?? 'image/jpeg',
            'largura' => $dados['largura'] ?? 0,
            'altura' => $dados['altura'] ?? 0,
            'eh_principal' => $dados['eh_principal'] ?? false,
            'ordem' => $dados['ordem'] ?? $this->getProximaOrdem($dados['produto_id']),
            'deletado_em' => null
        ];
        
        return $this->create($data);
    }

    /**
     * Buscar imagens de um produto
     */
    public function getImagensProduto($produtoId) {
        try {
            $sql = "SELECT * FROM " . $this->table_name . " 
                    WHERE produto_id = :produto_id 
                    AND (deletado_em IS NULL)
                    ORDER BY ordem ASC, id ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':produto_id', $produtoId);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro em getImagensProduto: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Definir imagem principal
     * @param int $imagemId ID da imagem
     * @return bool
     */
    public function definirPrincipal($imagemId) {
        // Buscar a imagem para saber o produto_id
        $imagem = $this->findById($imagemId);
        if (!$imagem) {
            return false;
        }
        
        $produtoId = $imagem['produto_id'];
        
        try {
            // Desmarcar todas as imagens do produto como principal
            $sql = "UPDATE " . $this->table_name . " 
                    SET eh_principal = 0 
                    WHERE produto_id = :produto_id AND id != :imagem_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':produto_id', $produtoId);
            $stmt->bindParam(':imagem_id', $imagemId);
            $stmt->execute();
            
            // Marcar a imagem atual como principal
            return $this->update($imagemId, ['eh_principal' => true]);
            
        } catch (PDOException $e) {
            error_log("Erro em definirImagemPrincipal: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reordenar imagens
     */
    public function reordenarImagens($produtoId, $ordensIds) {
        foreach ($ordensIds as $ordem => $imagemId) {
            $this->update($imagemId, ['ordem' => $ordem + 1]);
        }
        return true;
    }

    /**
     * Remover imagem (soft delete)
     */
    public function removerImagem($imagemId) {
        return $this->update($imagemId, [
            'deletado_em' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Obter próxima ordem para o produto
     */
    private function getProximaOrdem($produtoId) {
        $imagens = $this->getImagensProduto($produtoId);
        $maxOrdem = 0;
        
        foreach ($imagens as $img) {
            if (($img['ordem'] ?? 0) > $maxOrdem) {
                $maxOrdem = $img['ordem'];
            }
        }
        
        return $maxOrdem + 1;
    }

    /**
     * Obter imagem principal do produto
     */
    public function getImagemPrincipal($produtoId) {
        try {
            $sql = "SELECT * FROM " . $this->table_name . " 
                    WHERE produto_id = :produto_id 
                    AND (deletado_em IS NULL)
                    AND eh_principal = 1
                    LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':produto_id', $produtoId);
            $stmt->execute();
            
            $result = $stmt->fetch();
            
            // Se não encontrou principal, buscar a primeira por ordem
            if (!$result) {
                $sql = "SELECT * FROM " . $this->table_name . " 
                        WHERE produto_id = :produto_id 
                        AND (deletado_em IS NULL)
                        ORDER BY ordem ASC, id ASC
                        LIMIT 1";
                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(':produto_id', $produtoId);
                $stmt->execute();
                $result = $stmt->fetch();
            }
            
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("Erro em getImagemPrincipal: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Contar imagens ativas de um produto
     */
    public function contarImagensProduto($produtoId) {
        return count($this->getImagensProduto($produtoId));
    }

    /**
     * Limpar imagens de um produto (para quando produto é deletado)
     */
    public function limparImagensProduto($produtoId) {
        $imagens = $this->getImagensProduto($produtoId);
        
        foreach ($imagens as $imagem) {
            $this->removerImagem($imagem['id']);
        }
        
        return true;
    }

    /**
     * Buscar produtos com mais imagens
     */
    public function getProdutosMaisImagens($limite = 10) {
        try {
            $sql = "
                SELECT 
                    produto_id,
                    COUNT(*) as total_imagens
                FROM " . $this->table_name . "
                WHERE deletado_em IS NULL OR deletado_em = ''
                GROUP BY produto_id
                ORDER BY total_imagens DESC
                LIMIT :limite
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetchAll();
            $contadores = [];
            foreach ($result as $row) {
                $contadores[$row['produto_id']] = (int)$row['total_imagens'];
            }
            
            return $contadores;
            
        } catch (PDOException $e) {
            error_log("Erro em getProdutosMaisImagens: " . $e->getMessage());
            return [];
        }
    }
}
