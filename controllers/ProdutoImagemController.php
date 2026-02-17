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
            
            // Fallback: se não houver registros no banco, tentar ler do filesystem
            if (empty($imagens)) {
                $fs = $this->listarFs($produto_id);
                
                if (!empty($fs)) {
                    // Sincronizar FS -> persistência (DB ou JSON fallback)
                    $ordem = 1;
                    foreach ($fs as $img) {
                        $dados = [
                            'produto_id' => $produto_id,
                            'nome_arquivo' => $img['nome_arquivo'],
                            'caminho' => $img['caminho'],
                            'descricao' => $img['descricao'] ?? '',
                            'alt_text' => $img['alt_text'] ?? '',
                            'tamanho' => $img['tamanho'] ?? 0,
                            'tipo_mime' => $img['tipo_mime'] ?? 'image/*',
                            'largura' => $img['largura'] ?? 0,
                            'altura' => $img['altura'] ?? 0,
                            'eh_principal' => $ordem === 1 ? 1 : 0,
                            'ordem' => $ordem
                        ];
                        $this->produtoImagemModel->adicionarImagem($dados);
                        $ordem++;
                    }
                    // Buscar novamente, agora já persistido
                    $imagens = $this->produtoImagemModel->getImagensProduto($produto_id);
                    return $this->jsonResponse($imagens);
                }
            }
            return $this->jsonResponse($imagens);
        } catch (Exception $e) {
            return $this->jsonResponse(['error' => 'Erro ao listar imagens: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Retornar apenas a foto principal de um produto para uso em cards
     * Retorna URL da imagem ou placeholder se não houver
     */
    public function fotoPrincipal($produto_id) {
        try {
            $imagem = $this->produtoImagemModel->getImagemPrincipal($produto_id);
            
            if (!$imagem) {
                // Retornar placeholder ou URL padrão
                return $this->jsonResponse([
                    'success' => true,
                    'url' => '/uploads/produtos/placeholder.svg', // URL placeholder SVG
                    'alt_text' => 'Imagem não disponível',
                    'produto_id' => $produto_id,
                    'is_placeholder' => true
                ]);
            }
            
            // Construir URL completa da imagem
            $base_url = $this->getBaseUrl();
            $url_imagem = $base_url . '/uploads/produtos/' . $produto_id . '/' . $imagem['nome_arquivo'];
            
            return $this->jsonResponse([
                'success' => true,
                'url' => $url_imagem,
                'alt_text' => $imagem['alt_text'] ?? $imagem['descricao'] ?? 'Produto',
                'produto_id' => $produto_id
            ]);
            
        } catch (Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'error' => 'Erro ao obter foto principal: ' . $e->getMessage(),
                'url' => '/uploads/produtos/placeholder.svg',
                'is_placeholder' => true
            ], 500);
        }
    }

    /**
     * Obter URL base do sistema
     */
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
        return $protocol . '://' . $host;
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

            // Resolver arquivo em chaves alternativas
            $chaves = ['imagem', 'file', 'arquivo', 'image', 'foto', 'imagens'];
            $arquivo = null;
            $chaveUsada = null;
            foreach ($chaves as $k) {
                if (isset($_FILES[$k])) {
                    $chaveUsada = $k;
                    // Se veio como array de arquivos (ex.: imagens[]), pegar o primeiro
                    if (is_array($_FILES[$k]['name'])) {
                        // Normalizar para um único arquivo
                        $arquivo = [
                            'name' => $_FILES[$k]['name'][0] ?? null,
                            'type' => $_FILES[$k]['type'][0] ?? null,
                            'tmp_name' => $_FILES[$k]['tmp_name'][0] ?? null,
                            'error' => $_FILES[$k]['error'][0] ?? UPLOAD_ERR_NO_FILE,
                            'size' => $_FILES[$k]['size'][0] ?? 0,
                        ];
                    } else {
                        $arquivo = $_FILES[$k];
                    }
                    break;
                }
            }
            
            // Verificar se arquivo foi enviado
            if ($arquivo === null) {
                $detalhes = [
                    'mensagem' => 'Nenhum arquivo de imagem enviado. Envie multipart/form-data com o campo "imagem".',
                    'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
                    'chaves_recebidas' => array_keys($_FILES ?: []),
                ];
                return $this->jsonResponse(['error' => $detalhes['mensagem'], 'details' => $detalhes], 400);
            }
            if (($arquivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                $map = [
                    UPLOAD_ERR_INI_SIZE => 'Arquivo excede upload_max_filesize do php.ini',
                    UPLOAD_ERR_FORM_SIZE => 'Arquivo excede MAX_FILE_SIZE do formulário',
                    UPLOAD_ERR_PARTIAL => 'Upload incompleto',
                    UPLOAD_ERR_NO_FILE => 'Nenhum arquivo enviado',
                    UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário ausente',
                    UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar no disco',
                    UPLOAD_ERR_EXTENSION => 'Upload interrompido por extensão PHP',
                ];
                $msg = $map[$arquivo['error']] ?? ('Erro de upload código ' . $arquivo['error']);
                return $this->jsonResponse(['error' => $msg], 400);
            }

            // Obter dados adicionais
            $produto_id = $_POST['produto_id'] ?? null;
            $descricao = $_POST['descricao'] ?? '';
            $alt_text = $_POST['alt_text'] ?? '';
            $eh_principal = isset($_POST['eh_principal']) ? (bool)$_POST['eh_principal'] : false;

            if (!$produto_id) {
                return $this->jsonResponse(['error' => 'produto_id é obrigatório'], 400);
            }

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
     * Fallback: listar imagens diretamente do filesystem
     */
    private function listarFs($produto_id) {
        $dir = __DIR__ . '/../uploads/produtos/' . $produto_id . '/';
        if (!is_dir($dir)) {
            return [];
        }
        $arquivos = @scandir($dir) ?: [];
        $out = [];
        $ordem = 1;
        foreach ($arquivos as $f) {
            if ($f === '.' || $f === '..') continue;
            $path = $dir . $f;
            if (!is_file($path)) continue;
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'])) continue;
            $rel = 'uploads/produtos/' . $produto_id . '/' . $f;
            $info = @getimagesize($path);
            // Gerar ID negativo estável a partir do caminho (evita chaves React duplicadas)
            $hash = sprintf('%u', crc32($rel));
            $negId = 0 - (int)$hash;
            $out[] = [
                'id' => $negId,
                'produto_id' => $produto_id,
                'nome_arquivo' => $f,
                'caminho' => $rel,
                'descricao' => '',
                'alt_text' => '',
                'tamanho' => @filesize($path) ?: 0,
                'tipo_mime' => $info['mime'] ?? 'image/*',
                'largura' => $info[0] ?? 0,
                'altura' => $info[1] ?? 0,
                'eh_principal' => $ordem === 1 ? 1 : 0,
                'ordem' => $ordem++,
                'deletado_em' => null
            ];
        }
        return $out;
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
