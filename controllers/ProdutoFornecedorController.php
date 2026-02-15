<?php
/**
 * Controller para Vínculos Produto-Fornecedor
 * Virtual Market System
 */

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/ProdutoFornecedorModel.php';

class ProdutoFornecedorController extends BaseController {
    private $produtoFornecedorModel;

    public function __construct() {
        $this->produtoFornecedorModel = new ProdutoFornecedorModel();
    }

    /**
     * Listar todos os vínculos
     * @param array $filters
     * @return array
     */
    public function index($filters = []) {
        try {
            $vinculos = $this->produtoFornecedorModel->getAllVinculos($filters);
            
            return $this->successResponse($vinculos, 'Vínculos listados com sucesso');
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoFornecedorController::index: " . $e->getMessage());
            return $this->errorResponse('Erro ao listar vínculos');
        }
    }

    /**
     * Criar novo vínculo
     * @param array $data
     * @return array
     */
    public function store($data) {
        try {
            // Validar dados obrigatórios
            $required_fields = ['id_produto', 'id_fornecedor'];
            $validation_errors = $this->validateRequiredFields($required_fields, $data);
            
            if (!empty($validation_errors)) {
                return $this->validationErrorResponse($validation_errors);
            }

            // Validar IDs
            if (!$this->isValidId($data['id_produto'])) {
                return $this->errorResponse('ID do produto inválido', 400);
            }

            if (!$this->isValidId($data['id_fornecedor'])) {
                return $this->errorResponse('ID do fornecedor inválido', 400);
            }

            // Verificar se vínculo já existe
            if ($this->produtoFornecedorModel->vinculoExists($data['id_produto'], $data['id_fornecedor'])) {
                return $this->errorResponse('Vínculo já existe', 409);
            }

            // Criar vínculo
            if ($this->produtoFornecedorModel->criarVinculo($data['id_produto'], $data['id_fornecedor'])) {
                $this->logActivity('CREATE_VINCULO', 'produto_fornecedor', null, $data);
                
                return $this->successResponse($data, 'Vínculo criado com sucesso', 201);
            } else {
                return $this->errorResponse('Erro ao criar vínculo');
            }
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoFornecedorController::store: " . $e->getMessage());
            return $this->errorResponse('Erro interno ao criar vínculo');
        }
    }

    /**
     * Remover vínculo específico
     * @param int $id_produto
     * @param int $id_fornecedor
     * @return array
     */
    public function delete($id_produto, $id_fornecedor) {
        try {
            if (!$this->isValidId($id_produto) || !$this->isValidId($id_fornecedor)) {
                return $this->errorResponse('IDs inválidos', 400);
            }

            // Verificar se vínculo existe
            if (!$this->produtoFornecedorModel->vinculoExists($id_produto, $id_fornecedor)) {
                return $this->notFoundResponse('Vínculo');
            }

            // Remover vínculo
            if ($this->produtoFornecedorModel->removerVinculo($id_produto, $id_fornecedor)) {
                $this->logActivity('DELETE_VINCULO', 'produto_fornecedor', null, [
                    'id_produto' => $id_produto,
                    'id_fornecedor' => $id_fornecedor
                ]);
                
                return $this->successResponse(null, 'Vínculo removido com sucesso');
            } else {
                return $this->errorResponse('Erro ao remover vínculo');
            }
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoFornecedorController::delete: " . $e->getMessage());
            return $this->errorResponse('Erro interno ao remover vínculo');
        }
    }

    /**     * Remover um vínculo por seu ID
     * @param int $vinculo_id
     * @return array
     */
    public function deleteById($vinculo_id) {
        try {
            if (!$this->isValidId($vinculo_id)) {
                return $this->errorResponse('ID inválido', 400);
            }

            // Verificar se vínculo existe
            $vinculo = $this->produtoFornecedorModel->findById($vinculo_id);
            if (!$vinculo) {
                return $this->errorResponse('Vínculo não encontrado', 404);
            }

            // Deletar por ID
            if ($this->produtoFornecedorModel->deleteById($vinculo_id)) {
                $this->logActivity('DELETE', 'vinculo', $vinculo_id, $vinculo);
                
                return $this->successResponse(null, 'Vínculo removido com sucesso');
            } else {
                return $this->errorResponse('Erro ao remover vínculo', 500);
            }
            
        } catch (Exception $e) {
            error_log("Erro em deleteById: " . $e->getMessage());
            return $this->errorResponse('Erro interno do servidor', 500);
        }
    }

    /**     * Remover vínculos em massa
     * @param array $data
     * @return array
     */
    public function deleteMultiple($data) {
        try {
            if (!isset($data['vinculos']) || !is_array($data['vinculos'])) {
                return $this->errorResponse('Lista de vínculos não informada', 400);
            }

            $vinculos = $data['vinculos'];
            
            // Validar cada vínculo
            foreach ($vinculos as $vinculo) {
                if (!isset($vinculo['id_produto']) || !isset($vinculo['id_fornecedor'])) {
                    return $this->errorResponse('Dados de vínculo incompletos', 400);
                }
                
                if (!$this->isValidId($vinculo['id_produto']) || !$this->isValidId($vinculo['id_fornecedor'])) {
                    return $this->errorResponse('IDs inválidos na lista de vínculos', 400);
                }
            }

            // Remover vínculos
            if ($this->produtoFornecedorModel->removerVinculosEmMassa($vinculos)) {
                $this->logActivity('DELETE_VINCULOS_MASSA', 'produto_fornecedor', null, ['count' => count($vinculos)]);
                
                return $this->successResponse(
                    ['removidos' => count($vinculos)], 
                    'Vínculos removidos com sucesso'
                );
            } else {
                return $this->errorResponse('Erro ao remover vínculos em massa');
            }
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoFornecedorController::deleteMultiple: " . $e->getMessage());
            return $this->errorResponse('Erro interno ao remover vínculos');
        }
    }

