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
     * Definir fornecedor principal
     * PUT /api/vinculos/{id_produto}/{id_fornecedor}/principal
     */
    public function setPrincipal($id_produto, $id_fornecedor) {
        try {
            if (!$this->isValidId($id_produto) || !$this->isValidId($id_fornecedor)) {
                return $this->errorResponse('IDs inválidos', 400);
            }

            // Verificar se vínculo existe
            if (!$this->produtoFornecedorModel->vinculoExists($id_produto, $id_fornecedor)) {
                return $this->errorResponse('Vínculo não encontrado. Crie o vínculo antes de definir como principal.', 404);
            }
            
            // Verificar status do fornecedor (opcional, mas recomendado)
            require_once __DIR__ . '/../models/FornecedorModel.php';
            $fornecedorModel = new FornecedorModel();
            $fornecedor = $fornecedorModel->findById($id_fornecedor);
            if ($fornecedor && strtolower($fornecedor['status']) !== 'ativo') {
                return $this->errorResponse('Fornecedor inativo não pode ser definido como principal', 403);
            }

            if ($this->produtoFornecedorModel->setPrincipal($id_produto, $id_fornecedor)) {
                $this->logActivity('SET_PRINCIPAL_FORNECEDOR', 'produto_fornecedor', null, [
                    'produto_id' => $id_produto,
                    'fornecedor_id' => $id_fornecedor
                ]);
                return $this->successResponse(null, 'Fornecedor definido como principal com sucesso');
            } else {
                return $this->errorResponse('Erro ao definir fornecedor principal');
            }
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoFornecedorController::setPrincipal: " . $e->getMessage());
            return $this->errorResponse('Erro interno ao definir principal');
        }
    }

    /**
     * Listar histórico de vínculos
     * GET /api/vinculos/historico
     * GET /api/vinculos/historico?produto_id=X
     */
    public function historico() {
        try {
            $id_produto = $_GET['produto_id'] ?? null;
            
            $historico = $this->produtoFornecedorModel->getHistorico($id_produto);
            
            return $this->successResponse($historico, 'Histórico de vínculos listado com sucesso');
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoFornecedorController::historico: " . $e->getMessage());
            return $this->errorResponse('Erro ao listar histórico');
        }
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
            // Normalizar entrada para aceitar tanto id_produto quanto produto_id
            $id_produto = $data['id_produto'] ?? $data['produto_id'] ?? null;
            $id_fornecedor = $data['id_fornecedor'] ?? $data['fornecedor_id'] ?? null;

            if (!$id_produto || !$id_fornecedor) {
                return $this->errorResponse('Campos id_produto e id_fornecedor são obrigatórios', 400);
            }
            
            // Validar IDs
            if (!$this->isValidId($id_produto)) {
                return $this->errorResponse('ID do produto inválido', 400);
            }

            if (!$this->isValidId($id_fornecedor)) {
                return $this->errorResponse('ID do fornecedor inválido', 400);
            }
            
            // Verificar status do fornecedor (impedido vincular se inativo)
            require_once __DIR__ . '/../models/FornecedorModel.php';
            $fornecedorModel = new FornecedorModel();
            $fornecedor = $fornecedorModel->findById($id_fornecedor);
            
            if (!$fornecedor) {
                return $this->errorResponse('Fornecedor não encontrado', 404);
            }
            
            // Verifica status de forma case-insensitive
            $status = $fornecedor['status'] ?? '';
            if (strcasecmp($status, 'Ativo') !== 0) {
                return $this->errorResponse('Não é possível vincular produtos a um fornecedor inativo', 403);
            }

            // Verificar se vínculo já existe
            if ($this->produtoFornecedorModel->vinculoExists($id_produto, $id_fornecedor)) {
                return $this->errorResponse('Vínculo já existe', 409);
            }

            // Criar vínculo
            if ($this->produtoFornecedorModel->criarVinculo($id_produto, $id_fornecedor)) {
                // Log activity
                try {
                     $this->logActivity('CREATE_VINCULO', 'produto_fornecedor', null, [
                        'produto_id' => $id_produto, 
                        'fornecedor_id' => $id_fornecedor
                     ]);
                } catch (Exception $e) {
                     error_log("Erro de log: " . $e->getMessage());
                }
                
                return $this->successResponse([
                    'id_produto' => $id_produto,
                    'id_fornecedor' => $id_fornecedor
                ], 'Vínculo criado com sucesso', 201);
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