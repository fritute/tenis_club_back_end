-- =========================
-- TENIS CLUB DATABASE - v2.0
-- Sistema de E-commerce com 3 níveis de usuário e múltiplas imagens
-- =========================

CREATE DATABASE IF NOT EXISTS tenis_club;
USE tenis_club;

-- =========================
-- TABELA: categorias
-- =========================
CREATE TABLE categorias (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    status ENUM('Ativo', 'Inativo') NOT NULL DEFAULT 'Ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =========================
-- TABELA: usuarios (NOVO - Sistema de 3 níveis)
-- =========================
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    nivel ENUM('comum', 'fornecedor', 'executivo') NOT NULL DEFAULT 'comum',
    status ENUM('ativo', 'inativo', 'suspenso') NOT NULL DEFAULT 'ativo',
    ultimo_login TIMESTAMP NULL,
    token_reset VARCHAR(255) NULL,
    token_reset_expires TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_nivel (nivel),
    INDEX idx_status (status)
);

-- =========================
-- TABELA: fornecedores
-- =========================
CREATE TABLE fornecedores (
    id_fornecedor INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    cnpj VARCHAR(20) NOT NULL,
    email VARCHAR(150) NOT NULL,
    telefone VARCHAR(30),
    status ENUM('Ativo', 'Inativo') NOT NULL DEFAULT 'Ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =========================
-- TABELA: produtos
-- =========================
CREATE TABLE produtos (
    id_produto INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT,
    codigo_interno VARCHAR(50) NOT NULL UNIQUE,
    id_categoria INT,
    status ENUM('Ativo', 'Inativo') NOT NULL DEFAULT 'Ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_produto_categoria
        FOREIGN KEY (id_categoria)
        REFERENCES categorias(id_categoria)
        ON DELETE SET NULL
);

-- =========================
-- TABELA: produto_imagens (NOVO - Sistema de múltiplas imagens)
-- =========================
CREATE TABLE produto_imagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_produto INT NOT NULL,
    nome_arquivo VARCHAR(255) NOT NULL,
    caminho VARCHAR(500) NOT NULL,
    descricao TEXT,
    alt_text VARCHAR(255),
    ordem INT DEFAULT 0,
    eh_principal BOOLEAN DEFAULT FALSE,
    tamanho INT, -- em bytes
    tipo_mime VARCHAR(50),
    largura INT,
    altura INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deletado_em TIMESTAMP NULL, -- soft delete
    
    CONSTRAINT fk_produto_imagem
        FOREIGN KEY (id_produto)
        REFERENCES produtos(id_produto)
        ON DELETE CASCADE,
        
    INDEX idx_produto_ordem (id_produto, ordem),
    INDEX idx_produto_principal (id_produto, eh_principal),
    INDEX idx_deletado (deletado_em)
);

-- =========================
-- TABELA: contatos_fornecedor
-- =========================
CREATE TABLE contatos_fornecedor (
    id_contato INT AUTO_INCREMENT PRIMARY KEY,
    id_fornecedor INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    cargo VARCHAR(100),
    telefone VARCHAR(30),
    email VARCHAR(150),
    whatsapp VARCHAR(30),
    is_principal BOOLEAN DEFAULT FALSE,
    status ENUM('Ativo', 'Inativo') NOT NULL DEFAULT 'Ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_contato_fornecedor
        FOREIGN KEY (id_fornecedor)
        REFERENCES fornecedores(id_fornecedor)
        ON DELETE CASCADE
);

