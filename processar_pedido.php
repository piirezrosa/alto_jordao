<?php
/**
 *  ALTO JORDÃO — Processador de Pedidos com Fallback
 *  Gateway primário:   Mercado Pago
 *  Gateway secundário: PagSeguro (acionado automaticamente
 *                      se o MP falhar ou retornar erro)
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'mercadopago.php';
require_once 'pagseguro.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

// LER JSON DO BODY 
$dados = json_decode(file_get_contents('php://input'), true);

if (!$dados || empty($dados['carrinho'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Carrinho vazio ou dados inválidos.']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$metodo     = $dados['metodo'] ?? 'pix';
$carrinho   = $dados['carrinho'];

// BUSCAR USUÁRIO 
$usuario = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$usuario->execute([$usuario_id]);
$usuario = $usuario->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    echo json_encode(['sucesso' => false, 'erro' => 'Usuário não encontrado.']);
    exit;
}

// Atualiza CPF e telefone
if (!empty($dados['cpf'])) {
    $pdo->prepare("UPDATE usuarios SET cpf = ?, telefone = ? WHERE id = ?")
        ->execute([$dados['cpf'], $dados['telefone'] ?? '', $usuario_id]);
    $usuario['cpf']      = $dados['cpf'];
    $usuario['telefone'] = $dados['telefone'] ?? '';
}

// SALVAR / ATUALIZAR ENDEREÇO
$end = [
    'cep'    => $dados['cep']    ?? '',
    'rua'    => $dados['rua']    ?? '',
    'numero' => $dados['numero'] ?? '',
    'bairro' => $dados['bairro'] ?? '',
    'cidade' => $dados['cidade'] ?? '',
    'estado' => $dados['estado'] ?? '',
];

$checkEnd = $pdo->prepare("SELECT id FROM enderecos WHERE usuario_id = ?");
$checkEnd->execute([$usuario_id]);

if ($checkEnd->fetchColumn()) {
    $pdo->prepare("UPDATE enderecos SET cep=?,rua=?,numero=?,bairro=?,cidade=?,estado=? WHERE usuario_id=?")
        ->execute([$end['cep'],$end['rua'],$end['numero'],$end['bairro'],$end['cidade'],$end['estado'],$usuario_id]);
} else {
    $pdo->prepare("INSERT INTO enderecos (usuario_id,cep,rua,numero,bairro,cidade,estado) VALUES (?,?,?,?,?,?,?)")
        ->execute([$usuario_id,$end['cep'],$end['rua'],$end['numero'],$end['bairro'],$end['cidade'],$end['estado']]);
}

// CALCULAR TOTAL
$subtotal = 0;
foreach ($carrinho as $item) {
    $subtotal += (float)$item['preco'] * (int)($item['qtd'] ?? 1);
}

$desconto = ($metodo === 'pix') ? round($subtotal * 0.05, 2) : 0;
$total    = round($subtotal - $desconto, 2);

// CRIAR PEDIDO NO BANCO
try {
    $pdo->beginTransaction();

    $pdo->prepare("
        INSERT INTO pedidos
            (usuario_id, status, total, subtotal, desconto, forma_pagamento,
             end_cep, end_rua, end_numero, end_bairro, end_cidade, end_estado)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    ")->execute([
        $usuario_id, 'pendente', $total, $subtotal, $desconto, $metodo,
        $end['cep'], $end['rua'], $end['numero'],
        $end['bairro'], $end['cidade'], $end['estado'],
    ]);

    $pedido_id = $pdo->lastInsertId();

    $stmtItem = $pdo->prepare("
        INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario, variacoes)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($carrinho as $item) {
        $vars = ($item['tamanho_escolhido'] ?? 'Único') . ' | ' . ($item['cor_escolhida'] ?? 'Padrão');
        $stmtItem->execute([
            $pedido_id, (int)$item['id'],
            (int)($item['qtd'] ?? 1), (float)$item['preco'], $vars,
        ]);
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao registrar pedido. Tente novamente.']);
    exit;
}

$pedido_arr = ['id' => $pedido_id, 'total' => $total];

//  FUNÇÃO DE FALLBACK AUTOMÁTICO
/**
 * Tenta o Mercado Pago. Se falhar (erro de rede, API fora,
 * erro HTTP), tenta o PagSeguro automaticamente.
 * Registra qual gateway foi usado em cada tentativa.
 */
