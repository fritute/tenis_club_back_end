<?php
require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/PedidoItensModel.php';

class PedidoModel extends BaseModel {
    protected $table = 'pedidos';
    protected $primaryKey = 'id';
    protected $table_name = 'pedidos';
    protected $primary_key = 'id';
    
    protected $fillable = [
        'usuario_id',
        'fornecedor_id',
        'status',
        'endereco_entrega',
        'valor_total',
        'observacoes'
    ];
    
    private $itensModel;
    
    /**
     * Status permitidos para pedidos
     */
    const STATUS_PENDENTE = 'pendente';
    const STATUS_CONFIRMADO = 'confirmado';
    const STATUS_EM_SEPARACAO = 'em_separacao';
    const STATUS_EM_TRANSITO = 'em_transito';
    const STATUS_ENVIADO = 'enviado';
    const STATUS_ENTREGUE = 'entregue';
    const STATUS_CANCELADO = 'cancelado';
    
    /**
     * Construtor
     */
    public function __construct() {
        parent::__construct();
        $this->itensModel = new PedidoItensModel();
    }

    /**
     * Criar pedido (sobrescrever para tratar endereco_entrega como JSON)
     */
    public function create($data) {
        // Converter endereco_entrega para JSON se for array
        if (isset($data['endereco_entrega']) && is_array($data['endereco_entrega'])) {
            $data['endereco_entrega'] = json_encode($data['endereco_entrega'], JSON_UNESCAPED_UNICODE);
        }
        
        return parent::create($data);
    }

    /**
     * Atualizar pedido (sobrescrever para tratar endereco_entrega como JSON)
     */
    public function update($id, $data) {
        // Converter endereco_entrega para JSON se for array
        if (isset($data['endereco_entrega']) && is_array($data['endereco_entrega'])) {
            $data['endereco_entrega'] = json_encode($data['endereco_entrega'], JSON_UNESCAPED_UNICODE);
        }
        
        return parent::update($id, $data);
    }
    
