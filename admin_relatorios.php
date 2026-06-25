<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

if (!isset($_SESSION['usuario_nivel']) || !in_array($_SESSION['usuario_nivel'], ['admin','superadmin','gerente'])) {
    header("Location: login.php"); exit();
}

$periodo    = $_GET['periodo']    ?? '30';
$data_ini   = $_GET['data_ini']   ?? date('Y-m-d', strtotime("-{$periodo} days"));
$data_fim   = $_GET['data_fim']   ?? date('Y-m-d');
$tipo       = $_GET['tipo']       ?? 'vendas';

// ── RELATÓRIO DE VENDAS ───────────────────────
$vendas_periodo = $pdo->prepare("
    SELECT DATE(data_pedido) as dia, COUNT(*) as pedidos, SUM(total) as receita
    FROM pedidos WHERE status IN('pago','enviado','entregue')
    AND DATE(data_pedido) BETWEEN ? AND ?
    GROUP BY DATE(data_pedido) ORDER BY dia ASC
");
$vendas_periodo->execute([$data_ini, $data_fim]);
$vendas_periodo = $vendas_periodo->fetchAll(PDO::FETCH_ASSOC);

// ── TOP PRODUTOS ──────────────────────────────
$top_prod = $pdo->prepare("
    SELECT p.nome, p.imagem, p.categoria,
           SUM(ip.quantidade) as qtd_vendida,
           SUM(ip.quantidade * ip.preco_unitario) as receita,
           SUM(ip.quantidade * COALESCE(ip.custo_unitario, p.custo, 0)) as custo_total
    FROM itens_pedido ip
    JOIN produtos p ON ip.produto_id = p.id
    JOIN pedidos ped ON ip.pedido_id = ped.id
    WHERE ped.status IN('pago','enviado','entregue')
    AND DATE(ped.data_pedido) BETWEEN ? AND ?
    GROUP BY ip.produto_id ORDER BY qtd_vendida DESC LIMIT 10
");
$top_prod->execute([$data_ini, $data_fim]);
$top_prod = $top_prod->fetchAll(PDO::FETCH_ASSOC);

// ── RELATÓRIO DE CLIENTES ─────────────────────
$top_clientes = $pdo->prepare("
    SELECT u.nome, u.email, COUNT(ped.id) as total_pedidos,
           SUM(ped.total) as total_gasto,
           MAX(ped.data_pedido) as ultima_compra
    FROM pedidos ped JOIN usuarios u ON ped.usuario_id = u.id
    WHERE ped.status IN('pago','enviado','entregue')
    AND DATE(ped.data_pedido) BETWEEN ? AND ?
    GROUP BY ped.usuario_id ORDER BY total_gasto DESC LIMIT 10
");
$top_clientes->execute([$data_ini, $data_fim]);
$top_clientes = $top_clientes->fetchAll(PDO::FETCH_ASSOC);

// ── MÉTRICAS GERAIS ───────────────────────────
$receita_total  = array_sum(array_column($vendas_periodo, 'receita'));
$pedidos_total  = array_sum(array_column($vendas_periodo, 'pedidos'));
$ticket_medio   = $pedidos_total > 0 ? $receita_total / $pedidos_total : 0;
$lucro_total    = array_sum(array_map(fn($r) => $r['receita'] - $r['custo_total'], $top_prod));
$dias_no_periodo= max(1, (strtotime($data_fim) - strtotime($data_ini)) / 86400 + 1);
$media_diaria   = $receita_total / $dias_no_periodo;

// ── RELATÓRIO POR CATEGORIA ───────────────────
$por_categoria = $pdo->prepare("
    SELECT p.categoria, SUM(ip.quantidade) as qtd, SUM(ip.quantidade * ip.preco_unitario) as receita
    FROM itens_pedido ip
    JOIN produtos p ON ip.produto_id = p.id
    JOIN pedidos ped ON ip.pedido_id = ped.id
    WHERE ped.status IN('pago','enviado','entregue')
    AND DATE(ped.data_pedido) BETWEEN ? AND ?
    GROUP BY p.categoria ORDER BY receita DESC
");
$por_categoria->execute([$data_ini, $data_fim]);
$por_categoria = $por_categoria->fetchAll(PDO::FETCH_ASSOC);

// Badges sidebar
$devolucoes_pend = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();
$estoque_critico = $pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque<=3 AND ativo=1")->fetchColumn();
$p_pendente_sb   = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();

$graf_labels = json_encode(array_map(fn($r) => date('d/m', strtotime($r['dia'])), $vendas_periodo));
$graf_receita= json_encode(array_map(fn($r) => round($r['receita'],2), $vendas_periodo));
$graf_pedidos= json_encode(array_map(fn($r) => (int)$r['pedidos'], $vendas_periodo));

define('CONTEUDO_AUTORIZADO', true);
$pagina_atual = 'relatorios';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios | Alto Jordão Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .report-tabs { display:flex; gap:8px; margin-bottom:22px; flex-wrap:wrap; }

        .tab-btn {
            padding:9px 20px; border-radius:50px; border:1px solid var(--border);
            background:var(--white); color:var(--text2); font-size:12px; font-weight:700;
            cursor:pointer; text-decoration:none; transition:var(--transition);
        }
        .tab-btn:hover { background:var(--grey-bg); color:var(--black); }
        .tab-btn.active { background:var(--black); color:var(--white); border-color:var(--black); }

        .filter-bar {
            background:var(--white); border:1px solid var(--border); border-radius:30px;
            padding:18px 26px; display:flex; gap:14px; align-items:flex-end;
            margin-bottom:22px; flex-wrap:wrap; box-shadow:var(--shadow);
        }
        .filter-group { display:flex; flex-direction:column; gap:6px; }
        .filter-group label { font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1px; }
        .filter-group input, .filter-group select {
            background:var(--grey-bg); border:1px solid var(--border); border-radius:50px;
            color:var(--black); padding:10px 16px; font-family:var(--font-main); font-size:13px; outline:none;
        }

        .two-col { display:grid; grid-template-columns:1.6fr 1fr; gap:20px; margin-bottom:22px; }

        .rank-row {
            display:flex; align-items:center; gap:12px;
            padding:12px 0; border-bottom:1px solid var(--border);
        }
        .rank-row:last-child { border-bottom:none; }
        .rank-n { width:22px; font-weight:900; color:var(--muted); font-size:16px; flex-shrink:0; }
        .rank-1 .rank-n { color:var(--black); }
        .rank-img { width:40px; height:40px; border-radius:10px; background:var(--grey-bg); object-fit:cover; flex-shrink:0; }
        .rank-info { flex:1; min-width:0; }
        .rank-name { font-weight:700; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .rank-sub  { font-size:11px; color:var(--text2); margin-top:2px; }
        .rank-val  { font-weight:900; font-size:13px; white-space:nowrap; }

        .cat-bar { margin-bottom:14px; }
        .cat-bar:last-child { margin-bottom:0; }
        .cat-row { display:flex; justify-content:space-between; font-size:12px; margin-bottom:5px; }
        .cat-name { font-weight:700; text-transform:uppercase; }
        .cat-val  { font-weight:800; }
    </style>
</head>
<body class="admin-page">

<?php include 'sidebar.php'; ?>

<main class="admin-main">
    <div class="admin-topbar">
        <div>
            <h1>Relatórios</h1>
            <p>Análise completa de vendas, produtos e clientes.</p>
        </div>
    </div>

    <!-- FILTROS -->
    <form method="GET" class="filter-bar">
        <input type="hidden" name="tipo" value="<?= $tipo ?>">
        <div class="filter-group">
            <label>Período rápido</label>
            <select name="periodo" onchange="this.form.submit()">
                <option value="7"   <?= $periodo=='7'  ?'selected':''?>>Últimos 7 dias</option>
                <option value="30"  <?= $periodo=='30' ?'selected':''?>>Últimos 30 dias</option>
                <option value="90"  <?= $periodo=='90' ?'selected':''?>>Últimos 3 meses</option>
                <option value="365" <?= $periodo=='365'?'selected':''?>>Último ano</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Data Início</label>
            <input type="date" name="data_ini" value="<?= $data_ini ?>">
        </div>
        <div class="filter-group">
            <label>Data Fim</label>
            <input type="date" name="data_fim" value="<?= $data_fim ?>">
        </div>
        <button type="submit" class="btn-admin-primary">Aplicar</button>
    </form>

    <!-- TABS DE TIPO -->
    <div class="report-tabs">
        <a href="?tipo=vendas&data_ini=<?= $data_ini ?>&data_fim=<?= $data_fim ?>"    class="tab-btn <?= $tipo==='vendas'   ?'active':''?>">📊 Vendas</a>
        <a href="?tipo=produtos&data_ini=<?= $data_ini ?>&data_fim=<?= $data_fim ?>"  class="tab-btn <?= $tipo==='produtos' ?'active':''?>">👕 Produtos</a>
        <a href="?tipo=clientes&data_ini=<?= $data_ini ?>&data_fim=<?= $data_fim ?>"  class="tab-btn <?= $tipo==='clientes' ?'active':''?>">👥 Clientes</a>
        <a href="?tipo=financeiro&data_ini=<?= $data_ini ?>&data_fim=<?= $data_fim ?>" class="tab-btn <?= $tipo==='financeiro'?'active':''?>">💰 Financeiro</a>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card featured">
            <div class="kpi-icon">💵</div>
            <div class="kpi-label">Receita no Período</div>
            <div class="kpi-value">R$ <?= number_format($receita_total,2,',','.') ?></div>
            <div class="kpi-sub"><?= $pedidos_total ?> pedidos confirmados</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">🎯</div>
            <div class="kpi-label">Ticket Médio</div>
            <div class="kpi-value">R$ <?= number_format($ticket_medio,2,',','.') ?></div>
            <div class="kpi-sub">Por pedido pago</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">📅</div>
            <div class="kpi-label">Média Diária</div>
            <div class="kpi-value">R$ <?= number_format($media_diaria,2,',','.') ?></div>
            <div class="kpi-sub"><?= $dias_no_periodo ?> dias no período</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">💡</div>
            <div class="kpi-label">Lucro Estimado</div>
            <div class="kpi-value" style="color:var(--success);">R$ <?= number_format($lucro_total,2,',','.') ?></div>
            <div class="kpi-sub">Baseado no custo dos produtos</div>
        </div>
    </div>

    <?php if($tipo === 'vendas' || $tipo === 'financeiro'): ?>
    <!-- GRÁFICO RECEITA -->
    <div class="two-col">
        <div class="admin-card">
            <div class="card-header">
                <span class="card-title">Receita por Dia</span>
            </div>
            <canvas id="chartReceita" height="120"></canvas>
        </div>
        <div class="admin-card">
            <div class="card-header">
                <span class="card-title">Pedidos por Dia</span>
            </div>
            <canvas id="chartPedidos" height="120"></canvas>
        </div>
    </div>

    <!-- TABELA DIÁRIA -->
    <div class="admin-card">
        <div class="card-header">
            <span class="card-title">Resumo Diário</span>
        </div>
        <table class="data-table">
            <thead>
                <tr><th>Data</th><th>Pedidos</th><th>Receita</th><th>Média por Pedido</th></tr>
            </thead>
            <tbody>
                <?php foreach(array_reverse($vendas_periodo) as $v): ?>
                <tr>
                    <td style="font-weight:700;"><?= date('d/m/Y', strtotime($v['dia'])) ?></td>
                    <td><?= $v['pedidos'] ?></td>
                    <td style="font-weight:800;">R$ <?= number_format($v['receita'],2,',','.') ?></td>
                    <td style="color:var(--text2);">R$ <?= number_format($v['pedidos']>0 ? $v['receita']/$v['pedidos'] : 0,2,',','.') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($vendas_periodo)): ?>
                <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:40px;">Sem dados no período.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if($tipo === 'produtos'): ?>
    <!-- TOP PRODUTOS -->
    <div class="two-col">
        <div class="admin-card">
            <div class="card-header"><span class="card-title">Top 10 Produtos</span></div>
            <?php foreach($top_prod as $i => $p): ?>
            <div class="rank-row rank-<?= $i+1 ?>">
                <div class="rank-n"><?= $i+1 ?></div>
                <img class="rank-img" src="img/produtos/<?= htmlspecialchars($p['imagem']) ?>" onerror="this.style.opacity='.2'">
                <div class="rank-info">
                    <div class="rank-name"><?= htmlspecialchars($p['nome']) ?></div>
                    <div class="rank-sub"><?= $p['qtd_vendida'] ?> unidades vendidas</div>
                </div>
                <div class="rank-val">R$ <?= number_format($p['receita'],0,',','.') ?></div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($top_prod)): ?><p style="color:var(--muted);text-align:center;padding:30px;font-size:13px;">Sem dados no período.</p><?php endif; ?>
        </div>

        <div class="admin-card">
            <div class="card-header"><span class="card-title">Por Categoria</span></div>
            <?php 
            $total_cat = array_sum(array_column($por_categoria, 'receita'));
            foreach($por_categoria as $cat): 
                $pct = $total_cat > 0 ? ($cat['receita'] / $total_cat * 100) : 0;
            ?>
            <div class="cat-bar">
                <div class="cat-row">
                    <span class="cat-name"><?= htmlspecialchars($cat['categoria'] ?: 'Sem categoria') ?></span>
                    <span class="cat-val">R$ <?= number_format($cat['receita'],0,',','.') ?></span>
                </div>
                <div class="progress-bar"><div class="progress-fill" style="width:<?= round($pct) ?>%"></div></div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($por_categoria)): ?><p style="color:var(--muted);font-size:13px;padding:20px 0;">Sem dados no período.</p><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if($tipo === 'clientes'): ?>
    <!-- TOP CLIENTES -->
    <div class="admin-card">
        <div class="card-header"><span class="card-title">Top 10 Clientes por Faturamento</span></div>
        <table class="data-table">
            <thead>
                <tr><th>#</th><th>Cliente</th><th>E-mail</th><th>Pedidos</th><th>Total Gasto</th><th>Última Compra</th></tr>
            </thead>
            <tbody>
                <?php foreach($top_clientes as $i => $c): ?>
                <tr>
                    <td style="font-weight:900;color:var(--muted);"><?= $i+1 ?></td>
                    <td style="font-weight:700;"><?= htmlspecialchars($c['nome']) ?></td>
                    <td style="color:var(--text2);font-size:12px;"><?= htmlspecialchars($c['email']) ?></td>
                    <td><?= $c['total_pedidos'] ?></td>
                    <td style="font-weight:800;">R$ <?= number_format($c['total_gasto'],2,',','.') ?></td>
                    <td style="color:var(--text2);font-size:12px;"><?= date('d/m/Y',strtotime($c['ultima_compra'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($top_clientes)): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:40px;">Sem dados no período.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</main>

<script src="script.js?v=<?= time() ?>"></script>
<script>
<?php if($tipo === 'vendas' || $tipo === 'financeiro'): ?>
const chartOpts = (color) => ({
    responsive: true,
    plugins: { legend: { display: false }, tooltip: {
        backgroundColor:'#fff', titleColor:'#999', bodyColor:'#000',
        borderColor:'#e5e5e5', borderWidth:1, padding:12,
        callbacks: { label: c => 'R$ ' + c.raw.toLocaleString('pt-br',{minimumFractionDigits:2}) }
    }},
    scales: {
        y: { beginAtZero:true, grid:{color:'#f0f0f0'}, ticks:{color:'#aaa'} },
        x: { grid:{display:false}, ticks:{color:'#aaa', maxRotation:0, maxTicksLimit:10} }
    }
});

new Chart(document.getElementById('chartReceita').getContext('2d'), {
    type: 'line',
    data: {
        labels: <?= $graf_labels ?>,
        datasets: [{ data: <?= $graf_receita ?>, borderColor:'#000', backgroundColor:'rgba(0,0,0,0.03)', fill:true, tension:0.4, borderWidth:2.5, pointRadius:3, pointBackgroundColor:'#000', pointBorderColor:'#fff', pointBorderWidth:2 }]
    },
    options: chartOpts()
});

new Chart(document.getElementById('chartPedidos').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= $graf_labels ?>,
        datasets: [{ data: <?= $graf_pedidos ?>, backgroundColor:'rgba(0,0,0,0.08)', borderRadius:8, borderSkipped:false }]
    },
    options: {
        responsive: true,
        plugins: { legend:{display:false}, tooltip:{ backgroundColor:'#fff', titleColor:'#999', bodyColor:'#000', borderColor:'#e5e5e5', borderWidth:1, padding:12 }},
        scales: {
            y: { beginAtZero:true, grid:{color:'#f0f0f0'}, ticks:{color:'#aaa', stepSize:1} },
            x: { grid:{display:false}, ticks:{color:'#aaa', maxRotation:0, maxTicksLimit:10} }
        }
    }
});
<?php endif; ?>
</script>
</body>
</html>