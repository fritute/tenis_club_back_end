<?php
/**
 * Controller para Categorias
 * Virtual Market System
 */

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/CategoriaModel.php';

class CategoriaController extends BaseController {
    private $categoriaModel;

    public function __construct() {
        $this->categoriaModel = new CategoriaModel();
    }

    /**
     * Listar todas as categorias
     * @param array $filters
     * @return array
     */
    public function index($filters = []) {
        try {
            $where = '';
            $params = [];
            
            if (!empty($filters['nome'])) {
                $where .= "nome LIKE :nome";
                $params[':nome'] = '%' . $filters['nome'] . '%';
            }
            
            if (!empty($filters['status'])) {
                $where .= ($where ? ' AND ' : '') . "status = :status";
                $params[':status'] = $filters['status'];
            }
            
            $categorias = $this->categoriaModel->findAll($where, $params, 'nome ASC');
            
            return $this->successResponse($categorias, 'Categorias listadas com sucesso');
            
        } catch (Exception $e) {
            error_log("Erro em CategoriaController::index: " . $e->getMessage());
            return $this->errorResponse('Erro ao listar categorias');
        }
    }

    /**
     * Buscar categoria por ID
     * @param int $id
     * @return array
     */
    public function show($id) {
        try {
            if (!$this->isValidId($id)) {
                return $this->errorResponse('ID inválido', 400);
            }

            $categoria = $this->categoriaModel->findById($id);
            
            if (!$categoria) {
                return $this->notFoundResponse('Categoria');
            }

            return $this->successResponse($categoria, 'Categoria encontrada');
            
        } catch (Exception $e) {
            error_log("Erro em CategoriaController::show: " . $e->getMessage());
            return $this->errorResponse('Erro ao buscar categoria');
        }
    }

    /**
     * Criar nova categoria
     * @param array $data
     * @return array
     */
    public function store($data) {
        try {
            // Validar dados
            $validation = $this->categoriaModel->validate($data);
            if (!$validation['valid']) {
                return $this->validationErrorResponse($validation['errors']);
            }

            // Verificar duplicação do nome
            if ($this->categoriaModel->nomeExists($data['nome'])) {
                return $this->errorResponse('Já existe uma categoria com este nome', 409);
            }

            // Definir status padrão se não informado
            if (empty($data['status'])) {
                $data['status'] = 'Ativo';
            }

            // Criar categoria
            $id = $this->categoriaModel->create($data);
            
            if ($id > 0) {
                $this->logActivity('CREATE', 'categoria', $id, $data);
                
                $categoria = $this->categoriaModel->findById($id);
                return $this->successResponse($categoria, 'Categoria criada com sucesso', 201);
            } else {
                return $this->errorResponse('Erro ao criar categoria');
            }
            
        } catch (Exception $e) {
            error_log("Erro em CategoriaController::store: " . $e->getMessage());
            return $this->errorResponse('Erro interno ao criar categoria');
        }
    }

    /**
     * Atualizar categoria
     * @param int $id
     * @param array $data
     * @return array
     */
    public function update($id, $data) {
        try {
            if (!$this->isValidId($id)) {
                return $this->errorResponse('ID inválido', 400);
            }

            // Verificar se categoria existe
            $categoria_atual = $this->categoriaModel->findById($id);
            if (!$categoria_atual) {
                return $this->notFoundResponse('Categoria');
            }

            // Validar dados
            $validation = $this->categoriaModel->validate($data);
            if (!$validation['valid']) {
                return $this->validationErrorResponse($validation['errors']);
            }

            // Verificar duplicação do nome (excluindo o próprio registro)
            if ($this->categoriaModel->nomeExists($data['nome'], $id)) {
                return $this->errorResponse('Já existe outra categoria com este nome', 409);
            }

            // Atualizar
            if ($this->categoriaModel->update($id, $data)) {
                $this->logActivity('UPDATE', 'categoria', $id, $data);
                
                $categoria = $this->categoriaModel->findById($id);
                return $this->successResponse($categoria, 'Categoria atualizada com sucesso');
            } else {
                return $this->errorResponse('Erro ao atualizar categoria');
            }
            
        } catch (Exception $e) {
            error_log("Erro em CategoriaController::update: " . $e->getMessage());
            return $this->errorResponse('Erro interno ao atualizar categoria');
        }
    }

