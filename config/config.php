<?php
/**
 * Configurações Gerais do Sistema
 * Tenis Club System
 */

$__env_file = __DIR__ . '/../.env';
if (file_exists($__env_file)) {
    $lines = file($__env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trim = ltrim($line);
        if ($trim === '' || $trim[0] === '#') { continue; }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $k = trim($parts[0]);
            $v = trim($parts[1]);
            $v = trim($v, "\"'");
            if ($k !== '' && getenv($k) === false) {
                $_ENV[$k] = $v;
                putenv($k . '=' . $v);
            }
        }
    }
}

// Definir timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações do sistema
define('APP_NAME', 'Tenis Club');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost');

// Configurações de erro
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configurações de sessão
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// CORS headers são configurados no router.php e api/index.php
// Não enviar headers aqui para evitar duplicação

// Função para retornar resposta JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Função para sanitizar entrada
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Função para validar CNPJ (simples)
function validarCNPJ($cnpj) {
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    return strlen($cnpj) == 14;
}

// Função para validar email
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Autoload das classes
spl_autoload_register(function ($class_name) {
    $directories = [
        __DIR__ . '/../models/',
        __DIR__ . '/../controllers/',
        __DIR__ . '/../config/'
    ];
    
    foreach ($directories as $directory) {
        $file = $directory . $class_name . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
