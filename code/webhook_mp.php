<?php
/**
 * ALTO JORDÃO - WEBHOOK MERCADO PAGO
 *
 * O Mercado Pago chama esta URL quando o status de um pagamento muda.
 *
 * Regras:
 *  - O corpo recebido NÃO é confiável: usamos só o id do pagamento e consultamos a API.
 *  - Toda mudança de estado é idempotente (só age em pedido 'pendente'): um evento
 *    atrasado/duplicado nunca cancela pedido pago nem devolve estoque duas vezes.
 *  - Aprovação só vale se o valor pago bater com o total do pedido.
 *  - Pagamento aprovado em pedido já cancelado gera ALERTA no log (reembolso manual).
 *  - Se MP_WEBHOOK_SECRET estiver definido (mercadopago.php), a assinatura x-signature é exigida.
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);
ignore_user_abort(true);   // termina o processamento mesmo se o MP fechar a conexão

require_once 'config.php';
require_once 'mercadopago.php';
require_once 'pedido_helpers.php';

// Responde 200 logo (o MP reenvia se não receber 2xx)
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['status' => 'ok']);
if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); }

// ── ENTRADA ──────────────────────────────────────────────
$payload = json_decode(file_get_contents('php://input'), true);
$tipo    = $payload['type'] ?? ($_GET['type'] ?? ($_GET['topic'] ?? null));
$id      = $payload['data']['id'] ?? ($_GET['data_id'] ?? ($_GET['data']['id'] ?? ($_GET['id'] ?? null)));

// Só eventos de pagamento, com id numérico
if ($tipo !== 'payment' || !is_scalar($id) || !preg_match('/^\d+$/', (string)$id)) {
    pedido_log($pdo, null, 'webhook_mp_ignorado', 'tipo: ' . (is_scalar($tipo) ? $tipo : '?') . ' | id: ' . (is_scalar($id) ? $id : '?'));
    exit;
}
$id = (string)$id;

// ── ASSINATURA (opcional, ativada pela constante MP_WEBHOOK_SECRET) ──
if (defined('MP_WEBHOOK_SECRET') && MP_WEBHOOK_SECRET !== '') {
    $sig = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $req = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
    $ts = $v1 = '';
    foreach (explode(',', $sig) as $parte) {
        [$k, $v] = array_pad(explode('=', trim($parte), 2), 2, '');
        if ($k === 'ts') $ts = $v;
        if ($k === 'v1') $v1 = $v;
    }
    $data_id_url = isset($_GET['data_id']) ? (string)$_GET['data_id'] : $id;
    $manifesto   = 'id:' . strtolower($data_id_url) . ";request-id:$req;ts:$ts;";
    $esperado    = hash_hmac('sha256', $manifesto, MP_WEBHOOK_SECRET);
    if ($ts === '' || $v1 === '' || !hash_equals($esperado, strtolower($v1))) {
        pedido_log($pdo, null, 'webhook_mp_assinatura_invalida', "payment_id: $id");
        exit;
    }
}

// ── CONSULTA O PAGAMENTO NA API (fonte da verdade) ──────
$pagamento = MercadoPago::consultarPagamento($id);
if (!empty($pagamento['error']) || empty($pagamento['id'])) {
    pedido_log($pdo, null, 'webhook_mp_erro_consulta', "payment_id: $id | " . ($pagamento['message'] ?? ''));
    exit;
}

$payment_id    = (string)$pagamento['id'];
$status        = (string)($pagamento['status'] ?? '');         // approved, pending, in_process, rejected, cancelled, refunded, charged_back...
$status_detail = (string)($pagamento['status_detail'] ?? '');
$external_ref  = (string)($pagamento['external_reference'] ?? '');
$metodo        = (string)($pagamento['payment_type_id'] ?? '');
$valor         = round((float)($pagamento['transaction_amount'] ?? 0), 2);

// ── LOCALIZA O PEDIDO ───────────────────────────────────
$pedido = null;
if (preg_match('/^\d+$/', $external_ref)) {
    $stmt = $pdo->prepare("SELECT id, status, total, observacoes FROM pedidos WHERE id = ?");
    $stmt->execute([(int)$external_ref]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$pedido) {   // fallback: pelo payment_id gravado no pedido
    $stmt = $pdo->prepare("SELECT id, status, total, observacoes FROM pedidos WHERE observacoes LIKE ? LIMIT 1");
    $stmt->execute(["%mp_payment_id:$payment_id%"]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$pedido) {
    pedido_log($pdo, null, 'webhook_mp_pedido_nao_encontrado', "payment_id: $payment_id | ref: $external_ref");
    exit;
}
$pedido_id = (int)$pedido['id'];

// Se o pedido já tem um payment_id gravado, precisa ser este.
// (Se ainda não tem, o webhook chegou antes do checkout gravar — aceitamos pela referência + valor.)
if (preg_match('/mp_payment_id:(\d+)/', (string)$pedido['observacoes'], $m) && $m[1] !== $payment_id) {
    pedido_log($pdo, $pedido_id, 'webhook_mp_pagamento_divergente', "recebido: $payment_id | gravado: {$m[1]}");
    exit;
}

// ── ESTADOS ─────────────────────────────────────────────
switch ($status) {

    case 'approved':
        // Valor pago precisa bater com o total do pedido
        if (abs($valor - round((float)$pedido['total'], 2)) > 0.009) {
            pedido_log($pdo, $pedido_id, 'webhook_mp_valor_divergente',
                "pago: $valor | esperado: {$pedido['total']} | payment_id: $payment_id");
            break;
        }
        if (pedido_marcar_pago($pdo, $pedido_id, 'webhook_mp_approved')) {
            pedido_log($pdo, $pedido_id, 'pagamento_aprovado', "payment_id: $payment_id | método: $metodo | valor: R$ $valor | gateway: mp");
        } elseif ($pedido['status'] === 'cancelado') {
            // Cliente pagou, mas o pedido já foi cancelado (ex.: PIX pago depois de expirar/cancelar)
            pedido_log($pdo, $pedido_id, 'ALERTA_pagamento_aprovado_pedido_cancelado',
                "payment_id: $payment_id | valor: R$ $valor | REEMBOLSAR OU REATIVAR MANUALMENTE");
        } else {
            pedido_log($pdo, $pedido_id, 'webhook_mp_ignorado_status_final', "status atual: {$pedido['status']}");
        }
        break;

    case 'pending':
    case 'in_process':
    case 'authorized':
        // Aguardando (PIX/boleto/análise): nada a mudar — o pedido já nasce 'pendente'
        pedido_log($pdo, $pedido_id, 'pagamento_pendente', "payment_id: $payment_id | status: $status | detalhe: $status_detail");
        break;

    case 'rejected':
    case 'cancelled':
        // Cancela e devolve o estoque, mas só se ainda estiver pendente (nunca mexe em pedido pago)
        if (pedido_cancelar_devolvendo_estoque($pdo, $pedido_id, "webhook_mp_$status: $status_detail")) {
            pedido_log($pdo, $pedido_id, $status === 'rejected' ? 'pagamento_recusado' : 'pagamento_cancelado',
                "payment_id: $payment_id | detalhe: $status_detail");
        } else {
            pedido_log($pdo, $pedido_id, 'webhook_mp_ignorado_status_final', "evento: $status | status atual: {$pedido['status']}");
        }
        break;

    case 'refunded':
    case 'charged_back':
        if (pedido_marcar_estornado($pdo, $pedido_id, "webhook_mp_$status")) {
            pedido_log($pdo, $pedido_id, $status === 'refunded' ? 'pagamento_estornado' : 'ALERTA_chargeback',
                "payment_id: $payment_id | status: $status | valor: R$ $valor");
        }
        break;

    default:   // in_mediation etc.
        pedido_log($pdo, $pedido_id, 'webhook_mp_status_nao_tratado', "status: $status | payment_id: $payment_id");
        break;
}
