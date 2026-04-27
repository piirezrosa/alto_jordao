<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php'; 

// SEGURANÇA: Bloqueia quem não é admin
if (!isset($_SESSION['usuario_nivel']) || $_SESSION['usuario_nivel'] !== 'admin') {
    header("Location: login.php?erro=acesso_negado");
    exit();
}

// BUSCA DE DADOS
try {
    $lucrototal = $pdo->query("SELECT SUM(total) FROM pedidos WHERE status = 'pago'")->fetchColumn() ?: 0;
    $totalPedidos = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status = 'pago'")->fetchColumn() ?: 1;
    $ticketMedio = $lucrototal / $totalPedidos;
    $produtosEsgotados = $pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque <= 0")->fetchColumn();
    $totalProdutos = $pdo->query("SELECT COUNT(*) FROM produtos")->fetchColumn();
    $stmt = $pdo->query("SELECT * FROM produtos ORDER BY id DESC");
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel Administrativo | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        /* Estilos para a visualização de variações na tabela */
        .variations-container { display: flex; flex-direction: column; gap: 8px; }
        
        .color-list { display: flex; gap: 4px; align-items: center; }
        .dot-color { 
            width: 12px; height: 12px; border-radius: 50%; 
            border: 1px solid #ddd; display: inline-block; 
        }

        .size-list { display: flex; gap: 4px; flex-wrap: wrap; }
        .mini-badge { 
            font-size: 9px; font-weight: 800; padding: 2px 6px; 
            background: #f0f0f0; border-radius: 4px; color: #666;
            text-transform: uppercase;
        }

        .stock-badge.critical { background: #ffebeb; color: #d93025; font-weight: 700; }
    </style>
</head>
<body class="admin-body">

    <?php include 'header.php'; ?>

    <div class="admin-main-container">
        <header class="admin-page-header">
            <div class="header-text">
                <h1>Gestão de Inventário</h1>
                <p>Controle de produtos e variações premium</p>
            </div>
            <a href="cadastrar_produto.php" class="btn-black-capsule">+ ADICIONAR NOVO ITEM</a>
        </header>

        <section class="admin-stats">
            <div class="stat-card">
                <span class="stat-label">Modelos Ativos</span>
                <span class="stat-value"><?= $totalProdutos ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Volume de Estoque</span>
                <span class="stat-value"><?= $estoqueTotal ?> <small>un.</small></span>
            </div>
        </section>

        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Variações (Cores/Tamanhos)</th>
                        <th>Preço</th>
                        <th>Estoque</th>
                        <th style="text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos as $p): ?>
                    <tr>
                        <td class="td-product">
                            <img src="img/produtos/<?= htmlspecialchars($p['imagem']) ?>" 
                                 onerror="this.src='img/produtos/placeholder.jpg'" alt="Capa">
                            <div class="product-info">
                                <strong><?= htmlspecialchars($p['nome']) ?></strong>
                                <span>ID #<?= $p['id'] ?></span>
                            </div>
                        </td>
                        <td>
                            <div class="variations-container">
                                <div class="color-list">
                                    <?php 
                                    if(!empty($p['cor'])){
                                        $c_list = explode(',', $p['cor']);
                                        foreach($c_list as $c) {
                                            $corHex = traduzirCor(trim($c)); 
                                            echo "<span class='dot-color' style='background:$corHex' title='".trim($c)."'></span>";
                                        }
                                    } else { echo "<small style='color:#ccc'>Sem cor</small>"; }
                                    ?>
                                </div>
                                <div class="size-list">
                                    <?php 
                                    if(!empty($p['tamanho'])){
                                        $t_list = explode(',', $p['tamanho']);
                                        foreach($t_list as $t) {
                                            echo "<span class='mini-badge'>".trim($t)."</span>";
                                        }
                                    } else { echo "<small style='color:#ccc'>Sem tam.</small>"; }
                                    ?>
                                </div>
                            </div>
                        </td>
                        <td><strong>R$ <?= number_format($p['preco'], 2, ',', '.') ?></strong></td>
                        <td>
                            <span class="stock-badge <?= $p['estoque'] < 5 ? 'critical' : '' ?>">
                                <?= $p['estoque'] ?> un.
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <a href="editar_produto.php?id=<?= $p['id'] ?>" class="edit-link">EDITAR</a>
                            <a href="excluir_produto.php?id=<?= $p['id'] ?>" class="delete-link" 
                               onclick="return confirm('Deseja realmente remover este item do catálogo?')">REMOVER</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>