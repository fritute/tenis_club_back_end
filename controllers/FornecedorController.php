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
            $fornecedores = $this->fornecedorModel->search($filters);
            
            return $this->successResponse($fornecedores, 'Fornecedores listados com sucesso');
            
        } catch (Exception $e) {
            error_log("Erro em FornecedorController::index: " . $e->getMessage());
            return $this->errorResponse('Erro ao listar fornecedores');
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