function processarComFallback(
    string $metodo,
    array  $pedido_arr,
    array  $usuario,
    array  $end,
    array  $carrinho,
    array  $dados,
    PDO    $pdo
): array {

    $pedido_id = $pedido_arr['id'];
    $resultado = null;
    $gateway   = null;

    // TENTATIVA 1: MERCADO PAGO 
    try {
        switch ($metodo) {

            case 'pix':
                $resultado = MercadoPago::gerarPix($pedido_arr, $usuario);
                break;

            case 'boleto':
                $resultado = MercadoPago::gerarBoleto($pedido_arr, $usuario, $end);
                break;

            case 'cartao':
                if (empty($dados['token_mp'])) throw new Exception('Token MP ausente');
                $resultado = MercadoPago::processarCartao(
                    $pedido_arr, $usuario,
                    $dados['token_mp'],
                    (int)($dados['parcelas']          ?? 1),
                    (string)($dados['issuer_id']      ?? ''),
                    (string)($dados['payment_method_id'] ?? '')
                );
                break;
        }

        // Considera erro se a API retornou flag de erro
        if (!empty($resultado['error'])) {
            throw new Exception($resultado['message'] ?? 'Erro MP');
        }

        $gateway = 'mercadopago';
        registrarGatewayLog($pdo, $pedido_id, 'mercadopago', 'sucesso', $metodo);

    } catch (Exception $e) {

        // Loga a falha do MP
        registrarGatewayLog($pdo, $pedido_id, 'mercadopago', 'falha: ' . $e->getMessage(), $metodo);
        error_log("[GATEWAY FALLBACK] MP falhou no pedido $pedido_id: " . $e->getMessage());

        // TENTATIVA 2: PAGSEGURO
        try {
            switch ($metodo) {

                case 'pix':
                    $resultado = PagSeguro::gerarPix($pedido_arr, $usuario, $end, $carrinho);
                    break;

                case 'boleto':
                    $resultado = PagSeguro::gerarBoleto($pedido_arr, $usuario, $end, $carrinho);
                    break;

                case 'cartao':
                    if (empty($dados['token_ps'])) {
                        throw new Exception('Token PS ausente — cliente precisa selecionar gateway PS');
                    }
                    $resultado = PagSeguro::processarCartao(
                        $pedido_arr, $usuario, $end, $carrinho,
                        $dados['token_ps'],
                        (int)($dados['parcelas'] ?? 1)
                    );
                    break;
            }

            if (!empty($resultado['error'])) {
                throw new Exception($resultado['message'] ?? 'Erro PS');
            }

            $gateway = 'pagseguro';
            registrarGatewayLog($pdo, $pedido_id, 'pagseguro', 'sucesso (fallback)', $metodo);

        } catch (Exception $e2) {
            registrarGatewayLog($pdo, $pedido_id, 'pagseguro', 'falha: ' . $e2->getMessage(), $metodo);
            error_log("[GATEWAY FALLBACK] PS também falhou no pedido $pedido_id: " . $e2->getMessage());

            // Ambos falharam
            return [
                'error'   => true,
                'message' => 'Todos os gateways de pagamento estão indisponíveis no momento. '
                           . 'Seu pedido foi salvo — tente novamente em alguns minutos.',
            ];
        }
    }

    return array_merge($resultado, ['gateway_usado' => $gateway]);
}

// LOG DE GATEWAY
function registrarGatewayLog(PDO $pdo, int $pedido_id, string $gateway, string $status, string $metodo): void
{
    try {
        $pdo->prepare("
            INSERT INTO logs_sistema (acao, tabela, registro_id, detalhes, ip)
            VALUES (?, 'pedidos', ?, ?, ?)
        ")->execute([
            'gateway_tentativa',
            $pedido_id,
            "gateway: $gateway | status: $status | método: $metodo",
            $_SERVER['REMOTE_ADDR'] ?? 'server',
        ]);
    } catch (Exception $e) {
        error_log("[LOG ERROR] $e");
    }
}

