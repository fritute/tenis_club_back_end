# Documentação Completa da API - Virtual Market

## Configuração do Banco de Dados (MySQL)

1. **Crie o banco de dados:**
   - No MySQL, crie um banco (ex: `tenis_club`):
     ```sql
     CREATE DATABASE tenis_ club CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     ```
2. **Importe as tabelas e dados iniciais:**
   - Execute:
     ```bash
     mysql -u usuario -p tenis_club < config/database.sql
     ```
3. **Configure o acesso ao banco:**
   - O sistema lê variáveis de ambiente ou edite `config/database.php`:
     - `DB_HOST` (padrão: localhost)
     - `DB_PORT` (padrão: 3306)
     - `DB_NAME` (nome do banco criado)
     - `DB_USER` (usuário do MySQL)
     - `DB_PASS` (senha do MySQL)
     - `DB_CHARSET` (padrão: utf8mb4)
   - Exemplo `.env`:
     ```env
     DB_HOST=localhost
     DB_PORT=3306
     DB_NAME=virtual_market
     DB_USER=root
     DB_PASS=suasenha
     DB_CHARSET=utf8mb4
     ```
   - Recomenda-se usar `localhost` para o host.

---

# Documentação Completa da API - Virtual Market

## Autenticação

- **Login:**
  - `POST /api/usuarios/login`
  - Body: `{ "email": "...", "senha": "..." }`
  - Retorna: `{ success, token, data }`
- **Validação de token:**
  - `POST /api/usuarios/validar-token`
  - Header: `Authorization: Bearer {token}`

---

## Usuários
'existe o nivel executivo que não foi utilizado no projeto caso der algum problema crie uma conta nivel executivo para teste de url se precisar.'

- `GET /api/usuarios` — Listar todos (executivo)
- `GET /api/usuarios/perfil` — Ver perfil próprio
- `GET /api/usuarios/{id}` — Buscar por ID
- `POST /api/usuarios` — Criar usuário
- `PUT /api/usuarios/{id}` — Atualizar
- `DELETE /api/usuarios/{id}` — Deletar

### Exemplo de criação
```json
{
  "nome": "João",
  "email": "joao@email.com",
  "senha": "123456",
  "nivel": "comum"
}
```

---

## Produtos

- `GET /api/produtos` — Listar todos
- `GET /api/produtos/{id}` — Buscar por ID
- `GET /api/produtos/ativos` — Apenas ativos
- `GET /api/produtos/minha-empresa` — Produtos do fornecedor logado
- `GET /api/produtos?fornecedor_id={id}` — Filtrar por fornecedor
- `GET /api/produtos?categoria_id={id}` — Filtrar por categoria
- `GET /api/produtos?nome={termo}` — Buscar por nome
- `POST /api/produtos` — Criar produto
- `PUT /api/produtos/{id}` — Atualizar
- `DELETE /api/produtos/{id}` — Deletar (soft delete)

### Exemplo de criação
```json
{
  "nome": "Tênis Nike Air Max",
  "descricao": "Tênis esportivo confortável",
  "categoria_id": 1,
  "fornecedor_id": 1,
  "preco": 299.90,
  "estoque": 50
}
```

---

## Fornecedores

- `GET /api/fornecedores` — Listar todos
- `GET /api/fornecedores/{id}` — Buscar por ID
- `GET /api/fornecedores/ativos` — Apenas ativos
- `GET /api/fornecedores/minha-loja` — Loja do fornecedor logado
- `POST /api/fornecedores` — Criar fornecedor
- `POST /api/fornecedores/minha-loja` — Criar minha loja
- `PUT /api/fornecedores/{id}` — Atualizar
- `DELETE /api/fornecedores/{id}` — Deletar

---

## Categorias

- `GET /api/categorias` — Listar todas
- `GET /api/categorias/{id}` — Buscar por ID
- `GET /api/categorias/ativas` — Apenas ativas
- `POST /api/categorias` — Criar categoria
- `PUT /api/categorias/{id}` — Atualizar
- `DELETE /api/categorias/{id}` — Deletar

---

## Imagens de Produtos

- `GET /api/produtos/imagens?produto_id={id}` — Listar imagens do produto
- `GET /api/produtos/imagens/{id}` — Buscar imagem por ID
- `POST /api/produtos/imagens` — Upload (multipart/form-data)
- `PUT /api/produtos/imagens/{id}` — Atualizar metadados
- `PUT /api/produtos/imagens/{id}/principal` — Definir como principal
- `PUT /api/produtos/imagens/{id}/ordem` — Alterar ordem
- `DELETE /api/produtos/imagens/{id}` — Deletar (soft delete)

### Exemplo de upload
```http
POST /api/produtos/imagens
Content-Type: multipart/form-data

imagem: arquivo.jpg
produto_id: 1
descricao: "Imagem frontal"
eh_principal: true
```

---

## Pedidos

- `GET /api/pedidos` — Listar todos (executivo)
- `GET /api/pedidos/{id}` — Buscar por ID
- `GET /api/pedidos/meus` — Meus pedidos (usuário)
- `GET /api/pedidos/recebidos` — Pedidos recebidos (fornecedor)
- `GET /api/pedidos/estatisticas` — Estatísticas (fornecedor)
- `POST /api/pedidos` — Criar pedido
- `PUT /api/pedidos/{id}/status` — Atualizar status
- `PUT /api/pedidos/{id}/cancelar` — Cancelar pedido

### Exemplo de criação
```json
{
  "itens": [
    { "produto_id": 1, "quantidade": 2 },
    { "produto_id": 3, "quantidade": 1 }
  ],
  "endereco_entrega": {
    "rua": "Rua Exemplo",
    "numero": "123",
    "bairro": "Centro",
    "cidade": "Cidade",
    "uf": "SP"
  }
}
```

---

## Relatórios

- `GET /api/relatorios` — Tipos disponíveis
- `GET /api/relatorios/dashboard` — KPIs principais
- `GET /api/relatorios/fornecedores` — Relatório de fornecedores
- `GET /api/relatorios/produtos` — Relatório de produtos
- `GET /api/relatorios/categorias` — Estatísticas por categoria
- `GET /api/relatorios/financeiro` — Análise financeira

---

## Respostas da API

- **Sucesso:**
```json
{
  "success": true,
  "data": { ... },
  "message": "Operação realizada com sucesso"
}
```
- **Erro:**
```json
{
  "success": false,
  "error": "Mensagem do erro",
  "code": 400
}
```

---

## Observações

- Todas as rotas retornam JSON.
- Para rotas protegidas, envie o header: `Authorization: Bearer {token}`
- Consulte o README para instruções de instalação e uso.
- Para detalhes de cada campo, consulte os models e o SQL.