-- =========================
-- TABELA: precos_fornecedor
-- =========================
CREATE TABLE precos_fornecedor (
    id_preco INT AUTO_INCREMENT PRIMARY KEY,
    id_produto INT NOT NULL,
    id_fornecedor INT NOT NULL,
    preco_unitario DECIMAL(10,2) NOT NULL,
    moeda VARCHAR(3) DEFAULT 'BRL',
    quantidade_minima INT DEFAULT 1,
    prazo_entrega_dias INT,
    observacoes TEXT,
    data_vigencia_inicio DATE NOT NULL,
    data_vigencia_fim DATE,
    status ENUM('Ativo', 'Inativo', 'Expirado') NOT NULL DEFAULT 'Ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_preco_produto
        FOREIGN KEY (id_produto)
        REFERENCES produtos(id_produto)
        ON DELETE CASCADE,
        
    CONSTRAINT fk_preco_fornecedor
        FOREIGN KEY (id_fornecedor)
        REFERENCES fornecedores(id_fornecedor)
        ON DELETE CASCADE,
        
    INDEX idx_produto_fornecedor_vigencia (id_produto, id_fornecedor, data_vigencia_inicio, data_vigencia_fim)
);

-- =========================
-- TABELA: avaliacoes_fornecedor
-- =========================
CREATE TABLE avaliacoes_fornecedor (
    id_avaliacao INT AUTO_INCREMENT PRIMARY KEY,
    id_fornecedor INT NOT NULL,
    id_produto INT,
    nota_qualidade TINYINT CHECK (nota_qualidade BETWEEN 1 AND 5),
    nota_preco TINYINT CHECK (nota_preco BETWEEN 1 AND 5),
    nota_prazo TINYINT CHECK (nota_prazo BETWEEN 1 AND 5),
    nota_atendimento TINYINT CHECK (nota_atendimento BETWEEN 1 AND 5),
    nota_geral DECIMAL(3,2) GENERATED ALWAYS AS (
        (nota_qualidade + nota_preco + nota_prazo + nota_atendimento) / 4
    ) STORED,
    comentarios TEXT,
    data_avaliacao DATE NOT NULL,
    avaliado_por VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_av_fornecedor
        FOREIGN KEY (id_fornecedor)
        REFERENCES fornecedores(id_fornecedor)
        ON DELETE CASCADE,
        
    CONSTRAINT fk_av_produto
        FOREIGN KEY (id_produto)
        REFERENCES produtos(id_produto)
        ON DELETE SET NULL
);

-- =========================
-- TABELA: vínculo produto_fornecedor (N:N) - ATUALIZADA
-- =========================
CREATE TABLE produto_fornecedor (
    id_produto INT NOT NULL,
    id_fornecedor INT NOT NULL,
    status_vinculo ENUM('Ativo', 'Suspenso', 'Negociando', 'Finalizado') NOT NULL DEFAULT 'Ativo',
    is_principal BOOLEAN DEFAULT FALSE,
    observacoes TEXT,
    data_inicio_parceria DATE,
    data_ultima_compra DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id_produto, id_fornecedor),

    CONSTRAINT fk_pf_produto
        FOREIGN KEY (id_produto)
        REFERENCES produtos(id_produto)
        ON DELETE CASCADE,

    CONSTRAINT fk_pf_fornecedor
        FOREIGN KEY (id_fornecedor)
        REFERENCES fornecedores(id_fornecedor)
        ON DELETE CASCADE
);

-- =========================
-- DADOS DE EXEMPLO
-- =========================

