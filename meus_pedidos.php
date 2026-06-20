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

// Função para formatar o status com cores
function formatarStatus($status) {
    $status = strtolower($status);
    switch ($status) {
        case 'pago': case 'aprovado': case 'entregue':
            return '<span style="color: #27ae60; font-weight: 800;">● ' . strtoupper($status) . '</span>';
        case 'pendente': case 'aguardando':
            return '<span style="color: #f1c40f; font-weight: 800;">● PENDENTE</span>';
        case 'cancelado':
            return '<span style="color: #e74c3c; font-weight: 800;">● CANCELADO</span>';
        default:
            return '<span style="color: #888; font-weight: 800;">● ' . strtoupper($status) . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Pedidos | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        .orders-container { max-width: 1000px; margin: 60px auto; padding: 0 20px; min-height: 60vh; }
        .page-title { text-align: center; margin-bottom: 50px; }
        .page-title h1 { font-weight: 900; text-transform: uppercase; letter-spacing: -1px; margin-top: 5px; }
        
        .order-card {
            background: #fff;
            border: 1px solid #eee;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            align-items: center;
            transition: 0.3s;
        }
        .order-card:hover { border-color: #000; box-shadow: 0 10px 40px rgba(0,0,0,0.05); }

        .order-info span { display: block; font-size: 10px; color: #bbb; font-weight: 800; text-transform: uppercase; margin-bottom: 5px; letter-spacing: 1px; }
        .order-info p { font-weight: 800; font-size: 15px; margin: 0; color: #1a1a1a; }

        .actions-area {
            grid-column: span 4;
            display: flex;
            gap: 15px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #f8f8f8;
        }

        .btn-detail {
            flex: 2;
            background: #000; color: #fff; text-decoration: none; padding: 15px;
            border-radius: 50px; font-size: 11px; font-weight: 800; text-align: center;
            text-transform: uppercase; transition: 0.3s; letter-spacing: 1px;
        }
        .btn-detail:hover { background: #333; transform: translateY(-2px); }

        .btn-return {
            flex: 1;
            background: #fff; color: #ff4d4d; text-decoration: none; padding: 15px;
            border-radius: 50px; font-size: 11px; font-weight: 800; text-align: center;
            text-transform: uppercase; transition: 0.3s; border: 1.5px solid #ff4d4d;
        }
        .btn-return:hover { background: #ff4d4d; color: #fff; }

        @media (max-width: 768px) {
            .order-card { grid-template-columns: 1fr 1fr; gap: 20px; }
            .actions-area { grid-column: span 2; flex-direction: column; }
        }
    </style>
</head>
<body class="bg-light">

    <?php include 'header.php'; ?>

    <main class="orders-container">
        <div class="page-title">
            <span style="letter-spacing: 5px; color: #bbb; font-weight: 800; font-size: 10px; text-transform: uppercase;">Customer Experience</span>
            <h1>Minhas Compras</h1>
        </div>

        <?php if (count($pedidos) > 0): ?>
            <?php foreach ($pedidos as $pedido): ?>
                <div class="order-card">
                    <div class="order-info">
                        <span>Identificação</span>
                        <p>#<?= str_pad($pedido['id'], 5, "0", STR_PAD_LEFT) ?></p>
                    </div>
                    <div class="order-info">
                        <span>Data do Pedido</span>
                        <p><?= date('d/m/Y', strtotime($pedido['data_pedido'])) ?></p>
                    </div>
                    <div class="order-info">
                        <span>Status Atual</span>
                        <p><?= formatarStatus($pedido['status']) ?></p>
                    </div>
                    <div class="order-info" style="text-align: right;">
                        <span>Valor Total</span>
                        <p>R$ <?= number_format($pedido['total'], 2, ',', '.') ?></p>
                    </div>

                    <div class="actions-area">
                        <a href="pedido_detalhes.php?id=<?= $pedido['id'] ?>" class="btn-detail">Ver Detalhes do Pedido</a>
                        
                        <?php if (in_array(strtolower($pedido['status']), ['entregue', 'pago', 'aprovado'])): ?>
                            <a href="solicitar_devolucao.php?pedido_id=<?= $pedido['id'] ?>" class="btn-return">
                                Troca ou Devolução
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 80px 20px;">
                <div style="font-size: 60px; margin-bottom: 20px;">📦</div>
                <h2 style="font-weight: 900; text-transform: uppercase; letter-spacing: -1px;">Nenhum pedido por aqui</h2>
                <p style="color: #888; margin-bottom: 30px; font-size: 14px;">Sua sacola está vazia. Que tal conferir as novidades?</p>
                <a href="index.php" style="background: #000; color: #fff; padding: 18px 40px; border-radius: 50px; text-decoration: none; font-weight: 900; font-size: 11px; letter-spacing: 1px;">EXPLORAR LOJA</a>
            </div>
        <?php endif; ?>
    </main>

    <footer style="background: #000; color: #fff; padding: 60px 20px; text-align: center; margin-top: 100px;">
        <p style="font-size: 10px; opacity: 0.4; letter-spacing: 2px; font-weight: 700;">ALTO JORDÃO ORIGINALS &copy; 2026 — TODOS OS DIREITOS RESERVADOS</p>
    </footer>

</body>
</html>