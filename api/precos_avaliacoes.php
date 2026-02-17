<?php
/**
 * API Endpoints para Preços e Avaliações
 * Virtual Market System - Funcionalidades Avançadas
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

require_once __DIR__ . '/../models/PrecoFornecedorModel.php';
require_once __DIR__ . '/../models/AvaliacaoFornecedorModel.php';
require_once __DIR__ . '/../controllers/BaseController.php';

class PrecoAvaliacaoController extends BaseController {
    private $precoModel;
    private $avaliacaoModel;

    public function __construct() {
        $this->precoModel = new PrecoFornecedorModel();
        $this->avaliacaoModel = new AvaliacaoFornecedorModel();
    }

    /**
     * Comparativo de preços para um produto
     */
    public function comparativoPrecos($id_produto) {
        try {
            if (!$this->isValidId($id_produto)) {
                return $this->errorResponse('ID do produto inválido', 400);
            }

            $comparativo = $this->precoModel->getComparativoPrecos($id_produto);
            return $this->successResponse($comparativo, 'Comparativo de preços gerado');
            
        } catch (Exception $e) {
            error_log("Erro em comparativoPrecos: " . $e->getMessage());
            return $this->errorResponse('Erro ao gerar comparativo de preços');
        }
    }

    /**
     * Melhor preço para um produto
     */
    public function melhorPreco($id_produto, $quantidade = 1) {
        try {
            if (!$this->isValidId($id_produto)) {
                return $this->errorResponse('ID do produto inválido', 400);
            }

            $melhor = $this->precoModel->getMelhorPreco($id_produto, $quantidade);
            
            if (!$melhor) {
                return $this->errorResponse('Nenhum preço encontrado para este produto', 404);
            }

            return $this->successResponse($melhor, 'Melhor preço encontrado');
            
        } catch (Exception $e) {
            error_log("Erro em melhorPreco: " . $e->getMessage());
            return $this->errorResponse('Erro ao buscar melhor preço');
        }
    }

    /**
     * Ranking de fornecedores por avaliação
     */
    public function rankingFornecedores($limite = 10, $tipoNota = 'geral') {
        try {
            $ranking = $this->avaliacaoModel->getRankingFornecedores($limite, $tipoNota);
            return $this->successResponse($ranking, 'Ranking de fornecedores gerado');
            
        } catch (Exception $e) {
            error_log("Erro em rankingFornecedores: " . $e->getMessage());
            return $this->errorResponse('Erro ao gerar ranking');
        }
    }

    /**
     * Histórico de preços
     */
    public function historicoPrecos($id_produto, $id_fornecedor = null) {
        try {
            if (!$this->isValidId($id_produto)) {
                return $this->errorResponse('ID do produto inválido', 400);
            }

            $historico = $this->precoModel->getHistoricoPrecos($id_produto, $id_fornecedor);
            return $this->successResponse($historico, 'Histórico de preços');
            
        } catch (Exception $e) {
            error_log("Erro em historicoPrecos: " . $e->getMessage());
            return $this->errorResponse('Erro ao buscar histórico');
        }
    }

    /**
     * Análise de tendência de avaliações
     */
    public function tendenciaAvaliacoes($id_fornecedor, $meses = 6) {
        try {
            if (!$this->isValidId($id_fornecedor)) {
                return $this->errorResponse('ID do fornecedor inválido', 400);
            }

            $tendencia = $this->avaliacaoModel->getTendenciaAvaliacoes($id_fornecedor, $meses);
            return $this->successResponse($tendencia, 'Análise de tendência gerada');
            
        } catch (Exception $e) {
            error_log("Erro em tendenciaAvaliacoes: " . $e->getMessage());
            return $this->errorResponse('Erro ao gerar análise');
        }
    }
}

// Instanciar controller
$controller = new PrecoAvaliacaoController();

// Definir variáveis de roteamento
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$segments = explode('/', trim($path, '/'));

// Remover 'api' se existir
if (isset($segments[0]) && $segments[0] === 'api') {
    array_shift($segments);
}

$resource = $segments[0] ?? '';
$action = $segments[1] ?? '';
$id = $segments[2] ?? '';

// Se action é numérico, é na verdade um ID
if (is_numeric($action)) {
    $id = $action;
    $action = '';
}

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if ($action === 'comparativo' && !empty($id)) {
                // GET /precos-avaliacoes/comparativo/{produto_id}
                $result = $controller->comparativoPrecos($id);
                
            } else if ($action === 'melhor-preco' && !empty($id)) {
                // GET /precos-avaliacoes/melhor-preco/{produto_id}?quantidade=X
                $quantidade = $_GET['quantidade'] ?? 1;
                $result = $controller->melhorPreco($id, $quantidade);
                
            } else if ($action === 'ranking') {
                // GET /precos-avaliacoes/ranking?limite=X&tipo=geral
                $limite = $_GET['limite'] ?? 10;
                $tipo = $_GET['tipo'] ?? 'geral';
                $result = $controller->rankingFornecedores($limite, $tipo);
                
            } else if ($action === 'historico' && !empty($id)) {
                // GET /precos-avaliacoes/historico/{produto_id}?fornecedor=X
                $fornecedor = $_GET['fornecedor'] ?? null;
                $result = $controller->historicoPrecos($id, $fornecedor);
                
            } else if ($action === 'tendencia' && !empty($id)) {
                // GET /precos-avaliacoes/tendencia/{fornecedor_id}?meses=X
                $meses = $_GET['meses'] ?? 6;
                $result = $controller->tendenciaAvaliacoes($id, $meses);
                
            } else {
                $result = [
                    'success' => false,
                    'message' => 'Endpoint não encontrado',
                    'available_actions' => [
                        'comparativo/{produto_id}',
                        'melhor-preco/{produto_id}',
                        'ranking',
                        'historico/{produto_id}',
                        'tendencia/{fornecedor_id}'
                    ],
                    'code' => 404
                ];
            }
            break;
            
        default:
            $result = [
                'success' => false,
                'message' => 'Apenas método GET é suportado para este endpoint',
                'code' => 405
            ];
            break;
    }
    
    // Definir código de resposta HTTP
    http_response_code($result['code'] ?? 200);
    
    // Retornar resposta JSON
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro no endpoint precos-avaliacoes: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'code' => 500
    ], JSON_UNESCAPED_UNICODE);
}