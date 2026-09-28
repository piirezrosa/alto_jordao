<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php'; 

if (!isset($_SESSION['usuario_nivel']) || !in_array($_SESSION['usuario_nivel'], ['admin','superadmin','gerente'])) {
    header("Location: login.php"); exit();
}

try {
    $totalProdutos   = $pdo->query("SELECT COUNT(*) FROM produtos WHERE ativo=1")->fetchColumn();
    $estoqueTotal    = $pdo->query("SELECT SUM(estoque) FROM produtos WHERE ativo=1")->fetchColumn() ?: 0;
    $faturamento     = $pdo->query("SELECT SUM(total) FROM pedidos WHERE status='pago'")->fetchColumn() ?: 0;
    $vendasHoje      = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE DATE(data_pedido) = CURDATE()")->fetchColumn() ?: 0;
    $produtosRecentes= $pdo->query("SELECT * FROM produtos ORDER BY id DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
    $estoqueCritico  = $pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque<=3 AND ativo=1")->fetchColumn();
} catch (PDOException $e) {
    $faturamento = $vendasHoje = $totalProdutos = $estoqueTotal = $estoqueCritico = 0;
    $produtosRecentes = [];
}

$devolucoes_pend = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();
$p_pendente_sb   = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();

define('CONTEUDO_AUTORIZADO', true);
$pagina_atual = 'produtos';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos | Alto Jordão Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <style>
        .stock-tag {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .in-stock  { background: rgba(46,125,50,.10); color: var(--success); }
        .low-stock { background: rgba(245,158,11,.10); color: #b45309; }
        .out-stock { background: rgba(255,77,77,.10);  color: var(--danger); }

        .btn-edit {
            color: var(--black);
            text-decoration: none;
            font-weight: 800;
            font-size: 11px;
            text-transform: uppercase;
            border-bottom: 1px solid var(--black);
            transition: var(--transition);
        }
        .btn-edit:hover { opacity: .5; }

        .prod-thumb {
            width: 44px; height: 44px;
            border-radius: 10px;
            object-fit: cover;
            background: var(--grey-bg);
        }
    </style>
</head>
<body class="admin-page">

<?php include 'sidebar.php'; ?>

<main class="admin-main">
    <div class="admin-topbar">
        <div>
            <h1>Controle de Produtos</h1>
            <p>Performance e catálogo da Alto Jordão em tempo real.</p>
        </div>
        <div class="topbar-actions">
            <a href="cadastrar_produto.php" class="btn-admin-primary">+ Novo Produto</a>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card featured">
            <div class="kpi-icon">💵</div>
            <div class="kpi-label">Faturamento Total</div>
            <div class="kpi-value">R$ <?= number_format($faturamento,2,',','.') ?></div>
            <div class="kpi-sub">Pedidos pagos</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">🛒</div>
            <div class="kpi-label">Vendas Hoje</div>
            <div class="kpi-value"><?= $vendasHoje ?></div>
            <div class="kpi-sub">Pedidos do dia</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">👕</div>
            <div class="kpi-label">Modelos Ativos</div>
            <div class="kpi-value"><?= $totalProdutos ?></div>
            <div class="kpi-sub">No catálogo</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">📦</div>
            <div class="kpi-label">Total em Estoque</div>
            <div class="kpi-value"><?= number_format($estoqueTotal) ?> <span style="font-size:14px;color:var(--muted)">un.</span></div>
            <div class="kpi-sub"><?= $estoqueCritico > 0 ? '<span style="color:var(--danger);font-weight:700;">'.$estoqueCritico.' críticos</span>' : 'Estoque saudável' ?></div>
        </div>
    </div>

    <div class="admin-card">
        <div class="card-header">
            <span class="card-title">Produtos Recentes</span>
            <a href="cadastrar_produto.php" class="card-link">+ Adicionar →</a>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Produto</th>
                    <th>Preço</th>
                    <th>Estoque</th>
                    <th>Categoria</th>
                    <th style="text-align:right;">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($produtosRecentes as $p): 
                    $stock_class = $p['estoque'] <= 0 ? 'out-stock' : ($p['estoque'] <= 3 ? 'low-stock' : 'in-stock');
                    $stock_label = $p['estoque'] <= 0 ? 'Sem estoque' : $p['estoque'].' un.';
                ?>
                <tr>
                    <td><img class="prod-thumb" src="img/produtos/<?= htmlspecialchars($p['imagem']) ?>" onerror="this.style.opacity='.2'"></td>
                    <td style="font-weight:700;"><?= htmlspecialchars($p['nome']) ?></td>
                    <td style="font-weight:800;">R$ <?= number_format($p['preco'],2,',','.') ?></td>
                    <td><span class="stock-tag <?= $stock_class ?>"><?= $stock_label ?></span></td>
                    <td style="color:var(--text2); font-size:12px; text-transform:uppercase;"><?= htmlspecialchars($p['categoria']??'—') ?></td>
                    <td style="text-align:right;">
                        <a href="editar_produto.php?id=<?= $p['id'] ?>" class="btn-edit">Gerenciar →</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($produtosRecentes)): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:50px;">Nenhum produto cadastrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>