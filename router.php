<?php
/**
 * Router para PHP Built-in Server
 * Redireciona todas as requisições para api/index.php
 */

// Se for arquivo estático que existe, servir diretamente
if (preg_match('/\.(?:png|jpg|jpeg|gif|css|js|ico|svg|woff|woff2|ttf|eot)$/', $_SERVER["REQUEST_URI"])) {
    return false; // serve o arquivo estático
}

// Redirecionar para api/index.php
$_SERVER['SCRIPT_NAME'] = '/api/index.php';
require __DIR__ . '/api/index.php';
