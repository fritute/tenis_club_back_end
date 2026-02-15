<?php
/**
 * Controller Base
 * Virtual Market System
 */

abstract class BaseController {
    
    public function __construct() {
        // Construtor vazio - pode ser estendido por controllers filhos
    }
    
    /**
     * Validar dados de entrada
     * @param array $required_fields
     * @param array $data
     * @return array
     */
    protected function validateRequiredFields($required_fields, $data) {
        $errors = [];
        
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $errors[$field] = "Campo {$field} é obrigatório";
            }
        }
        
        return $errors;
    }

    /**
     * Sanitizar dados de entrada
     * @param array $data
     * @return array
     */
    protected function sanitizeData($data) {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = htmlspecialchars(strip_tags(trim($value)));
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Resposta de sucesso
     * @param mixed $data
     * @param string $message
     * @param int $code
     * @return array
     */
    protected function successResponse($data = null, $message = 'Sucesso', $code = 200) {
        $response = [
            'success' => true,
            'message' => $message,
            'code' => $code
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        return $response;
    }

    /**
     * Resposta de erro
     * @param string $message
     * @param int $code
     * @param array $errors
     * @return array
     */
    protected function errorResponse($message = 'Erro interno', $code = 500, $errors = []) {
        $response = [
            'success' => false,
            'message' => $message,
            'code' => $code
        ];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        return $response;
    }

    /**
     * Validar ID
     * @param mixed $id
     * @return bool
     */
    protected function isValidId($id) {
        return is_numeric($id) && $id > 0;
    }

    /**
     * Resposta de validação
     * @param array $errors
     * @return array
     */
    protected function validationErrorResponse($errors) {
        return $this->errorResponse('Dados inválidos', 400, $errors);
    }

    /**
     * Resposta de não encontrado
     * @param string $entity
     * @return array
     */
    protected function notFoundResponse($entity = 'Registro') {
        return $this->errorResponse("{$entity} não encontrado", 404);
    }

    /**
     * Processar dados do POST/PUT
     * @return array
     */
    protected function getInputData() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        // Se não veio JSON, pegar do POST normal
        if (!$data) {
            $data = $_POST;
        }
        
        return $this->sanitizeData($data);
    }

    /**
     * Log de atividade (para auditoria futura)
     * @param string $action
     * @param string $entity
     * @param int $entity_id
     * @param array $details
     */
    protected function logActivity($action, $entity, $entity_id = null, $details = []) {
        $log_data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entity_id,
            'details' => $details,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        // Por enquanto log em arquivo, depois pode ser banco
        error_log("Activity: " . json_encode($log_data));
    }

    /**
     * Obter dados JSON da requisição
     */
    protected function getJsonData() {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?: [];
    }

    /**
     * Resposta JSON
     */
    protected function jsonResponse($data, $httpCode = 200) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        return true;
    }

    /**
     * Obter token de autorização
     */
    protected function getAuthToken() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return $_GET['token'] ?? null; // Fallback para query param
    }

    /**
     * Obter usuário logado
     */
    protected function getUsuarioLogado() {
        $token = $this->getAuthToken();
        
        if (!$token) {
            return null;
        }
        
        try {
            require_once __DIR__ . '/../models/UsuarioModel.php';
            $usuarioModel = new UsuarioModel();
            $payload = $usuarioModel->validarToken($token);
            
            if ($payload) {
                return $payload;
            }
        } catch (Exception $e) {
            error_log("Erro ao validar token: " . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Verificar se usuário tem permissão
     */
    protected function verificarPermissao($niveisPermitidos = []) {
        $usuario = $this->getUsuarioLogado();
        
        if (!$usuario) {
            return false;
        }
        
        if (empty($niveisPermitidos)) {
            return true; // Qualquer usuário logado
        }
        
        return in_array($usuario['nivel'], $niveisPermitidos);
    }

    /**
     * Middleware de autenticação
     */
    protected function requireAuth($niveisPermitidos = []) {
        if (!$this->verificarPermissao($niveisPermitidos)) {
            $this->jsonResponse(['error' => 'Acesso não autorizado'], 401);
            exit();
        }
        
        return $this->getUsuarioLogado();
    }
}