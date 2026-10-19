<?php
/**
 * Processar Pedido — Alto Jordão
 *
 * Recebe JSON do checkout.php e responde JSON:
 *   { sucesso: true,  pedido_id, status, gateway, qr_code, qr_base64, qr_img_url,
 *     expiracao, boleto_url, barcode, vencimento }
 *   { sucesso: false, erro: "mensagem" }
 *
 * Regras de segurança:
 *  - preço, nome e estoque vêm SEMPRE do banco (o carrinho do navegador só informa id/qtd/variação)
 *  - estoque é travado (FOR UPDATE) e baixado na mesma transação do pedido
 *  - se o pagamento falhar, o pedido é cancelado e o estoque devolvido
 *  - gateway principal: Mercado Pago; fallback automático: PagSeguro
 */
// Endpoint JSON: warnings/notices nunca podem vazar para o corpo da resposta
ini_set('display_errors', '0');
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'loja_helpers.php';
require_once 'financeiro_helpers.php';
require_once 'pedido_helpers.php';

header('Content-Type: application/json; charset=utf-8');

function responder(array $dados, int $http = 200): void {
    http_response_code($http);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['sucesso' => false, 'erro' => 'Método não permitido.'], 405);
}
if (!isset($_SESSION['usuario_id'])) {
    responder(['sucesso' => false, 'erro' => 'Sessão expirada. Faça login novamente.'], 401);
}

$usuario_id = (int)$_SESSION['usuario_id'];
$dados      = json_decode(file_get_contents('php://input'), true);
if (!is_array($dados)) {
    responder(['sucesso' => false, 'erro' => 'Requisição inválida.'], 400);
}

// ── ENTRADA ──────────────────────────────────────────────
$metodo    = $dados['metodo'] ?? 'pix';
if (!in_array($metodo, ['pix', 'cartao', 'boleto'], true)) {
    responder(['sucesso' => false, 'erro' => 'Forma de pagamento inválida.'], 400);
}

$cpf       = preg_replace('/\D/', '', $dados['cpf'] ?? '');
$telefone  = preg_replace('/\D/', '', $dados['telefone'] ?? '');
$cep       = preg_replace('/\D/', '', $dados['cep'] ?? '');
$rua       = trim($dados['rua']    ?? '');
$numero    = trim($dados['numero'] ?? '');
$bairro    = trim($dados['bairro'] ?? '');
$cidade    = trim($dados['cidade'] ?? '');
$estado    = strtoupper(substr(trim($dados['estado'] ?? ''), 0, 2));
$parcelas  = max(1, min(12, (int)($dados['parcelas'] ?? 1)));
$token_mp  = (string)($dados['token_mp'] ?? '');
$token_ps  = (string)($dados['token_ps'] ?? '');
$issuer_id = (string)($dados['issuer_id'] ?? '');
$pm_id     = (string)($dados['payment_method_id'] ?? '');
$cupom_codigo = strtoupper(trim((string)($dados['cupom'] ?? '')));
$frete_servico_pedido = preg_replace('/[^a-z]/', '', strtolower((string)($dados['frete_servico'] ?? '')));

if (strlen($cpf) !== 11)  responder(['sucesso' => false, 'erro' => 'CPF inválido.'], 422);
if (strlen($cep) !== 8 || $rua === '' || $numero === '' || $cidade === '') {
    responder(['sucesso' => false, 'erro' => 'Endereço de entrega incompleto.'], 422);
}
if ($metodo === 'cartao' && $token_mp === '' && $token_ps === '') {
    responder(['sucesso' => false, 'erro' => 'Dados do cartão não foram validados.'], 422);
}

// Carrinho do cliente: só id, quantidade e variações são aproveitados
$carrinho_in = $dados['carrinho'] ?? [];
if (!is_array($carrinho_in) || !$carrinho_in) {
    responder(['sucesso' => false, 'erro' => 'Seu carrinho está vazio.'], 422);
}

