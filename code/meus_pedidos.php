<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Segurança: Se não estiver logado, vai para o login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Busca os pedidos do usuário logado
$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE usuario_id = ? ORDER BY data_pedido DESC");
$stmt->execute([$usuario_id]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Rótulo e cor do status do pedido
function formatarStatus($status) {
    $mapa = [
        'pendente'     => ['Aguardando pagamento', 'wait'],
        'aguardando'   => ['Aguardando pagamento', 'wait'],
        'pago'         => ['Pago', 'ok'],
        'aprovado'     => ['Pago', 'ok'],
        'em_separacao' => ['Em separação', 'go'],
        'enviado'      => ['Enviado', 'go'],
        'entregue'     => ['Entregue', 'ok'],
        'cancelado'    => ['Cancelado', 'bad'],
        'devolvido'    => ['Devolvido', 'bad'],
    ];
    $k = strtolower((string)$status);
    [$rotulo, $cls] = $mapa[$k] ?? [ucfirst(str_replace('_', ' ', $k)), ''];
    return '<span class="aj-status ' . $cls . '">' . htmlspecialchars($rotulo) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus pedidos | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="aj-page">

<?php include 'header.php'; ?>

<main class="aj-wrap">
    <section class="aj-pagehead">
        <h1>Meus pedidos</h1>
        <p>Acompanhe o status das suas compras e peça troca ou devolução.</p>
    </section>

    <div class="aj-account">
        <nav class="aj-side" aria-label="Minha conta">
            <a href="usuario.php">Meus dados</a>
            <a class="active" href="meus_pedidos.php">Meus pedidos</a>
            <a href="favoritos.php">Favoritos</a>
            <a class="sair" href="logout.php">Sair da conta</a>
        </nav>

        <div>
        <?php if (count($pedidos) > 0): ?>
            <?php foreach ($pedidos as $pedido): ?>
                <article class="aj-order">
                    <div class="aj-order-top">
                        <div><small>Pedido</small><b>#<?= str_pad($pedido['id'], 5, "0", STR_PAD_LEFT) ?></b></div>
                        <div><small>Data</small><b><?= date('d/m/Y', strtotime($pedido['data_pedido'])) ?></b></div>
                        <div><small>Status</small><?= formatarStatus($pedido['status']) ?></div>
                        <div class="tot"><small>Total</small><b>R$ <?= number_format((float)$pedido['total'], 2, ',', '.') ?></b></div>
                    </div>
                    <div class="aj-order-actions">
                        <a href="pedido_detalhes.php?id=<?= (int)$pedido['id'] ?>" class="aj-btn">Ver detalhes</a>
                        <?php if (in_array(strtolower($pedido['status']), ['entregue', 'pago', 'aprovado'])): ?>
                            <a href="solicitar_devolucao.php?pedido_id=<?= (int)$pedido['id'] ?>" class="aj-btn aj-btn--ghost">Troca ou devolução</a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="aj-emptypage">
                <h2>Você ainda não fez pedidos</h2>
                <p>Quando comprar, o acompanhamento aparece aqui.</p>
                <a class="aj-btn" href="lancamentos.php">Ver lançamentos</a>
            </div>
        <?php endif; ?>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>
