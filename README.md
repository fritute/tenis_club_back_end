# Virtual Market - Backend

Sistema de gestão de produtos, fornecedores e pedidos feito em **PHP** com **MySQL**. API RESTful pronta para integração com qualquer frontend.

## Como usar

1. **Pré-requisitos:**
   - PHP 8.0+
   - MySQL 5.7+
   - Extensões: `pdo_mysql`, `fileinfo`, `gd`

2. **Instalação:**
   - Clone este repositório
   - Crie um banco de dados MySQL (ex: `virtual_market`)
   - Importe o arquivo `config/database.sql` para criar as tabelas e dados iniciais:
     ```bash
     mysql -u usuario -p virtual_market < config/database.sql
     ```
   - Configure o acesso ao banco em `config/database.php` (ou use variáveis de ambiente)

3. **Rodando o servidor:**
   ```bash
   php -S localhost:8000 router.php
   ```
   Acesse: [http://localhost:8000/api](http://localhost:8000/api)

4. **Testando:**
   - Teste rápido: [http://localhost:8000/api/produtos](http://localhost:8000/api/produtos)
   - Usuários de exemplo: `fornecedor@teste.com` / `forn123` e `usuario@teste.com` / `user123`

## Endpoints principais

- `/api/usuarios` - CRUD de usuários e login
- `/api/produtos` - CRUD de produtos
- `/api/fornecedores` - CRUD de fornecedores
- `/api/categorias` - CRUD de categorias
- `/api/pedidos` - CRUD de pedidos
- `/api/produtos/imagens` - Upload/listagem de imagens

Todos os endpoints retornam JSON. Use o header `Authorization: Bearer {token}` para rotas protegidas.

## Estrutura

- `config/` - Configurações e SQL
- `models/` - Lógica de acesso ao banco
- `controllers/` - Regras de negócio e respostas
- `api/` - Endpoints REST
- `uploads/` - Imagens de produtos

## Dúvidas?
Consulte a documentação completa em `API_DOCUMENTACAO.md` ou abra uma issue.
