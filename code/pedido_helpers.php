<?php
/**
 * Funções compartilhadas de ciclo de vida do pedido (webhooks e polling).
 * Todas são idempotentes: só agem sobre pedidos ainda 'pendente'.
 */

/** Marca o pedido como pago. Retorna true se alterou (false se já estava processado). */
function pedido_marcar_pago(PDO $pdo, int $pedido_id, string $nota = ''): bool
{
    $st = $pdo->prepare("
        UPDATE pedidos
           SET status = 'pago', status_pagamento = 'aprovado', data_pagamento = NOW(),
               observacoes = CONCAT(COALESCE(observacoes, ''), ?)
         WHERE id = ? AND status = 'pendente'
    ");
    $st->execute([$nota !== '' ? " | $nota" : '', $pedido_id]);
    return $st->rowCount() === 1;
}

/**
 * Cancela o pedido pendente e devolve o estoque dos itens.
 * Retorna true se cancelou agora (false se já estava pago/cancelado).
 */
function pedido_cancelar_devolvendo_estoque(PDO $pdo, int $pedido_id, string $motivo = ''): bool
{
    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare("
            UPDATE pedidos
               SET status = 'cancelado', status_pagamento = 'recusado',
                   observacoes = CONCAT(COALESCE(observacoes, ''), ?)
             WHERE id = ? AND status = 'pendente'
        ");
        $st->execute([$motivo !== '' ? " | $motivo" : '', $pedido_id]);

        if ($st->rowCount() !== 1) {   // já tratado por outro caminho
            $pdo->rollBack();
            return false;
        }

        $itens = $pdo->prepare("SELECT produto_id, SUM(quantidade) AS q FROM itens_pedido WHERE pedido_id = ? GROUP BY produto_id");
        $itens->execute([$pedido_id]);

        $volta = $pdo->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id = ?");
        $mov   = $pdo->prepare("INSERT INTO estoque_movimentacoes (produto_id, tipo, quantidade, motivo, usuario_id) VALUES (?,?,?,?,NULL)");
        foreach ($itens->fetchAll(PDO::FETCH_ASSOC) as $it) {
            $volta->execute([(int)$it['q'], (int)$it['produto_id']]);
            $mov->execute([(int)$it['produto_id'], 'entrada', (int)$it['q'], "estorno_pedido_$pedido_id"]);
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("[PEDIDO CANCELAR] pedido=$pedido_id: " . $e->getMessage());
        return false;
    }
}

/** Registro em logs_sistema sem nunca quebrar o fluxo. */
function pedido_log(PDO $pdo, ?int $pedido_id, string $acao, string $detalhes): void
{
    try {
        $pdo->prepare("INSERT INTO logs_sistema (acao, tabela, registro_id, detalhes, ip) VALUES (?, 'pedidos', ?, ?, ?)")
            ->execute([$acao, $pedido_id, mb_substr($detalhes, 0, 500), $_SERVER['REMOTE_ADDR'] ?? 'webhook']);
    } catch (Throwable $e) {
        error_log("[LOG ERROR] $acao — " . $e->getMessage());
    }
}

/**
 * Interpreta uma ordem do PagBank (GET /orders/{id}) e devolve:
 *   'pago'      => true se existe cobrança PAID
 *   'recusado'  => true se todas as cobranças terminaram em DECLINED/CANCELED
 *   'valor_pago'=> valor pago em centavos (0 se não pago)
 *   'status'    => status da cobrança mais relevante (ou '' se ainda não há cobrança)
 */
function pagseguro_interpretar_ordem(array $ordem): array
{
    $charges = $ordem['charges'] ?? [];
    $pago = false; $valor = 0; $status = '';
    $finais_ruins = 0;

    foreach ($charges as $c) {
        $s = strtoupper($c['status'] ?? '');
        $status = $status ?: $s;
        if ($s === 'PAID') {
            $pago  = true;
            $valor = (int)($c['amount']['summary']['paid'] ?? $c['amount']['value'] ?? 0);
            $status = 'PAID';
            break;
        }
        if (in_array($s, ['DECLINED', 'CANCELED'], true)) $finais_ruins++;
    }

    return [
        'pago'       => $pago,
        'recusado'   => !$pago && $charges && $finais_ruins === count($charges),
        'valor_pago' => $valor,
        'status'     => $status,
    ];
}