// Agrupa por produto + variação
$linhas = [];
foreach ($carrinho_in as $it) {
    $pid = (int)($it['id'] ?? 0);
    $qtd = (int)($it['qtd'] ?? 1);
    if ($pid <= 0 || $qtd <= 0 || $qtd > 20) {
        responder(['sucesso' => false, 'erro' => 'Item inválido no carrinho.'], 422);
    }
    $var = trim(($it['tamanho_escolhido'] ?? '') . ' ' . ($it['cor_escolhida'] ?? '')) ?: 'Padrão';
    $var = mb_substr($var, 0, 100);
    $k   = $pid . '|' . $var;
    if (isset($linhas[$k])) { $linhas[$k]['qtd'] += $qtd; }
    else { $linhas[$k] = ['id' => $pid, 'qtd' => $qtd, 'variacoes' => $var]; }
}

// ── GATEWAYS ─────────────────────────────────────────────
$mp_ok = false; $ps_ok = false;
if (is_file(__DIR__ . '/mercadopago.php')) {
    require_once __DIR__ . '/mercadopago.php';
    $mp_ok = defined('MP_ACCESS_TOKEN') && MP_ACCESS_TOKEN !== '' && strpos(MP_ACCESS_TOKEN, 'DEPOIS_COLOCAR') === false;
}
if (is_file(__DIR__ . '/pagseguro.php')) {
    require_once __DIR__ . '/pagseguro.php';
    $ps_ok = defined('PS_TOKEN') && PS_TOKEN !== '';
}
if (!$mp_ok && !$ps_ok) {
    responder(['sucesso' => false, 'erro' => 'Pagamento indisponível no momento. Tente novamente mais tarde.'], 503);
}

// ── CRIA O PEDIDO (transação: valida preço/estoque e baixa estoque) ──
$itens_validados = [];   // [{id, nome, preco, qtd, variacoes}]
$subtotal        = 0.0;

fin_garantir_schema($pdo);   // inclui as colunas do checkout (frete/cupom) e do financeiro

