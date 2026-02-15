<?php
/**
 * Controller para Usuários
 * Virtual Market System
 */

require_once __DIR__ . '/../models/UsuarioModel.php';
require_once __DIR__ . '/BaseController.php';

class UsuarioController extends BaseController {
    
    private $usuarioModel;
    
    public function __construct() {
        parent::__construct();
        $this->usuarioModel = new UsuarioModel();
    }

    /**
     * Listar usuários (apenas para executivos)
     */
    public function listar() {
        try {
            // Verificar permissões
            if (!$this->verificarPermissao(['executivo'])) {
                return $this->jsonResponse(['error' => 'Acesso negado'], 403);
            }
            
            $usuarios = $this->usuarioModel->findAll();
            
            // Remove senhas do retorno
            foreach ($usuarios as &$usuario) {
                unset($usuario['senha']);
            }
            
            return $this->jsonResponse($usuarios);
            
        } catch (Exception $e) {
            return $this->jsonResponse(['error' => 'Erro ao listar usuários: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Buscar usuário por ID
     */
    public function buscarPorId($id) {
        try {
            // Usuários podem ver próprios dados, executivos veem todos
            $usuarioLogado = $this->getUsuarioLogado();
            if (!$usuarioLogado || ($usuarioLogado['id'] != $id && $usuarioLogado['nivel'] !== 'executivo')) {
                return $this->jsonResponse(['error' => 'Acesso negado'], 403);
            }
            
            $usuario = $this->usuarioModel->findById($id);
            
            if (!$usuario) {
                return $this->jsonResponse(['error' => 'Usuário não encontrado'], 404);
            }
            
            // Remove senha
            unset($usuario['senha']);
            
            return $this->jsonResponse($usuario);
            
        } catch (Exception $e) {
            return $this->jsonResponse(['error' => 'Erro ao buscar usuário: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Criar usuário
     */
    public function criar() {
        try {
            $data = $this->getJsonData();
            
            // Validar dados
            $validation = $this->usuarioModel->validate($data);
            if (!$validation['valid']) {
                return $this->jsonResponse([
                    'error' => 'Dados inválidos',
                    'details' => $validation['errors']
                ], 400);
            }

            // PROJETO DE TESTE: Permitir criar qualquer nível sem autenticação
            // Para produção, adicionar verificação de permissões aqui
            
            $id = $this->usuarioModel->create($data);
            
            if ($id) {
                return $this->jsonResponse([
                    'success' => true,
                    'message' => 'Usuário criado com sucesso',
                    'id' => $id
                ], 201);
            } else {
                return $this->jsonResponse(['error' => 'Erro ao criar usuário'], 500);
            }
            
        } catch (Exception $e) {
            return $this->jsonResponse(['error' => 'Erro ao criar usuário: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Atualizar usuário
     */
    public function atualizar($id) {
        try {
            // Verificar permissões
            $usuarioLogado = $this->getUsuarioLogado();
            if (!$usuarioLogado || ($usuarioLogado['id'] != $id && $usuarioLogado['nivel'] !== 'executivo')) {
                return $this->jsonResponse(['error' => 'Acesso negado'], 403);
            }
            
            $data = $this->getJsonData();
            $data['id'] = $id; // Para validação
            
            // Validar dados
            $validation = $this->usuarioModel->validate($data);
            if (!$validation['valid']) {
                return $this->jsonResponse([
                    'error' => 'Dados inválidos',
                    'details' => $validation['errors']
                ], 400);
            }
            
            unset($data['id']); // Remove da atualização
            
            $success = $this->usuarioModel->update($id, $data);
            
            if ($success) {
                return $this->jsonResponse([
                    'success' => true,
                    'message' => 'Usuário atualizado com sucesso'
                ]);
            } else {
                return $this->jsonResponse(['error' => 'Erro ao atualizar usuário'], 500);
            }
            
        } catch (Exception $e) {
            return $this->jsonResponse(['error' => 'Erro ao atualizar usuário: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Deletar usuário
     */
    public function deletar($id) {
        try {
            // Apenas executivos podem deletar
            if (!$this->verificarPermissao(['executivo'])) {
                return $this->jsonResponse(['error' => 'Acesso negado'], 403);
            }
            
            $usuario = $this->usuarioModel->findById($id);
            if (!$usuario) {
                return $this->jsonResponse(['error' => 'Usuário não encontrado'], 404);
            }
            
            // Não permitir deletar executivo se for o único
            if ($usuario['nivel'] === 'executivo') {
                $executivos = $this->usuarioModel->findByNivel('executivo');
                if (count($executivos) <= 1) {
                    return $this->jsonResponse([
                        'error' => 'Não é possível deletar o último executivo do sistema'
                    ], 400);
                }
            }
            
            $success = $this->usuarioModel->delete($id);
            
            if ($success) {
                return $this->jsonResponse([
                    'success' => true,
                    'message' => 'Usuário deletado com sucesso'
                ]);
            } else {
                return $this->jsonResponse(['error' => 'Erro ao deletar usuário'], 500);
            }
            
        } catch (Exception $e) {
            return $this->jsonResponse(['error' => 'Erro ao deletar usuário: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Login
     */
    public function login() {
        try {
            $data = $this->getJsonData();
            
            if (empty($data['email']) || empty($data['senha'])) {
                return $this->jsonResponse(['error' => 'Email e senha são obrigatórios'], 400);
            }
            
            $resultado = $this->usuarioModel->login($data['email'], $data['senha']);
            
            if ($resultado['success']) {
                return $this->jsonResponse($resultado);
            } else {
                return $this->jsonResponse(['error' => $resultado['message']], 401);
            }
            
        } catch (Exception $e) {
            return $this->jsonResponse(['error' => 'Erro no login: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Perfil do usuário logado
     */
    public function perfil() {
        try {
            $usuario = $this->getUsuarioLogado();
            
            if (!$usuario) {
                return $this->jsonResponse(['error' => 'Usuário não autenticado'], 401);
            }
            
            // Buscar dados atualizados
            $usuarioAtual = $this->usuarioModel->findById($usuario['id']);
            if ($usuarioAtual) {
                unset($usuarioAtual['senha']);
                return $this->jsonResponse($usuarioAtual);
            }
            
            return $this->jsonResponse(['error' => 'Usuário não encontrado'], 404);
            
        } catch (Exception $e) {
            return $this->jsonResponse(['error' => 'Erro ao buscar perfil: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Listar por nível
     */
    public function listarPorNivel($nivel) {
        try {
            // Verificar permissões
            if (!$this->verificarPermissao(['executivo'])) {
                return $this->jsonResponse(['error' => 'Acesso negado'], 403);
            }
            
            $usuarios = $this->usuarioModel->findByNivel($nivel);
            
            // Remove senhas
            foreach ($usuarios as &$usuario) {
                unset($usuario['senha']);
            }
            
            return $this->jsonResponse(array_values($usuarios));
            
        } catch (Exception $e) {
            return $this->jsonResponse(['error' => 'Erro ao listar usuários: ' . $e->getMessage()], 500);
        }
    }
}