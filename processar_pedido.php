<?php
/**
 * Processar Pedido — Alto Jordão
 * Aceita tanto POST de formulário HTML (checkout.php)
 * quanto JSON (checkout com SDK do MP, futuro).
 * Funciona com ou sem gateway configurado.
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php"); exit;
}

$usuario_id = $_SESSION['usuario_id'];

// ── LER DADOS (form POST ou JSON) ────────────────────────
$is_json = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');

if ($is_json) {
    $dados   = json_decode(file_get_contents('php://input'), true) ?? [];
    $metodo  = $dados['metodo']     ?? 'pix';
    $carrinho= $dados['carrinho']   ?? [];
    $cep     = $dados['cep']        ?? '';
    $rua     = $dados['rua']        ?? '';
    $numero  = $dados['numero']     ?? '';
    $bairro  = $dados['bairro']     ?? '';
    $cidade  = $dados['cidade']     ?? '';
    $estado  = $dados['estado']     ?? '';
    $cpf     = $dados['cpf']        ?? '';
    $telefone= $dados['telefone']   ?? '';
} else {
    // Formulário HTML padrão
    $metodo   = $_POST['pagamento']      ?? 'pix';
    $cpf      = $_POST['cpf']            ?? '';
    $telefone = $_POST['telefone']       ?? '';
    $cep      = $_POST['cep']            ?? '';
    $rua      = $_POST['rua']            ?? $_POST['endereco'] ?? '';
    $numero   = $_POST['numero']         ?? '';
    $bairro   = $_POST['bairro']         ?? '';
    $cidade   = $_POST['cidade']         ?? '';
    $estado   = $_POST['estado']         ?? '';
    $carrinho = json_decode($_POST['carrinho_dados'] ?? '[]', true) ?? [];
}

// ── VALIDAÇÕES ────────────────────────────────────────────
if (empty($carrinho)) {
    header("Location: carrinho.php?erro=carrinho_vazio"); exit;
}
if (!in_array($metodo, ['pix','cartao','boleto'])) {
    $metodo = 'pix';
}

// ── ATUALIZA CPF/TELEFONE DO USUÁRIO ─────────────────────
$cpf_limpo = preg_replace('/\D/', '', $cpf);
if ($cpf_limpo) {
    $pdo->prepare("UPDATE usuarios SET cpf = ?, telefone = ? WHERE id = ?")
        ->execute([$cpf_limpo, $telefone, $usuario_id]);
}

// ── SALVA / ATUALIZA ENDEREÇO ─────────────────────────────
$cep_limpo = preg_replace('/\D/', '', $cep);
if ($cep_limpo) {
    $chk = $pdo->prepare("SELECT id FROM enderecos WHERE usuario_id = ?");
    $chk->execute([$usuario_id]);
    if ($chk->fetchColumn()) {
        $pdo->prepare("UPDATE enderecos SET cep=?,rua=?,numero=?,bairro=?,cidade=?,estado=? WHERE usuario_id=?")
            ->execute([$cep_limpo, $rua, $numero, $bairro, $cidade, $estado, $usuario_id]);
    } else {
        $pdo->prepare("INSERT INTO enderecos (usuario_id,cep,rua,numero,bairro,cidade,estado) VALUES (?,?,?,?,?,?,?)")
            ->execute([$usuario_id, $cep_limpo, $rua, $numero, $bairro, $cidade, $estado]);
    }
}

// ── CALCULAR TOTAL ────────────────────────────────────────
$subtotal = 0;
foreach ($carrinho as $item) {
    $subtotal += (float)($item['preco'] ?? 0) * (int)($item['qtd'] ?? 1);
}

$desconto = ($metodo === 'pix') ? round($subtotal * 0.05, 2) : 0;
$total    = round($subtotal - $desconto, 2);

// ── CRIAR PEDIDO ──────────────────────────────────────────
try {
    $pdo->beginTransaction();

    $pdo->prepare("
        INSERT INTO pedidos
            (usuario_id, status, total, subtotal, desconto, forma_pagamento,
             end_cep, end_rua, end_numero, end_bairro, end_cidade, end_estado,
             status_pagamento, data_pedido)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'pendente',NOW())
    ")->execute([
        $usuario_id, 'pendente', $total, $subtotal, $desconto, $metodo,
        $cep_limpo, $rua, $numero, $bairro, $cidade, $estado,
    ]);

    $pedido_id = $pdo->lastInsertId();

    $stmtItem = $pdo->prepare("
        INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario, variacoes)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($carrinho as $item) {
        $produto_id = (int)($item['id'] ?? 0);
        if (!$produto_id) continue;

        $variacoes = trim(
            ($item['tamanho_escolhido'] ?? '') . ' ' .
            ($item['cor_escolhida']     ?? '')
        ) ?: 'Padrão';

        $stmtItem->execute([
            $pedido_id,
            $produto_id,
            (int)($item['qtd'] ?? 1),
            (float)($item['preco'] ?? 0),
            $variacoes,
        ]);
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("[PEDIDO ERROR] usuario=$usuario_id: " . $e->getMessage());
    header("Location: checkout.php?erro=erro_interno"); exit;
}

// ── TENTAR PROCESSAR PAGAMENTO (se gateway configurado) ───
$gateway_resultado = null;
$gateway_erro      = null;

$mp_configurado = defined('MP_ACCESS_TOKEN')
    && MP_ACCESS_TOKEN !== ''
    && MP_ACCESS_TOKEN !== 'SEU_ACCESS_TOKEN_AQUI';

if ($mp_configurado && file_exists(__DIR__ . '/mercadopago.php')) {
    require_once 'mercadopago.php';

    $usuario_dados = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $usuario_dados->execute([$usuario_id]);
    $usuario_dados = $usuario_dados->fetch(PDO::FETCH_ASSOC);

    $pedido_arr = ['id' => $pedido_id, 'total' => $total];
    $end_arr    = ['cep'=>$cep_limpo,'rua'=>$rua,'numero'=>$numero,'bairro'=>$bairro,'cidade'=>$cidade,'estado'=>$estado];

    try {
        switch ($metodo) {
            case 'pix':
                $gateway_resultado = MercadoPago::gerarPix($pedido_arr, $usuario_dados);
                break;
            case 'boleto':
                $gateway_resultado = MercadoPago::gerarBoleto($pedido_arr, $usuario_dados, $end_arr);
                break;
            // cartão via form padrão não suporta tokenização — precisa do SDK JS
            default:
                $gateway_resultado = null;
        }
    } catch (Exception $e) {
        $gateway_erro = $e->getMessage();
        error_log("[MP ERROR] pedido=$pedido_id: $gateway_erro");
    }
}

// Salva payment_id se disponível
if (!empty($gateway_resultado['payment_id'])) {
    $pdo->prepare("UPDATE pedidos SET observacoes = ? WHERE id = ?")
        ->execute(["mp_payment_id:" . $gateway_resultado['payment_id'], $pedido_id]);
}

// Log
$pdo->prepare("INSERT INTO logs_sistema (usuario_id, acao, tabela, registro_id, detalhes, ip) VALUES (?,?,?,?,?,?)")
    ->execute([
        $usuario_id, 'pedido_criado', 'pedidos', $pedido_id,
        "método: $metodo | total: R$$total | gateway: " . ($mp_configurado ? 'MP' : 'sem_gateway'),
        $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

// ── SALVA DADOS DO GATEWAY NA SESSÃO (para a tela de confirmação) ──
if ($gateway_resultado) {
    $_SESSION['mp_payment_data'] = $gateway_resultado;
}

// ── REDIRECIONA PARA CONFIRMAÇÃO ──────────────────────────
header("Location: pedido_confirmado.php?id=$pedido_id&metodo=$metodo");
exit;