    /**
     * Excluir categoria
     * @param int $id
     * @return array
     */
    public function delete($id) {
        try {
            if (!$this->isValidId($id)) {
                return $this->errorResponse('ID inválido', 400);
            }

            // Verificar se categoria existe
            $categoria = $this->categoriaModel->findById($id);
            if (!$categoria) {
                return $this->notFoundResponse('Categoria');
            }

            // Verificar se tem produtos vinculados
            $total_produtos = $this->categoriaModel->countProdutos($id);
            if ($total_produtos > 0) {
                return $this->errorResponse(
                    "Não é possível excluir. Categoria possui {$total_produtos} produto(s)", 
                    409
                );
            }

            // Excluir
            if ($this->categoriaModel->delete($id)) {
                $this->logActivity('DELETE', 'categoria', $id, $categoria);
                
                return $this->successResponse(null, 'Categoria excluída com sucesso');
            } else {
                return $this->errorResponse('Erro ao excluir categoria');
            }
            
        } catch (Exception $e) {
            error_log("Erro em CategoriaController::delete: " . $e->getMessage());
            return $this->errorResponse('Erro interno ao excluir categoria');
        }
    }

    /**
     * Listar apenas categorias ativas
     * @return array
     */
    public function ativasLegado() {
        try {
            $categorias = $this->categoriaModel->findAtivas();
            return $this->successResponse($categorias, 'Categorias ativas listadas');
            
        } catch (Exception $e) {
            error_log("Erro em CategoriaController::ativasLegado: " . $e->getMessage());
            return $this->errorResponse('Erro ao listar categorias ativas');
        }
    }

    /**
     * Categorias com contagem de produtos
     * @return array
     */
    public function comContagem() {
        try {
            $categorias = $this->categoriaModel->findWithProdutoCount();
            return $this->successResponse($categorias, 'Categorias com contagem listadas');
            
        } catch (Exception $e) {
            error_log("Erro em CategoriaController::comContagem: " . $e->getMessage());
            return $this->errorResponse('Erro ao listar categorias com contagem');
        }
    }

    /**
     * Ranking de categorias mais populares
     * @param int $limit
     * @return array
     */
    public function populares($limit = 10) {
        try {
            $categorias = $this->categoriaModel->getCategoriasPopulares($limit);
            return $this->successResponse($categorias, 'Ranking de categorias populares');
            
        } catch (Exception $e) {
            error_log("Erro em CategoriaController::populares: " . $e->getMessage());
            return $this->errorResponse('Erro ao gerar ranking de categorias');
        }
    }

    /**
     * Alterar status da categoria
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

            $categoria = $this->categoriaModel->findById($id);
            if (!$categoria) {
                return $this->notFoundResponse('Categoria');
            }

            if ($this->categoriaModel->update($id, ['status' => $status])) {
                $this->logActivity('UPDATE_STATUS', 'categoria', $id, ['status' => $status]);
                
                $categoria_atualizada = $this->categoriaModel->findById($id);
                return $this->successResponse($categoria_atualizada, 'Status atualizado com sucesso');
            } else {
                return $this->errorResponse('Erro ao atualizar status');
            }
            
        } catch (Exception $e) {
            error_log("Erro em CategoriaController::alterarStatus: " . $e->getMessage());
            return $this->errorResponse('Erro interno ao alterar status');
        }
    }

    /**
     * Listar categorias ativas (para usuários visualizarem)
     * @return array
     */
    public function ativas() {
        try {
            // Qualquer usuário pode ver categorias ativas
            $categorias = $this->categoriaModel->findAll("status = 'Ativo'", [], 'nome ASC');
            
            return $this->successResponse($categorias, 'Categorias ativas listadas');
            
        } catch (Exception $e) {
            error_log("Erro em CategoriaController::ativas: " . $e->getMessage());
            return $this->errorResponse('Erro ao listar categorias ativas');
        }
    }
}