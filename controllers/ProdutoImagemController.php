<?php
/**
 * Controller para Imagens de Produtos
 * Virtual Market System
 * VERSÃO SEM AUTENTICAÇÃO PARA TESTE
 */

require_once __DIR__ . '/../models/ProdutoImagemModel.php';
require_once __DIR__ . '/BaseController.php';

class ProdutoImagemController extends BaseController {
    
    private $produtoImagemModel;
    
    public function __construct() {
        parent::__construct();
        $this->produtoImagemModel = new ProdutoImagemModel();
    }

    /**
     * Listar imagens de um produto
     */
    public function listarPorProduto($produto_id) {
        try {
            $imagens = $this->produtoImagemModel->getImagensProduto($produto_id);
            return $this->jsonResponse($imagens);
        } catch (Exception $e) {
            return $this->jsonResponse(['error' => 'Erro ao listar imagens: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obter uma imagem específica
     */
    public function obter($id) {
        try {
            $imagem = $this->produtoImagemModel->findById($id);
            
            if (!$imagem || $imagem['deletado_em']) {
                return $this->jsonResponse(['error' => 'Imagem não encontrada'], 404);
            }
            
            return $this->jsonResponse($imagem);
        } catch (Exception $e) {
            return $this->jsonResponse(['error' => 'Erro ao obter imagem: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Upload de imagem
     * AUTENTICAÇÃO REMOVIDA PARA TESTE
     */
    public function upload() {
        try {
            // AUTENTICAÇÃO DESABILITADA
            // $usuario = $this->getUsuarioLogado();
            // if (!$usuario) {
            //     return $this->jsonResponse(['error' => 'Usuário não autenticado'], 401);
            // }

            // PERMISSÃO DESABILITADA
            // if (!in_array($usuario['nivel'], ['fornecedor', 'executivo'])) {
            //     return $this->jsonResponse(['error' => 'Sem permissão para fazer upload de imagens'], 403);
            // }

            // Verificar se arquivo foi enviado
            if (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
                return $this->jsonResponse(['error' => 'Nenhum arquivo de imagem enviado'], 400);
            }

            // Obter dados adicionais
            $produto_id = $_POST['produto_id'] ?? null;
            $descricao = $_POST['descricao'] ?? '';
            $alt_text = $_POST['alt_text'] ?? '';
            $eh_principal = isset($_POST['eh_principal']) ? (bool)$_POST['eh_principal'] : false;

            if (!$produto_id) {
                return $this->jsonResponse(['error' => 'produto_id é obrigatório'], 400);
            }

            $arquivo = $_FILES['imagem'];
            
            // Validar tipo de arquivo
            $tipos_permitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!in_array($arquivo['type'], $tipos_permitidos)) {
                return $this->jsonResponse(['error' => 'Tipo de arquivo não permitido. Use JPEG, PNG ou WebP'], 400);
            }

            // Validar tamanho (máximo 5MB)
            $tamanho_maximo = 5 * 1024 * 1024; // 5MB
            if ($arquivo['size'] > $tamanho_maximo) {
                return $this->jsonResponse(['error' => 'Arquivo muito grande. Máximo 5MB'], 400);
            }

            // Criar diretório se não existir
            $upload_dir = __DIR__ . '/../uploads/produtos/' . $produto_id . '/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Gerar nome único para o arquivo
            $extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
            $nome_arquivo = uniqid('img_') . '.' . $extensao;
            $caminho_completo = $upload_dir . $nome_arquivo;
            $caminho_relativo = 'uploads/produtos/' . $produto_id . '/' . $nome_arquivo;

            // Mover arquivo
            if (!move_uploaded_file($arquivo['tmp_name'], $caminho_completo)) {
                return $this->jsonResponse(['error' => 'Erro ao salvar arquivo'], 500);
            }

            // Obter dimensões da imagem
            $info_imagem = getimagesize($caminho_completo);
            $largura = $info_imagem[0] ?? 0;
            $altura = $info_imagem[1] ?? 0;

            // Salvar no banco
            $dados_imagem = [
                'produto_id' => $produto_id,
                'nome_arquivo' => $nome_arquivo,
                'caminho' => $caminho_relativo,
                'descricao' => $descricao,
                'alt_text' => $alt_text,
                'tamanho' => $arquivo['size'],
                'tipo_mime' => $arquivo['type'],
                'largura' => $largura,
                'altura' => $altura,
                'eh_principal' => $eh_principal
            ];

            $id = $this->produtoImagemModel->adicionarImagem($dados_imagem);

            if ($id) {
                return $this->jsonResponse([
                    'success' => true,
                    'id' => $id,
                    'caminho' => $caminho_relativo,
                    'message' => 'Imagem enviada com sucesso'
                ], 201);
            } else {
                // Remover arquivo se falhou ao salvar no banco
                unlink($caminho_completo);
                return $this->jsonResponse(['error' => 'Erro ao salvar imagem no banco'], 500);
            }

        } catch (Exception $e) {
            return $this->jsonResponse(['error' => 'Erro no upload: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Definir imagem como principal
     * AUTENTICAÇÃO REMOVIDA PARA TESTE
     */
    public function definirPrincipal($id) {
        try {
            // AUTENTICAÇÃO DESABILITADA
            // $usuario = $this->requireAuth(['fornecedor', 'executivo']);
            
            $imagem = $this->produtoImagemModel->findById($id);
            if (!$imagem || $imagem['deletado_em']) {
                return $this->jsonResponse(['error' => 'Imagem não encontrada'], 404);
            }
            
            $resultado = $this->produtoImagemModel->definirPrincipal($id);

            if ($resultado) {
                return $this->jsonResponse([
                    'success' => true,
                    'message' => 'Imagem definida como principal'
                ]);
            } else {
                return $this->jsonResponse(['error' => 'Erro ao definir imagem como principal'], 500);
            }

        } catch (Exception $e) {
            return $this->jsonResponse(['error' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Alterar ordem de uma imagem
     * AUTENTICAÇÃO REMOVIDA PARA TESTE
     */
    public function alterarOrdem($id) {
        try {
            // AUTENTICAÇÃO DESABILITADA
            // $usuario = $this->requireAuth(['fornecedor', 'executivo']);
            
            $data = $this->getJsonData();

            if (!isset($data['ordem'])) {
                return $this->jsonResponse(['error' => 'Ordem é obrigatória'], 400);
            }

            $imagem = $this->produtoImagemModel->findById($id);
            if (!$imagem || $imagem['deletado_em']) {
                return $this->jsonResponse(['error' => 'Imagem não encontrada'], 404);
            }

            $resultado = $this->produtoImagemModel->update($id, ['ordem' => (int)$data['ordem']]);

            if ($resultado) {
                return $this->jsonResponse([
                    'success' => true,
                    'message' => 'Ordem da imagem alterada'
                ]);
            } else {
                return $this->jsonResponse(['error' => 'Erro ao alterar ordem'], 500);
            }

        } catch (Exception $e) {
            return $this->jsonResponse(['error' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reordenar todas as imagens de um produto
     * AUTENTICAÇÃO REMOVIDA PARA TESTE
     */
    public function reordenar() {
        try {
            // AUTENTICAÇÃO DESABILITADA
            // $usuario = $this->requireAuth(['fornecedor', 'executivo']);
            
            $data = $this->getJsonData();

            if (!isset($data['produto_id']) || !isset($data['ordem'])) {
                return $this->jsonResponse(['error' => 'produto_id e array de ordem são obrigatórios'], 400);
            }

            $resultado = $this->produtoImagemModel->reordenarImagens($data['produto_id'], $data['ordem']);

            if ($resultado) {
                return $this->jsonResponse([
                    'success' => true,
                    'message' => 'Imagens reordenadas com sucesso'
                ]);
            } else {
                return $this->jsonResponse(['error' => 'Erro ao reordenar imagens'], 500);
            }

        } catch (Exception $e) {
            return $this->jsonResponse(['error' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Atualizar metadados da imagem
     * AUTENTICAÇÃO REMOVIDA PARA TESTE
     */
    public function atualizar($id) {
        try {
            // AUTENTICAÇÃO DESABILITADA
            // $usuario = $this->requireAuth(['fornecedor', 'executivo']);
            
            $data = $this->getJsonData();

            $imagem = $this->produtoImagemModel->findById($id);
            if (!$imagem || $imagem['deletado_em']) {
                return $this->jsonResponse(['error' => 'Imagem não encontrada'], 404);
            }

            // Campos permitidos para atualização
            $campos_permitidos = ['descricao', 'alt_text', 'ordem', 'eh_principal'];
            $dados_atualizacao = [];

            foreach ($campos_permitidos as $campo) {
                if (isset($data[$campo])) {
                    $dados_atualizacao[$campo] = $data[$campo];
                }
            }

            if (empty($dados_atualizacao)) {
                return $this->jsonResponse(['error' => 'Nenhum campo para atualizar'], 400);
            }

            $resultado = $this->produtoImagemModel->update($id, $dados_atualizacao);

            if ($resultado) {
                return $this->jsonResponse([
                    'success' => true,
                    'message' => 'Imagem atualizada com sucesso'
                ]);
            } else {
                return $this->jsonResponse(['error' => 'Erro ao atualizar imagem'], 500);
            }

        } catch (Exception $e) {
            return $this->jsonResponse(['error' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Deletar imagem (soft delete)
     * AUTENTICAÇÃO REMOVIDA PARA TESTE
     */
    public function deletar($id) {
        try {
            // AUTENTICAÇÃO DESABILITADA
            // $usuario = $this->requireAuth(['fornecedor', 'executivo']);

            $imagem = $this->produtoImagemModel->findById($id);
            if (!$imagem || $imagem['deletado_em']) {
                return $this->jsonResponse(['error' => 'Imagem não encontrada'], 404);
            }

            $resultado = $this->produtoImagemModel->removerImagem($id);

            if ($resultado) {
                return $this->jsonResponse([
                    'success' => true,
                    'message' => 'Imagem removida com sucesso'
                ]);
            } else {
                return $this->jsonResponse(['error' => 'Erro ao remover imagem'], 500);
            }

        } catch (Exception $e) {
            return $this->jsonResponse(['error' => 'Erro: ' . $e->getMessage()], 500);
        }
    }
}