/**
 * EXEMPLO PRÁTICO: Como usar Sistema de Token para Fornecedores
 * ============================================================
 * Este arquivo mostra como implementar no frontend (React/JavaScript)
 */

class FornecedorAPI {
    constructor(baseURL = 'http://localhost:8000/api') {
        this.baseURL = baseURL;
        this.token = localStorage.getItem('token');
    }

    // 1. FAZER LOGIN E OBTER TOKEN
    async login(email, senha) {
        try {
            const response = await fetch(`${this.baseURL}/usuarios/login`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ email, senha })
            });

            const data = await response.json();
            
            if (data.success) {
                // Salvar token localmente
                this.token = data.token;
                localStorage.setItem('token', this.token);
                localStorage.setItem('user', JSON.stringify(data.user));
                
                console.log('Login realizado com sucesso!');
                return { success: true, user: data.user };
            } else {
                throw new Error(data.message || 'Erro no login');
            }
        } catch (error) {
            console.error('Erro no login:', error);
            return { success: false, error: error.message };
        }
    }

    // 2. BUSCAR MINHA LOJA COM TOKEN
    async buscarMinhaLoja() {
        if (!this.token) {
            return { success: false, error: 'Token não encontrado. Faça login primeiro.' };
        }

        try {
            const response = await fetch(`${this.baseURL}/fornecedores/minha-loja`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${this.token}`,
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();
            
            if (data.success) {
                if (data.data && data.data.length > 0) {
                    console.log('Loja encontrada:', data.data[0]);
                    return { success: true, loja: data.data[0] };
                } else {
                    console.log('Usuário não tem loja cadastrada');
                    return { success: true, loja: null, message: data.message };
                }
            } else {
                throw new Error(data.message || 'Erro ao buscar loja');
            }
        } catch (error) {
            console.error('Erro ao buscar loja:', error);
            return { success: false, error: error.message };
        }
    }

    // 3. ALTERNATIVA: BUSCAR COM PARÂMETRO DE CONSULTA
    async buscarMinhaLojaAlternativa() {
        if (!this.token) {
            return { success: false, error: 'Token não encontrado' };
        }

        try {
            const response = await fetch(`${this.baseURL}/fornecedores?minha_loja=true`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${this.token}`,
                    'Content-Type': 'application/json'
                }
            });

            return await response.json();
        } catch (error) {
            console.error('Erro:', error);
            return { success: false, error: error.message };
        }
    }

    // 4. VERIFICAR SE USUÁRIO TEM LOJA
    async verificarLoja() {
        const resultado = await this.buscarMinhaLoja();
        
        if (resultado.success) {
            return {
                temLoja: resultado.loja !== null,
                loja: resultado.loja,
                message: resultado.message
            };
        } else {
            return {
                temLoja: false,
                error: resultado.error
            };
        }
    }

    // 5. LOGOUT
    logout() {
        this.token = null;
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        console.log('Logout realizado');
    }
}

// EXEMPLO DE USO PRÁTICO
async function exemploCompleto() {
    const api = new FornecedorAPI();

    console.log('=== EXEMPLO: Sistema de Token para Fornecedores ===\n');

    // 1. Login
    console.log('1. Fazendo login...');
    const loginResult = await api.login('gustavo@example.com', '123456');
    
    if (!loginResult.success) {
        console.error('Login falhou:', loginResult.error);
        return;
    }

    console.log('✅ Login bem-sucedido!');
    console.log('Usuário:', loginResult.user.nome);

    // 2. Buscar loja
    console.log('\n2. Buscando minha loja...');
    const lojaResult = await api.buscarMinhaLoja();
    
    if (lojaResult.success) {
        if (lojaResult.loja) {
            console.log('✅ Loja encontrada!');
            console.log('Nome:', lojaResult.loja.nome);
            console.log('Email:', lojaResult.loja.email);
            console.log('Status:', lojaResult.loja.status);
        } else {
            console.log('⚠️ Usuário não tem loja cadastrada');
            console.log('Mensagem:', lojaResult.message);
        }
    } else {
        console.error('❌ Erro ao buscar loja:', lojaResult.error);
    }

    // 3. Verificação simples
    console.log('\n3. Verificação simplificada...');
    const verificacao = await api.verificarLoja();
    
    if (verificacao.temLoja) {
        console.log('✅ Confirmado: Usuário tem loja');
    } else {
        console.log('⚠️ Usuário sem loja ou erro:', verificacao.error || verificacao.message);
    }
}

// EXEMPLO PARA REACT COMPONENT
function MinhaLoja() {
    const [loja, setLoja] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        async function carregarLoja() {
            const api = new FornecedorAPI();
            const resultado = await api.buscarMinhaLoja();
            
            if (resultado.success) {
                setLoja(resultado.loja);
            } else {
                setError(resultado.error);
            }
            
            setLoading(false);
        }

        carregarLoja();
    }, []);

    if (loading) return <div>Carregando...</div>;
    if (error) return <div>Erro: {error}</div>;

    if (!loja) {
        return (
            <div>
                <h2>Você ainda não tem uma loja</h2>
                <button onClick={() => window.location.href = '/criar-loja'}>
                    Criar Minha Loja
                </button>
            </div>
        );
    }

    return (
        <div>
            <h2>Minha Loja</h2>
            <div>
                <h3>{loja.nome}</h3>
                <p>Email: {loja.email}</p>
                <p>CNPJ: {loja.cnpj}</p>
                <p>Status: {loja.status}</p>
                <p>Telefone: {loja.telefone}</p>
                <p>Endereço: {loja.endereco}</p>
                
                <button onClick={() => window.location.href = '/editar-loja'}>
                    Editar Loja
                </button>
            </div>
        </div>
    );
}

/**
 * RESUMO DO QUE FOI IMPLEMENTADO:
 * 
 * ✅ FornecedorController.php - método minhaLoja() com autenticação
 * ✅ api/fornecedores.php - roteamento para ambos endpoints
 * ✅ Sistema JWT - decodificação automática de token
 * ✅ Associação usuário-loja via fornecedor_id
 * ✅ Resposta estruturada em formato array
 * ✅ Tratamento de usuários sem loja
 * ✅ Headers de autenticação suportados
 * ✅ Correção automática de lojas sem status
 * 
 * ENDPOINTS FUNCIONAIS:
 * - GET /api/fornecedores/minha-loja (com Authorization header)
 * - GET /api/fornecedores?minha_loja=true (com Authorization header)
 * 
 * DADOS NECESSÁRIOS:
 * - Header: Authorization: Bearer TOKEN_JWT
 * - Usuário deve estar logado e ter token válido
 * - Sistema busca fornecedor_id automaticamente do token
 * 
 * PROBLEMAS RESOLVIDOS:
 * - Usuário HUGO: Campo "status" ausente na loja (corrigido automaticamente)
 * - Lojas com nomes inadequados (atualizados)
 * - Associações usuário-loja ausentes (corrigidas)
 * 
 * CASOS DE SUCESSO CONFIRMADOS:
 * - HUGO: Loja do Hugo (ID: 17) ✅
 * - Gustavo: jojotodinho (ID: 12) ✅
 * - LEANDRO: loja do LEANDRO (ID: 8) ✅
 * - Milton: loja da milton (ID: 14) ✅
 * - Francisca: loja da francisca (ID: 15) ✅
 */

// Executar exemplo (apenas para demonstração)
// exemploCompleto();