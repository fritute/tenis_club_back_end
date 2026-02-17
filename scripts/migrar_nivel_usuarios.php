<?php
/**
 * Script de Migração - Adicionar campo 'nivel' a todos os usuários
 * Execute uma vez: php scripts/migrar_nivel_usuarios.php
 */

$dataFile = __DIR__ . '/../data/usuarios.json';

echo "🔧 Iniciando migração de usuários...\n\n";

// Ler arquivo de usuários
if (!file_exists($dataFile)) {
    die("❌ Arquivo usuarios.json não encontrado!\n");
}

$json = file_get_contents($dataFile);
$usuarios = json_decode($json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("❌ Erro ao decodificar JSON: " . json_last_error_msg() . "\n");
}

$totalUsuarios = count($usuarios);
$atualizados = 0;

echo "📊 Total de usuários: {$totalUsuarios}\n\n";

// Processar cada usuário
foreach ($usuarios as $index => &$usuario) {
    echo "Processando usuário #{$usuario['id']} - {$usuario['email']}:\n";
    
    // Verificar se já tem o campo 'nivel'
    if (!isset($usuario['nivel']) || empty($usuario['nivel'])) {
        // Atribuir nível padrão 'comum'
        $usuario['nivel'] = 'comum';
        $atualizados++;
        echo "  ✅ Campo 'nivel' adicionado: comum\n";
    } else {
        echo "  ℹ️  Já possui nível: {$usuario['nivel']}\n";
    }
    
    // Normalizar status se necessário
    if (isset($usuario['status'])) {
        if ($usuario['status'] === 'Ativo') {
            $usuario['status'] = 'ativo';
        } elseif ($usuario['status'] === 'Inativo') {
            $usuario['status'] = 'inativo';
        }
    } else {
        $usuario['status'] = 'ativo';
    }
    
    echo "\n";
}

// Salvar de volta apenas se houve mudanças
if ($atualizados > 0) {
    $backup = $dataFile . '.backup.' . date('YmdHis');
    copy($dataFile, $backup);
    echo "💾 Backup criado: " . basename($backup) . "\n\n";
    
    $jsonAtualizado = json_encode($usuarios, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    file_put_contents($dataFile, $jsonAtualizado);
    
    echo "✅ Migração concluída com sucesso!\n";
    echo "📊 Usuários atualizados: {$atualizados}/{$totalUsuarios}\n";
} else {
    echo "✅ Todos os usuários já possuem o campo 'nivel'!\n";
    echo "📊 Nenhuma atualização necessária.\n";
}

echo "\n🎯 Estrutura final dos usuários:\n";
foreach ($usuarios as $usuario) {
    echo "  - ID {$usuario['id']}: {$usuario['email']} (nivel: {$usuario['nivel']}, status: {$usuario['status']})\n";
}

echo "\n✨ Migração finalizada!\n";