try {
    $pdo->beginTransaction();

    $stmtProd = $pdo->prepare("SELECT id, nome, preco, custo, estoque FROM produtos WHERE id = ? AND ativo = 1 FOR UPDATE");

    // Ordena por id para evitar deadlock entre pedidos concorrentes
    uasort($linhas, fn($a, $b) => $a['id'] <=> $b['id']);

    // Quantidade total por produto (o estoque é por produto, não por variação)
    $qtd_por_produto = [];
    foreach ($linhas as $l) { $qtd_por_produto[$l['id']] = ($qtd_por_produto[$l['id']] ?? 0) + $l['qtd']; }

    $produtos = [];
    foreach ($qtd_por_produto as $pid => $qtd_total) {
        $stmtProd->execute([$pid]);
        $p = $stmtProd->fetch(PDO::FETCH_ASSOC);
        if (!$p) {
            throw new RuntimeException("Um dos produtos do carrinho não está mais disponível.");
        }
        if ((int)$p['estoque'] < $qtd_total) {
            $disp = (int)$p['estoque'];
            throw new RuntimeException($disp > 0
                ? "Estoque insuficiente para \"{$p['nome']}\" (restam $disp)."
                : "\"{$p['nome']}\" está sem estoque.");
        }
        $produtos[$pid] = $p;
    }

    foreach ($linhas as $l) {
        $p     = $produtos[$l['id']];
        $preco = round((float)$p['preco'], 2);
        $subtotal += $preco * $l['qtd'];
        $itens_validados[] = [
            'id' => $l['id'], 'nome' => $p['nome'], 'preco' => $preco,
            'custo' => ($p['custo'] !== null && $p['custo'] !== '') ? round((float)$p['custo'], 2) : null,
            'qtd' => $l['qtd'], 'variacoes' => $l['variacoes'],
        ];
    }
    $subtotal = round($subtotal, 2);

    // Cupom: valida e reserva 1 uso (atômico). Se algo falhar abaixo, o rollback devolve o uso.
    $cupom_val = null;
    if ($cupom_codigo !== '') {
        $cupom_val = cupom_validar($pdo, $cupom_codigo, $subtotal);
        if (!$cupom_val['ok']) throw new RuntimeException($cupom_val['erro']);
        if (!cupom_consumir($pdo, (int)$cupom_val['cupom']['id'])) {
            throw new RuntimeException('Este cupom atingiu o limite de usos.');
        }
    }

    // Mesma função que gerou o resumo exibido ao cliente
    $qtd_pecas = 0; foreach ($itens_validados as $it) $qtd_pecas += (int)$it['qtd'];
    $t = loja_calcular_totais($pdo, $subtotal, $metodo, $cupom_val, $cep, $qtd_pecas, $frete_servico_pedido);
    if (!$t['frete_definido']) throw new RuntimeException($t['frete_erro'] ?: 'Não foi possível calcular o frete.');
    // O cliente precisa ter escolhido uma opção que o servidor realmente oferece para este CEP
    if ($frete_servico_pedido === '' || $t['frete_servico'] !== $frete_servico_pedido) {
        throw new RuntimeException('Escolha uma opção de entrega válida para o seu CEP.');
    }
    if ($t['erro'] !== '' || $t['total'] <= 0) throw new RuntimeException($t['erro'] ?: 'Total do pedido inválido.');

    $desconto       = $t['desconto_total'];   // cupom + PIX (relatórios somam esta coluna)
    $desconto_cupom = $t['desconto_cupom'];
    $frete          = $t['frete'];
    $total          = $t['total'];
    $cupom_gravar   = $cupom_val ? $cupom_val['cupom']['codigo'] : null;

    // Atualiza cadastro do cliente
    $pdo->prepare("UPDATE usuarios SET cpf = ?, telefone = ? WHERE id = ?")
        ->execute([$cpf, $telefone, $usuario_id]);

    // Endereço (um por usuário)
    $chk = $pdo->prepare("SELECT id FROM enderecos WHERE usuario_id = ?");
    $chk->execute([$usuario_id]);
    if ($chk->fetchColumn()) {
        $pdo->prepare("UPDATE enderecos SET cep=?,rua=?,numero=?,bairro=?,cidade=?,estado=? WHERE usuario_id=?")
            ->execute([$cep, $rua, $numero, $bairro, $cidade, $estado, $usuario_id]);
    } else {
        $pdo->prepare("INSERT INTO enderecos (usuario_id,cep,rua,numero,bairro,cidade,estado) VALUES (?,?,?,?,?,?,?)")
            ->execute([$usuario_id, $cep, $rua, $numero, $bairro, $cidade, $estado]);
    }

    $pdo->prepare("
        INSERT INTO pedidos
            (usuario_id, status, total, subtotal, desconto, desconto_cupom, frete, frete_servico, frete_prazo, transportadora, cupom_codigo, forma_pagamento,
             end_cep, end_rua, end_numero, end_bairro, end_cidade, end_estado,
             status_pagamento, data_pedido)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pendente',NOW())
    ")->execute([
        $usuario_id, 'pendente', $total, $subtotal, $desconto, $desconto_cupom, $frete, $t['frete_servico'], $t['prazo_dias'], $t['frete_transportadora'], $cupom_gravar, $metodo,
        $cep, $rua, $numero, $bairro, $cidade, $estado,
    ]);
    $pedido_id = (int)$pdo->lastInsertId();

    $tem_custo_col = in_array('custo_unitario', fin_colunas($pdo, 'itens_pedido'), true);
    $stmtItem = $pdo->prepare($tem_custo_col
        ? "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario, variacoes, custo_unitario) VALUES (?, ?, ?, ?, ?, ?)"
        : "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario, variacoes) VALUES (?, ?, ?, ?, ?)");
    foreach ($itens_validados as $it) {
        $args = [$pedido_id, $it['id'], $it['qtd'], $it['preco'], $it['variacoes']];
        if ($tem_custo_col) $args[] = $it['custo'];
        $stmtItem->execute($args);
    }

    // Baixa de estoque + movimentação
    $stmtBaixa = $pdo->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ?");
    $stmtMov   = $pdo->prepare("INSERT INTO estoque_movimentacoes (produto_id, tipo, quantidade, motivo, usuario_id) VALUES (?,?,?,?,?)");
    foreach ($qtd_por_produto as $pid => $q) {
        $stmtBaixa->execute([$q, $pid]);
        $stmtMov->execute([$pid, 'saida', $q, "pedido_$pedido_id", $usuario_id]);
    }

    $pdo->commit();
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    responder(['sucesso' => false, 'erro' => $e->getMessage()], 409);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("[PEDIDO ERROR] usuario=$usuario_id: " . $e->getMessage());
    responder(['sucesso' => false, 'erro' => 'Não foi possível criar o pedido. Tente novamente.'], 500);
}

