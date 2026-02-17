<?php
/**
 * Model para Itens de Pedidos
 * Virtual Market System
 */

require_once __DIR__ . '/BaseModel.php';

class PedidoItensModel extends BaseModel {
    
    public function __construct() {
        parent::__construct();
        $this->table_name = 'pedido_itens';
        $this->primary_key = 'id';
    }

    /**
     * Buscar itens de um pedido
     * @param int $pedidoId
     * @return array
     */
    public function findByPedido($pedidoId) {
        return $this->findAll("pedido_id = :pedido_id", [':pedido_id' => $pedidoId], 'id ASC');
    }

    /**
     * Criar item de pedido
     * @param array $data
     * @return int ID do item criado
     */
    public function criarItem($data) {
        // Garantir que apenas campos da tabela sejam passados
        $dadosLimpos = [
            'pedido_id' => $data['pedido_id'],
            'produto_id' => $data['produto_id'],
            'quantidade' => $data['quantidade'],
            'preco_unitario' => $data['preco_unitario'],
            'subtotal' => $data['subtotal'] ?? ($data['quantidade'] * $data['preco_unitario'])
        ];
        
        return $this->create($dadosLimpos);
    }

    /**
     * Criar múltiplos itens de uma vez
     * @param array $itens Array de itens
     * @return array IDs dos itens criados
     */
    public function criarItens($itens) {
        $ids = [];
        
        foreach ($itens as $item) {
            $ids[] = $this->criarItem($item);
        }
        
        return $ids;
    }

    /**
     * Remover todos os itens de um pedido
     * @param int $pedidoId
     * @return bool
     */
    public function removerItensPorPedido($pedidoId) {
        try {
            $sql = "DELETE FROM " . $this->table_name . " WHERE pedido_id = :pedido_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':pedido_id', $pedidoId);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Erro em removerItensPorPedido: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Calcular total de um pedido pelos itens
     * @param int $pedidoId
     * @return float
     */
    public function calcularTotalPedido($pedidoId) {
        try {
            $sql = "SELECT SUM(subtotal) as total FROM " . $this->table_name . " WHERE pedido_id = :pedido_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':pedido_id', $pedidoId);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return (float) ($result['total'] ?? 0);
            
        } catch (PDOException $e) {
            error_log("Erro em calcularTotalPedido: " . $e->getMessage());
            return 0;
        }
    }
}
