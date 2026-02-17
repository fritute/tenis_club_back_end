CREATE TABLE `usuarios`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `senha` VARCHAR(255) NOT NULL,
    `nivel` ENUM('comum', 'fornecedor', 'executivo') NOT NULL DEFAULT 'comum',
    `fornecedor_id` INT NULL COMMENT 'ID do fornecedor associado  ( para multi-usuário)',
    `status` ENUM('ativo', 'inativo', 'suspenso') NOT NULL DEFAULT 'ativo',
    `ultimo_login` TIMESTAMP NULL,
    `token_reset` VARCHAR(255) NULL,
    `token_reset_expires` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(), `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP());
ALTER TABLE
    `usuarios` ADD UNIQUE `usuarios_email_unique`(`email`);
ALTER TABLE
    `usuarios` ADD INDEX `usuarios_nivel_index`(`nivel`);
ALTER TABLE
    `usuarios` ADD INDEX `usuarios_fornecedor_id_index`(`fornecedor_id`);
ALTER TABLE
    `usuarios` ADD INDEX `usuarios_status_index`(`status`);
CREATE TABLE `categorias`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(100) NOT NULL,
    `descricao` TEXT NULL,
    `status` ENUM('Ativo', 'Inativo') NOT NULL DEFAULT 'Ativo',
    `data_criacao` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(), `data_cadastro` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP());
CREATE TABLE `produtos`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(150) NOT NULL,
    `descricao` TEXT NULL,
    `codigo_interno` VARCHAR(50) NULL,
    `categoria_id` INT NULL,
    `preco_base` DECIMAL(10, 2) NULL DEFAULT 0,
    `fornecedor_id` INT NULL COMMENT 'Fornecedor dono do produto  ( marketplace)',
    `status` ENUM('Ativo', 'Inativo') NOT NULL DEFAULT 'Ativo',
    `data_cadastro` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(), `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP());
ALTER TABLE
    `produtos` ADD UNIQUE `produtos_codigo_interno_unique`(`codigo_interno`);
ALTER TABLE
    `produtos` ADD INDEX `produtos_fornecedor_id_index`(`fornecedor_id`);
ALTER TABLE
    `produtos` ADD INDEX `produtos_status_index`(`status`);
CREATE TABLE `produto_imagens`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `produto_id` INT NOT NULL,
    `nome_arquivo` VARCHAR(255) NOT NULL,
    `caminho` VARCHAR(500) NOT NULL,
    `descricao` TEXT NULL,
    `alt_text` VARCHAR(255) NULL,
    `ordem` INT NULL,
    `eh_principal` BOOLEAN NULL,
    `tamanho` INT NULL COMMENT 'Tamanho em bytes',
    `tipo_mime` VARCHAR(50) NULL,
    `largura` INT NULL,
    `altura` INT NULL,
    `criado_em` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(), `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(), `deletado_em` TIMESTAMP NULL COMMENT 'Soft delete');
ALTER TABLE
    `produto_imagens` ADD INDEX `produto_imagens_produto_id_eh_principal_index`(`produto_id`, `eh_principal`);
ALTER TABLE
    `produto_imagens` ADD INDEX `produto_imagens_produto_id_ordem_index`(`produto_id`, `ordem`);
ALTER TABLE
    `produto_imagens` ADD INDEX `produto_imagens_deletado_em_index`(`deletado_em`);
CREATE TABLE `fornecedores`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(150) NOT NULL,
    `cnpj` VARCHAR(20) NULL,
    `email` VARCHAR(150) NULL,
    `telefone` VARCHAR(30) NULL,
    `endereco` VARCHAR(255) NULL,
    `nivel` VARCHAR(20) NULL DEFAULT 'basico',
    `status` ENUM('Ativo', 'Inativo') NOT NULL DEFAULT 'Ativo',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(), `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP());
CREATE TABLE `contatos_fornecedor`(
    `id_contato` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `id_fornecedor` INT NOT NULL,
    `nome` VARCHAR(100) NOT NULL,
    `cargo` VARCHAR(100) NULL,
    `telefone` VARCHAR(30) NULL,
    `email` VARCHAR(150) NULL,
    `whatsapp` VARCHAR(30) NULL,
    `is_principal` BOOLEAN NULL,
    `status` ENUM('Ativo', 'Inativo') NOT NULL DEFAULT 'Ativo',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(), `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP());
CREATE TABLE `precos_fornecedor`(
    `id_preco` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `id_produto` INT NOT NULL,
    `id_fornecedor` INT NOT NULL,
    `preco_unitario` DECIMAL(10, 2) NOT NULL,
    `moeda` VARCHAR(3) NULL DEFAULT 'BRL',
    `quantidade_minima` INT NULL DEFAULT 1,
    `prazo_entrega_dias` INT NULL,
    `observacoes` TEXT NULL,
    `data_vigencia_inicio` DATE NOT NULL,
    `data_vigencia_fim` DATE NULL,
    `status` ENUM('Ativo', 'Inativo', 'Expirado') NOT NULL DEFAULT 'Ativo',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(), `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP());
ALTER TABLE
    `precos_fornecedor` ADD INDEX `index_2a10e00a31e44c8dce41a8a3e39f56d3`(
        `id_produto`,
        `id_fornecedor`,
        `data_vigencia_inicio`,
        `data_vigencia_fim`
    );
