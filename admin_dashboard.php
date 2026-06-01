<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

// Descomente quando for para produção:
// if (!isset($_SESSION['usuario_nivel']) || !in_array($_SESSION['usuario_nivel'], ['admin','superadmin','gerente'])) {
//     header("Location: login.php"); exit();
// }

$hoje    = date('Y-m-d');
$mes_ini = date('Y-m-01');
$ano_ini = date('Y-01-01');

function q($pdo, $sql, $p = []) { $s = $pdo->prepare($sql); $s->execute($p); return $s; }

$v_hoje          = q($pdo,"SELECT COALESCE(SUM(total),0) FROM pedidos WHERE status IN('pago','enviado','entregue') AND DATE(data_pedido)=?",[$hoje])->fetchColumn();
$v_mes           = q($pdo,"SELECT COALESCE(SUM(total),0) FROM pedidos WHERE status IN('pago','enviado','entregue') AND data_pedido>=?",[$mes_ini])->fetchColumn();
$v_ano           = q($pdo,"SELECT COALESCE(SUM(total),0) FROM pedidos WHERE status IN('pago','enviado','entregue') AND data_pedido>=?",[$ano_ini])->fetchColumn();
$p_pendente      = q($pdo,"SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();
$p_enviado       = q($pdo,"SELECT COUNT(*) FROM pedidos WHERE status='enviado'")->fetchColumn();
$p_cancelado     = q($pdo,"SELECT COUNT(*) FROM pedidos WHERE status='cancelado'")->fetchColumn();
$p_entregue      = q($pdo,"SELECT COUNT(*) FROM pedidos WHERE status='entregue'")->fetchColumn();
$total_clientes  = q($pdo,"SELECT COUNT(*) FROM usuarios WHERE nivel='cliente'")->fetchColumn();
$total_produtos  = q($pdo,"SELECT COUNT(*) FROM produtos WHERE ativo=1")->fetchColumn();
$estoque_critico = q($pdo,"SELECT COUNT(*) FROM produtos WHERE estoque<=3 AND ativo=1")->fetchColumn();
$devolucoes_pend = q($pdo,"SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();

$lucro_mes = q($pdo,"
    SELECT COALESCE(SUM((ip.preco_unitario - COALESCE(ip.custo_unitario, pr.custo, 0)) * ip.quantidade), 0)
    FROM itens_pedido ip
    JOIN pedidos ped ON ip.pedido_id = ped.id
    JOIN produtos pr  ON ip.produto_id = pr.id
    WHERE ped.status IN('pago','enviado','entregue') AND ped.data_pedido >= ?
", [$mes_ini])->fetchColumn();

$grafico = q($pdo,"
    SELECT DATE(data_pedido) as dia, SUM(total) as total
    FROM pedidos WHERE status IN('pago','enviado','entregue')
    AND data_pedido >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(data_pedido) ORDER BY dia ASC
")->fetchAll(PDO::FETCH_ASSOC);

$top_produtos = q($pdo,"
    SELECT p.nome, p.imagem, SUM(i.quantidade) as vendidos, SUM(i.quantidade*i.preco_unitario) as receita
    FROM itens_pedido i
    JOIN produtos p   ON i.produto_id = p.id
    JOIN pedidos ped  ON i.pedido_id  = ped.id
    WHERE ped.status IN('pago','enviado','entregue')
    GROUP BY i.produto_id ORDER BY vendidos DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$pedidos_recentes = q($pdo,"
    SELECT ped.*, u.nome as cliente
    FROM pedidos ped JOIN usuarios u ON ped.usuario_id = u.id
    ORDER BY ped.data_pedido DESC LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

$dist_pgto = q($pdo,"
    SELECT forma_pagamento, COUNT(*) as total
    FROM pedidos WHERE status IN('pago','enviado','entregue') AND data_pedido >= ?
    GROUP BY forma_pagamento
", [$mes_ini])->fetchAll(PDO::FETCH_ASSOC);

$graf_labels = json_encode(array_map(fn($r) => date('d/m', strtotime($r['dia'])), $grafico));
$graf_data   = json_encode(array_map(fn($r) => round($r['total'], 2), $grafico));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Alto Jordão Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-page">

<!-- ── SIDEBAR ───────────────────────────────── -->
<aside class="admin-sidebar">
    <div class="sb-logo">ALTO JORDÃO</div>

    <div class="sb-section">
        <span class="sb-section-title">Visão Geral</span>
        <a href="admin_dashboard.php" class="sb-item active">📊 Dashboard</a>
    </div>

    <div class="sb-section">
        <span class="sb-section-title">Vendas</span>
        <a href="admin_pedidos.php" class="sb-item">
            🛒 Pedidos
            <?php if($p_pendente > 0): ?><span class="sb-badge"><?= $p_pendente ?></span><?php endif; ?>
        </a>
        <a href="admin_vendas.php"     class="sb-item">💰 Financeiro</a>
        <a href="entregas.php"         class="sb-item">📦 Logística</a>
        <a href="admin_devolucoes.php" class="sb-item">
            🔄 Devoluções
            <?php if($devolucoes_pend > 0): ?><span class="sb-badge"><?= $devolucoes_pend ?></span><?php endif; ?>
        </a>
    </div>

    <div class="sb-section">
        <span class="sb-section-title">Catálogo</span>
        <a href="admin_produtos.php"    class="sb-item">👕 Produtos</a>
        <a href="admin_estoque.php"     class="sb-item">
            📋 Estoque
            <?php if($estoque_critico > 0): ?><span class="sb-badge"><?= $estoque_critico ?></span><?php endif; ?>
        </a>
        <a href="admin_categorias.php"  class="sb-item">🏷️ Categorias</a>
        <a href="admin_colecoes.php"    class="sb-item">✨ Coleções</a>
        <a href="admin_marcas.php"      class="sb-item">🔖 Marcas</a>
        <a href="cadastrar_produto.php" class="sb-item">➕ Novo Produto</a>
    </div>

    <div class="sb-section">
        <span class="sb-section-title">Usuários</span>
        <a href="admin_clientes.php" class="sb-item">👥 Clientes</a>
        <a href="admin_admins.php"   class="sb-item">🛡️ Administradores</a>
    </div>

    <div class="sb-section">
        <span class="sb-section-title">Marketing</span>
        <a href="admin_cupons.php"    class="sb-item">🎟️ Cupons</a>
        <a href="admin_avaliacoes.php" class="sb-item">⭐ Avaliações</a>
    </div>

    <div class="sb-section">
        <span class="sb-section-title">Sistema</span>
        <a href="admin_relatorios.php"    class="sb-item">📈 Relatórios</a>
        <a href="admin_logs.php"          class="sb-item">🔍 Logs & Auditoria</a>
        <a href="admin_configuracoes.php" class="sb-item">⚙️ Configurações</a>
    </div>

    <div class="sb-footer">
        <div class="sb-user">
            <div class="sb-avatar"><?= strtoupper(substr($_SESSION['usuario_nome'] ?? 'A', 0, 1)) ?></div>
            <div class="sb-user-info">
                <small><?= strtoupper($_SESSION['usuario_nivel'] ?? 'admin') ?></small>
                <strong><?= explode(' ', $_SESSION['usuario_nome'] ?? 'Admin')[0] ?></strong>
            </div>
        </div>
        <a href="index.php"  class="sb-item">🏪 Ver Loja</a>
        <a href="logout.php" class="sb-item" style="color: var(--danger);">🚪 Sair</a>
    </div>
</aside>

<!-- ── CONTEÚDO PRINCIPAL ─────────────────────── -->
<main class="admin-main">

    <!-- TOPBAR -->
    <div class="admin-topbar">
        <div>
            <h1>Dashboard</h1>
            <p><?= date('d/m/Y') ?> &mdash; Bem-vindo, <?= explode(' ', $_SESSION['usuario_nome'] ?? 'Admin')[0] ?>!</p>
        </div>
        <div class="topbar-actions">
            <a href="admin_relatorios.php" class="btn-admin-ghost">📥 Exportar</a>
            <a href="cadastrar_produto.php" class="btn-admin-primary">+ Novo Produto</a>
        </div>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card featured">
            <div class="kpi-icon">💵</div>
            <div class="kpi-label">Vendas Hoje</div>
            <div class="kpi-value">R$ <?= number_format($v_hoje, 2, ',', '.') ?></div>
            <div class="kpi-sub"><span class="up">↑</span> Tempo real</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">📅</div>
            <div class="kpi-label">Vendas do Mês</div>
            <div class="kpi-value">R$ <?= number_format($v_mes, 2, ',', '.') ?></div>
            <div class="kpi-sub"><?= date('F Y') ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">📆</div>
            <div class="kpi-label">Vendas do Ano</div>
            <div class="kpi-value">R$ <?= number_format($v_ano, 2, ',', '.') ?></div>
            <div class="kpi-sub"><?= date('Y') ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">💡</div>
            <div class="kpi-label">Lucro Estimado (Mês)</div>
            <div class="kpi-value" style="color: var(--success);">R$ <?= number_format($lucro_mes, 2, ',', '.') ?></div>
            <div class="kpi-sub">Baseado no custo dos produtos</div>
        </div>
    </div>

    <!-- STATUS DOS PEDIDOS -->
    <div class="status-strip">
        <div class="status-card">
            <div class="status-dot sd-warn">⏳</div>
            <div>
                <div class="status-card-val"><?= $p_pendente ?></div>
                <div class="status-card-label">Pedidos Pendentes</div>
            </div>
        </div>
        <div class="status-card">
            <div class="status-dot sd-info">🚀</div>
            <div>
                <div class="status-card-val"><?= $p_enviado ?></div>
                <div class="status-card-label">Enviados</div>
            </div>
        </div>
        <div class="status-card">
            <div class="status-dot sd-ok">✅</div>
            <div>
                <div class="status-card-val"><?= $p_entregue ?></div>
                <div class="status-card-label">Entregues</div>
            </div>
        </div>
        <div class="status-card">
            <div class="status-dot sd-danger">❌</div>
            <div>
                <div class="status-card-val"><?= $p_cancelado ?></div>
                <div class="status-card-label">Cancelados</div>
            </div>
        </div>
    </div>

    <!-- GRÁFICO + FINANCEIRO -->
    <div class="two-col">
        <div class="admin-card">
            <div class="card-header">
                <span class="card-title">Receita — Últimos 30 dias</span>
                <a href="admin_vendas.php" class="card-link">Ver tudo →</a>
            </div>
            <canvas id="chartReceita" height="110"></canvas>
        </div>

        <div class="admin-card">
            <div class="card-header">
                <span class="card-title">Resumo Financeiro</span>
            </div>

            <div class="fin-item">
                <span class="fin-label">Faturamento (mês)</span>
                <span class="fin-value">R$ <?= number_format($v_mes, 2, ',', '.') ?></span>
            </div>
            <div class="fin-item">
                <span class="fin-label">Lucro Estimado (mês)</span>
                <span class="fin-value" style="color: var(--success);">R$ <?= number_format($lucro_mes, 2, ',', '.') ?></span>
            </div>
            <div class="fin-item">
                <span class="fin-label">Total de Clientes</span>
                <span class="fin-value"><?= $total_clientes ?></span>
            </div>
            <div class="fin-item">
                <span class="fin-label">Produtos Ativos</span>
                <span class="fin-value"><?= $total_produtos ?></span>
            </div>
            <div class="fin-item">
                <span class="fin-label">Estoque Crítico</span>
                <span class="fin-value" style="color:<?= $estoque_critico > 0 ? 'var(--danger)' : 'var(--success)' ?>">
                    <?= $estoque_critico ?> produto<?= $estoque_critico != 1 ? 's' : '' ?>
                </span>
            </div>
            <div class="fin-item">
                <span class="fin-label">Devoluções Pendentes</span>
                <span class="fin-value" style="color:<?= $devolucoes_pend > 0 ? 'var(--warning)' : 'var(--success)' ?>">
                    <?= $devolucoes_pend ?>
                </span>
            </div>

            <?php if(!empty($dist_pgto)): ?>
            <div style="margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--border);">
                <div class="card-title" style="font-size:10px; margin-bottom:14px;">Pagamentos (mês)</div>
                <?php $tot_pgto = array_sum(array_column($dist_pgto,'total')); ?>
                <?php foreach($dist_pgto as $d): ?>
                <div style="margin-bottom:10px;">
                    <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:5px;">
                        <span style="color:var(--text2); text-transform:uppercase; font-weight:600;"><?= $d['forma_pagamento'] ?></span>
                        <span style="font-weight:800;"><?= $d['total'] ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width:<?= min(100, $d['total'] / max(1, $tot_pgto) * 100) ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PEDIDOS RECENTES + TOP PRODUTOS -->
    <div class="two-col">
        <div class="admin-card">
            <div class="card-header">
                <span class="card-title">Pedidos Recentes</span>
                <a href="admin_pedidos.php" class="card-link">Ver todos →</a>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th><th>Cliente</th><th>Valor</th><th>Status</th><th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pedidos_recentes as $ped): ?>
                    <tr onclick="window.location='admin_pedidos.php?id=<?= $ped['id'] ?>'" style="cursor:pointer;">
                        <td style="font-weight:800; color:var(--muted);">#<?= str_pad($ped['id'],4,'0',STR_PAD_LEFT) ?></td>
                        <td style="font-weight:700;"><?= htmlspecialchars(explode(' ', $ped['cliente'])[0]) ?></td>
                        <td style="font-weight:800;">R$ <?= number_format($ped['total'],2,',','.') ?></td>
                        <td><span class="badge badge-<?= $ped['status'] ?>"><?= $ped['status'] ?></span></td>
                        <td style="color:var(--text2); font-size:12px;"><?= date('d/m H:i', strtotime($ped['data_pedido'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($pedidos_recentes)): ?>
                    <tr><td colspan="5" style="text-align:center; color:var(--muted); padding:30px;">Nenhum pedido ainda.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="admin-card">
            <div class="card-header">
                <span class="card-title">Mais Vendidos</span>
                <a href="admin_relatorios.php" class="card-link">Relatório →</a>
            </div>
            <?php if(empty($top_produtos)): ?>
                <p style="color:var(--muted); text-align:center; padding:30px; font-size:12px;">Sem dados suficientes.</p>
            <?php endif; ?>
            <?php foreach($top_produtos as $i => $prod): ?>
            <div class="product-rank rank-<?= $i+1 ?>">
                <div class="rank-num"><?= $i+1 ?></div>
                <img class="rank-img" src="img/produtos/<?= $prod['imagem'] ?>" onerror="this.style.display='none'">
                <div class="rank-info">
                    <div class="rank-name"><?= htmlspecialchars($prod['nome']) ?></div>
                    <div class="rank-qty"><?= $prod['vendidos'] ?> unidades vendidas</div>
                </div>
                <div class="rank-value">R$ <?= number_format($prod['receita'],0,',','.') ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ALERTAS -->
    <?php if($estoque_critico > 0 || $devolucoes_pend > 0 || $p_pendente > 5): ?>
    <div class="admin-card">
        <div class="card-header">
            <span class="card-title">⚠️ Alertas do Sistema</span>
        </div>
        <div class="alert-list">
            <?php if($estoque_critico > 0): ?>
            <div class="alert-item danger">
                🔴 <strong><?= $estoque_critico ?> produto(s)</strong> com estoque crítico (≤ 3 unidades).
                <a href="admin_estoque.php">Resolver →</a>
            </div>
            <?php endif; ?>
            <?php if($devolucoes_pend > 0): ?>
            <div class="alert-item">
                🟡 <strong><?= $devolucoes_pend ?> devolução(ões)</strong> aguardando análise.
                <a href="admin_devolucoes.php">Analisar →</a>
            </div>
            <?php endif; ?>
            <?php if($p_pendente > 5): ?>
            <div class="alert-item info">
                🔵 <strong><?= $p_pendente ?> pedidos</strong> pendentes de processamento.
                <a href="admin_pedidos.php">Ver pedidos →</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</main>

<script src="script.js?v=<?= time() ?>"></script>
<script>
const ctx = document.getElementById('chartReceita').getContext('2d');
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