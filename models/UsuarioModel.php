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
        
        // Validar fornecedor_id se nível for fornecedor
        if (!empty($data['nivel']) && $data['nivel'] === self::NIVEL_FORNECEDOR) {
            if (!empty($data['fornecedor_id']) && !is_numeric($data['fornecedor_id'])) {
                $errors['fornecedor_id'] = 'ID do fornecedor deve ser numérico';
            }
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
        // Permitir apenas colunas existentes na tabela
        $allowed = ['nome','email','senha','nivel','fornecedor_id','status'];
        $filtered = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $filtered[$k] = $data[$k];
            }
        }
        // Defaults
        if (empty($filtered['nivel'])) {
            $filtered['nivel'] = self::NIVEL_COMUM;
        }
        if (empty($filtered['status'])) {
            $filtered['status'] = 'ativo';
        }
        // Hash da senha
        if (!empty($filtered['senha'])) {
            $filtered['senha'] = password_hash($filtered['senha'], PASSWORD_DEFAULT);
        }

        // Log dos dados recebidos (sem senha)
        $logData = $filtered;
        if (isset($logData['senha'])) {
            $logData['senha'] = '[HASHED]';
        }
        error_log('[UsuarioModel][create] Dados recebidos: ' . json_encode($logData, JSON_UNESCAPED_UNICODE));

        $result = parent::create($filtered);
        error_log('[UsuarioModel][create] Resultado do insert: ' . print_r($result, true));
        return $result;
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
        if (!$this->conn) {
            $path = __DIR__ . '/../data/usuarios.json';
            $rows = file_exists($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
            foreach ($rows as $r) {
                if (isset($r['email']) && strtolower($r['email']) === strtolower($email)) {
                    return $r;
                }
            }
            return null;
        }
        try {
            $sql = "SELECT * FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("Erro em findByEmail: " . $e->getMessage());
            return null;
        }
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
        
        if ($usuario['status'] !== 'Ativo' && strtolower($usuario['status']) !== 'ativo') {
            return ['success' => false, 'message' => 'Usuário inativo'];
        }
        
        if (!$this->verificarSenha($senha, $usuario['senha'])) {
            return ['success' => false, 'message' => 'Senha incorreta'];
        }
        
        // Remove senha do retorno
        unset($usuario['senha']);
        
        // Se usuário for fornecedor e tiver loja, buscar dados da loja
        $loja = null;
        if (!empty($usuario['fornecedor_id'])) {
            require_once __DIR__ . '/FornecedorModel.php';
            $fornecedorModel = new FornecedorModel();
            $loja = $fornecedorModel->findById($usuario['fornecedor_id']);
        }
        
        return [
            'success' => true,
            'message' => 'Login realizado com sucesso',
            'usuario' => $usuario,
            'loja' => $loja,
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
            'nivel' => $usuario['nivel'] ?? 'comum',
            'fornecedor_id' => $usuario['fornecedor_id'] ?? null,
            'exp' => time() + (24 * 60 * 60) // 24 horas
        ];
        
        return base64_encode(json_encode($payload));
    }
    
    /**
     * Validar token
     */
    public function validarToken($token) {
        // Aceitar dois formatos: JWT (3 partes) ou base64 JSON simples
        if (strpos($token, '.') === false) {
            $payload = json_decode(base64_decode($token), true);
            if (!$payload || empty($payload['exp']) || $payload['exp'] < time()) return false;
            return $payload;
        }
        $secret = 'SUA_CHAVE_SECRETA_AQUI';
        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;
        list($headerB64, $payloadB64, $signatureB64) = $parts;
        $header = json_decode(base64_decode(strtr($headerB64, '-_', '+/')), true);
        $payload = json_decode(base64_decode(strtr($payloadB64, '-_', '+/')), true);
        if (!$header || !$payload) return false;
        if (empty($payload['exp']) || $payload['exp'] < time()) return false;
        if (empty($header['alg']) || $header['alg'] !== 'HS256') return false;
        $base = $headerB64 . '.' . $payloadB64;
        $expected = base64_encode(hash_hmac('sha256', $base, $secret, true));
        $expected = rtrim(strtr($expected, '+/', '-_'), '=');
        return hash_equals($expected, $signatureB64) ? $payload : false;
    }

    /**
     * Buscar usuários por nível
     */
    public function findByNivel($nivel) {
        if (!$this->conn) {
            $path = __DIR__ . '/../data/usuarios.json';
            $rows = file_exists($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
            $out = [];
            foreach ($rows as $r) {
                if (isset($r['nivel']) && strtolower($r['nivel']) === strtolower($nivel)) {
                    $out[] = $r;
                }
            }
            usort($out, function($a, $b) {
                return strcmp($a['nome'] ?? '', $b['nome'] ?? '');
            });
            return $out;
        }
        return $this->findAll("nivel = :nivel", [':nivel' => $nivel], 'nome ASC');
    }
}
