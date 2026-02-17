<?php
/**
 * Controller para Fornecedores
 * Virtual Market System
 */

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/FornecedorModel.php';

class FornecedorController extends BaseController {
    private $fornecedorModel;

    public function __construct() {
        $this->fornecedorModel = new FornecedorModel();
    }

    /**
     * Listar todos os fornecedores
     * @param array $filters
     * @return array
     */
    public function index($filters = []) {
        try {
            // Verificar se o modelo foi inicializado corretamente
            if (!$this->fornecedorModel) {
                throw new Exception("Modelo de fornecedor não foi inicializado");
            }
            
            $fornecedores = $this->fornecedorModel->search($filters);
            
            return $this->successResponse($fornecedores, 'Fornecedores listados com sucesso');
            
        } catch (PDOException $e) {
            error_log("Erro PDO em FornecedorController::index: " . $e->getMessage());
            error_log("SQL State: " . $e->getCode());
            error_log("SQL Error Info: " . print_r($e->errorInfo ?? [], true));
            return $this->errorResponse('Erro ao conectar ao banco de dados', 500);
        } catch (Exception $e) {
            error_log("Erro em FornecedorController::index: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return $this->errorResponse('Erro ao listar fornecedores: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Alterar status da loja (Ativo/Inativo)
     * Permite que fornecedor altere o status da sua própria loja
     * @param int $id
     * @param string $status
     * @return array
     */
    public function alterarStatusLoja($id, $status) {
        try {
            if (!$this->isValidId($id)) {
                return $this->errorResponse('ID inválido', 400);
            }
            
            // Verificar autenticação
            $user = $this->requireAuth(['fornecedor', 'executivo']);
            
            // Validar status
            if (!in_array($status, ['Ativo', 'Inativo'])) {
                return $this->errorResponse('Status deve ser Ativo ou Inativo', 400);
            }
            
            // Verificar se loja existe
            $loja = $this->fornecedorModel->findById($id);
            if (!$loja) {
                return $this->notFoundResponse('Loja');
            }
            
            // Se for fornecedor, verificar se a loja pertence a ele
            if ($user['nivel'] === 'fornecedor') {
                if (empty($user['fornecedor_id']) || $user['fornecedor_id'] != $id) {
                    return $this->errorResponse('Você não tem permissão para alterar esta loja', 403);
                }
            }
            
            // Atualizar status
            $dados_atualizacao = ['status' => $status];
            $sucesso = $this->fornecedorModel->update($id, $dados_atualizacao);
            
            if ($sucesso) {
                $this->logActivity('STATUS_CHANGE', 'fornecedor', $id, [
                    'status_anterior' => $loja['status'],
                    'status_novo' => $status
                ]);
                
                return $this->successResponse([
                    'id' => $id,
                    'status' => $status,
                    'status_anterior' => $loja['status']
                ], 'Status da loja alterado com sucesso');
            } else {
                return $this->errorResponse('Erro ao alterar status da loja');
            }
            
        } catch (Exception $e) {
            error_log("Erro em FornecedorController::alterarStatusLoja: " . $e->getMessage());
            return $this->errorResponse('Erro interno ao alterar status');
        }
    }

    /**
     * Buscar fornecedor por ID
     * @param int $id
     * @return array
     */
    public function show($id) {
        try {
            if (!$this->isValidId($id)) {
                return $this->errorResponse('ID inválido', 400);
            }

            $fornecedor = $this->fornecedorModel->findById($id);
            
            if (!$fornecedor) {
                return $this->notFoundResponse('Fornecedor');
            }

            return $this->successResponse($fornecedor, 'Fornecedor encontrado');
            
        } catch (Exception $e) {
            error_log("Erro em FornecedorController::show: " . $e->getMessage());
            return $this->errorResponse('Erro ao buscar fornecedor');
        }
    }

    /**
     * Criar novo fornecedor
     * @param array $data
     * @return array
     */
    public function store($data) {
        try {
            // Validar dados
            $validation = $this->fornecedorModel->validate($data);
            if (!$validation['valid']) {
                return $this->validationErrorResponse($validation['errors']);
            }

            // Verificar duplicações
            if ($this->fornecedorModel->cnpjExists($data['cnpj'])) {
                return $this->errorResponse('CNPJ já cadastrado', 409);
            }

            if ($this->fornecedorModel->emailExists($data['email'])) {
                return $this->errorResponse('E-mail já cadastrado', 409);
            }

            // Limpar CNPJ para salvar apenas números
            $data['cnpj'] = preg_replace('/[^0-9]/', '', $data['cnpj']);

            // Criar fornecedor
            $id = $this->fornecedorModel->create($data);
            
            if ($id > 0) {
                $this->logActivity('CREATE', 'fornecedor', $id, $data);
                
                $fornecedor = $this->fornecedorModel->findById($id);
                return $this->successResponse($fornecedor, 'Fornecedor criado com sucesso', 201);
            } else {
                return $this->errorResponse('Erro ao criar fornecedor');
            }
            
        } catch (Exception $e) {
            error_log("Erro em FornecedorController::store: " . $e->getMessage());
            return $this->errorResponse('Erro interno ao criar fornecedor');
        }
    }
    
    /**
     * Criar minha loja (fornecedor logado cria seu próprio registro)
     * POST /api/fornecedores/minha-loja
     */
    public function criarMinhaLoja($data) {
        try {
            // Primeiro tentar pegar usuário do token JWT
            $user = $this->getUsuarioLogado();
            
            // Se não houver token, verificar se tem nível nos dados (registro sem login)
            if (!$user) {
                $nivel = $data['nivel'] ?? null;
                
                if ($nivel === 'fornecedor') {
                    // Permitir criar sem token se nivel for fornecedor
                    $user = [
                        'nivel' => 'fornecedor',
                        'id' => null,
                        'fornecedor_id' => null
                    ];
                } else {
                    return $this->errorResponse('Token de acesso obrigatório. Faça login primeiro.', 401);
                }
            }
            
            // Verificar se usuário tem permissão
            $nivelUsuario = $user['nivel'] ?? 'comum';
            if (!in_array($nivelUsuario, ['fornecedor', 'executivo'])) {
                return $this->errorResponse('Apenas usuários fornecedores ou executivos podem criar loja', 403);
            }
            
            // Validar dados obrigatórios
            if (empty($data['nome']) || empty($data['email']) || empty($data['cnpj'])) {
                return $this->errorResponse('Nome, email e CNPJ são obrigatórios', 400);
            }
            
            // Verificar se já tem loja (apenas se for fornecedor autenticado via token)
            if ($user && !empty($user['fornecedor_id'])) {
                $lojaExistente = $this->fornecedorModel->findById($user['fornecedor_id']);
                if ($lojaExistente) {
                    return $this->errorResponse('Você já possui uma loja cadastrada: ' . ($lojaExistente['nome'] ?? 'Loja'), 409);
                }
            }
            
            // LIMITE: Verificar se usuário já tem loja pelo email (1 loja por usuário)
            if (!empty($data['email'])) {
                require_once __DIR__ . '/../models/UsuarioModel.php';
                $usuarioModel = new UsuarioModel();
                $usuarioExistente = $usuarioModel->findByEmail($data['email']);
                
                if ($usuarioExistente && !empty($usuarioExistente['fornecedor_id'])) {
                    $lojaExistente = $this->fornecedorModel->findById($usuarioExistente['fornecedor_id']);
                    if ($lojaExistente) {
                        return $this->errorResponse('Email já possui loja cadastrada: ' . $lojaExistente['nome'], 409);
                    }
                }
            }
            
            // Validar dados
            $validation = $this->fornecedorModel->validate($data);
            if (!$validation['valid']) {
                return $this->validationErrorResponse($validation['errors']);
            }

            // Verificar duplicações
            if ($this->fornecedorModel->cnpjExists($data['cnpj'])) {
                return $this->errorResponse('CNPJ já cadastrado', 409);
            }

            if ($this->fornecedorModel->emailExists($data['email'])) {
                return $this->errorResponse('E-mail já cadastrado', 409);
            }

            // Limpar CNPJ
            $data['cnpj'] = preg_replace('/[^0-9]/', '', $data['cnpj']);

            // Criar fornecedor
            $fornecedorId = $this->fornecedorModel->create($data);
            
            if ($fornecedorId > 0) {
                require_once __DIR__ . '/../models/UsuarioModel.php';
                $usuarioModel = new UsuarioModel();
                $usuarioVinculado = false;
                
                // Se houver usuário autenticado via token, vincular diretamente
                if ($user && isset($user['id']) && $user['id']) {
                    $usuarioModel->update($user['id'], ['fornecedor_id' => $fornecedorId]);
                    error_log("Loja ID $fornecedorId vinculada ao usuário autenticado ID " . $user['id']);
                    $usuarioVinculado = true;
                }
                // Se temos email no token, buscar por esse email
                else if ($user && isset($user['email']) && $user['email']) {
                    $usuarioExistente = $usuarioModel->findByEmail($user['email']);
                    if ($usuarioExistente) {
                        $usuarioModel->update($usuarioExistente['id'], ['fornecedor_id' => $fornecedorId]);
                        error_log("Loja ID $fornecedorId vinculada ao usuário ID " . $usuarioExistente['id'] . " (por email do token)");
                        $usuarioVinculado = true;
                    }
                }
                // Fallback: tentar buscar usuário pelo email fornecido nos dados da loja
                else if (!empty($data['email'])) {
                    $usuarioExistente = $usuarioModel->findByEmail($data['email']);
                    
                    if ($usuarioExistente && $usuarioExistente['nivel'] === 'fornecedor') {
                        $usuarioModel->update($usuarioExistente['id'], ['fornecedor_id' => $fornecedorId]);
                        error_log("Loja ID $fornecedorId vinculada ao usuário ID " . $usuarioExistente['id'] . " (por email dos dados)");
                        $usuarioVinculado = true;
                    }
                }
                
                $this->logActivity('CREATE', 'fornecedor', $fornecedorId, $data);
                
                $fornecedor = $this->fornecedorModel->findById($fornecedorId);
                
                // Resposta diferente se vinculou ou não ao usuário
                if ($usuarioVinculado) {
                    return $this->successResponse([
                        'fornecedor' => $fornecedor,
                        'mensagem' => 'Loja criada e vinculada ao seu usuário com sucesso!'
                    ], 'Loja criada com sucesso', 201);
                } else {
                    return $this->successResponse([
                        'fornecedor' => $fornecedor,
                        'mensagem' => 'Loja criada com sucesso!'
                    ], 'Loja criada com sucesso', 201);
                }
            } else {
                return $this->errorResponse('Erro ao criar loja');
            }
            
        } catch (Exception $e) {
            error_log("Erro em FornecedorController::criarMinhaLoja: " . $e->getMessage());
            return $this->errorResponse('Erro interno ao criar loja');
        }
    }
    
    /**
     * Obter minha loja (fornecedor logado)
     * GET /api/fornecedores/minha-loja ou GET /api/fornecedores?minha_loja=true
     */
    public function minhaLoja() {
        try {
            // Verificar autenticação
            $user = $this->requireAuth(['fornecedor', 'executivo']);
            
            if (!$user) {
                return $this->errorResponse('Token de acesso inválido ou nível insuficiente', 401);
            }
            
            // Verificar se tem loja vinculada
            if (empty($user['fornecedor_id'])) {
                return $this->successResponse([], 'Nenhuma loja encontrada. Crie uma loja primeiro.');
            }
            
            $fornecedor = $this->fornecedorModel->findById($user['fornecedor_id']);
            
            if (!$fornecedor) {
                return $this->successResponse([], 'Loja não encontrada no sistema.');
            }
            
            return $this->successResponse([$fornecedor], 'Sua loja encontrada');
            
        } catch (Exception $e) {
            error_log("Erro em FornecedorController::minhaLoja: " . $e->getMessage());
            return $this->errorResponse('Erro ao buscar sua loja');
        }
    }

    /**
     * Atualizar fornecedor
     * @param int $id
     * @param array $data
     * @return array
     */
    public function update($id, $data) {
        try {
            if (!$this->isValidId($id)) {
                return $this->errorResponse('ID inválido', 400);
            }

            // Verificar se fornecedor existe
            $fornecedor_atual = $this->fornecedorModel->findById($id);
            if (!$fornecedor_atual) {
                return $this->notFoundResponse('Fornecedor');
            }

            // Validar dados
            $validation = $this->fornecedorModel->validate($data);
            if (!$validation['valid']) {
                return $this->validationErrorResponse($validation['errors']);
            }

            // Verificar duplicações (excluindo o próprio registro)
            if ($this->fornecedorModel->cnpjExists($data['cnpj'], $id)) {
                return $this->errorResponse('CNPJ já cadastrado para outro fornecedor', 409);
            }

            if ($this->fornecedorModel->emailExists($data['email'], $id)) {
                return $this->errorResponse('E-mail já cadastrado para outro fornecedor', 409);
            }

            // Limpar CNPJ
            $data['cnpj'] = preg_replace('/[^0-9]/', '', $data['cnpj']);

            // Atualizar
            if ($this->fornecedorModel->update($id, $data)) {
                $this->logActivity('UPDATE', 'fornecedor', $id, $data);
                
                $fornecedor = $this->fornecedorModel->findById($id);
                return $this->successResponse($fornecedor, 'Fornecedor atualizado com sucesso');
            } else {
                return $this->errorResponse('Erro ao atualizar fornecedor');
            }
            
        } catch (Exception $e) {
            error_log("Erro em FornecedorController::update: " . $e->getMessage());
            return $this->errorResponse('Erro interno ao atualizar fornecedor');
        }
    }

    /**
     * Excluir fornecedor
     * @param int $id
     * @return array
     */
    public function delete($id) {
        try {
            if (!$this->isValidId($id)) {
                return $this->errorResponse('ID inválido', 400);
            }

            // Verificar se fornecedor existe
            $fornecedor = $this->fornecedorModel->findById($id);
            if (!$fornecedor) {
                return $this->notFoundResponse('Fornecedor');
            }

            // Verificar se tem produtos vinculados
            $total_produtos = $this->fornecedorModel->countProdutos($id);
            if ($total_produtos > 0) {
                return $this->errorResponse(
                    "Não é possível excluir. Fornecedor está vinculado a {$total_produtos} produto(s)", 
                    409
                );
            }

            // Excluir
            if ($this->fornecedorModel->delete($id)) {
                $this->logActivity('DELETE', 'fornecedor', $id, $fornecedor);
                
                return $this->successResponse(null, 'Fornecedor excluído com sucesso');
            } else {
                return $this->errorResponse('Erro ao excluir fornecedor');
            }
            
        } catch (Exception $e) {
            error_log("Erro em FornecedorController::delete: " . $e->getMessage());
            return $this->errorResponse('Erro interno ao excluir fornecedor');
        }
    }

    /**
     * Listar apenas fornecedores ativos
     * @return array
     */
    public function ativos() {
        try {
            $fornecedores = $this->fornecedorModel->findAtivos();
            return $this->successResponse($fornecedores, 'Fornecedores ativos listados');
            
        } catch (Exception $e) {
            error_log("Erro em FornecedorController::ativos: " . $e->getMessage());
            return $this->errorResponse('Erro ao listar fornecedores ativos');
        }
    }

    /**
     * Alterar status do fornecedor
     * @param int $id
     * @param string $status
     * @return array
     */
    public function alterarStatus($id, $status) {
        try {
            if (!$this->isValidId($id)) {
                return $this->errorResponse('ID inválido', 400);
            }

            if (!in_array($status, ['Ativo', 'Inativo'])) {
                return $this->errorResponse('Status deve ser Ativo ou Inativo', 400);
            }

            $fornecedor = $this->fornecedorModel->findById($id);
            if (!$fornecedor) {
                return $this->notFoundResponse('Fornecedor');
            }

            if ($this->fornecedorModel->update($id, ['status' => $status])) {
                $this->logActivity('UPDATE_STATUS', 'fornecedor', $id, ['status' => $status]);
                
                $fornecedor_atualizado = $this->fornecedorModel->findById($id);
                return $this->successResponse($fornecedor_atualizado, 'Status atualizado com sucesso');
            } else {
                return $this->errorResponse('Erro ao atualizar status');
            }
            
        } catch (Exception $e) {
            error_log("Erro em FornecedorController::alterarStatus: " . $e->getMessage());
            return $this->errorResponse('Erro interno ao alterar status');
        }
    }
}