// ── PAGAMENTO (MP → fallback PagSeguro) ──────────────────
$stmtU = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmtU->execute([$usuario_id]);
$usuario = $stmtU->fetch(PDO::FETCH_ASSOC);

$pedido_arr = ['id' => $pedido_id, 'total' => $total];
$end_arr    = ['cep' => $cep, 'rua' => $rua, 'numero' => $numero, 'bairro' => $bairro, 'cidade' => $cidade, 'estado' => $estado];
$carrinho_ps = array_map(fn($i) => ['nome' => $i['nome'], 'preco' => $i['preco'], 'qtd' => $i['qtd']], $itens_validados);

$resultado = null;     // resultado normalizado do gateway que atendeu
$gateway   = null;
$aprovado  = false;
$ultimo_erro = null;
$erro_recusa = null;   // recusa "de verdade" do cartão (não tenta o outro gateway)

// Tentativa 1: Mercado Pago
if ($mp_ok && ($metodo !== 'cartao' || $token_mp !== '')) {
    try {
        $r = match ($metodo) {
            'pix'    => MercadoPago::gerarPix($pedido_arr, $usuario),
            'boleto' => MercadoPago::gerarBoleto($pedido_arr, $usuario, $end_arr),
            'cartao' => MercadoPago::processarCartao($pedido_arr, $usuario, $token_mp, $parcelas, $issuer_id, $pm_id),
        };
        if (!empty($r['error'])) {
            $ultimo_erro = $r['message'] ?? 'Erro no Mercado Pago';
            error_log("[MP ERROR] pedido=$pedido_id: $ultimo_erro");
        } elseif ($metodo === 'cartao' && ($r['status'] ?? '') === 'rejected') {
            // Recusa pelo emissor: tentar outro gateway com o mesmo cartão não faz sentido
            $erro_recusa = MercadoPago::traduzirErroCartao($r['status_detail'] ?? '');
        } else {
            $resultado = $r; $gateway = 'mp';
            $aprovado  = ($metodo === 'cartao') && !empty($r['approved']);
        }
    } catch (Throwable $e) {
        $ultimo_erro = $e->getMessage();
        error_log("[MP EXCEPTION] pedido=$pedido_id: $ultimo_erro");
    }
}

