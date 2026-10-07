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
$cores  = ['pago' => '#27ae60', 'entregue' => '#27ae60', 'aprovado' => '#27ae60',
           'pendente' => '#f1c40f', 'cancelado' => '#e74c3c'];
$cor    = $cores[$status] ?? '#888';
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family:'Inter',sans-serif; background:#fcfcfc; margin:0; }
        .det-container { max-width:820px; margin:50px auto 100px; padding:0 20px; }
        .det-container h1 { font-size:2rem; font-weight:900; letter-spacing:-1px; margin:6px 0 24px; }
        .det-eyebrow { letter-spacing:5px; color:#bbb; font-weight:800; font-size:10px; text-transform:uppercase; }
        .det-card { background:#fff; border:1px solid #eee; border-radius:20px; padding:28px; margin-bottom:20px; }
        .det-card h3 { font-size:12px; font-weight:900; text-transform:uppercase; letter-spacing:1px; margin:0 0 18px; color:#888; }
        .det-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:18px; }
        .det-grid span { display:block; font-size:10px; font-weight:800; text-transform:uppercase; color:#aaa; letter-spacing:1px; }
        .det-grid p { margin:4px 0 0; font-weight:700; }
        .det-item { display:flex; gap:16px; align-items:center; padding:14px 0; border-bottom:1px solid #f2f2f2; }
        .det-item:last-child { border-bottom:0; }
        .det-item img { width:64px; height:80px; object-fit:cover; border-radius:10px; background:#f3f3f3; }
        .det-item .info { flex:1; }
        .det-item .info h4 { margin:0 0 4px; font-size:14px; }
        .det-item .info small { color:#888; }
        .det-totais .linha { display:flex; justify-content:space-between; padding:6px 0; color:#666; }
        .det-totais .final { border-top:1px solid #eee; margin-top:8px; padding-top:14px; font-weight:900; font-size:18px; color:#000; }
        .det-acoes { display:flex; gap:12px; flex-wrap:wrap; margin-top:10px; }
        .det-acoes a { padding:12px 22px; border-radius:50px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1px; text-decoration:none; border:1px solid #000; color:#000; }
        .det-acoes a.primario { background:#000; color:#fff; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<main class="det-container">
    <span class="det-eyebrow">Detalhes da compra</span>
    <h1>Pedido #<?= str_pad($pedido['id'], 5, '0', STR_PAD_LEFT) ?></h1>

    <section class="det-card">
        <h3>Resumo</h3>
        <div class="det-grid">
            <div><span>Data</span><p><?= e(date('d/m/Y H:i', strtotime($pedido['data_pedido']))) ?></p></div>
            <div><span>Status</span><p style="color:<?= $cor ?>">● <?= e(strtoupper($pedido['status'])) ?></p></div>
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
                    <small><?= e($it['variacoes']) ?> · <?= (int)$it['quantidade'] ?>x <?= brl($it['preco_unitario']) ?></small>
                </div>
                <strong><?= brl($it['preco_unitario'] * $it['quantidade']) ?></strong>
            </div>
        <?php endforeach; ?>

        <div class="det-totais" style="margin-top:18px;">
            <div class="linha"><span>Subtotal</span><span><?= brl($pedido['subtotal'] ?? $pedido['total']) ?></span></div>
            <?php if ((float)($pedido['desconto'] ?? 0) > 0): ?>
            <div class="linha"><span>Desconto</span><span>- <?= brl($pedido['desconto']) ?></span></div>
            <?php endif; ?>
            <div class="linha"><span>Frete</span><span style="color:#27ae60;font-weight:800;">GRÁTIS</span></div>
            <div class="linha final"><span>Total</span><span><?= brl($pedido['total']) ?></span></div>
        </div>
    </section>

    <section class="det-card">
        <h3>Entrega</h3>
        <p style="margin:0;line-height:1.7;">
            <?= e($pedido['end_rua']) ?>, <?= e($pedido['end_numero']) ?><?= $pedido['end_bairro'] ? ' — ' . e($pedido['end_bairro']) : '' ?><br>
            <?= e($pedido['end_cidade']) ?>/<?= e($pedido['end_estado']) ?> · CEP <?= e(preg_replace('/^(\d{5})(\d{3})$/', '$1-$2', $pedido['end_cep'])) ?>
        </p>
    </section>

    <div class="det-acoes">
        <a href="meus_pedidos.php">← Meus pedidos</a>
        <?php if (in_array($status, ['pago', 'aprovado', 'entregue'], true)): ?>
            <a class="primario" href="solicitar_devolucao.php?pedido_id=<?= (int)$pedido['id'] ?>">Troca ou devolução</a>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
