<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'mercadopago.php';

header('Content-Type: application/json');

if (!isset($_POST['usuario_id'])){
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
$metodo = $dados['metodo'] ?? 'pix';
$carrinho = $dados['carrinho'];

// BUSCAR DADOS DO USUÁRIO
$usuario = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$usuario->execute([$usuario_id]);
$usuario = $usuario->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    echo json_encode(['sucesso' => false, 'erro' => 'Usuário não encontrado.']);
    exit;
}

// Atualiza o CPF e telefone se vieram do formulário
if (!empty($dados['cpf'])) {
    $pdo->prepare("UPDATE usuarios SET cpf = ?, telefone = ? WHERE id = ?")
        ->execute([$dados['cpf'], $dados['telefone'], $usuario_id]);
         $usuario['cpf'] = $dados['cpf'];
}

// Salvar / Atualizar endereço
$end = [
    'cep' => $dados['cep'] ?? '',
    'rua' => $dados['rua'] ?? '',
    'numero' => $dados['numero'] ?? '',
    'bairro' => $dados['bairro'] ?? '',
    'cidade' => $dados['cidade'] ?? '',
    'estado' => $dados['estado'] ?? '',
];

$checkEnd = $pdo->prepare("SELECT id FROM enderecos WHERE usuario_id = ?");
$checkEnd->execute([$usuario_id]);

if ($checkEnd->fetchColumn()) {
    $pdo->prepare("UPDATE enderecos SET cep = ?, rua = ?, numero = ?, bairro = ?, cidade = ?, estado = ? WHERE usuario_id = ?")
        ->execute([$end['cep'], $end['rua'], $end['numero'], $end['bairro'], $end['cidade'], $end['estado'], $usuario_id]);
} else {
    $pdo->prepare("INSERT INTO enderecos (usuario_id, cep, rua, numero, bairro, cidade, estado) VALUES (?, ?, ?, ?, ?, ?, ?)")
        ->execute([$usuario_id, $end['cep'], $end['rua'], $end['numero'], $end['bairro'], $end['cidade'], $end['estado']]);
}

// Calcular total
$subtotal = 0;
foreach ($carrinho as $item) {
    $subtotal += (float)$item['preco'] * (int)($item['qtd'] ?? 1);
}

// Desconto de 5% para pagamentos via PIX
$desconto = ($metodo === 'pix') ? round($subtotal * 0.05, 2) : 0;
$total = round($subtotal - $desconto, 2);

