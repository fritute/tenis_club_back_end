// ===================================
// EXEMPLO: Validar Token JWT
// ===================================

async function validarToken(token) {
    try {
        const response = await fetch('http://localhost:8000/api/usuarios/validar-token', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ token })
        });
        
        const resultado = await response.json();
        
        if (response.ok && resultado.valid) {
            console.log('✅ Token válido:', resultado.user);
            return resultado.user;
        } else {
            console.log('❌ Token inválido:', resultado.message);
            return null;
        }
    } catch (error) {
        console.error('❌ Erro na validação:', error.message);
        return null;
    }
}

// Exemplo no React/JavaScript (verificação automática)
async function checkAuth() {
    const token = localStorage.getItem('token');
    
    if (!token) {
        console.log('Sem token salvo');
        return null;
    }
    
    const usuario = await validarToken(token);
    
    if (usuario) {
        console.log(`Usuário autenticado: ${usuario.nome} (${usuario.nivel})`);
        return usuario;
    } else {
        // Token inválido, limpar localStorage
        localStorage.removeItem('token');
        console.log('Token inválido removido');
        return null;
    }
}

// ===================================
// EXEMPLO: Criar loja sem token (Fornecedor)
// ===================================

async function cadastrarLojaFornecedor(dadosLoja) {
    const dadosComNivel = {
        nivel: 'fornecedor',  // ← Campo obrigatório
        ...dadosLoja
    };
    
    try {
        const response = await fetch('http://localhost:8000/api/fornecedores/minha-loja', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
                // ← Sem Authorization header!
            },
            body: JSON.stringify(dadosComNivel)
        });
        
        const resultado = await response.json();
        
        if (response.ok) {
            console.log('✅ Loja criada:', resultado.data.fornecedor);
            return resultado.data.fornecedor;
        } else {
            console.error('❌ Erro:', resultado.message);
            throw new Error(resultado.message);
        }
    } catch (error) {
        console.error('❌ Erro na requisição:', error.message);
        throw error;
    }
}

// Exemplo de uso no frontend:
const dadosFormulario = {
    nome: 'Nike Brasil',
    email: 'contato@nike.com.br', 
    cnpj: '12.345.678/0001-90',
    telefone: '(11) 1234-5678',
    endereco: 'Av. Paulista, 1000, São Paulo-SP'
};

cadastrarLojaFornecedor(dadosFormulario)
    .then(loja => {
        // Sucesso - redirecionar ou mostrar mensagem
        alert(`Loja "${loja.nome}" criada com sucesso!`);
    })
    .catch(error => {
        // Erro - mostrar mensagem de erro
        alert(`Erro ao criar loja: ${error.message}`);
    });