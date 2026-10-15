<?php
/**
 * Calcula o resumo do checkout NO SERVIDOR (itens, cupom, desconto PIX, frete, total).
 * A tela só exibe o que vem daqui; a cobrança real usa a mesma função (loja_calcular_totais).
 *
 * POST JSON: { carrinho: [{id, qtd}], cupom: "CODIGO"|"", metodo: "pix"|"cartao"|"boleto" }
 */
ini_set('display_errors', '0');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'loja_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function saida(array $d, int $http = 200): void {
    http_response_code($http);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') saida(['ok' => false, 'erro' => 'Método não permitido.'], 405);
if (!isset($_SESSION['usuario_id']))       saida(['ok' => false, 'erro' => 'Sessão expirada. Faça login novamente.'], 401);

$d = json_decode(file_get_contents('php://input'), true);
if (!is_array($d)) saida(['ok' => false, 'erro' => 'Requisição inválida.'], 400);

$metodo = in_array($d['metodo'] ?? 'pix', ['pix', 'cartao', 'boleto'], true) ? $d['metodo'] : 'pix';
$codigo = strtoupper(trim((string)($d['cupom'] ?? '')));

$c = loja_carregar_itens($pdo, $d['carrinho'] ?? []);
if (!$c['ok']) saida(['ok' => false, 'erro' => $c['erro']], 409);

// Cupom (com freio simples contra tentativa de adivinhar códigos)
$cupom_val = null; $cupom_erro = '';
if ($codigo !== '') {
    if (($_SESSION['cupom_bloqueio_ate'] ?? 0) > time()) {
        $cupom_erro = 'Muitas tentativas. Aguarde alguns minutos e tente novamente.';
    } else {
        $cupom_val = cupom_validar($pdo, $codigo, $c['subtotal']);
        if (!$cupom_val['ok']) {
            $cupom_erro = $cupom_val['erro'];
            $_SESSION['cupom_falhas'] = ($_SESSION['cupom_falhas'] ?? 0) + 1;
            if ($_SESSION['cupom_falhas'] >= 8) {
                $_SESSION['cupom_bloqueio_ate'] = time() + 600;
                $_SESSION['cupom_falhas'] = 0;
            }
            $cupom_val = null;
        } else {
            $_SESSION['cupom_falhas'] = 0;
        }
    }
}

$t = loja_calcular_totais($pdo, $c['subtotal'], $metodo, $cupom_val);
if ($t['erro'] !== '') {   // cupom que zeraria o pedido: recalcula sem ele
    $cupom_erro = $t['erro'];
    $cupom_val  = null;
    $t = loja_calcular_totais($pdo, $c['subtotal'], $metodo, null);
}

saida([
    'ok'          => true,
    'cupom'       => $cupom_val ? ['codigo' => $cupom_val['cupom']['codigo'], 'descricao' => $cupom_val['cupom']['descricao'] ?? ''] : null,
    'cupom_erro'  => $cupom_erro,
    'subtotal'    => $t['subtotal'],
    'desconto_cupom' => $t['desconto_cupom'],
    'desconto_pix'   => $t['desconto_pix'],
    'frete'       => $t['frete'],
    'frete_motivo'=> $t['frete_motivo'],
    'frete_falta' => $t['frete_falta'],
    'prazo_dias'  => $t['prazo_dias'],
    'total'       => $t['total'],
]);