CREATE TABLE `produto_fornecedor`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `produto_id` INT NOT NULL,
    `id_produto` INT NOT NULL,
    `fornecedor_id` INT NOT NULL,
    `id_fornecedor` INT NOT NULL,
    `status` ENUM(
        'Ativo',
        'Suspenso',
        'Negociando',
        'Finalizado'
    ) NOT NULL DEFAULT 'Ativo',
    `preco_fornecedor` DECIMAL(10, 2) NULL,
    `is_principal` BOOLEAN NULL,
    `observacoes` TEXT NULL,
    `data_inicio_parceria` DATE NULL,
    `data_ultima_compra` DATE NULL,
    `data_vinculo` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(), `data_cadastro` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(), `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP());
ALTER TABLE
    `produto_fornecedor` ADD INDEX `produto_fornecedor_produto_id_index`(`produto_id`);
ALTER TABLE
    `produto_fornecedor` ADD INDEX `produto_fornecedor_id_produto_index`(`id_produto`);
ALTER TABLE
    `produto_fornecedor` ADD INDEX `produto_fornecedor_fornecedor_id_index`(`fornecedor_id`);
ALTER TABLE
    `produto_fornecedor` ADD INDEX `produto_fornecedor_id_fornecedor_index`(`id_fornecedor`);
CREATE TABLE `pedidos`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT NOT NULL COMMENT 'Cliente que fez o pedido',
    `fornecedor_id` INT NOT NULL COMMENT 'Fornecedor/empresa que receberá o pedido',
    `status` ENUM(
        'pendente',
        'confirmado',
        'enviado',
        'entregue',
        'cancelado'
    ) NOT NULL DEFAULT 'pendente',
    `endereco_entrega` JSON NOT NULL COMMENT 'Endereço completo de entrega em formato JSON',
    `valor_total` DECIMAL(10, 2) NOT NULL DEFAULT 0,
    `observacoes` TEXT NULL COMMENT 'Observações do cliente sobre o pedido',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(), `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP());
ALTER TABLE
    `pedidos` ADD INDEX `pedidos_usuario_id_index`(`usuario_id`);
ALTER TABLE
    `pedidos` ADD INDEX `pedidos_fornecedor_id_index`(`fornecedor_id`);
ALTER TABLE
    `pedidos` ADD INDEX `pedidos_status_index`(`status`);
ALTER TABLE
    `pedidos` ADD INDEX `pedidos_created_at_index`(`created_at`);
CREATE TABLE `pedido_itens`(
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `pedido_id` INT NOT NULL,
    `produto_id` INT NOT NULL,
    `quantidade` INT NOT NULL DEFAULT 1,
    `preco_unitario` DECIMAL(10, 2) NOT NULL COMMENT 'Preço unitário no momento da compra',
    `subtotal` DECIMAL(10, 2) NOT NULL COMMENT 'Quantidade × Preço unitário',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP());
ALTER TABLE
    `pedido_itens` ADD INDEX `pedido_itens_pedido_id_index`(`pedido_id`);
ALTER TABLE
    `pedido_itens` ADD INDEX `pedido_itens_produto_id_index`(`produto_id`);
ALTER TABLE
    `contatos_fornecedor` ADD CONSTRAINT `contatos_fornecedor_id_fornecedor_foreign` FOREIGN KEY(`id_fornecedor`) REFERENCES `fornecedores`(`id`);
ALTER TABLE
    `produto_fornecedor` ADD CONSTRAINT `produto_fornecedor_fornecedor_id_foreign` FOREIGN KEY(`fornecedor_id`) REFERENCES `fornecedores`(`id`);
ALTER TABLE
    `pedidos` ADD CONSTRAINT `pedidos_fornecedor_id_foreign` FOREIGN KEY(`fornecedor_id`) REFERENCES `fornecedores`(`id`);
ALTER TABLE
    `pedidos` ADD CONSTRAINT `pedidos_usuario_id_foreign` FOREIGN KEY(`usuario_id`) REFERENCES `usuarios`(`id`);
ALTER TABLE
    `pedido_itens` ADD CONSTRAINT `pedido_itens_produto_id_foreign` FOREIGN KEY(`produto_id`) REFERENCES `produtos`(`id`);
ALTER TABLE
    `produtos` ADD CONSTRAINT `produtos_categoria_id_foreign` FOREIGN KEY(`categoria_id`) REFERENCES `categorias`(`id`);
ALTER TABLE
    `produto_imagens` ADD CONSTRAINT `produto_imagens_produto_id_foreign` FOREIGN KEY(`produto_id`) REFERENCES `produtos`(`id`);
ALTER TABLE
    `pedido_itens` ADD CONSTRAINT `pedido_itens_pedido_id_foreign` FOREIGN KEY(`pedido_id`) REFERENCES `pedidos`(`id`);
ALTER TABLE
    `precos_fornecedor` ADD CONSTRAINT `precos_fornecedor_id_produto_foreign` FOREIGN KEY(`id_produto`) REFERENCES `produtos`(`id`);
ALTER TABLE
    `produto_imagens` ADD CONSTRAINT `produto_imagens_produto_id_foreign` FOREIGN KEY(`produto_id`) REFERENCES `produtos`(`id`);
ALTER TABLE
    `produtos` ADD CONSTRAINT `produtos_categoria_id_foreign` FOREIGN KEY(`categoria_id`) REFERENCES `categorias`(`id`);
ALTER TABLE
    `precos_fornecedor` ADD CONSTRAINT `precos_fornecedor_id_fornecedor_foreign` FOREIGN KEY(`id_fornecedor`) REFERENCES `fornecedores`(`id`);
ALTER TABLE
    `produto_fornecedor` ADD CONSTRAINT `produto_fornecedor_produto_id_foreign` FOREIGN KEY(`produto_id`) REFERENCES `produtos`(`id`);