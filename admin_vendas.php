<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php'; 

if (!isset($_SESSION['usuario_nivel']) || !in_array($_SESSION['usuario_nivel'], ['admin','superadmin','gerente'])) {
    header("Location: login.php"); exit();
}

$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-d', strtotime('-30 days'));
$data_fim    = isset($_GET['data_fim'])    ? $_GET['data_fim']    : date('Y-m-d');

try {
    $stmtPagas = $pdo->prepare("SELECT p.*, u.nome as cliente FROM pedidos p 
                                JOIN usuarios u ON p.usuario_id = u.id 
                                WHERE p.status = 'pago' AND DATE(p.data_pedido) BETWEEN ? AND ? 
                                ORDER BY p.data_pedido DESC");
    $stmtPagas->execute([$data_inicio, $data_fim]);
    $vendasPagas = $stmtPagas->fetchAll(PDO::FETCH_ASSOC);

    $stmtPendentes = $pdo->prepare("SELECT p.*, u.nome as cliente FROM pedidos p 
                                    JOIN usuarios u ON p.usuario_id = u.id 
                                    WHERE p.status = 'pendente' AND DATE(p.data_pedido) BETWEEN ? AND ? 
                                    ORDER BY p.data_pedido DESC");
    $stmtPendentes->execute([$data_inicio, $data_fim]);
    $vendasPendentes = $stmtPendentes->fetchAll(PDO::FETCH_ASSOC);

    $stmtGrafico = $pdo->prepare("SELECT DATE(data_pedido) as dia, SUM(total) as total 
                                  FROM pedidos WHERE status = 'pago' AND DATE(data_pedido) BETWEEN ? AND ? 
                                  GROUP BY DATE(data_pedido) ORDER BY dia ASC");
    $stmtGrafico->execute([$data_inicio, $data_fim]);
    $dadosGrafico = $stmtGrafico->fetchAll(PDO::FETCH_ASSOC);

    $stmtTop = $pdo->prepare("SELECT p.nome, SUM(it.quantidade) as total_vendas 
                             FROM itens_pedido it 
                             JOIN produtos p ON it.produto_id = p.id 
                             JOIN pedidos ped ON it.pedido_id = ped.id
                             WHERE ped.status = 'pago' AND DATE(ped.data_pedido) BETWEEN ? AND ?
                             GROUP BY it.produto_id ORDER BY total_vendas DESC LIMIT 1");
    $stmtTop->execute([$data_inicio, $data_fim]);
    $produtoTop = $stmtTop->fetch(PDO::FETCH_ASSOC);

    $stmtLow = $pdo->prepare("SELECT p.nome, IFNULL(SUM(it.quantidade), 0) as total_vendas 
                              FROM produtos p
                              LEFT JOIN itens_pedido it ON p.id = it.produto_id
                              LEFT JOIN pedidos ped ON it.pedido_id = ped.id AND ped.status = 'pago'
                              WHERE (DATE(ped.data_pedido) BETWEEN ? AND ? OR ped.id IS NULL)
                              GROUP BY p.id ORDER BY total_vendas ASC LIMIT 1");
    $stmtLow->execute([$data_inicio, $data_fim]);
    $produtoLow = $stmtLow->fetch(PDO::FETCH_ASSOC);

    // Faturamento total do período (pago)
    $faturamento_periodo = array_sum(array_column($vendasPagas, 'total'));

} catch (PDOException $e) {
    $produtoTop = $produtoLow = null;
    $vendasPagas = $vendasPendentes = $dadosGrafico = [];
    $faturamento_periodo = 0;
}

// Badges para sidebar
$devolucoes_pend = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();
$estoque_critico = $pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque<=3 AND ativo=1")->fetchColumn();
$p_pendente_sb   = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();

$graf_labels = json_encode(array_map(fn($d) => date('d/m', strtotime($d['dia'])), $dadosGrafico));
$graf_data   = json_encode(array_map(fn($d) => round((float)$d['total'], 2), $dadosGrafico));

define('CONTEUDO_AUTORIZADO', true);
$pagina_atual = 'financeiro';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendas | Alto Jordão Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ── EXTRAS EXCLUSIVOS DA PÁGINA DE VENDAS ── */

        .filter-bar {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 20px 28px;
            display: flex;
            gap: 16px;
            align-items: flex-end;
            margin-bottom: 22px;
            flex-wrap: wrap;
            box-shadow: var(--shadow);
        }

        .filter-group { display: flex; flex-direction: column; gap: 7px; }

        .filter-group label {
            font-size: 10px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .filter-group input {
            background: var(--grey-bg);
            border: 1px solid var(--border);
            border-radius: 50px;
            color: var(--black);
            padding: 10px 16px;
            font-family: var(--font-main);
            font-size: 13px;
            min-width: 150px;
            outline: none;
            transition: var(--transition);
        }

        .filter-group input:focus { border-color: var(--black); background: var(--white); }

        /* Cards de performance (mais/menos vendido) */
        .perf-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 22px;
        }

        .perf-card {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .perf-icon {
            width: 52px; height: 52px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .perf-icon.top  { background: var(--black); color: var(--white); }
        .perf-icon.low  { background: var(--grey-bg); color: var(--black); }

        .perf-info small {
            font-size: 10px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .perf-info h3 {
            margin: 6px 0 2px;
            font-size: 16px;
            font-weight: 800;
        }

        .perf-info span { font-size: 12px; font-weight: 800; }
        .perf-info span.up   { color: var(--success); }
        .perf-info span.down { color: var(--danger); }

        /* Tabs de pagas / pendentes */
        .status-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 9px 20px;
            border-radius: 50px;
            border: 1px solid var(--border);
            background: var(--white);
            color: var(--text2);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }

        .tab-btn:hover { background: var(--grey-bg); color: var(--black); }

        .tab-btn.active {
            background: var(--black);
            color: var(--white);
            border-color: var(--black);
        }

        .vendas-table { display: none; }
        .vendas-table.active { display: table; }
    </style>
</head>
<body class="admin-page">

<!-- ── SIDEBAR ───────────────────────────────── -->
<?php include 'sidebar.php'; ?>

<!-- ── CONTEÚDO ───────────────────────────────── -->
<main class="admin-main">

    <div class="admin-topbar">
        <div>
            <h1>Relatório de Vendas</h1>
            <p>Análise de faturamento e performance da Alto Jordão.</p>
        </div>
        <div class="topbar-actions">
            <a href="admin_relatorios.php" class="btn-admin-ghost">📥 Exportar</a>
        </div>
    </div>

    <!-- FILTROS -->
    <form method="GET" class="filter-bar">
        <div class="filter-group">
            <label>Data Inicial</label>
            <input type="date" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>">
        </div>
        <div class="filter-group">
            <label>Data Final</label>
            <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>">
        </div>
        <button type="submit" class="btn-admin-primary">Filtrar Resultados</button>
        <a href="admin_vendas.php" class="btn-admin-ghost">Limpar</a>
    </form>

    <!-- KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card featured">
            <div class="kpi-icon">💵</div>
            <div class="kpi-label">Faturamento (Pago) no Período</div>
            <div class="kpi-value">R$ <?= number_format($faturamento_periodo, 2, ',', '.') ?></div>
            <div class="kpi-sub"><?= count($vendasPagas) ?> venda(s) confirmada(s)</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">⏳</div>
            <div class="kpi-label">Aguardando Pagamento</div>
            <div class="kpi-value">R$ <?= number_format(array_sum(array_column($vendasPendentes,'total')), 2, ',', '.') ?></div>
            <div class="kpi-sub"><?= count($vendasPendentes) ?> pedido(s) pendente(s)</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">📈</div>
            <div class="kpi-label">Ticket Médio</div>
            <div class="kpi-value">
                R$ <?= number_format(count($vendasPagas) > 0 ? $faturamento_periodo / count($vendasPagas) : 0, 2, ',', '.') ?>
            </div>
            <div class="kpi-sub">Por pedido pago</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">🗓️</div>
            <div class="kpi-label">Período Selecionado</div>
            <div class="kpi-value" style="font-size:18px;">
                <?= date('d/m', strtotime($data_inicio)) ?> — <?= date('d/m', strtotime($data_fim)) ?>
            </div>
            <div class="kpi-sub"><?= (strtotime($data_fim) - strtotime($data_inicio)) / 86400 + 1 ?> dias</div>
        </div>
    </div>

    <!-- PERFORMANCE: MAIS / MENOS VENDIDO -->
    <div class="perf-grid">
        <div class="admin-card perf-card">
            <div class="perf-icon top">🏆</div>
            <div class="perf-info">
                <small>Mais Vendido</small>
                <h3><?= $produtoTop ? htmlspecialchars($produtoTop['nome']) : 'Sem dados' ?></h3>
                <span class="up"><?= $produtoTop ? $produtoTop['total_vendas'].' unidades' : '-' ?></span>
            </div>
        </div>
        <div class="admin-card perf-card">
            <div class="perf-icon low">📉</div>
            <div class="perf-info">
                <small>Menos Vendido</small>
                <h3><?= $produtoLow ? htmlspecialchars($produtoLow['nome']) : 'Sem dados' ?></h3>
                <span class="down"><?= $produtoLow ? $produtoLow['total_vendas'].' unidades' : '-' ?></span>
            </div>
        </div>
    </div>

    <!-- GRÁFICO -->
    <div class="admin-card" style="margin-bottom: 22px;">
        <div class="card-header">
            <span class="card-title">Receita Bruta (R$)</span>
        </div>
        <canvas id="graficoVendas" height="80"></canvas>
    </div>

    <!-- TABS + TABELAS -->
    <div class="status-tabs">
        <button class="tab-btn active" onclick="switchTab('pagas', this)">Pagos (<?= count($vendasPagas) ?>)</button>
        <button class="tab-btn" onclick="switchTab('pendentes', this)">Aguardando (<?= count($vendasPendentes) ?>)</button>
    </div>

    <div class="admin-card">
        <table class="data-table vendas-table active" id="table-pagas">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Valor</th>
                    <th>Status</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($vendasPagas as $v): ?>
                <tr>
                    <td style="font-weight:900; color:var(--muted);">#<?= str_pad($v['id'],4,'0',STR_PAD_LEFT) ?></td>
                    <td style="font-weight:700;"><?= htmlspecialchars($v['cliente']) ?></td>
                    <td style="font-weight:800;">R$ <?= number_format($v['total'], 2, ',', '.') ?></td>
                    <td><span class="badge badge-pago">Pago</span></td>
                    <td style="color:var(--text2); font-size:12px;"><?= date('d/m/Y H:i', strtotime($v['data_pedido'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($vendasPagas)): ?>
                <tr><td colspan="5" style="text-align:center; color:var(--muted); padding:50px; font-size:13px;">Nenhuma venda paga no período selecionado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <table class="data-table vendas-table" id="table-pendentes">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Valor</th>
                    <th>Status</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($vendasPendentes as $v): ?>
                <tr>
                    <td style="font-weight:900; color:var(--muted);">#<?= str_pad($v['id'],4,'0',STR_PAD_LEFT) ?></td>
                    <td style="font-weight:700;"><?= htmlspecialchars($v['cliente']) ?></td>
                    <td style="font-weight:800;">R$ <?= number_format($v['total'], 2, ',', '.') ?></td>
                    <td><span class="badge badge-pendente">Pendente</span></td>
                    <td style="color:var(--text2); font-size:12px;"><?= date('d/m/Y H:i', strtotime($v['data_pedido'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($vendasPendentes)): ?>
                <tr><td colspan="5" style="text-align:center; color:var(--muted); padding:50px; font-size:13px;">Nenhum pedido pendente no período selecionado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>

<script src="script.js?v=<?= time() ?>"></script>
<script>
function switchTab(type, btn) {
    document.querySelectorAll('.vendas-table').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('table-' + type).classList.add('active');
    btn.classList.add('active');
}

const ctx = document.getElementById('graficoVendas').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= $graf_labels ?>,
        datasets: [{
            label: 'Receita',
            data: <?= $graf_data ?>,
            borderColor: '#000000',
            backgroundColor: 'rgba(0,0,0,0.03)',
            fill: true,
            tension: 0.4,
            borderWidth: 2.5,
            pointRadius: 4,
            pointBackgroundColor: '#000000',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#fff',
                titleColor: '#999',
                bodyColor: '#000',
                borderColor: '#e5e5e5',
                borderWidth: 1,
                padding: 12,
                callbacks: {
                    label: ctx => 'R$ ' + ctx.raw.toLocaleString('pt-br', {minimumFractionDigits: 2})
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: '#f0f0f0' },
                ticks: { color: '#aaa', callback: v => 'R$ ' + (v/1000).toFixed(0) + 'k' }
            },
            x: {
                grid: { display: false },
                ticks: { color: '#aaa', maxRotation: 0 }
            }
        }
    }
});
</script>
</body>
</html>