<?php
/**
 * ALTO JORDÃO - WEBHOOK MP
 * O mercado Pago deve chamar esta URL automaticamente quando o status de pagamento mudar.
 */
require_once 'config.php';
require_once 'mercadopago.php';

// Responder com 200 OK para o Mercado Pago
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['status' => 'ok']);

// Encerra o output mas mantém o script rodando
if (function_exists('fastcgi_finish_request')){
    fastcgi_finish_request();
}

// Ler payload
$payload = json_decode(file_get_contents('php://input'), true);
$tipo = $payload['type'] ?? ($_GET['topic'] ?? null);
$id = $payload['data']['id'] ?? ($_GET['id'] ?? null);

// Processar apenas eventos de pagamento
if ($tipo !== 'payment' || !$id) {
    registrarLog(null, "webhook_ignorado", "Tipo: $tipo | ID: $id");
    exit;
}

// Consultar pagamento na API do Mercado Pago
$pagamento = MercadoPago::consultarPagamento($id);

if (empty($pagamento['id'])) {
    registrarLog(null, "webhook_erro_consulta", "payment_id: $id");
    exit;
}

$payment_id = $pagamento['id'];
$status = $pagamento['status']; // approved, pending, rejected, refunded, cancelled
$status_detail = $pagamento['status_detail']; // accredited, cc_rejected_...
$external_ref = $pagamento['external_reference'];
$metodo = $pagamento['payment_type_id'];
$valor = $pagamento['transaction_amount'];

// Localizar pedido
$pedido = null;

if ($external_ref) {
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
    $stmt->execute([(int)$external_ref]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fallback: tentar localizar pelo payment_id
if (!$pedido) {
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE observacoes LIKE ?");
    $stmt->execute(["%mp_payment_id:$payment_id%"]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$pedido) {
    registrarLog(null, "webhook_pedido_nao_encontrado", "payment_id: $payment_id | ref: $external_ref");
    exit;
}

$pedido_id = $pedido['id'];

// Evitar reprocessamento
// Se o pedido já está num status final, não alterar novamente.
$status_finais = ['pago', 'entregue', 'cancelado'];
if (in_array($pedido['status'], $status_finais) && $status === 'approved') {
    registrarLog($pedido_id, "webhook_ignorado_status_final", "Status atual: {$pedido['status']}");
    exit;
}

// Mapear status MP -> Status interno
switch ($status) {
    case 'approved':
        // PIX pago, boleto compensado, cartão aprovado
        $pdo->prepare("UPDATE pedidos SET status = 'pago', status_pagamento = 'aprovado', data_pagamento = NOW(), observacoes = CONCAT(COALESCE(observacoes, ''), ' | webhook_approved') WHERE id = ?")->execute([$pedido_id]);
        registrarLog($pedido_id, "pagamento_aprovado", "payment_id: $payment_id | método: $metodo | valor: R$ $valor");
        break;
    
    case 'in_process':
        // PIX aguardando, boleto aguardando compensação
        $pdo->prepare("UPDATE pedidos SET status_pagamento = 'pendente', observacoes = CONCAT(COALESCE(observacoes, ''), ' | webhook_pending') WHERE id = ?")->execute([$pedido_id]);
        registrarLog($pedido_id, "pagamento_pendente", "payment_id: $payment_id | detalhe: $status_detail");
        break;

    case 'rejected':
        // Cartão recusado ou PIX expirado
        $pdo->prepare("UPDATE pedidos SET status = 'cancelado', status_pagamento = 'recusado', observacoes = CONCAT(COALESCE(observacoes, ''), ' | webhook_rejected: $status_detail') WHERE id = ?")->execute(['pedido_id']);
        registrarLog($pedido_id, "pagamento_recusado", "payment_id: $payment_id | detalhe: $status_detail");
        break;

    case 'cancelled':
        // PIX expirado sem pagamento, boleto cancelado
        $pdo->prepare("UPDATE pedidos SET status = 'cancelado', status_pagamento = 'recusado', observacoes = CONCAT(COALESCE(observacoes, ''), ' | webhook_cancelled') WHERE id = ?")->execute([$pedido_id]);
        registrarLog($pedido_id, "pagamento_cancelado", "payment_id: $payment_id");
        break;

    case 'refunded':
        // Estorno ou chargeback
        $pdo->prepare("
            UPDATE pedidos
            SET status_pagamento = 'estornado',
                observacoes = CONCAT(COALESCE(observacoes,''), ' | webhook_refunded')
            WHERE id = ?
        ")->execute([$pedido_id]);

        registrarLog($pedido_id, "pagamento_estornado",
            "payment_id: $payment_id | status: $status");
        break;

    default:
        registrarLog($pedido_id, "webhook_status_desconhecido",
            "status: $status | payment_id: $payment_id");
        break;
}

// Função de log
function registrarLog(?int $pedido_id, string $acao, string $detalhes): void
{
    global $pdo;
    try {
        $pdo->prepare("
            INSERT INTO logs_sistema (acao, tabela, registro_id, detalhes, ip)
            VALUES (?, 'pedidos', ?, ?, ?)
        ")->execute([
            $acao,
            $pedido_id,
            $detalhes,
            $_SERVER['REMOTE_ADDR'] ?? 'webhook',
        ]);
    } catch (Exception $e) {
        error_log("[WEBHOOK LOG ERROR] $acao — $detalhes — " . $e->getMessage());
    }
}