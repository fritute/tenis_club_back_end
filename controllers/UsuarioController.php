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
            $allowed = ['nome','email','senha','nivel','fornecedor_id','status'];
            $extra = [];
            foreach ($data as $k => $v) {
                if (!in_array($k, $allowed, true)) {
                    $extra[] = $k;
                    unset($data[$k]);
                }
            }
            
            // Validar dados
            $validation = $this->usuarioModel->validate($data);
            
            if (!$validation['valid']) {
                // Identificar erro mais específico para email duplicado
                $mainError = 'Dados inválidos';
                if (isset($validation['errors']['email']) && 
                    strpos($validation['errors']['email'], 'já está cadastrado') !== false) {
                    $mainError = $validation['errors']['email'];
                }
                
                return $this->jsonResponse([
                    'error' => $mainError,
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
                $msg = 'Erro ao criar usuário';
                if (!empty($extra)) {
                    $msg .= ' (campos ignorados: ' . implode(', ', $extra) . ')';
                }
                return $this->jsonResponse(['error' => $msg], 500);
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
            $nivelUsuario = $usuario['nivel'] ?? 'comum';
            if ($nivelUsuario === 'executivo') {
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
                // Estrutura compatível com frontend esperando data.userData
                return $this->jsonResponse([
                    'success' => true,
                    'message' => $resultado['message'],
                    'data' => [
                        'userData' => $resultado['usuario'],
                        'loja' => $resultado['loja'],
                        'token' => $resultado['token']
                    ],
                    // Manter campos originais para backward compatibility
                    'usuario' => $resultado['usuario'],
                    'loja' => $resultado['loja'],  
                    'token' => $resultado['token']
                ]);
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
    
    /**
     * Validar token JWT
     * POST /api/usuarios/validar-token
     */
    public function validarToken() {
        try {
            // Obter dados do request
            $data = $this->getJsonData();
            
            if (empty($data['token'])) {
                return $this->jsonResponse([
                    'success' => false,
                    'valid' => false,
                    'message' => 'Token não fornecido'
                ], 400);
            }
            
            $token = $data['token'];
            
            // Validar o token usando o modelo
            $payload = $this->usuarioModel->validarToken($token);
            
            if (!$payload) {
                return $this->jsonResponse([
                    'success' => false,
                    'valid' => false,
                    'message' => 'Token inválido ou expirado'
                ], 401);
            }
            
            // Buscar dados completos do usuário
            $usuario = $this->usuarioModel->findById($payload['id']);
            
            if (!$usuario) {
                return $this->jsonResponse([
                    'success' => false,
                    'valid' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
            }
            
            // Remove senha do retorno
            unset($usuario['senha']);
            
            return $this->jsonResponse([
                'success' => true,
                'valid' => true,
                'user' => $usuario,
                'message' => 'Token válido'
            ]);
            
        } catch (PDOException $e) {
            error_log("Erro PDO em validarToken: " . $e->getMessage());
            return $this->jsonResponse([
                'success' => false,
                'valid' => false,
                'message' => 'Erro ao conectar ao banco de dados',
                'error' => $e->getMessage()
            ], 500);
        } catch (Exception $e) {
            error_log("Erro em validarToken: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return $this->jsonResponse([
                'success' => false,
                'valid' => false,
                'message' => 'Erro interno ao validar token',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
