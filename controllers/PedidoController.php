<?php
/**
 * Controller para Pedidos
 * Virtual Market System
 */

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/PedidoModel.php';
require_once __DIR__ . '/../models/UsuarioModel.php';

class PedidoController extends BaseController {
    private $pedidoModel;
    private $usuarioModel;

    public function __construct() {
        $this->pedidoModel = new PedidoModel();
        $this->usuarioModel = new UsuarioModel();
    }

    /**
     * Criar novo pedido completo (com itens)
     * POST /api/pedidos
     */
    public function criar() {
        try {
            // Autenticar usuário
            $usuario = $this->requireAuth();
            
            $data = $this->getJsonInput();
            
            // Validar dados do pedido
            if (!isset($data['fornecedor_id']) || !isset($data['endereco_entrega']) || !isset($data['itens'])) {
                return $this->errorResponse('Dados incompletos: fornecedor_id, endereco_entrega e itens são obrigatórios', 400);
            }

            // Normalizar endereco_entrega: se vier string JSON, decodificar primeiro para validar
            $endereco = $data['endereco_entrega'];
            if (is_string($endereco)) {
                $endereco = json_decode($endereco, true);
            }
            // Se ainda não for array válido ou estiver vazio, erro
            if (empty($endereco) || !is_array($endereco)) {
                return $this->errorResponse('Endereço de entrega inválido', 400);
            }
            
            // Preparar dados do pedido
            $dadosPedido = [
                'usuario_id' => $usuario['id'],
                'fornecedor_id' => $data['fornecedor_id'],
                // Converter para JSON string para o banco
                'endereco_entrega' => json_encode($endereco, JSON_UNESCAPED_UNICODE),
                'observacoes' => $data['observacoes'] ?? ''
            ];
            
            // Validar itens
            if (empty($data['itens']) || !is_array($data['itens'])) {
                return $this->errorResponse('O pedido deve conter pelo menos um item', 400);
            }
            
            // Criar pedido completo
            $pedidoId = $this->pedidoModel->criarPedidoCompleto($dadosPedido, $data['itens']);
            
            // Buscar pedido criado com itens
            $pedido = $this->pedidoModel->findComItens($pedidoId);
            
            // Log de atividade
            if ($usuario && isset($usuario['id'])) {
                $this->logActivity($usuario['id'], 'criar_pedido', 'pedidos', $pedidoId, 
                    "Pedido #{$pedidoId} criado com " . count($data['itens']) . " itens");
            }
            
            return $this->successResponse($pedido, 'Pedido criado com sucesso', 201);
            
        } catch (Exception $e) {
            error_log("Erro em PedidoController::criar: " . $e->getMessage());
            return $this->errorResponse('Erro ao criar pedido: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Listar meus pedidos (usuário logado)
     * GET /api/pedidos/meus
     */
    public function meusPedidos() {
        try {
            // Autenticar usuário
            $usuario = $this->requireAuth();
            
            $pedidos = $this->pedidoModel->findByUsuario($usuario['id']);
            
            return $this->successResponse($pedidos, 'Meus pedidos listados com sucesso');
            
        } catch (Exception $e) {
            error_log("Erro em PedidoController::meusPedidos: " . $e->getMessage());
            return $this->errorResponse('Erro ao listar pedidos', 500);
        }
    }
    
    /**
     * Listar pedidos recebidos (fornecedor logado)
     * GET /api/pedidos/recebidos
     */
    public function pedidosRecebidos() {
        try {
            // Autenticar usuário
            $usuario = $this->requireAuth(['fornecedor', 'executivo']);
            
            // Verificar se usuário tem fornecedor_id
            if (empty($usuario['fornecedor_id'])) {
                return $this->errorResponse('Usuário não está associado a nenhuma empresa', 403);
            }
            
            $pedidos = $this->pedidoModel->findByFornecedor($usuario['fornecedor_id']);
            
            return $this->successResponse($pedidos, 'Pedidos recebidos listados com sucesso');
            
        } catch (Exception $e) {
            error_log("Erro em PedidoController::pedidosRecebidos: " . $e->getMessage());
            return $this->errorResponse('Erro ao listar pedidos recebidos', 500);
        }
    }
    
    /**
     * Buscar pedido específico por ID
     * GET /api/pedidos/{id}
     */
    public function buscarPorId($id) {
        try {
            // Autenticar usuário
            $usuario = $this->requireAuth();
            
            $pedido = $this->pedidoModel->findComItens($id);
            
            if (!$pedido) {
                return $this->errorResponse('Pedido não encontrado', 404);
            }
            
            // Verificar permissão: usuário deve ser o dono do pedido OU fornecedor do pedido
            $nivelUsuario = $usuario['nivel'] ?? 'comum';
            $podever = ($pedido['usuario_id'] == $usuario['id']) || 
                       (!empty($usuario['fornecedor_id']) && $pedido['fornecedor_id'] == $usuario['fornecedor_id']) ||
                       ($nivelUsuario == 'executivo');
            
            if (!$podever) {
                return $this->errorResponse('Você não tem permissão para ver este pedido', 403);
            }
            
            return $this->successResponse($pedido, 'Pedido encontrado');
            
        } catch (Exception $e) {
            error_log("Erro em PedidoController::buscarPorId: " . $e->getMessage());
            return $this->errorResponse('Erro ao buscar pedido', 500);
        }
    }
    
    /**
     * Atualizar status do pedido (apenas fornecedor/executivo)
     * PUT /api/pedidos/{id}/status
     */
    public function atualizarStatus($id) {
        try {
            // Autenticar usuário
            $usuario = $this->requireAuth(['fornecedor', 'executivo']);
            
            $data = $this->getJsonInput();
            
            if (!isset($data['status'])) {
                return $this->errorResponse('Campo status é obrigatório', 400);
            }
            
            // Buscar pedido
            $pedido = $this->pedidoModel->findById($id);
            if (!$pedido) {
                return $this->errorResponse('Pedido não encontrado', 404);
            }
            
            // Verificar se fornecedor é o dono do pedido (exceto executivo)
            $nivelUsuario = $usuario['nivel'] ?? 'comum';
            if ($nivelUsuario != 'executivo') {
                if (empty($usuario['fornecedor_id']) || $pedido['fornecedor_id'] != $usuario['fornecedor_id']) {
                    return $this->errorResponse('Você não tem permissão para atualizar este pedido', 403);
                }
            }
            
            // Atualizar status
            $this->pedidoModel->atualizarStatus($id, $data['status']);
            
            try {
                $this->logActivity($usuario['id'], 'atualizar_status_pedido', 'pedidos', $id, 
                    "Status alterado para: {$data['status']}");
            } catch (Exception $e) {
                // Silenciar erro de log para não falhar a requisição principal
                error_log("Erro ao registrar log de atividade: " . $e->getMessage());
            }
            
            $pedidoAtualizado = $this->pedidoModel->findComItens($id);
            
            return $this->successResponse($pedidoAtualizado, 'Status do pedido atualizado com sucesso');
            
        } catch (Exception $e) {
            error_log("Erro em PedidoController::atualizarStatus: " . $e->getMessage());
            return $this->errorResponse('Erro ao atualizar status: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Cancelar pedido (apenas usuário dono do pedido e status pendente)
     * PUT /api/pedidos/{id}/cancelar
     */
    public function cancelar($id) {
        try {
            // Autenticar usuário
            $usuario = $this->requireAuth();
            
            // Buscar pedido
            $pedido = $this->pedidoModel->findById($id);
            if (!$pedido) {
                return $this->errorResponse('Pedido não encontrado', 404);
            }
            
            // Verificar se é o dono do pedido
            if ($pedido['usuario_id'] != $usuario['id']) {
                return $this->errorResponse('Você não tem permissão para cancelar este pedido', 403);
            }
            
            // Verificar se ainda pode ser cancelado
            if ($pedido['status'] != 'pendente') {
                return $this->errorResponse('Apenas pedidos pendentes podem ser cancelados', 400);
            }
            
            // Cancelar
            $this->pedidoModel->atualizarStatus($id, PedidoModel::STATUS_CANCELADO);
            
            $this->logActivity($usuario['id'], 'cancelar_pedido', 'pedidos', $id, 
                "Pedido #{$id} cancelado pelo usuário");
            
            $pedidoCancelado = $this->pedidoModel->findComItens($id);
            
            return $this->successResponse($pedidoCancelado, 'Pedido cancelado com sucesso');
            
        } catch (Exception $e) {
            error_log("Erro em PedidoController::cancelar: " . $e->getMessage());
            return $this->errorResponse('Erro ao cancelar pedido: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Estatísticas de pedidos do fornecedor logado
     * GET /api/pedidos/estatisticas
     */
    public function estatisticas() {
        try {
            // Autenticar usuário
            $usuario = $this->requireAuth(['fornecedor', 'executivo']);
            
            // Verificar se tem fornecedor_id
            if (empty($usuario['fornecedor_id'])) {
                return $this->errorResponse('Usuário não está associado a nenhuma empresa', 403);
            }
            
            $stats = $this->pedidoModel->estatisticasPorFornecedor($usuario['fornecedor_id']);
            
            return $this->successResponse($stats, 'Estatísticas obtidas com sucesso');
            
        } catch (Exception $e) {
            error_log("Erro em PedidoController::estatisticas: " . $e->getMessage());
            return $this->errorResponse('Erro ao obter estatísticas', 500);
        }
    }
    
    /**
     * Listar todos os pedidos (apenas executivo)
     * GET /api/pedidos
     */
    public function index() {
        try {
            // Autenticar usuário
            $usuario = $this->requireAuth(['executivo']);
            
            $pedidos = $this->pedidoModel->findAll();
            
            // Adicionar itens a cada pedido
            foreach ($pedidos as &$pedido) {
                $pedido['itens'] = $this->pedidoModel->findItensByPedido($pedido['id']);
            }
            
            return $this->successResponse($pedidos, 'Todos os pedidos listados com sucesso');
            
        } catch (Exception $e) {
            error_log("Erro em PedidoController::index: " . $e->getMessage());
            return $this->errorResponse('Erro ao listar pedidos', 500);
        }
    }
}