    /**
     * Buscar fornecedores de um produto
     * @param int $id_produto
     * @return array
     */
    public function getFornecedoresPorProduto($id_produto) {
        try {
            if (!$this->isValidId($id_produto)) {
                return $this->errorResponse('ID do produto inválido', 400);
            }

            $fornecedores = $this->produtoFornecedorModel->getFornecedoresPorProduto($id_produto);
            
            return $this->successResponse($fornecedores, 'Fornecedores do produto listados');
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoFornecedorController::getFornecedoresPorProduto: " . $e->getMessage());
            return $this->errorResponse('Erro ao buscar fornecedores do produto');
        }
    }

    /**
     * Buscar produtos de um fornecedor
     * @param int $id_fornecedor
     * @return array
     */
    public function getProdutosPorFornecedor($id_fornecedor) {
        try {
            if (!$this->isValidId($id_fornecedor)) {
                return $this->errorResponse('ID do fornecedor inválido', 400);
            }

            $produtos = $this->produtoFornecedorModel->getProdutosPorFornecedor($id_fornecedor);
            
            return $this->successResponse($produtos, 'Produtos do fornecedor listados');
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoFornecedorController::getProdutosPorFornecedor: " . $e->getMessage());
            return $this->errorResponse('Erro ao buscar produtos do fornecedor');
        }
    }

    /**
     * Remover todos os vínculos de um produto
     * @param int $id_produto
     * @return array
     */
    public function deleteAllByProduto($id_produto) {
        try {
            if (!$this->isValidId($id_produto)) {
                return $this->errorResponse('ID do produto inválido', 400);
            }

            if ($this->produtoFornecedorModel->removerTodosVinculosProduto($id_produto)) {
                $this->logActivity('DELETE_ALL_VINCULOS_PRODUTO', 'produto_fornecedor', $id_produto);
                
                return $this->successResponse(null, 'Todos os vínculos do produto foram removidos');
            } else {
                return $this->errorResponse('Erro ao remover todos os vínculos do produto');
            }
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoFornecedorController::deleteAllByProduto: " . $e->getMessage());
            return $this->errorResponse('Erro interno ao remover vínculos do produto');
        }
    }

    /**
     * Criar vínculos múltiplos para um produto
     * @param array $data
     * @return array
     */
    public function storeMultiple($data) {
        try {
            if (!isset($data['id_produto']) || !$this->isValidId($data['id_produto'])) {
                return $this->errorResponse('ID do produto inválido', 400);
            }

            if (!isset($data['fornecedores']) || !is_array($data['fornecedores'])) {
                return $this->errorResponse('Lista de fornecedores não informada', 400);
            }

            $id_produto = $data['id_produto'];
            $fornecedores = $data['fornecedores'];
            $vinculos_criados = 0;
            $vinculos_existentes = 0;

            foreach ($fornecedores as $id_fornecedor) {
                if (!$this->isValidId($id_fornecedor)) {
                    continue;
                }

                if ($this->produtoFornecedorModel->vinculoExists($id_produto, $id_fornecedor)) {
                    $vinculos_existentes++;
                } else {
                    if ($this->produtoFornecedorModel->criarVinculo($id_produto, $id_fornecedor)) {
                        $vinculos_criados++;
                    }
                }
            }

            $this->logActivity('CREATE_VINCULOS_MULTIPLOS', 'produto_fornecedor', $id_produto, [
                'criados' => $vinculos_criados,
                'existentes' => $vinculos_existentes
            ]);

            $message = "Vínculos processados: {$vinculos_criados} criados";
            if ($vinculos_existentes > 0) {
                $message .= ", {$vinculos_existentes} já existiam";
            }

            return $this->successResponse([
                'criados' => $vinculos_criados,
                'existentes' => $vinculos_existentes
            ], $message);
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoFornecedorController::storeMultiple: " . $e->getMessage());
            return $this->errorResponse('Erro interno ao criar vínculos múltiplos');
        }
    }

    /**
     * Relatórios e estatísticas
     * @return array
     */
    public function relatorios() {
        try {
            $data = [
                'estatisticas' => $this->produtoFornecedorModel->getEstatisticas(),
                'produtos_sem_fornecedores' => $this->produtoFornecedorModel->getProdutosSemFornecedores(),
                'fornecedores_sem_produtos' => $this->produtoFornecedorModel->getFornecedoresSemProdutos()
            ];
            
            return $this->successResponse($data, 'Relatórios gerados com sucesso');
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoFornecedorController::relatorios: " . $e->getMessage());
            return $this->errorResponse('Erro ao gerar relatórios');
        }
    }
}