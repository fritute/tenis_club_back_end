<?php
/**
 * Model para Usuários
 * Virtual Market System
 */

require_once __DIR__ . '/BaseModel.php';

class UsuarioModel extends BaseModel {
    
    public function __construct() {
        parent::__construct();
        $this->table_name = 'usuarios';
        $this->primary_key = 'id';
    }

    /**
     * Níveis de usuário
     */
    const NIVEL_COMUM = 'comum';
    const NIVEL_FORNECEDOR = 'fornecedor';
    const NIVEL_EXECUTIVO = 'executivo';

    /**
     * Validar dados do usuário
     */
    public function validate($data) {
        $errors = [];
        
        // Validar nome
        if (empty($data['nome']) || strlen(trim($data['nome'])) < 2) {
            $errors['nome'] = 'Nome é obrigatório e deve ter pelo menos 2 caracteres';
        }
        
        // Validar email
        if (empty($data['email'])) {
            $errors['email'] = 'E-mail é obrigatório';
        } else if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-mail inválido';
        } else {
            // Verificar se email já existe
            $existente = $this->findByEmail($data['email']);
            if ($existente && (!isset($data['id']) || $existente['id'] != $data['id'])) {
                $errors['email'] = 'Este e-mail já está cadastrado';
            }
        }
        
        // Validar senha (apenas para novos usuarios ou quando senha fornecida)
        if (empty($data['id']) || !empty($data['senha'])) {
            if (empty($data['senha']) || strlen($data['senha']) < 6) {
                $errors['senha'] = 'Senha deve ter pelo menos 6 caracteres';
            }
        }
        
        // Validar nível
        $niveisValidos = [self::NIVEL_COMUM, self::NIVEL_FORNECEDOR, self::NIVEL_EXECUTIVO];
        if (!empty($data['nivel']) && !in_array($data['nivel'], $niveisValidos)) {
            $errors['nivel'] = 'Nível deve ser: comum, fornecedor ou executivo';
        }
        
        // Status padrão se não fornecido
        if (empty($data['status'])) {
            $data['status'] = 'ativo';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Criar usuário
     */
    public function create($data) {
        // Hash da senha
        if (!empty($data['senha'])) {
            $data['senha'] = password_hash($data['senha'], PASSWORD_DEFAULT);
        }
        
        $data['data_cadastro'] = date('Y-m-d H:i:s');
        $data['status'] = $data['status'] ?? 'Ativo';
        
        return parent::create($data);
    }
    
    /**
     * Atualizar usuário
     */
    public function update($id, $data) {
        // Hash da senha se fornecida
        if (!empty($data['senha'])) {
            $data['senha'] = password_hash($data['senha'], PASSWORD_DEFAULT);
        } else {
            unset($data['senha']); // Remove campo vazio
        }
        
        return parent::update($id, $data);
    }

    /**
     * Buscar por email
     */
    public function findByEmail($email) {
        $usuarios = $this->findAll();
        foreach ($usuarios as $usuario) {
            if ($usuario['email'] === $email) {
                return $usuario;
            }
        }
        return null;
    }

    /**
     * Verificar senha
     */
    public function verificarSenha($senhaFornecida, $hashArmazenado) {
        return password_verify($senhaFornecida, $hashArmazenado);
    }

    /**
     * Login do usuário
     */
    public function login($email, $senha) {
        $usuario = $this->findByEmail($email);
        
        if (!$usuario) {
            return ['success' => false, 'message' => 'Usuário não encontrado'];
        }
        
        if ($usuario['status'] !== 'Ativo') {
            return ['success' => false, 'message' => 'Usuário inativo'];
        }
        
        if (!$this->verificarSenha($senha, $usuario['senha'])) {
            return ['success' => false, 'message' => 'Senha incorreta'];
        }
        
        // Remove senha do retorno
        unset($usuario['senha']);
        
        return [
            'success' => true,
            'message' => 'Login realizado com sucesso',
            'usuario' => $usuario,
            'token' => $this->gerarToken($usuario)
        ];
    }

    /**
     * Gerar token simples (JWT seria ideal em produção)
     */
    private function gerarToken($usuario) {
        $payload = [
            'id' => $usuario['id'],
            'email' => $usuario['email'],
            'nivel' => $usuario['nivel'],
            'exp' => time() + (24 * 60 * 60) // 24 horas
        ];
        
        return base64_encode(json_encode($payload));
    }
    
    /**
     * Validar token
     */
    public function validarToken($token) {
        try {
            $payload = json_decode(base64_decode($token), true);
            
            if (!$payload || $payload['exp'] < time()) {
                return false;
            }
            
            return $payload;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Buscar usuários por nível
     */
    public function findByNivel($nivel) {
        $usuarios = $this->findAll();
        return array_filter($usuarios, function($user) use ($nivel) {
            return $user['nivel'] === $nivel;
        });
    }
}