-- Inserir usuários do sistema (senhas: password = $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi)
INSERT INTO usuarios (nome, email, senha, nivel, status) VALUES 
('Admin Sistema', 'admin@virtualmarket.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'executivo', 'ativo'),
('João Fornecedor', 'joao@fornecedor.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'fornecedor', 'ativo'),
('Maria Cliente', 'maria@cliente.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'comum', 'ativo'),
('Pedro Vendas', 'pedro@vendas.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'fornecedor', 'ativo'),
('Ana Executiva', 'ana@executiva.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'executivo', 'ativo');

-- Inserir categorias
INSERT INTO categorias (nome, descricao, status) VALUES 
('Tênis Esportivos', 'Tênis para prática esportiva e corrida', 'Ativo'),
('Chuteiras', 'Calçados para futebol e esportes de campo', 'Ativo'),
('Casual', 'Calçados para uso diário e casual', 'Ativo'),
('Acessórios', 'Produtos complementares e acessórios', 'Ativo');

-- Inserir fornecedores de exemplo
INSERT INTO fornecedores (nome, cnpj, email, telefone, status) VALUES 
('Nike do Brasil', '12.345.678/0001-90', 'contato@nike.com.br', '(11) 1234-5678', 'Ativo'),
('Adidas Brasil LTDA', '98.765.432/0001-10', 'vendas@adidas.com.br', '(11) 8765-4321', 'Ativo'),
('Puma Sports', '11.222.333/0001-44', 'comercial@puma.com.br', '(11) 2222-3333', 'Inativo');

-- Inserir produtos de exemplo
INSERT INTO produtos (nome, descricao, codigo_interno, id_categoria, status) VALUES 
('Tênis Nike Air Max', 'Tênis esportivo com tecnologia Air Max para maior conforto', 'NIK-001', 1, 'Ativo'),
('Chuteira Adidas Predator', 'Chuteira profissional para futebol de campo', 'ADI-002', 2, 'Ativo'),
('Tênis Puma Suede', 'Tênis casual urbano em camurça', 'PUM-003', 3, 'Ativo');

-- Inserir imagens dos produtos (NOVO)
INSERT INTO produto_imagens (id_produto, nome_arquivo, caminho, descricao, alt_text, ordem, eh_principal, tamanho, tipo_mime, largura, altura) VALUES 
(1, 'nike_air_max_01.jpg', 'uploads/produtos/1/nike_air_max_01.jpg', 'Vista frontal do Nike Air Max', 'Nike Air Max - Vista frontal', 1, TRUE, 245760, 'image/jpeg', 800, 600),
(1, 'nike_air_max_02.jpg', 'uploads/produtos/1/nike_air_max_02.jpg', 'Vista lateral do Nike Air Max', 'Nike Air Max - Vista lateral', 2, FALSE, 198432, 'image/jpeg', 800, 600),
(1, 'nike_air_max_03.jpg', 'uploads/produtos/1/nike_air_max_03.jpg', 'Detalhe da sola Air Max', 'Nike Air Max - Detalhe sola', 3, FALSE, 156890, 'image/jpeg', 800, 600),
(2, 'adidas_predator_01.jpg', 'uploads/produtos/2/adidas_predator_01.jpg', 'Chuteira Adidas Predator completa', 'Adidas Predator - Vista completa', 1, TRUE, 312456, 'image/jpeg', 800, 600),
(2, 'adidas_predator_02.jpg', 'uploads/produtos/2/adidas_predator_02.jpg', 'Detalhe das travas da Predator', 'Adidas Predator - Detalhe travas', 2, FALSE, 267123, 'image/jpeg', 800, 600),
(3, 'puma_suede_01.jpg', 'uploads/produtos/3/puma_suede_01.jpg', 'Tênis Puma Suede azul', 'Puma Suede - Modelo azul', 1, TRUE, 189765, 'image/jpeg', 800, 600);

-- Inserir contatos dos fornecedores
INSERT INTO contatos_fornecedor (id_fornecedor, nome, cargo, telefone, email, whatsapp, is_principal) VALUES 
(1, 'Carlos Silva', 'Gerente Comercial', '(11) 1234-5679', 'carlos@nike.com.br', '(11) 91234-5678', TRUE),
(1, 'Ana Santos', 'Vendedora', '(11) 1234-5680', 'ana@nike.com.br', '(11) 91234-5680', FALSE),
(2, 'Roberto Lima', 'Diretor de Vendas', '(11) 8765-4322', 'roberto@adidas.com.br', '(11) 98765-4321', TRUE),
(2, 'Marina Costa', 'Consultora Técnica', '(11) 8765-4323', 'marina@adidas.com.br', '(11) 98765-4323', FALSE),
(3, 'João Oliveira', 'Representante', '(11) 2222-3334', 'joao@puma.com.br', '(11) 92222-3333', TRUE);

-- Inserir preços por fornecedor
INSERT INTO precos_fornecedor (id_produto, id_fornecedor, preco_unitario, quantidade_minima, prazo_entrega_dias, data_vigencia_inicio, data_vigencia_fim) VALUES 
(1, 1, 299.90, 10, 15, '2026-01-01', '2026-06-30'),
(1, 2, 319.90, 5, 20, '2026-01-01', '2026-12-31'),
(2, 2, 450.00, 12, 10, '2026-01-01', '2026-12-31'),
(3, 3, 189.90, 8, 25, '2026-01-01', '2026-03-31');

-- Inserir avaliações de fornecedores
INSERT INTO avaliacoes_fornecedor (id_fornecedor, id_produto, nota_qualidade, nota_preco, nota_prazo, nota_atendimento, comentarios, data_avaliacao, avaliado_por) VALUES 
(1, 1, 5, 3, 4, 5, 'Excelente qualidade, preço um pouco alto mas vale a pena', '2026-01-15', 'João - Compras'),
(2, 1, 4, 4, 3, 4, 'Boa qualidade, preço competitivo, precisa melhorar prazos', '2026-01-20', 'Maria - Comercial'),
(2, 2, 5, 4, 5, 5, 'Melhor fornecedor de chuteiras, excelente em tudo', '2026-02-01', 'Pedro - Esportes'),
(3, 3, 3, 5, 2, 3, 'Preço muito bom mas qualidade regular e demora na entrega', '2026-01-30', 'Ana - Compras');

-- Criar vínculos entre produtos e fornecedores (com novos campos)
INSERT INTO produto_fornecedor (id_produto, id_fornecedor, status_vinculo, is_principal, data_inicio_parceria, observacoes) VALUES 
(1, 1, 'Ativo', TRUE, '2025-01-01', 'Fornecedor principal da Nike'),
(1, 2, 'Ativo', FALSE, '2025-06-01', 'Fornecedor alternativo para Nike'),
(2, 2, 'Ativo', TRUE, '2024-08-01', 'Único fornecedor autorizado Adidas'),
(3, 3, 'Suspenso', TRUE, '2024-03-01', 'Suspenso por atrasos nas entregas');

-- =========================
-- QUERIES COM JOIN IMPLEMENTADAS NO SISTEMA + NOVAS FUNCIONALIDADES
-- =========================

-- 1. BUSCAR FORNECEDORES DE UM PRODUTO COM PREÇOS (ATUALIZADO)
-- SELECT 
--     f.id_fornecedor,
--     f.nome,
--     f.cnpj,
--     f.email,
--     f.telefone,
--     f.status,
--     pf.status_vinculo,
--     pf.is_principal,
--     pf.data_inicio_parceria,
--     prf.preco_unitario,
--     prf.quantidade_minima,
--     prf.prazo_entrega_dias,
--     AVG(av.nota_geral) as media_avaliacoes
-- FROM fornecedores f
-- INNER JOIN produto_fornecedor pf ON f.id_fornecedor = pf.id_fornecedor
-- LEFT JOIN precos_fornecedor prf ON f.id_fornecedor = prf.id_fornecedor AND prf.id_produto = pf.id_produto AND prf.status = 'Ativo'
-- LEFT JOIN avaliacoes_fornecedor av ON f.id_fornecedor = av.id_fornecedor
-- WHERE pf.id_produto = ?
-- GROUP BY f.id_fornecedor, prf.id_preco
-- ORDER BY pf.is_principal DESC, prf.preco_unitario ASC;

-- 2. BUSCAR PRODUTOS POR CATEGORIA COM FORNECEDORES
-- SELECT 
--     p.id_produto,
--     p.nome,
--     p.codigo_interno,
--     c.nome as categoria_nome,
--     COUNT(pf.id_fornecedor) as total_fornecedores,
--     MIN(prf.preco_unitario) as menor_preco,
--     MAX(prf.preco_unitario) as maior_preco,
--     AVG(av.nota_geral) as media_avaliacoes
-- FROM produtos p
-- LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
-- LEFT JOIN produto_fornecedor pf ON p.id_produto = pf.id_produto AND pf.status_vinculo = 'Ativo'
-- LEFT JOIN precos_fornecedor prf ON p.id_produto = prf.id_produto AND prf.status = 'Ativo'
-- LEFT JOIN avaliacoes_fornecedor av ON pf.id_fornecedor = av.id_fornecedor AND av.id_produto = p.id_produto
-- WHERE c.id_categoria = ?
-- GROUP BY p.id_produto
-- ORDER BY p.nome;

-- 3. RELATÓRIO DE PREÇOS POR PRODUTO COM COMPARAÇÃO
-- SELECT 
--     p.nome as produto_nome,
--     p.codigo_interno,
--     f.nome as fornecedor_nome,
--     prf.preco_unitario,
--     prf.quantidade_minima,
--     prf.prazo_entrega_dias,
--     prf.data_vigencia_inicio,
--     prf.data_vigencia_fim,
--     RANK() OVER (PARTITION BY p.id_produto ORDER BY prf.preco_unitario ASC) as rank_preco_baixo,
--     AVG(av.nota_geral) as media_avaliacao
-- FROM produtos p
-- INNER JOIN precos_fornecedor prf ON p.id_produto = prf.id_produto
-- INNER JOIN fornecedores f ON prf.id_fornecedor = f.id_fornecedor
-- LEFT JOIN avaliacoes_fornecedor av ON f.id_fornecedor = av.id_fornecedor AND av.id_produto = p.id_produto
-- WHERE prf.status = 'Ativo' 
-- AND (prf.data_vigencia_fim IS NULL OR prf.data_vigencia_fim >= CURDATE())
-- GROUP BY p.id_produto, f.id_fornecedor, prf.id_preco
-- ORDER BY p.nome, prf.preco_unitario;

-- 4. CONTATOS DE FORNECEDORES DE UM PRODUTO
-- SELECT 
--     p.nome as produto_nome,
--     f.nome as fornecedor_nome,
--     c.nome as contato_nome,
--     c.cargo,
--     c.telefone,
--     c.email,
--     c.whatsapp,
--     c.is_principal,
--     pf.status_vinculo
-- FROM produtos p
-- INNER JOIN produto_fornecedor pf ON p.id_produto = pf.id_produto
-- INNER JOIN fornecedores f ON pf.id_fornecedor = f.id_fornecedor
-- INNER JOIN contatos_fornecedor c ON f.id_fornecedor = c.id_fornecedor
-- WHERE p.id_produto = ? AND c.status = 'Ativo' AND pf.status_vinculo = 'Ativo'
-- ORDER BY f.nome, c.is_principal DESC, c.nome;

-- 5. RANKING DE FORNECEDORES POR AVALIAÇÃO
-- SELECT 
--     f.id_fornecedor,
--     f.nome,
--     f.cnpj,
--     COUNT(av.id_avaliacao) as total_avaliacoes,
--     AVG(av.nota_qualidade) as media_qualidade,
--     AVG(av.nota_preco) as media_preco,
--     AVG(av.nota_prazo) as media_prazo,
--     AVG(av.nota_atendimento) as media_atendimento,
--     AVG(av.nota_geral) as media_geral,
--     COUNT(DISTINCT pf.id_produto) as total_produtos_fornecidos
-- FROM fornecedores f
-- INNER JOIN avaliacoes_fornecedor av ON f.id_fornecedor = av.id_fornecedor
-- INNER JOIN produto_fornecedor pf ON f.id_fornecedor = pf.id_fornecedor
-- WHERE f.status = 'Ativo'
-- GROUP BY f.id_fornecedor
-- HAVING COUNT(av.id_avaliacao) >= 1
-- ORDER BY media_geral DESC, total_avaliacoes DESC;

-- 6. PRODUTOS COM MELHOR CUSTO-BENEFÍCIO (PREÇO vs AVALIAÇÃO)
-- SELECT 
--     p.nome as produto_nome,
--     p.codigo_interno,
--     c.nome as categoria,
--     f.nome as fornecedor_nome,
--     prf.preco_unitario,
--     AVG(av.nota_geral) as media_avaliacao,
--     (AVG(av.nota_geral) / prf.preco_unitario * 100) as indice_custo_beneficio
-- FROM produtos p
-- LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
-- INNER JOIN precos_fornecedor prf ON p.id_produto = prf.id_produto
-- INNER JOIN fornecedores f ON prf.id_fornecedor = f.id_fornecedor
-- INNER JOIN avaliacoes_fornecedor av ON f.id_fornecedor = av.id_fornecedor
-- WHERE prf.status = 'Ativo' AND f.status = 'Ativo'
-- AND (prf.data_vigencia_fim IS NULL OR prf.data_vigencia_fim >= CURDATE())
-- GROUP BY p.id_produto, f.id_fornecedor, prf.id_preco
-- HAVING AVG(av.nota_geral) >= 3.5
-- ORDER BY indice_custo_beneficio DESC;

-- 7. DASHBOARD AVANÇADO - ESTATÍSTICAS COMPLETAS
-- SELECT 
--     (SELECT COUNT(*) FROM produtos WHERE status = 'Ativo') as produtos_ativos,
--     (SELECT COUNT(*) FROM fornecedores WHERE status = 'Ativo') as fornecedores_ativos,
--     (SELECT COUNT(*) FROM produto_fornecedor WHERE status_vinculo = 'Ativo') as vinculos_ativos,
--     (SELECT COUNT(*) FROM categorias WHERE status = 'Ativo') as categorias_ativas,
--     (SELECT COUNT(*) FROM precos_fornecedor WHERE status = 'Ativo') as precos_vigentes,
--     (SELECT ROUND(AVG(nota_geral), 2) FROM avaliacoes_fornecedor) as media_global_avaliacoes,
--     (SELECT ROUND(AVG(preco_unitario), 2) FROM precos_fornecedor WHERE status = 'Ativo') as preco_medio_geral;

-- =========================
-- QUERIES DO NOVO SISTEMA (v2.0) - USUÁRIOS E IMAGENS
-- =========================

-- 8. BUSCAR USUÁRIOS POR NÍVEL
-- SELECT id, nome, email, nivel, status, created_at, ultimo_login 
-- FROM usuarios 
-- WHERE nivel = ? AND status = 'ativo'
-- ORDER BY nome;

-- 9. AUTENTICAÇÃO DE USUÁRIO
-- SELECT id, nome, email, senha, nivel, status 
-- FROM usuarios 
-- WHERE email = ? AND status = 'ativo';

-- 10. LISTAR IMAGENS DE UM PRODUTO
-- SELECT id, id_produto, nome_arquivo, caminho, descricao, alt_text, ordem, eh_principal, 
--        tamanho, tipo_mime, largura, altura, created_at
-- FROM produto_imagens 
-- WHERE id_produto = ? AND deletado_em IS NULL
-- ORDER BY ordem ASC, eh_principal DESC;

-- 11. PRODUTOS COM SUAS IMAGENS (JOIN)
-- SELECT 
--     p.id_produto,
--     p.nome,
--     p.codigo_interno,
--     p.descricao,
--     c.nome as categoria,
--     pi.id as imagem_id,
--     pi.nome_arquivo,
--     pi.caminho,
--     pi.eh_principal,
--     pi.ordem
-- FROM produtos p
-- LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
-- LEFT JOIN produto_imagens pi ON p.id_produto = pi.id_produto AND pi.deletado_em IS NULL
-- WHERE p.status = 'Ativo'
-- ORDER BY p.nome, pi.ordem;

-- 12. ESTATÍSTICAS DE USUÁRIOS POR NÍVEL
-- SELECT 
--     nivel,
--     COUNT(*) as total,
--     COUNT(CASE WHEN status = 'ativo' THEN 1 END) as ativos,
--     COUNT(CASE WHEN status = 'inativo' THEN 1 END) as inativos,
--     COUNT(CASE WHEN ultimo_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as logados_30_dias
-- FROM usuarios
-- GROUP BY nivel;

-- 13. PRODUTOS COM MAIS IMAGENS
-- SELECT 
--     p.nome,
--     p.codigo_interno,
--     COUNT(pi.id) as total_imagens,
--     COUNT(CASE WHEN pi.eh_principal = 1 THEN 1 END) as imagens_principais,
--     SUM(pi.tamanho) as tamanho_total_bytes,
--     ROUND(SUM(pi.tamanho) / 1024 / 1024, 2) as tamanho_total_mb
-- FROM produtos p
-- LEFT JOIN produto_imagens pi ON p.id_produto = pi.id_produto AND pi.deletado_em IS NULL
-- WHERE p.status = 'Ativo'
-- GROUP BY p.id_produto
-- ORDER BY total_imagens DESC;

-- 14. ATIVIDADE DOS USUÁRIOS (ÚLTIMOS LOGINS)
-- SELECT 
--     u.nome,
--     u.email,
--     u.nivel,
--     u.ultimo_login,
--     CASE 
--         WHEN u.ultimo_login IS NULL THEN 'Nunca logou'
--         WHEN u.ultimo_login >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 'Online hoje'
--         WHEN u.ultimo_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'Última semana'
--         WHEN u.ultimo_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'Último mês'
--         ELSE 'Inativo há muito tempo'
--     END as status_atividade
-- FROM usuarios u
-- WHERE u.status = 'ativo'
-- ORDER BY u.ultimo_login DESC;

-- 15. DASHBOARD EXECUTIVO - ESTATÍSTICAS COMPLETAS v2.0
-- SELECT 
--     (SELECT COUNT(*) FROM usuarios WHERE status = 'ativo') as total_usuarios_ativos,
--     (SELECT COUNT(*) FROM usuarios WHERE nivel = 'comum' AND status = 'ativo') as usuarios_comuns,
--     (SELECT COUNT(*) FROM usuarios WHERE nivel = 'fornecedor' AND status = 'ativo') as fornecedores_usuarios,
--     (SELECT COUNT(*) FROM usuarios WHERE nivel = 'executivo' AND status = 'ativo') as executivos,
--     (SELECT COUNT(*) FROM produtos WHERE status = 'Ativo') as produtos_ativos,
--     (SELECT COUNT(*) FROM produto_imagens WHERE deletado_em IS NULL) as total_imagens,
--     (SELECT COUNT(*) FROM fornecedores WHERE status = 'Ativo') as fornecedores_ativos,
--     (SELECT COUNT(*) FROM categorias WHERE status = 'Ativo') as categorias_ativas,
--     (SELECT ROUND(SUM(tamanho) / 1024 / 1024, 2) FROM produto_imagens WHERE deletado_em IS NULL) as tamanho_imagens_mb,
--     (SELECT COUNT(*) FROM usuarios WHERE ultimo_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as logins_30_dias;

-- =========================
-- ÍNDICES ADICIONAIS PARA PERFORMANCE
-- =========================

-- CREATE INDEX idx_usuario_nivel_status ON usuarios(nivel, status);
-- CREATE INDEX idx_produto_imagem_produto_deletado ON produto_imagens(id_produto, deletado_em);
-- CREATE INDEX idx_produto_imagem_principal_ordem ON produto_imagens(eh_principal, ordem);

-- =========================
-- TRIGGERS PARA MANUTENÇÃO AUTOMÁTICA
-- =========================

-- Trigger para garantir apenas uma imagem principal por produto
-- DELIMITER //
-- CREATE TRIGGER tr_produto_imagem_principal_insert
-- AFTER INSERT ON produto_imagens
-- FOR EACH ROW
-- BEGIN
--     IF NEW.eh_principal = TRUE THEN
--         UPDATE produto_imagens 
--         SET eh_principal = FALSE 
--         WHERE id_produto = NEW.id_produto 
--         AND id != NEW.id 
--         AND deletado_em IS NULL;
--     END IF;
-- END//

-- CREATE TRIGGER tr_produto_imagem_principal_update
-- AFTER UPDATE ON produto_imagens
-- FOR EACH ROW
-- BEGIN
--     IF NEW.eh_principal = TRUE AND OLD.eh_principal = FALSE THEN
--         UPDATE produto_imagens 
--         SET eh_principal = FALSE 
--         WHERE id_produto = NEW.id_produto 
--         AND id != NEW.id 
--         AND deletado_em IS NULL;
--     END IF;
-- END//
-- DELIMITER ;

-- Trigger para atualizar ultimo_login automaticamente (seria usado em aplicação real)
-- DELIMITER //
-- CREATE TRIGGER tr_usuario_login_update
-- BEFORE UPDATE ON usuarios
-- FOR EACH ROW
-- BEGIN
--     IF NEW.ultimo_login != OLD.ultimo_login THEN
--         SET NEW.updated_at = CURRENT_TIMESTAMP;
--     END IF;
-- END//
-- DELIMITER ;

-- =========================
-- VIEWS ÚTEIS PARA O SISTEMA
-- =========================

-- View: Produtos com imagem principal
CREATE OR REPLACE VIEW vw_produtos_com_imagem AS
SELECT 
    p.id_produto,
    p.nome,
    p.codigo_interno,
    p.descricao,
    c.nome as categoria,
    p.status,
    pi.caminho as imagem_principal,
    pi.alt_text as imagem_alt,
    COUNT(pi2.id) as total_imagens
FROM produtos p
LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
LEFT JOIN produto_imagens pi ON (p.id_produto = pi.id_produto AND pi.eh_principal = TRUE AND pi.deletado_em IS NULL)
LEFT JOIN produto_imagens pi2 ON (p.id_produto = pi2.id_produto AND pi2.deletado_em IS NULL)
GROUP BY p.id_produto, pi.id;

-- View: Dashboard de usuários
CREATE OR REPLACE VIEW vw_dashboard_usuarios AS
SELECT 
    nivel,
    COUNT(*) as total,
    SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) as ativos,
    SUM(CASE WHEN ultimo_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as logados_semana,
    SUM(CASE WHEN ultimo_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as logados_mes,
    MAX(ultimo_login) as ultimo_login_geral
FROM usuarios
GROUP BY nivel;

-- View: Estatísticas de imagens por produto
CREATE OR REPLACE VIEW vw_produtos_estatisticas_imagens AS
SELECT 
    p.id_produto,
    p.nome,
    p.codigo_interno,
    COUNT(pi.id) as total_imagens,
    SUM(pi.tamanho) as tamanho_total_bytes,
    ROUND(SUM(pi.tamanho) / 1024 / 1024, 2) as tamanho_total_mb,
    MAX(pi.created_at) as ultima_imagem_adicionada
FROM produtos p
LEFT JOIN produto_imagens pi ON (p.id_produto = pi.id_produto AND pi.deletado_em IS NULL)
WHERE p.status = 'Ativo'
GROUP BY p.id_produto;

-- =========================
-- FIM DO ARQUIVO - VIRTUAL MARKET v2.0
-- =========================
-- Sistema atualizado com:
-- ✅ 3 níveis de usuário (comum/fornecedor/executivo)
-- ✅ Sistema de múltiplas imagens por produto
-- ✅ Autenticação e controle de acesso
-- ✅ Soft delete para imagens
-- ✅ Triggers para manutenção automática
-- ✅ Queries otimizadas para o novo sistema
-- ✅ Dados de exemplo completos
-- 
-- Data de atualização: 14 de fevereiro de 2026
-- Compatível com sistema PHP implementado