// Tentativa 2: PagSeguro (fallback) — só se o MP falhou tecnicamente
if (!$resultado && !$erro_recusa && $ps_ok && ($metodo !== 'cartao' || $token_ps !== '')) {
    try {
        $r = match ($metodo) {
            'pix'    => PagSeguro::gerarPix($pedido_arr, $usuario, $end_arr, $carrinho_ps),
            'boleto' => PagSeguro::gerarBoleto($pedido_arr, $usuario, $end_arr, $carrinho_ps),
            'cartao' => PagSeguro::processarCartao($pedido_arr, $usuario, $end_arr, $carrinho_ps, $token_ps, $parcelas),
        };
        if (!empty($r['error'])) {
            $ultimo_erro = $r['message'] ?? 'Erro no PagSeguro';
            error_log("[PS ERROR] pedido=$pedido_id: $ultimo_erro");
        } elseif ($metodo === 'cartao' && empty($r['approved'])) {
            $erro_recusa = PagSeguro::traduzirErroCartao($r['status_detail'] ?? '');
        } else {
            $resultado = $r; $gateway = 'ps';
            $aprovado  = ($metodo === 'cartao') && !empty($r['approved']);
            // normaliza nomes de campos para o front
            if (isset($r['expiracao']) && !isset($r['expires_at'])) $resultado['expires_at'] = $r['expiracao'];
        }
    } catch (Throwable $e) {
        $ultimo_erro = $e->getMessage();
        error_log("[PS EXCEPTION] pedido=$pedido_id: $ultimo_erro");
    }
}

// ── FALHA: cancela pedido, devolve estoque e libera o cupom ──
if (!$resultado) {
    pedido_cancelar_devolvendo_estoque($pdo, $pedido_id, 'falha_pagamento: ' . mb_substr((string)($erro_recusa ?? $ultimo_erro), 0, 200));
    $msg_erro = $erro_recusa ?: 'Não foi possível processar o pagamento agora. Tente novamente ou use outra forma de pagamento.';
    // Em sandbox, mostra o motivo técnico para facilitar o diagnóstico
    $em_sandbox = (defined('PS_SANDBOX') && PS_SANDBOX) || (defined('MP_SANDBOX') && MP_SANDBOX);
    if ($em_sandbox && !$erro_recusa && $ultimo_erro) { $msg_erro .= ' [sandbox: ' . $ultimo_erro . ']'; }
    responder(['sucesso' => false, 'erro' => $msg_erro], 402);
}

// ── SUCESSO: registra referência do pagamento ────────────
$ref = ($gateway === 'mp' ? 'mp_payment_id:' : 'ps_order_id:') . ($resultado['payment_id'] ?? '');
if ($aprovado) {
    $pdo->prepare("UPDATE pedidos SET status='pago', status_pagamento='aprovado', data_pagamento=NOW(), observacoes=? WHERE id=?")
        ->execute([$ref, $pedido_id]);
} else {
    $pdo->prepare("UPDATE pedidos SET observacoes=? WHERE id=?")->execute([$ref, $pedido_id]);
}

// Custos variáveis gravados no pedido (o histórico não muda se as taxas forem alteradas depois)
try {
    $pdo->prepare("UPDATE pedidos SET gateway = ?, taxa_gateway = ?, imposto_valor = ? WHERE id = ?")
        ->execute([$gateway, fin_taxa_gateway($pdo, $gateway, $metodo, $total), fin_imposto($pdo, $total), $pedido_id]);
} catch (Throwable $e) {
    error_log("[FIN SNAPSHOT] pedido=$pedido_id: " . $e->getMessage());
}

$pdo->prepare("INSERT INTO logs_sistema (usuario_id, acao, tabela, registro_id, detalhes, ip) VALUES (?,?,?,?,?,?)")
    ->execute([
        $usuario_id, 'pedido_criado', 'pedidos', $pedido_id,
        "método: $metodo | total: R$ $total | gateway: $gateway" . ($aprovado ? ' | aprovado' : ''),
        $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

responder([
    'sucesso'    => true,
    'pedido_id'  => $pedido_id,
    'gateway'    => $gateway,
    'aprovado'   => $aprovado,
    'qr_code'    => $resultado['qr_code']    ?? null,
    'qr_base64'  => $resultado['qr_base64']  ?? null,
    'qr_img_url' => $resultado['qr_img_url'] ?? null,
    'expiracao'  => $resultado['expires_at'] ?? ($resultado['expiracao'] ?? null),
    'boleto_url' => $resultado['boleto_url'] ?? null,
    'barcode'    => $resultado['barcode']    ?? null,
    'vencimento' => $resultado['expires_at'] ?? ($resultado['vencimento'] ?? null),
]);