try {
    $pdo->beginTransaction();

    // Criar pedido no banco (status inicial: pendente)
    $pdo->prepare("INSERT INTO pedidos (usuario_id, status, total, subtotal, desconto, forma_pagamento, end_cep, end_rua, end_numero, end_bairro, end_cidade, end_estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$usuario_id, 'pendente', $total, $subtotal, $desconto, $metodo, $end['cep'], $end['rua'], $end['numero'], $end['bairro'], $end['cidade'], $end['estado']]);

    $pedido_id = $pdo->lastInsertId();

    // Inserir itens do pedido
    $stmtItem = $pdo->prepare("INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario, variacoes) VALUES (?, ?, ?, ?, ?)");

    foreach ($carrinho as $item) {
        $vars = ($item['tamanho_escolhido'] ?? 'Único') . ' | ' . ($item['cor_escolhida'] ?? 'Padrão');
        $stmtItem->execute([$pedido_id, (int)$item['id'], (int)$item['qtd'] ?? 1, (float)$item['preco'], $vars]);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao processar pedido: ' . $e->getMessage()]);
    exit;
}

// Chamar API do Mercado Pago
$pedido_arr = ['id' => $pedido_id, 'total' => $total];
$mp_result = [];

switch ($metodo) {
    case 'pix':
        $mp_result = MercadoPago::gerarPix($pedido_arr, $usuario);
        break;
    
    case 'boleto':
        $mp_result = MercadoPago::gerarBoleto($pedido_arr, $usuario, $end);
        break;

    case 'cartao':
        if (empty($dados['token'])) {
            // Reverte pedido criado se não houver token de cartão
            $pdo->prepare("DELETE FROM itens_pedido WHERE pedido_id = ?")->execute([$pedido_id]);
            $pdo->prepare("DELETE FROM pedidos WHERE id = ?")->execute([$pedido_id]);
            echo json_encode(['sucesso' => false, 'erro' => 'Token de cartão não fornecido.']);
            exit;
        }
        $mp_result = MercadoPago::gerarCartao($pedido_arr, $usuario, $dados['token'], (int)($dados['parcelas'] ?? 1), (string)($dados['issuer_id'] ?? ''), (string)($dados['payment_method_id'] ?? ''));
        break;

    default:
        echo json_encode(['sucesso' => false, 'erro' => 'Método de pagamento inválido.']);
        exit;
}

// Checar erro da API do Mercado Pago
if (!empty($mp_result['erro'])) {
    // Mantém o pedido como "pendente" mas informa o erro
    error_log("[MP ERROR] Pedido $pedido_id: " . ($mp_result['message'] ?? 'Erro desconhecido'));
    echo json_encode(['sucesso' => false, 'erro' => 'Erro no gateway de pagamento: ' . ($mp_result['message'] ?? 'Tente novamente mais tarde.'), 'pedido_id' => $pedido_id]);
    exit;
}

// Salvar payment_id e atualizar status do cartão
$payment_id = $mp_result['payment_id'] ?? null;
$status_mp = $mp_result['status'] ?? 'pending';

// Cartão aprovado na hora -> atualiza status do pedido para "aprovado"
if ($metodo === 'cartao' && $status_mp === 'approved') {
    $pdo->prepare("UPDATE pedidos SET status = 'pago', status_pagamento = 'aprovado', data_pagamento = NOW() WHERE id = ?")->execute([$pedido_id]);
} elseif ($metodo === 'cartao' && $status_mp === 'rejected') {
    // Cartão recusado -> informa mensagem amigável
    $msg_erro = MercadoPago::traduzirErroCartao($mp_result['status_detail'] ?? '');
    echo json_encode(['sucesso' => false, 'erro' => $msg_erro, 'pedido_id' => $pedido_id]);
    exit;
}

// Salva o payment_id no campo de observações para o webhook poder localizar o pedido
if ($payment_id) {
    $pdo->prepare("UPDATE pedidos SET observacoes = ? WHERE id = ?")->execute(["mp_payment_id:$payment_id", $pedido_id]);
}

// ── LOG ───────────────────────────────────────────────────
$pdo->prepare("INSERT INTO logs_sistema (usuario_id, acao, tabela, registro_id, detalhes, ip) VALUES (?,?,?,?,?,?)")
    ->execute([
        $usuario_id, 'pedido_criado', 'pedidos', $pedido_id,
        "Método: $metodo | MP payment_id: $payment_id | Status: $status_mp",
        $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

// ── RESPOSTA DE SUCESSO ───────────────────────────────────
$resposta = [
    'sucesso'    => true,
    'pedido_id'  => $pedido_id,
    'metodo'     => $metodo,
    'payment_id' => $payment_id,
];

// Dados extras por método (para a tela de confirmação exibir)
if ($metodo === 'pix') {
    $resposta['qr_code']   = $mp_result['qr_code']   ?? null;
    $resposta['qr_base64'] = $mp_result['qr_base64'] ?? null;
    $resposta['expiracao'] = $mp_result['expiracao']  ?? null;
}

if ($metodo === 'boleto') {
    $resposta['boleto_url'] = $mp_result['boleto_url'] ?? null;
    $resposta['barcode']    = $mp_result['barcode']    ?? null;
    $resposta['vencimento'] = $mp_result['vencimento'] ?? null;
}

echo json_encode($resposta);
?>