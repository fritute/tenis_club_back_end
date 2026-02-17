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
            // Tentar pegar usuário autenticado (sem forçar autenticação)
            $user = $this->getAuthenticatedUser();
            
            // Se for fornecedor, filtrar apenas seus produtos
            if ($user && isset($user['nivel']) && $user['nivel'] === 'fornecedor') {
                if (!empty($user['fornecedor_id'])) {
                    // Fornecedor só vê produtos da sua loja
                    $filters['fornecedor_id'] = $user['fornecedor_id'];
                } else {
                    // Fornecedor sem loja criada não vê produtos
                    return $this->successResponse([], 'Você precisa criar sua loja primeiro');
                }
            }
            // Usuários comuns e executivos veem todos os produtos
            
            $produtos = $this->produtoModel->search($filters);
            
            return $this->successResponse($produtos, 'Produtos listados com sucesso');
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoController::index: " . $e->getMessage());
            return $this->errorResponse('Erro ao listar produtos');
        }
    }
    
    /**
     * Listar produtos da empresa do usuário logado
     * @return array
     */
    public function minhaEmpresa() {
        try {
            // Verificar autenticação
            $user = $this->requireAuth(['fornecedor', 'executivo']);
            
            // Verificar se usuário tem fornecedor_id
            if (empty($user['fornecedor_id'])) {
                return $this->errorResponse('Usuário não está associado a nenhuma empresa', 403);
            }
            
            // Buscar produtos do fornecedor
            $produtos = $this->produtoModel->findByFornecedor($user['fornecedor_id']);
            
            return $this->successResponse($produtos, 'Produtos da empresa listados com sucesso');
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoController::minhaEmpresa: " . $e->getMessage());
            return $this->errorResponse('Erro ao listar produtos da empresa');
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
            // Verificar autenticação - apenas fornecedores podem criar produtos
            $user = $this->requireAuth(['fornecedor']);
            
            // Verificar se usuário tem fornecedor_id
            if (empty($user['fornecedor_id'])) {
                return $this->errorResponse('Usuário não está associado a nenhuma loja. Crie sua loja primeiro.', 403);
            }
            
            // Atribuir automaticamente o fornecedor_id do usuário logado
            $data['fornecedor_id'] = $user['fornecedor_id'];
            
            // Definir valores padrão para facilitar a criação pelo fornecedor
            if (!isset($data['status']) || empty($data['status'])) {
                $data['status'] = 'Ativo'; // Produto ativo por padrão
            }
            
            if (!isset($data['descricao']) || empty($data['descricao'])) {
                $data['descricao'] = ''; // Descrição pode ficar vazia inicialmente
            }
            
            // Validar dados
            $validation = $this->produtoModel->validate($data);
            if (!$validation['valid']) {
                return $this->validationErrorResponse($validation['errors']);
            }
            
            // Usar dados validados (podem ter valores padrão aplicados)
            $validatedData = $validation['data'];

            // Verificar se código interno já existe (se foi fornecido)
            if (!empty($validatedData['codigo_interno']) && $this->produtoModel->codigoInternoExists($validatedData['codigo_interno'])) {
                return $this->errorResponse('Código interno já cadastrado', 409);
            }

            // Criar produto
            $id = $this->produtoModel->create($validatedData);
            
            if ($id > 0) {
                $this->logActivity('CREATE', 'produto', $id, $validatedData);
                
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
     * Adicionar produto à minha loja (fornecedor)
     * Versão simplificada para fornecedores adicionarem produtos rapidamente
     * @param array $data
     * @return array
     */
    public function addToMyStore($data) {
        try {
            // Verificar autenticação - apenas fornecedores
            $user = $this->requireAuth(['fornecedor']);
            
            // Verificar se usuário tem fornecedor_id
            if (empty($user['fornecedor_id'])) {
                return $this->errorResponse('Usuário não está associado a nenhuma loja. Crie sua loja primeiro.', 403);
            }
            
            // Atribuir automaticamente o fornecedor_id do usuário logado
            $data['fornecedor_id'] = $user['fornecedor_id'];
            
            // Definir valores padrão para simplificar criação
            $defaults = [
                'status' => 'Ativo',
                'descricao' => '',
                'codigo_interno' => '',
            ];
            
            // Aplicar valores padrão apenas para campos não informados
            foreach ($defaults as $field => $defaultValue) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    $data[$field] = $defaultValue;
                }
            }
            
            // Validar apenas campos obrigatórios
            $errors = [];
            
            if (empty($data['nome']) || strlen(trim($data['nome'])) < 2) {
                $errors['nome'] = 'Nome do produto é obrigatório';
            }
            
            if (!empty($errors)) {
                return $this->validationErrorResponse($errors);
            }
            
            // Validar dados completos
            $validation = $this->produtoModel->validate($data);
            if (!$validation['valid']) {
                return $this->validationErrorResponse($validation['errors']);
            }
            
            // Usar dados validados
            $validatedData = $validation['data'];
            
            // Verificar código interno único (se fornecido)
            if (!empty($validatedData['codigo_interno']) && $this->produtoModel->codigoInternoExists($validatedData['codigo_interno'])) {
                return $this->errorResponse('Código interno já cadastrado', 409);
            }
            
            // Criar produto
            $id = $this->produtoModel->create($validatedData);
            
            if ($id > 0) {
                $this->logActivity('CREATE', 'produto_minha_loja', $id, $validatedData);
                
                $produto = $this->produtoModel->findById($id);
                return $this->successResponse($produto, 'Produto adicionado à sua loja com sucesso!', 201);
            } else {
                return $this->errorResponse('Erro ao adicionar produto à loja');
            }
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoController::addToMyStore: " . $e->getMessage());
            return $this->errorResponse('Erro interno ao adicionar produto à loja');
        }
    }

    /**
     * Alterar status do produto (Ativo/Inativo)
     * Permite que fornecedor altere status dos seus produtos
     * @param int $id
     * @param string $status
     * @return array
     */
    public function alterarStatusProduto($id, $status) {
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
            
            // Verificar se produto existe
            $produto = $this->produtoModel->findById($id);
            if (!$produto) {
                return $this->notFoundResponse('Produto');
            }
            
            // Se for fornecedor, verificar se o produto pertence a ele
            if ($user['nivel'] === 'fornecedor') {
                if (empty($user['fornecedor_id'])) {
                    return $this->errorResponse('Usuário não está associado a nenhuma loja', 403);
                }
                
                if (empty($produto['fornecedor_id']) || $produto['fornecedor_id'] != $user['fornecedor_id']) {
                    return $this->errorResponse('Você não tem permissão para alterar este produto', 403);
                }
            }
            
            // Atualizar status
            $dados_atualizacao = ['status' => $status];
            $sucesso = $this->produtoModel->update($id, $dados_atualizacao);
            
            if ($sucesso) {
                $this->logActivity('STATUS_CHANGE', 'produto', $id, [
                    'status_anterior' => $produto['status'],
                    'status_novo' => $status
                ]);
                
                return $this->successResponse([
                    'id' => $id,
                    'status' => $status,
                    'status_anterior' => $produto['status']
                ], 'Status do produto alterado com sucesso');
            } else {
                return $this->errorResponse('Erro ao alterar status do produto');
            }
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoController::alterarStatusProduto: " . $e->getMessage());
            return $this->errorResponse('Erro interno ao alterar status');
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

            // Verificar autenticação
            $user = $this->requireAuth(['fornecedor', 'executivo']);

            // Verificar se produto existe
            $produto_atual = $this->produtoModel->findById($id);
            if (!$produto_atual) {
                return $this->notFoundResponse('Produto');
            }

            // Se for fornecedor, verificar se o produto pertence a ele
            if ($user['nivel'] === 'fornecedor') {
                if (empty($user['fornecedor_id'])) {
                    return $this->errorResponse('Usuário não está associado a nenhuma loja', 403);
                }
                
                if (empty($produto_atual['fornecedor_id']) || $produto_atual['fornecedor_id'] != $user['fornecedor_id']) {
                    return $this->errorResponse('Você não tem permissão para editar este produto', 403);
                }
                
                // Garantir que o fornecedor_id não seja alterado
                $data['fornecedor_id'] = $user['fornecedor_id'];
            }
            
            // Tratamento de valores monetários e numéricos
            if (isset($data['preco'])) {
                 $data['preco_base'] = (float)$data['preco'];
                 unset($data['preco']); // Remove campo inexistente na tabela
            } elseif (isset($data['preco_base'])) {
                 $data['preco_base'] = (float)$data['preco_base'];
            }

            if (isset($data['categoria_id'])) {
                 $data['categoria_id'] = (int)$data['categoria_id'];
            }
            
            // Remover campos que não devem ser atualizados via PUT genérico
            unset($data['id']);
            unset($data['data_cadastro']);
            unset($data['imagem']); // Imagem é atualizada via upload separado
            
            // Filtrar apenas campos que existem na tabela produtos
            $camposPermitidos = [
                'nome', 
                'descricao', 
                'codigo_interno', 
                'categoria_id', 
                'preco_base', 
                'fornecedor_id', 
                'status'
            ];
            
            // Remove chaves que não estão na lista de permitidos
            $data = array_intersect_key($data, array_flip($camposPermitidos));
            
            // Validar dados
            $validation = $this->produtoModel->validate($data);
            if (!$validation['valid']) {
                return $this->validationErrorResponse($validation['errors']);
            }

            // Verificar se código interno já existe (excluindo o próprio registro)
            if (!empty($data['codigo_interno']) && $this->produtoModel->codigoInternoExists($data['codigo_interno'], $id)) {
                return $this->errorResponse('Código interno já cadastrado para outro produto', 409);
            }

            // Atualizar
            if ($this->produtoModel->update($id, $data)) {
                // Log activity sem travar em caso de erro
                try {
                     $this->logActivity('UPDATE', 'produto', $id, $data);
                } catch (Exception $e) {
                     error_log("Erro ao logar atividade: " . $e->getMessage());
                }
                
                $produto = $this->produtoModel->findById($id);
                return $this->successResponse($produto, 'Produto atualizado com sucesso');
            } else {
                return $this->errorResponse('Erro ao atualizar produto');
            }
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoController::update: " . $e->getMessage());
            return $this->errorResponse('Erro interno ao atualizar produto: ' . $e->getMessage(), 500);
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

            // Verificar autenticação
            $user = $this->requireAuth(['fornecedor', 'executivo']);

            // Verificar se produto existe
            $produto = $this->produtoModel->findById($id);
            if (!$produto) {
                return $this->notFoundResponse('Produto');
            }

            // Se for fornecedor, verificar se o produto pertence a ele
            if ($user['nivel'] === 'fornecedor') {
                if (empty($user['fornecedor_id'])) {
                    return $this->errorResponse('Usuário não está associado a nenhuma loja', 403);
                }
                
                if (empty($produto['fornecedor_id']) || $produto['fornecedor_id'] != $user['fornecedor_id']) {
                    return $this->errorResponse('Você não tem permissão para excluir este produto', 403);
                }
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
     * Listar produtos disponíveis para vínculo (marketplace)
     * Mostra produtos de outras lojas que o fornecedor atual ainda não vende
     * @return array
     */
    public function disponiveisParaVinculo() {
        try {
            // Verificar autenticação
            $user = $this->requireAuth(['fornecedor']);
            
            if (empty($user['fornecedor_id'])) {
                return $this->errorResponse('Usuário não está associado a nenhuma loja', 403);
            }
            
            $produtos = $this->produtoModel->findDisponiveisParaVinculo($user['fornecedor_id']);
            
            return $this->successResponse($produtos, 'Produtos disponíveis para vínculo listados');
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoController::disponiveisParaVinculo: " . $e->getMessage());
            return $this->errorResponse('Erro ao listar produtos disponíveis');
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

            // Verificar autenticação
            $user = $this->requireAuth(['fornecedor', 'executivo']);

            $produto = $this->produtoModel->findById($id);
            if (!$produto) {
                return $this->notFoundResponse('Produto');
            }

            // Se for fornecedor, verificar se o produto pertence a ele
            if ($user['nivel'] === 'fornecedor') {
                if (empty($user['fornecedor_id'])) {
                    return $this->errorResponse('Usuário não está associado a nenhuma loja', 403);
                }
                
                if (empty($produto['fornecedor_id']) || $produto['fornecedor_id'] != $user['fornecedor_id']) {
                    return $this->errorResponse('Você não tem permissão para alterar o status deste produto', 403);
                }
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

    /**
     * Listar produtos públicos (para usuários visualizarem sem restrições)
     * @return array
     */
    public function publicos() {
        try {
            // Qualquer usuário pode ver produtos ativos - sem necessidade de autenticação
            $produtos = $this->produtoModel->findAtivos();
            
            return $this->successResponse($produtos, 'Produtos públicos listados');
            
        } catch (Exception $e) {
            error_log("Erro em ProdutoController::publicos: " . $e->getMessage());
            return $this->errorResponse('Erro ao listar produtos públicos');
        }
    }
}