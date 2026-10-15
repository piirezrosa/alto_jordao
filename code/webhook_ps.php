<?php
/**
 * ALTO JORDÃO - WEBHOOK PAGSEGURO / PAGBANK
 *
 * O PagBank chama esta URL (notification_urls) quando uma cobrança muda de status.
 * Segurança: NÃO confiamos no corpo recebido. Usamos só o id da ordem e consultamos
 * a API do PagBank com o nosso token — o status e o valor vêm de lá.
 *
 * Também confere: referência da ordem == pedido, ordem == a gravada no pedido e valor pago == total.
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once 'config.php';
require_once 'mercadopago.php';   // define SITE_URL (usada pelo pagseguro.php)
require_once 'pagseguro.php';
require_once 'pedido_helpers.php';

// Responde 200 logo (o PagBank reenvia se não receber 2xx)
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['status' => 'ok']);
if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); }

$raw     = file_get_contents('php://input');
$payload = json_decode($raw, true);

// Assinatura (informativa): sha256("<token>-<corpo>") no header x-authenticity-token
$assinatura_ok = null;
if (!empty($_SERVER['HTTP_X_AUTHENTICITY_TOKEN'])) {
    $esperado = hash('sha256', PS_TOKEN . '-' . $raw);
    $assinatura_ok = hash_equals($esperado, strtolower($_SERVER['HTTP_X_AUTHENTICITY_TOKEN']));
}

$ordem_id = is_array($payload) ? ($payload['id'] ?? '') : '';
if (!is_string($ordem_id) || !preg_match('/^ORDE_[A-Za-z0-9-]+$/', $ordem_id)) {
    pedido_log($pdo, null, 'webhook_ps_ignorado', 'payload sem id de ordem válido');
    exit;
}

// Fonte da verdade: consulta direta à API
$ordem = PagSeguro::consultarPedido($ordem_id);
if (!empty($ordem['error']) || empty($ordem['id'])) {
    pedido_log($pdo, null, 'webhook_ps_erro_consulta', "ordem: $ordem_id | " . ($ordem['message'] ?? ''));
    exit;
}

// Localiza o pedido pela referência "pedido-N"
if (!preg_match('/^pedido-(\d+)$/', (string)($ordem['reference_id'] ?? ''), $m)) {
    pedido_log($pdo, null, 'webhook_ps_referencia_invalida', "ordem: $ordem_id");
    exit;
}
$pedido_id = (int)$m[1];

$st = $pdo->prepare("SELECT id, status, total, observacoes FROM pedidos WHERE id = ?");
$st->execute([$pedido_id]);
$pedido = $st->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    pedido_log($pdo, null, 'webhook_ps_pedido_nao_encontrado', "ordem: $ordem_id | pedido: $pedido_id");
    exit;
}
// A ordem precisa ser a que gravamos neste pedido
if (strpos((string)$pedido['observacoes'], "ps_order_id:$ordem_id") === false) {
    pedido_log($pdo, $pedido_id, 'webhook_ps_ordem_divergente', "ordem recebida: $ordem_id");
    exit;
}

$r = pagseguro_interpretar_ordem($ordem);
$sufixo = $assinatura_ok === false ? ' | assinatura_divergente' : '';

if ($r['pago']) {
    $esperado_centavos = (int)round(((float)$pedido['total']) * 100);
    if ($r['valor_pago'] > 0 && $r['valor_pago'] !== $esperado_centavos) {
        pedido_log($pdo, $pedido_id, 'webhook_ps_valor_divergente',
            "pago: {$r['valor_pago']} | esperado: $esperado_centavos | ordem: $ordem_id");
        exit;   // não aprova pedido com valor diferente
    }
    if (pedido_marcar_pago($pdo, $pedido_id, 'webhook_ps_paid')) {
        pedido_log($pdo, $pedido_id, 'pagamento_aprovado', "ordem: $ordem_id | gateway: ps$sufixo");
    } else {
        pedido_log($pdo, $pedido_id, 'webhook_ps_ignorado_status_final', "status atual: {$pedido['status']}");
    }
} elseif ($r['recusado']) {
    if (pedido_cancelar_devolvendo_estoque($pdo, $pedido_id, 'webhook_ps_' . strtolower($r['status']))) {
        pedido_log($pdo, $pedido_id, 'pagamento_cancelado', "ordem: $ordem_id | status: {$r['status']} | gateway: ps$sufixo");
    }
} else {
    pedido_log($pdo, $pedido_id, 'webhook_ps_pendente', "ordem: $ordem_id | status: " . ($r['status'] ?: 'sem cobrança'));
}
