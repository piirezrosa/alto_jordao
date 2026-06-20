<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// Busca os pedidos do usuário logado
$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE usuario_id = ? ORDER BY data_pedido DESC");
$stmt->execute([$usuario_id]);
$pedidos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Meus Pedidos | Alto Jordão</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="user-full-wrapper">
        <main class="user-main-wide">
            <section class="user-content-section-wide">
                <h2>📦 Meus Pedidos</h2>
                
                <?php if (count($pedidos) > 0): ?>
                    <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                        <thead>
                            <tr style="text-align: left; border-bottom: 2px solid #eee;">
                                <th style="padding: 10px;">ID</th>
                                <th>Data</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pedidos as $p): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 15px;">#<?= $p['id'] ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($p['data_pedido'])) ?></td>
                                    <td>R$ <?= number_format($p['total'], 2, ',', '.') ?></td>
                                    <td><strong><?= $p['status'] ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; color: #888; margin-top: 30px;">Você ainda não realizou nenhum pedido.</p>
                <?php endif; ?>

                <div style="margin-top: 30px;">
                    <a href="usuario.php" style="text-decoration: none; color: #000; font-weight: bold;">← VOLTAR AO PERFIL</a>
                </div>
            </section>
        </main>
    </div>
</body>
</html>