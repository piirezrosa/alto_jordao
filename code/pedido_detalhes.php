<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php"); exit;
}

$pedido_id = (int)($_GET['id'] ?? 0);

// O pedido só é visível para o próprio dono
$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ? AND usuario_id = ?");
$stmt->execute([$pedido_id, $_SESSION['usuario_id']]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) { header("Location: meus_pedidos.php"); exit; }

$stmtI = $pdo->prepare("
    SELECT ip.*, p.nome AS produto_nome, p.imagem
    FROM itens_pedido ip
    JOIN produtos p ON p.id = ip.produto_id
    WHERE ip.pedido_id = ?
");
$stmtI->execute([$pedido_id]);
$itens = $stmtI->fetchAll(PDO::FETCH_ASSOC);

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function brl($v) { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }

$status = strtolower($pedido['status']);
function formatarStatusPedido($st) {
    $mapa = ['pendente' => ['Aguardando pagamento', 'wait'], 'aguardando' => ['Aguardando pagamento', 'wait'], 'pago' => ['Pago', 'ok'], 'aprovado' => ['Pago', 'ok'],
             'em_separacao' => ['Em separação', 'go'], 'enviado' => ['Enviado', 'go'], 'entregue' => ['Entregue', 'ok'], 'cancelado' => ['Cancelado', 'bad'], 'devolvido' => ['Devolvido', 'bad']];
    $k = strtolower((string)$st);
    [$r, $c] = $mapa[$k] ?? [ucfirst(str_replace('_', ' ', $k)), ''];
    return '<span class="aj-status ' . $c . '">' . htmlspecialchars($r) . '</span>';
}
$formas = ['pix' => 'PIX', 'cartao' => 'Cartão de Crédito', 'boleto' => 'Boleto'];
$forma  = $formas[$pedido['forma_pagamento']] ?? $pedido['forma_pagamento'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido #<?= str_pad($pedido['id'], 5, '0', STR_PAD_LEFT) ?> | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="aj-page aj-det">
<?php include 'header.php'; ?>

<main class="aj-wrap" style="max-width:860px">
    <section class="aj-pagehead">
        <h1>Pedido #<?= str_pad($pedido['id'], 5, '0', STR_PAD_LEFT) ?></h1>
    </section>
    <div style="height:24px"></div>

    <section class="det-card">
        <h3>Resumo</h3>
        <div class="det-grid">
            <div><span>Data</span><p><?= e(date('d/m/Y H:i', strtotime($pedido['data_pedido']))) ?></p></div>
            <div><span>Status</span><p><?= formatarStatusPedido($pedido['status']) ?></p></div>
            <div><span>Pagamento</span><p><?= e($forma) ?></p></div>
            <?php if (!empty($pedido['data_pagamento'])): ?>
            <div><span>Pago em</span><p><?= e(date('d/m/Y H:i', strtotime($pedido['data_pagamento']))) ?></p></div>
            <?php endif; ?>
        </div>
    </section>

    <section class="det-card">
        <h3>Itens</h3>
        <?php foreach ($itens as $it): ?>
            <div class="det-item">
                <?= exibirImagem($it['imagem']) ?>
                <div class="info">
                    <h4><?= e($it['produto_nome']) ?></h4>
                    <small><?= e($it['variacoes']) ?><?= $it['variacoes'] ? ', ' : '' ?><?= (int)$it['quantidade'] ?> x <?= brl($it['preco_unitario']) ?></small>
                </div>
                <strong><?= brl($it['preco_unitario'] * $it['quantidade']) ?></strong>
            </div>
        <?php endforeach; ?>

        <div class="det-totais" style="margin-top:18px;">
            <div class="linha"><span>Subtotal</span><span><?= brl($pedido['subtotal'] ?? $pedido['total']) ?></span></div>
            <?php $desc_cupom = (float)($pedido['desconto_cupom'] ?? 0);
                  $desc_pix   = max(0, (float)($pedido['desconto'] ?? 0) - $desc_cupom); ?>
            <?php if ($desc_cupom > 0): ?>
            <div class="linha"><span>Cupom <?= e($pedido['cupom_codigo'] ?? '') ?></span><span>- <?= brl($desc_cupom) ?></span></div>
            <?php endif; ?>
            <?php if ($desc_pix > 0): ?>
            <div class="linha"><span>Desconto PIX</span><span>- <?= brl($desc_pix) ?></span></div>
            <?php endif; ?>
            <div class="linha"><span>Frete</span>
                <?php if ((float)($pedido['frete'] ?? 0) > 0): ?><span><?= brl($pedido['frete']) ?></span>
                <?php else: ?><span style="color:var(--aj-ok);font-weight:700;">Grátis</span><?php endif; ?>
            </div>
            <div class="linha final"><span>Total</span><span><?= brl($pedido['total']) ?></span></div>
        </div>
    </section>

    <section class="det-card">
        <h3>Entrega</h3>
        <p style="margin:0;line-height:1.7;">
            <?= e($pedido['end_rua']) ?>, <?= e($pedido['end_numero']) ?><?= $pedido['end_bairro'] ? ' - ' . e($pedido['end_bairro']) : '' ?><br>
            <?= e($pedido['end_cidade']) ?> - <?= e($pedido['end_estado']) ?>, CEP <?= e(preg_replace('/^(\d{5})(\d{3})$/', '$1-$2', $pedido['end_cep'])) ?>
        </p>
    </section>

    <div class="det-acoes">
        <a class="aj-btn aj-btn--ghost" href="meus_pedidos.php">Voltar para meus pedidos</a>
        <?php if (in_array($status, ['pago', 'aprovado', 'entregue'], true)): ?>
            <a class="aj-btn" href="solicitar_devolucao.php?pedido_id=<?= (int)$pedido['id'] ?>">Troca ou devolução</a>
        <?php endif; ?>
    </div>
</main>

<?php include 'footer.php'; ?>
<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>
