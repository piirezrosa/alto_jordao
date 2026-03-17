<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_nivel']) || $_SESSION['usuario_nivel'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Dados Estatísticos
$totalProdutos = $pdo->query("SELECT COUNT(*) FROM produtos")->fetchColumn();
$estoqueTotal = $pdo->query("SELECT SUM(estoque) FROM produtos")->fetchColumn() ?: 0;

// Busca Produtos
$produtos = $pdo->query("SELECT * FROM produtos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Admin | FashionShop</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background: #f8f9fa; }
        .admin-wrapper { padding: 40px 5%; max-width: 1400px; margin: 0 auto; }
        
        /* Cards de Status */
        .admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .stats-container { display: flex; gap: 20px; margin-bottom: 30px; }
        .stat-box { background: #fff; padding: 20px; border-radius: 10px; flex: 1; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .stat-box h3 { font-size: 12px; color: #888; margin-bottom: 5px; }
        .stat-box p { font-size: 24px; font-weight: bold; margin: 0; }

        /* Tabela */
        .table-card { background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #fcfcfc; padding: 15px; text-align: left; font-size: 12px; color: #aaa; text-transform: uppercase; border-bottom: 1px solid #eee; }
        td { padding: 15px; border-bottom: 1px solid #f9f9f9; vertical-align: middle; }
        
        .img-admin { width: 50px; height: 60px; object-fit: cover; border-radius: 4px; background: #eee; }
        
        /* Badges de Cores e Tamanhos */
        .mini-cor { width: 12px; height: 12px; border-radius: 50%; display: inline-block; border: 1px solid #ddd; margin-right: 3px; }
        .badge-tam { background: #f0f0f0; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; margin-right: 3px; }
        
        .btn-new { background: #000; color: #fff; padding: 12px 25px; border-radius: 6px; text-decoration: none; font-weight: bold; transition: 0.3s; }
        .btn-new:hover { background: #333; }
        .action-link { text-decoration: none; font-weight: bold; font-size: 13px; margin-right: 10px; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="admin-wrapper">
        <div class="admin-header">
            <h1>Gestão de Produtos</h1>
            <a href="cadastrar_produto.php" class="btn-new">+ Novo Item</a>
        </div>

        <div class="stats-container">
            <div class="stat-box"><h3>Produtos Ativos</h3><p><?= $totalProdutos ?></p></div>
            <div class="stat-box"><h3>Estoque Físico</h3><p><?= $estoqueTotal ?> <small style="font-size: 12px;">un.</small></p></div>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Variações</th>
                        <th>Categoria</th>
                        <th>Preço</th>
                        <th>Estoque</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos as $p): ?>
                    <tr>
                        <td style="display: flex; gap: 15px; align-items: center;">
                            <img src="img/produtos/<?= $p['imagem'] ?>" class="img-admin">
                            <div>
                                <strong style="display:block;"><?= htmlspecialchars($p['nome']) ?></strong>
                                <small style="color:#bbb;">ID #<?= $p['id'] ?></small>
                            </div>
                        </td>
                        <td>
                            <div style="margin-bottom: 5px;">
                                <?php 
                                if(!empty($p['cor'])){
                                    $c_list = explode(',', $p['cor']);
                                    foreach($c_list as $c) {
                                        $css = traduzirCor(trim($c));
                                        echo "<span class='mini-cor' style='background:$css' title='".trim($c)."'></span>";
                                    }
                                }
                                ?>
                            </div>
                            <?php 
                            if(!empty($p['tamanho'])){
                                $t_list = explode(',', $p['tamanho']);
                                foreach($t_list as $t) echo "<span class='badge-tam'>".trim($t)."</span>";
                            }
                            ?>
                        </td>
                        <td><span style="font-size: 12px; color: #666;"><?= $p['categoria'] ?></span></td>
                        <td><strong>R$ <?= number_format($p['preco'], 2, ',', '.') ?></strong></td>
                        <td>
                            <span style="font-weight: bold; color: <?= $p['estoque'] < 5 ? 'red' : 'inherit' ?>">
                                <?= $p['estoque'] ?>
                            </span>
                        </td>
                        <td>
                            <a href="editar_produto.php?id=<?= $p['id'] ?>" class="action-link" style="color: #f39c12;">Editar</a>
                            <a href="excluir_produto.php?id=<?= $p['id'] ?>" class="action-link" style="color: #e74c3c;" onclick="return confirm('Excluir permanentemente?')">Excluir</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>