// VALIDAR TOKEN DO CARTÃO
if ($metodo === 'cartao' && empty($dados['token_mp']) && empty($dados['token_ps'])) {
    $pdo->prepare("DELETE FROM itens_pedido WHERE pedido_id=?")->execute([$pedido_id]);
    $pdo->prepare("DELETE FROM pedidos WHERE id=?")->execute([$pedido_id]);
    echo json_encode(['sucesso' => false, 'erro' => 'Token do cartão não recebido.']);
    exit;
}

// PROCESSAR COM FALLBACK
$resultado = processarComFallback(
    $metodo, $pedido_arr, $usuario, $end, $carrinho, $dados, $pdo
);

// AMBOS OS GATEWAYS FALHARAM
if (!empty($resultado['error'])) {
    echo json_encode(['sucesso' => false, 'erro' => $resultado['message'], 'pedido_id' => $pedido_id]);
    exit;
}

// PÓS-PROCESSAMENTO POR MÉTODO/GATEWAY
$payment_id  = $resultado['payment_id'] ?? $resultado['order_id'] ?? null;
$status_res  = $resultado['status']     ?? 'pending';
$gateway     = $resultado['gateway_usado'];

// Salva o payment_id e gateway para o webhook localizar o pedido
$pdo->prepare("UPDATE pedidos SET observacoes = ? WHERE id = ?")
    ->execute(["gateway:$gateway|payment_id:$payment_id", $pedido_id]);

// Cartão aprovado na hora (MP: approved | PS: PAID)
$cartao_aprovado = (
    $metodo === 'cartao' &&
    (!empty($resultado['approved']) ||
     in_array(strtolower($status_res), ['approved','paid']))
);

if ($cartao_aprovado) {
    $pdo->prepare("
        UPDATE pedidos
        SET status='pago', status_pagamento='aprovado', data_pagamento=NOW()
        WHERE id=?
    ")->execute([$pedido_id]);
}

// Cartão recusado
$cartao_recusado = (
    $metodo === 'cartao' &&
    (($resultado['approved'] ?? true) === false ||
     in_array(strtolower($status_res), ['rejected','declined','canceled']))
);

if ($cartao_recusado) {
    $msg = $gateway === 'mercadopago'
        ? MercadoPago::traduzirErroCartao($resultado['status_detail'] ?? '')
        : PagSeguro::traduzirErroCartao($resultado['status_detail'] ?? '');
    echo json_encode(['sucesso' => false, 'erro' => $msg, 'pedido_id' => $pedido_id]);
    exit;
}

// LOG FINAL
$pdo->prepare("INSERT INTO logs_sistema (usuario_id, acao, tabela, registro_id, detalhes, ip) VALUES (?,?,?,?,?,?)")
    ->execute([
        $usuario_id, 'pedido_criado', 'pedidos', $pedido_id,
        "método: $metodo | gateway: $gateway | payment_id: $payment_id | status: $status_res",
        $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

// RESPOSTA FINAL
$resposta = [
    'sucesso'       => true,
    'pedido_id'     => $pedido_id,
    'metodo'        => $metodo,
    'payment_id'    => $payment_id,
    'gateway_usado' => $gateway,
];

// Dados extras por método
if ($metodo === 'pix') {
    // MP retorna base64; PS retorna URL da imagem
    $resposta['qr_code']    = $resultado['qr_code']    ?? null;
    $resposta['qr_base64']  = $resultado['qr_base64']  ?? null;
    $resposta['qr_img_url'] = $resultado['qr_img_url'] ?? null;
    $resposta['expiracao']  = $resultado['expiracao']  ?? null;
}

if ($metodo === 'boleto') {
    $resposta['boleto_url'] = $resultado['boleto_url'] ?? null;
    $resposta['barcode']    = $resultado['barcode']    ?? null;
    $resposta['vencimento'] = $resultado['vencimento'] ?? null;
}

echo json_encode($resposta);