    /**
     * Criar pedido completo com itens
     */
    public function criarPedidoCompleto($dadosPedido, $itens) {
        // Validar dados do pedido
        $required = ['usuario_id', 'fornecedor_id', 'endereco_entrega'];
        foreach ($required as $field) {
            if (!isset($dadosPedido[$field]) || empty($dadosPedido[$field])) {
                throw new Exception("Campo obrigatório ausente: {$field}");
            }
        }
        
        // Validar itens
        if (empty($itens) || !is_array($itens)) {
            throw new Exception("Pedido deve conter pelo menos um item");
        }
        
        // Calcular valor total
        $valorTotal = 0;
        foreach ($itens as $item) {
            if (!isset($item['produto_id']) || !isset($item['quantidade']) || !isset($item['preco_unitario'])) {
                throw new Exception("Item inválido: produto_id, quantidade e preco_unitario são obrigatórios");
            }
            $valorTotal += $item['quantidade'] * $item['preco_unitario'];
        }
        
        // Preparar dados do pedido
        $dadosPedido['valor_total'] = $valorTotal;
        $dadosPedido['status'] = $dadosPedido['status'] ?? self::STATUS_PENDENTE;
        
        // Remover transações aninhadas para evitar timeout se o driver não suportar
        // O MySQL suporta, mas em alguns ambientes compartilhados pode dar lock
        
        try {
            // Log para debug
            error_log("Tentando criar pedido. Usuario: {$dadosPedido['usuario_id']}, Fornecedor: {$dadosPedido['fornecedor_id']}");
            
            // Garantir que não estamos passando campos que não existem na tabela
            $camposPermitidos = [
                'usuario_id', 
                'fornecedor_id', 
                'status', 
                'endereco_entrega', 
                'valor_total', 
                'observacoes'
            ];
            
            $dadosLimpos = array_intersect_key($dadosPedido, array_flip($camposPermitidos));
            
            // Garantir tipos corretos
            $dadosLimpos['usuario_id'] = (int)$dadosLimpos['usuario_id'];
            $dadosLimpos['fornecedor_id'] = (int)$dadosLimpos['fornecedor_id'];
            $dadosLimpos['valor_total'] = (float)$dadosLimpos['valor_total'];
            
            // Validar se usuario e fornecedor existem antes de inserir
            $stmtUser = $this->conn->prepare("SELECT id FROM usuarios WHERE id = ?");
            $stmtUser->execute([$dadosLimpos['usuario_id']]);
            if (!$stmtUser->fetch()) {
                 throw new Exception("Usuário ID {$dadosLimpos['usuario_id']} não encontrado.");
            }
            
            $stmtFornecedor = $this->conn->prepare("SELECT id FROM fornecedores WHERE id = ?");
            $stmtFornecedor->execute([$dadosLimpos['fornecedor_id']]);
            if (!$stmtFornecedor->fetch()) {
                 throw new Exception("Fornecedor ID {$dadosLimpos['fornecedor_id']} não encontrado.");
            }

            // Criar pedido no banco (sem transação explícita para evitar lock wait timeout em debug)
            $pedidoId = $this->create($dadosLimpos);
            
            if (!$pedidoId) {
                $error = "Erro ao criar pedido no banco de dados. Verifique os logs.";
                error_log($error);
                throw new Exception($error);
            }
            
            error_log("Pedido criado ID: $pedidoId. Criando " . count($itens) . " itens...");
            
            // Criar itens do pedido
            foreach ($itens as $item) {
                $item['pedido_id'] = $pedidoId;
                $item['subtotal'] = $item['quantidade'] * $item['preco_unitario'];
                
                // Garantir que campos extras não sejam passados
                $dadosItem = [
                    'pedido_id' => $pedidoId,
                    'produto_id' => $item['produto_id'],
                    'quantidade' => $item['quantidade'],
                    'preco_unitario' => $item['preco_unitario'],
                    'subtotal' => $item['subtotal']
                ];
                
                if (!$this->itensModel->criarItem($dadosItem)) {
                    // Se falhar item, tentar deletar pedido (rollback manual simples)
                    $this->delete($pedidoId);
                    throw new Exception("Erro ao criar item do pedido (Produto ID: {$item['produto_id']})");
                }
            }
            
            return $pedidoId;
            
        } catch (Exception $e) {
            error_log("Exception em criarPedidoCompleto: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Buscar pedido com seus itens
     */
    public function findComItens($id) {
        $pedido = $this->findById($id);
        if (!$pedido) {
            return null;
        }
        
        // Converter endereco_entrega de JSON para array se necessário
        if (is_string($pedido['endereco_entrega'])) {
            $pedido['endereco_entrega'] = json_decode($pedido['endereco_entrega'], true);
        }
        
        $pedido['itens'] = $this->findItensByPedido($id);
        return $pedido;
    }
    
    /**
     * Buscar pedidos de um usuário
     */
    public function findByUsuario($usuarioId) {
        $pedidos = $this->findAll("usuario_id = :usuario_id", [':usuario_id' => $usuarioId], 'created_at DESC');
        
        // Adicionar itens a cada pedido e converter endereco_entrega
        foreach ($pedidos as &$pedido) {
            if (is_string($pedido['endereco_entrega'])) {
                $pedido['endereco_entrega'] = json_decode($pedido['endereco_entrega'], true);
            }
            $pedido['itens'] = $this->findItensByPedido($pedido['id']);
        }
        
        return $pedidos;
    }
    
    /**
     * Buscar pedidos de um fornecedor
     */
    public function findByFornecedor($fornecedorId) {
        $pedidos = $this->findAll("fornecedor_id = :fornecedor_id", [':fornecedor_id' => $fornecedorId], 'created_at DESC');
        
        // Adicionar itens a cada pedido e converter endereco_entrega
        foreach ($pedidos as &$pedido) {
            if (is_string($pedido['endereco_entrega'])) {
                $pedido['endereco_entrega'] = json_decode($pedido['endereco_entrega'], true);
            }
            $pedido['itens'] = $this->findItensByPedido($pedido['id']);
        }
        
        return $pedidos;
    }
    
    /**
     * Buscar itens de um pedido
     */
    public function findItensByPedido($pedidoId) {
        return $this->itensModel->findByPedido($pedidoId);
    }
    
    /**
     * Atualizar status do pedido
     */
    public function atualizarStatus($id, $novoStatus) {
        $statusPermitidos = [
            self::STATUS_PENDENTE,
            self::STATUS_CONFIRMADO,
            self::STATUS_EM_SEPARACAO,
            self::STATUS_EM_TRANSITO,
            self::STATUS_ENVIADO,
            self::STATUS_ENTREGUE,
            self::STATUS_CANCELADO
        ];
        
        if (!in_array($novoStatus, $statusPermitidos)) {
            throw new Exception("Status inválido: {$novoStatus}");
        }
        
        // Verificar se pedido existe
        $pedido = $this->findById($id);
        if (!$pedido) {
            throw new Exception("Pedido não encontrado");
        }
        
        return $this->update($id, ['status' => $novoStatus]);
    }
    
    /**
     * Validar dados do pedido
     */
    public function validate($data) {
        $errors = [];
        
        // Validar usuario_id
        if (empty($data['usuario_id'])) {
            $errors[] = "Usuario é obrigatório";
        }
        
        // Validar fornecedor_id
        if (empty($data['fornecedor_id'])) {
            $errors[] = "Fornecedor é obrigatório";
        }
        
        // Validar endereço de entrega
        if (empty($data['endereco_entrega'])) {
            $errors[] = "Endereço de entrega é obrigatório";
        } else {
            // Se for string JSON, validar estrutura
            if (is_string($data['endereco_entrega'])) {
                $endereco = json_decode($data['endereco_entrega'], true);
                if (!$endereco) {
                    $errors[] = "Endereço de entrega inválido (JSON mal formatado)";
                } else {
                    // Validar campos obrigatórios
                    $temRua = !empty($endereco['logradouro']) || !empty($endereco['rua']);
                    $temCidade = !empty($endereco['cidade']);
                    $temCep = !empty($endereco['cep']);
                    
                    if (!$temRua) {
                        $errors[] = "Campo 'rua' ou 'logradouro' é obrigatório no endereço";
                    }
                    if (!$temCidade) {
                        $errors[] = "Campo 'cidade' é obrigatório no endereço";
                    }
                    if (!$temCep) {
                        $errors[] = "Campo 'cep' é obrigatório no endereço";
                    }
                }
            } else if (is_array($data['endereco_entrega'])) {
                // Se já veio como array, validar diretamente
                $endereco = $data['endereco_entrega'];
                $temRua = !empty($endereco['logradouro']) || !empty($endereco['rua']);
                $temCidade = !empty($endereco['cidade']);
                $temCep = !empty($endereco['cep']);
                
                if (!$temRua) {
                    $errors[] = "Campo 'rua' ou 'logradouro' é obrigatório no endereço";
                }
                if (!$temCidade) {
                    $errors[] = "Campo 'cidade' é obrigatório no endereço";
                }
                if (!$temCep) {
                    $errors[] = "Campo 'cep' é obrigatório no endereço";
                }
            }
        }
        
        // Validar status
        if (!empty($data['status'])) {
            $statusPermitidos = [
                self::STATUS_PENDENTE,
                self::STATUS_CONFIRMADO,
                self::STATUS_EM_SEPARACAO,
                self::STATUS_EM_TRANSITO,
                self::STATUS_ENVIADO,
                self::STATUS_ENTREGUE,
                self::STATUS_CANCELADO
            ];
            if (!in_array($data['status'], $statusPermitidos)) {
                $errors[] = "Status inválido";
            }
        }
        
        return $errors;
    }
    
    /**
     * Estatísticas de pedidos por fornecedor
     */
    public function estatisticasPorFornecedor($fornecedorId) {
        try {
            $sql = "
                SELECT 
                    COUNT(*) as total_pedidos,
                    SUM(valor_total) as valor_total,
                    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendente,
                    SUM(CASE WHEN status = 'confirmado' THEN 1 ELSE 0 END) as confirmado,
                    SUM(CASE WHEN status = 'enviado' THEN 1 ELSE 0 END) as enviado,
                    SUM(CASE WHEN status = 'entregue' THEN 1 ELSE 0 END) as entregue,
                    SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) as cancelado
                FROM pedidos
                WHERE fornecedor_id = :fornecedor_id
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':fornecedor_id', $fornecedorId);
            $stmt->execute();
            
            $result = $stmt->fetch();
            
            return [
                'total_pedidos' => (int) ($result['total_pedidos'] ?? 0),
                'valor_total' => (float) ($result['valor_total'] ?? 0),
                'por_status' => [
                    'pendente' => (int) ($result['pendente'] ?? 0),
                    'confirmado' => (int) ($result['confirmado'] ?? 0),
                    'enviado' => (int) ($result['enviado'] ?? 0),
                    'entregue' => (int) ($result['entregue'] ?? 0),
                    'cancelado' => (int) ($result['cancelado'] ?? 0)
                ]
            ];
            
        } catch (PDOException $e) {
            error_log("Erro em estatisticasPorFornecedor: " . $e->getMessage());
            return [
                'total_pedidos' => 0,
                'valor_total' => 0,
                'por_status' => [
                    'pendente' => 0,
                    'confirmado' => 0,
                    'enviado' => 0,
                    'entregue' => 0,
                    'cancelado' => 0
                ]
            ];
        }
    }
}
