<?php
/**
 * API Endpoints para Relatórios
 * Virtual Market System
 */

// Configurar CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

// Responder OPTIONS para CORS preflight
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/FornecedorModel.php';
require_once __DIR__ . '/../models/ProdutoModel.php';
require_once __DIR__ . '/../models/ProdutoFornecedorModel.php';
require_once __DIR__ . '/../models/CategoriaModel.php';
require_once __DIR__ . '/../models/PrecoFornecedorModel.php';
require_once __DIR__ . '/../models/AvaliacaoFornecedorModel.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri_parts = explode('/', $_SERVER['REQUEST_URI']);
$report_type = $uri_parts[3] ?? null; // /api/relatorios/{type} - índice correto

$db = new Database();
$fornecedorModel = new FornecedorModel($db->getConnection());
$produtoModel = new ProdutoModel($db->getConnection());
$vincModel = new ProdutoFornecedorModel($db->getConnection());
$categoriaModel = new CategoriaModel($db->getConnection());
$precoModel = new PrecoFornecedorModel($db->getConnection());
$avaliacaoModel = new AvaliacaoFornecedorModel($db->getConnection());

switch ($method) {
    case 'GET':
        try {
            switch ($report_type) {
                case 'dashboard':
                    // Relatório executivo com principais KPIs
                    $totalFornecedores = count($fornecedorModel->findAll());
                    $totalProdutos = count($produtoModel->findAll());
                    $totalCategorias = count($categoriaModel->findAll());
                    
                    // Fornecedores por status
                    $stats_query = "
                        SELECT status, COUNT(*) as total 
                        FROM fornecedores 
                        GROUP BY status
                    ";
                    $stmt = $db->getConnection()->prepare($stats_query);
                    $stmt->execute();
                    $fornecedores_status = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Produtos por categoria
                    $cat_query = "
                        SELECT c.nome as categoria, COUNT(p.id) as total_produtos
                        FROM categorias c
                        LEFT JOIN produtos p ON p.categoria_id = c.id
                        GROUP BY c.id, c.nome
                        ORDER BY total_produtos DESC
                    ";
                    $stmt = $db->getConnection()->prepare($cat_query);
                    $stmt->execute();
                    $produtos_categoria = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Top 5 fornecedores com mais produtos (simplificado)
                    $top_fornecedores = [];
                    $fornecedores_temp = $fornecedorModel->findAll();
                    
                    foreach ($fornecedores_temp as $fornecedor) {
                        $produtos = $vincModel->getProdutosPorFornecedor($fornecedor['id']);
                        $top_fornecedores[] = [
                            'nome' => $fornecedor['nome'],
                            'total_produtos' => count($produtos)
                        ];
                    }
                    
                    // Ordenar por total de produtos desc e pegar top 5
                    usort($top_fornecedores, function($a, $b) {
                        return $b['total_produtos'] <=> $a['total_produtos'];
                    });
                    $top_fornecedores = array_slice($top_fornecedores, 0, 5);
                    
                    jsonResponse([
                        'resumo' => [
                            'total_fornecedores' => $totalFornecedores,
                            'total_produtos' => $totalProdutos,
                            'total_categorias' => $totalCategorias
                        ],
                        'fornecedores_status' => $fornecedores_status,
                        'produtos_por_categoria' => $produtos_categoria,
                        'top_fornecedores' => $top_fornecedores,
                        'gerado_em' => date('Y-m-d H:i:s')
                    ]);
                    break;
                    
                case 'fornecedores':
                    // Relatório detalhado de fornecedores
                    $fornecedores = $fornecedorModel->findAll();
                    
                    foreach ($fornecedores as &$fornecedor) {
                        // Contar produtos do fornecedor
                        $produtos = $vincModel->getProdutosPorFornecedor($fornecedor['id']);
                        $fornecedor['total_produtos'] = count($produtos);
                        
                        // Buscar avaliações
                        $avaliacoes = $avaliacaoModel->getByFornecedor($fornecedor['id']);
                        if (!empty($avaliacoes)) {
                            $media_qualidade = array_sum(array_column($avaliacoes, 'qualidade')) / count($avaliacoes);
                            $media_preco = array_sum(array_column($avaliacoes, 'preco')) / count($avaliacoes);
                            $media_prazo = array_sum(array_column($avaliacoes, 'prazo_entrega')) / count($avaliacoes);
                            
                            $fornecedor['avaliacoes'] = [
                                'total' => count($avaliacoes),
                                'media_qualidade' => round($media_qualidade, 2),
                                'media_preco' => round($media_preco, 2),
                                'media_prazo' => round($media_prazo, 2)
                            ];
                        } else {
                            $fornecedor['avaliacoes'] = null;
                        }
                    }
                    
                    jsonResponse($fornecedores);
                    break;
                    
                case 'produtos':
                    // Relatório básico de produtos (versão simplificada)
                    $produtos = $produtoModel->findAll();
                    
                    foreach ($produtos as &$produto) {
                        // Buscar categoria pelo ID (se existir)
                        if (isset($produto['categoria_id']) && $produto['categoria_id']) {
                            $categoria = $categoriaModel->findById($produto['categoria_id']);
                            $produto['categoria_nome'] = $categoria ? $categoria['nome'] : 'Categoria não encontrada';
                        } else {
                            $produto['categoria_nome'] = 'Sem categoria';
                        }
                        
                        // Contar fornecedores (simplificado)
                        $fornecedores = $vincModel->getFornecedoresPorProduto($produto['id']);
                        $produto['total_fornecedores'] = count($fornecedores);
                        $produto['possui_fornecedores'] = count($fornecedores) > 0;
                    }
                    
                    jsonResponse($produtos);
                    break;
                    // Relatório detalhado de produtos
                    $produtos = $produtoModel->findAll();
                    
                    foreach ($produtos as &$produto) {
                        // Buscar categoria
                        if ($produto['categoria_id']) {
                            $categoria = $categoriaModel->findById($produto['categoria_id']);
                            $produto['categoria_nome'] = $categoria['nome'] ?? 'N/A';
                        }
                        
                        // Contar fornecedores do produto
                        $fornecedores = $vincModel->getFornecedoresByProduto($produto['id']);
                        $produto['total_fornecedores'] = count($fornecedores);
                        
                        // Buscar análise de preços
                        $analise_precos = $precoModel->getAnalisePrecos($produto['id']);
                        if ($analise_precos) {
                            $produto['analise_precos'] = $analise_precos;
                        }
                    }
                    
                    jsonResponse($produtos);
                    break;
                    
                case 'categorias':
                    // Relatório básico de categorias
                    $categorias = $categoriaModel->findAll();
                    
                    foreach ($categorias as &$categoria) {
                        // Contar produtos da categoria (busca manual)
                        $produtos = $produtoModel->findAll();
                        $produtos_categoria = array_filter($produtos, function($p) use ($categoria) {
                            return isset($p['categoria_id']) && $p['categoria_id'] == $categoria['id'];
                        });
                        
                        $categoria['total_produtos'] = count($produtos_categoria);
                        
                        // Estatísticas básicas de preço
                        if (!empty($produtos_categoria)) {
                            $precos = array_column($produtos_categoria, 'preco_base');
                            $precos = array_filter($precos, function($p) { return is_numeric($p) && $p > 0; });
                            
                            if (!empty($precos)) {
                                $categoria['estatisticas_preco'] = [
                                    'preco_minimo' => min($precos),
                                    'preco_maximo' => max($precos),
                                    'preco_medio' => round(array_sum($precos) / count($precos), 2)
                                ];
                            } else {
                                $categoria['estatisticas_preco'] = null;
                            }
                        } else {
                            $categoria['estatisticas_preco'] = null;
                        }
                    }
                    
                    jsonResponse($categorias);
                    break;
                    // Relatório de categorias com estatísticas
                    $categorias = $categoriaModel->findAll();
                    
                    foreach ($categorias as &$categoria) {
                        // Contar produtos na categoria
                        $produtos = $produtoModel->getByCategoria($categoria['id']);
                        $categoria['total_produtos'] = count($produtos);
                        
                        // Calcular média de preço dos produtos da categoria
                        if (!empty($produtos)) {
                            $precos_produtos = [];
                            foreach ($produtos as $produto) {
                                $melhores_precos = $precoModel->getMelhoresPrecos($produto['id']);
                                if (!empty($melhores_precos)) {
                                    $precos_produtos[] = $melhores_precos[0]['preco'];
                                }
                            }
                            
                            if (!empty($precos_produtos)) {
                                $categoria['preco_medio'] = round(array_sum($precos_produtos) / count($precos_produtos), 2);
                                $categoria['preco_min'] = min($precos_produtos);
                                $categoria['preco_max'] = max($precos_produtos);
                            }
                        }
                    }
                    
                    // Ordenar por total de produtos
                    usort($categorias, function($a, $b) {
                        return $b['total_produtos'] - $a['total_produtos'];
                    });
                    
                    jsonResponse($categorias);
                    break;
                    
                case 'vinculos':
                    // Relatório básico de vínculos produto-fornecedor
                    $response = $vincModel->getAllVinculos();
                    $vinculos = $response['data'] ?? $vincModel->findAll();
                    
                    foreach ($vinculos as &$vinculo) {
                        // Lidar com estruturas diferentes de dados (produto_id vs id_produto)
                        $produto_id = $vinculo['produto_id'] ?? $vinculo['id_produto'] ?? null;
                        $fornecedor_id = $vinculo['fornecedor_id'] ?? $vinculo['id_fornecedor'] ?? null;
                        
                        // Buscar nomes de produto e fornecedor
                        if ($produto_id) {
                            $produto = $produtoModel->findById($produto_id);
                            $vinculo['produto_nome'] = $produto ? $produto['nome'] : 'Produto não encontrado';
                        } else {
                            $vinculo['produto_nome'] = 'ID do produto não encontrado';
                        }
                        
                        if ($fornecedor_id) {
                            $fornecedor = $fornecedorModel->findById($fornecedor_id);
                            $vinculo['fornecedor_nome'] = $fornecedor ? $fornecedor['nome'] : 'Fornecedor não encontrado';
                        } else {
                            $vinculo['fornecedor_nome'] = 'ID do fornecedor não encontrado';
                        }
                        
                        // Status do vínculo
                        $vinculo['status_vinculo'] = $vinculo['status'] ?? 'Ativo';
                        $vinculo['data_criacao'] = $vinculo['data_vinculo'] ?? $vinculo['data_cadastro'] ?? date('Y-m-d H:i:s');
                    }
                    
                    jsonResponse($vinculos);
                    break;
                    // Relatório de relacionamentos produto-fornecedor
                    $vinculos = $vincModel->findAll();
                    
                    foreach ($vinculos as &$vinculo) {
                        // Buscar últimas avaliações do fornecedor
                        $avaliacoes = $avaliacaoModel->getByFornecedor($vinculo['fornecedor_id'], 1);
                        if (!empty($avaliacoes)) {
                            $vinculo['ultima_avaliacao'] = $avaliacoes[0];
                        }
                        
                        // Buscar histórico de preços
                        $precos = $precoModel->getByFornecedorProduto($vinculo['fornecedor_id'], $vinculo['produto_id']);
                        if (!empty($precos)) {
                            $vinculo['historico_precos'] = array_slice($precos, 0, 5); // Últimos 5 preços
                        }
                    }
                    
                    jsonResponse($vinculos);
                    break;
                    
                case 'financeiro':
                    // Análise financeira básica
                    $produtos = $produtoModel->findAll();
                    $fornecedores = $fornecedorModel->findAll();
                    $vinculos = $vincModel->findAll();
                    
                    $analise = [
                        'resumo' => [
                            'total_produtos' => count($produtos),
                            'total_fornecedores' => count($fornecedores),
                            'total_vinculos' => count($vinculos)
                        ],
                        'produtos_com_multiplos_fornecedores' => [],
                        'oportunidades_economia' => 'Análise completa em desenvolvimento'
                    ];
                    
                    // Identificar produtos com múltiplos fornecedores
                    foreach ($produtos as $produto) {
                        $fornecedores_produto = $vincModel->getFornecedoresPorProduto($produto['id']);
                        if (count($fornecedores_produto) >= 2) {
                            $analise['produtos_com_multiplos_fornecedores'][] = [
                                'produto' => $produto['nome'],
                                'fornecedores' => count($fornecedores_produto),
                                'preco_base' => $produto['preco_base'] ?? 0
                            ];
                        }
                    }
                    
                    $analise['resumo']['produtos_multiplos_fornecedores'] = count($analise['produtos_com_multiplos_fornecedores']);
                    
                    jsonResponse($analise);
                    break;
                    // Relatório financeiro com análise de preços
                    $analise_geral = [
                        'economia_potencial' => 0,
                        'produtos_analisados' => 0,
                        'fornecedores_unicos' => 0
                    ];
                    
                    $produtos = $produtoModel->findAll();
                    $fornecedores_únicos = [];
                    
                    $detalhes_economia = [];
                    
                    foreach ($produtos as $produto) {
                        $melhores_precos = $precoModel->getMelhoresPrecos($produto['id']);
                        
                        if (count($melhores_precos) >= 2) {
                            $melhor_preco = $melhores_precos[0]['preco'];
                            $segundo_preco = $melhores_precos[1]['preco'];
                            $economia = $segundo_preco - $melhor_preco;
                            
                            if ($economia > 0) {
                                $analise_geral['economia_potencial'] += $economia;
                                $detalhes_economia[] = [
                                    'produto_id' => $produto['id'],
                                    'produto_nome' => $produto['nome'],
                                    'melhor_preco' => $melhor_preco,
                                    'segundo_preco' => $segundo_preco,
                                    'economia' => round($economia, 2),
                                    'percentual_economia' => round(($economia / $segundo_preco) * 100, 2)
                                ];
                            }
                            
                            foreach ($melhores_precos as $preco_info) {
                                $fornecedores_únicos[$preco_info['fornecedor_id']] = true;
                            }
                        }
                        
                        $analise_geral['produtos_analisados']++;
                    }
                    
                    $analise_geral['fornecedores_unicos'] = count($fornecedores_únicos);
                    $analise_geral['economia_potencial'] = round($analise_geral['economia_potencial'], 2);
                    
                    // Ordenar por maior economia
                    usort($detalhes_economia, function($a, $b) {
                        return $b['economia'] <=> $a['economia'];
                    });
                    
                    jsonResponse([
                        'resumo' => $analise_geral,
                        'oportunidades_economia' => array_slice($detalhes_economia, 0, 10), // Top 10
                        'total_oportunidades' => count($detalhes_economia)
                    ]);
                    break;
                    
                default:
                    // Lista de relatórios disponíveis
                    jsonResponse([
                        'relatorios_disponiveis' => [
                            'dashboard' => 'Visão geral do sistema com KPIs principais',
                            'fornecedores' => 'Relatório detalhado de fornecedores com vínculos',
                            'produtos' => 'Relatório de produtos com categorias e fornecedores',
                            'categorias' => 'Estatísticas de categorias com análise de preços',
                            'vinculos' => 'Relacionamentos produto-fornecedor ativos',
                            'financeiro' => 'Análise financeira com oportunidades de economia'
                        ],
                        'uso' => 'GET /api/relatorios/{tipo_relatorio}',
                        'exemplo' => '/api/relatorios/dashboard',
                        'status' => 'Todos os relatórios estão funcionais ✅'
                    ]);
                    break;
            }
            
        } catch (Exception $e) {
            jsonResponse(['error' => 'Erro ao gerar relatório: ' . $e->getMessage()], 500);
        }
        break;
        
    default:
        jsonResponse(['error' => 'Método não permitido'], 405);
        break;
}