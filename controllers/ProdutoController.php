<?php
/**
 * Controller para Produtos
 * Virtual Market System
 */

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/ProdutoModel.php';

class ProdutoController extends BaseController {
    private $produtoModel;

    public function __construct() {
        $this->produtoModel = new ProdutoModel();
    }

    /**
     * Listar todos os produtos
     * @param array $filters
     * @return array
     */
    public function index($filters = []) {
        try {
            $produtos = $this->produtoModel->search($filters);
            
            return $this->successResponse($produtos, 'Produtos listados com sucesso');
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoController::index: " . $e->getMessage());
            return $this->errorResponse('Erro ao listar produtos');
        }
    }

    /**
     * Buscar produto por ID
     * @param int $id
     * @return array
     */
    public function show($id) {
        try {
            if (!$this->isValidId($id)) {
                return $this->errorResponse('ID inválido', 400);
            }

            $produto = $this->produtoModel->findById($id);
            
            if (!$produto) {
                return $this->notFoundResponse('Produto');
            }

            return $this->successResponse($produto, 'Produto encontrado');
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoController::show: " . $e->getMessage());
            return $this->errorResponse('Erro ao buscar produto');
        }
    }

    /**
     * Criar novo produto
     * @param array $data
     * @return array
     */
    public function store($data) {
        try {
            // Validar dados
            $validation = $this->produtoModel->validate($data);
            if (!$validation['valid']) {
                return $this->validationErrorResponse($validation['errors']);
            }

            // Verificar se código interno já existe (se foi fornecido)
            if (!empty($data['codigo_interno']) && $this->produtoModel->codigoInternoExists($data['codigo_interno'])) {
                return $this->errorResponse('Código interno já cadastrado', 409);
            }

            // Criar produto
            $id = $this->produtoModel->create($data);
            
            if ($id > 0) {
                $this->logActivity('CREATE', 'produto', $id, $data);
                
                $produto = $this->produtoModel->findById($id);
                return $this->successResponse($produto, 'Produto criado com sucesso', 201);
            } else {
                return $this->errorResponse('Erro ao criar produto');
            }
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoController::store: " . $e->getMessage());
            return $this->errorResponse('Erro interno ao criar produto');
        }
    }

    /**
     * Atualizar produto
     * @param int $id
     * @param array $data
     * @return array
     */
    public function update($id, $data) {
        try {
            if (!$this->isValidId($id)) {
                return $this->errorResponse('ID inválido', 400);
            }

            // Verificar se produto existe
            $produto_atual = $this->produtoModel->findById($id);
            if (!$produto_atual) {
                return $this->notFoundResponse('Produto');
            }

            // Validar dados
            $validation = $this->produtoModel->validate($data);
            if (!$validation['valid']) {
                return $this->validationErrorResponse($validation['errors']);
            }

            // Verificar se código interno já existe (excluindo o próprio registro)
            if ($this->produtoModel->codigoInternoExists($data['codigo_interno'], $id)) {
                return $this->errorResponse('Código interno já cadastrado para outro produto', 409);
            }

            // Atualizar
            if ($this->produtoModel->update($id, $data)) {
                $this->logActivity('UPDATE', 'produto', $id, $data);
                
                $produto = $this->produtoModel->findById($id);
                return $this->successResponse($produto, 'Produto atualizado com sucesso');
            } else {
                return $this->errorResponse('Erro ao atualizar produto');
            }
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoController::update: " . $e->getMessage());
            return $this->errorResponse('Erro interno ao atualizar produto');
        }
    }

    /**
     * Excluir produto
     * @param int $id
     * @return array
     */
    public function delete($id) {
        try {
            if (!$this->isValidId($id)) {
                return $this->errorResponse('ID inválido', 400);
            }

            // Verificar se produto existe
            $produto = $this->produtoModel->findById($id);
            if (!$produto) {
                return $this->notFoundResponse('Produto');
            }

            // Verificar se tem fornecedores vinculados
            $total_fornecedores = $this->produtoModel->countFornecedores($id);
            if ($total_fornecedores > 0) {
                return $this->errorResponse(
                    "Não é possível excluir. Produto está vinculado a {$total_fornecedores} fornecedor(es)", 
                    409
                );
            }

            // Excluir
            if ($this->produtoModel->delete($id)) {
                $this->logActivity('DELETE', 'produto', $id, $produto);
                
                return $this->successResponse(null, 'Produto excluído com sucesso');
            } else {
                return $this->errorResponse('Erro ao excluir produto');
            }
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoController::delete: " . $e->getMessage());
            return $this->errorResponse('Erro interno ao excluir produto');
        }
    }

    /**
     * Listar apenas produtos ativos
     * @return array
     */
    public function ativos() {
        try {
            $produtos = $this->produtoModel->findAtivos();
            return $this->successResponse($produtos, 'Produtos ativos listados');
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoController::ativos: " . $e->getMessage());
            return $this->errorResponse('Erro ao listar produtos ativos');
        }
    }

    /**
     * Alterar status do produto
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

            $produto = $this->produtoModel->findById($id);
            if (!$produto) {
                return $this->notFoundResponse('Produto');
            }

            if ($this->produtoModel->update($id, ['status' => $status])) {
                $this->logActivity('UPDATE_STATUS', 'produto', $id, ['status' => $status]);
                
                $produto_atualizado = $this->produtoModel->findById($id);
                return $this->successResponse($produto_atualizado, 'Status atualizado com sucesso');
            } else {
                return $this->errorResponse('Erro ao atualizar status');
            }
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoController::alterarStatus: " . $e->getMessage());
            return $this->errorResponse('Erro interno ao alterar status');
        }
    }

    /**
     * Buscar produtos com seus fornecedores
     * @param int $id
     * @return array
     */
    public function showWithFornecedores($id = null) {
        try {
            if ($id && !$this->isValidId($id)) {
                return $this->errorResponse('ID inválido', 400);
            }

            $produtos = $this->produtoModel->findWithFornecedores($id);
            
            if ($id && empty($produtos)) {
                return $this->notFoundResponse('Produto');
            }

            return $this->successResponse($produtos, 'Produtos com fornecedores listados');
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoController::showWithFornecedores: " . $e->getMessage());
            return $this->errorResponse('Erro ao buscar produtos com fornecedores');
        }
    }

    /**
     * Buscar produtos sem fornecedores
     * @return array
     */
    public function semFornecedores() {
        try {
            $produtos = $this->produtoModel->findSemFornecedores();
            
            return $this->successResponse($produtos, 'Produtos sem fornecedores listados');
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoController::semFornecedores: " . $e->getMessage());
            return $this->errorResponse('Erro ao buscar produtos sem fornecedores');
        }
    }
}