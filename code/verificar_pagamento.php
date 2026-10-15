<?php
/**
 * Verificar Pagamento — Alto Jordão
 * Usado pelo polling de pedido_confirmado.php (PIX).
 * Resposta: { pago: bool, status: string, status_pagamento: string }
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['pago' => false, 'erro' => 'não autenticado']);
    exit;
}

$pedido_id = (int)($_GET['pedido_id'] ?? 0);
$stmt = $pdo->prepare("SELECT id, status, status_pagamento, observacoes FROM pedidos WHERE id = ? AND usuario_id = ?");
$stmt->execute([$pedido_id, $_SESSION['usuario_id']]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    http_response_code(404);
    echo json_encode(['pago' => false, 'erro' => 'pedido não encontrado']);
    exit;
}

$pago = ($pedido['status'] === 'pago');

// Se o webhook ainda não chegou, consulta o Mercado Pago diretamente
if (!$pago && $pedido['status'] === 'pendente'
    && preg_match('/mp_payment_id:(\d+)/', (string)$pedido['observacoes'], $m)
    && is_file(__DIR__ . '/mercadopago.php')) {

    require_once __DIR__ . '/mercadopago.php';
    try {
        $pg = MercadoPago::consultarPagamento($m[1]);
        if (($pg['status'] ?? '') === 'approved'
            && (string)($pg['external_reference'] ?? '') === (string)$pedido_id) {
            $pdo->prepare("UPDATE pedidos SET status='pago', status_pagamento='aprovado', data_pagamento=NOW()
                           WHERE id = ? AND status = 'pendente'")->execute([$pedido_id]);
            $pago = true;
            $pedido['status'] = 'pago';
            $pedido['status_pagamento'] = 'aprovado';
        }
    } catch (Throwable $e) {
        error_log("[VERIFICAR_PAGAMENTO] pedido=$pedido_id: " . $e->getMessage());
    }
}

echo json_encode([
    'pago'             => $pago,
    'status'           => $pedido['status'],
    'status_pagamento' => $pedido['status_pagamento'],
]);
