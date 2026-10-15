<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

if (!isset($_SESSION['usuario_nivel']) ||
    !in_array($_SESSION['usuario_nivel'], ['admin','superadmin','gerente'])) {
    header("Location: login.php"); exit;
}

$aba       = $_GET['aba']    ?? 'vendas';
$periodo   = $_GET['periodo']?? '30';
$data_ini  = $_GET['data_ini']?? date('Y-m-01');
$data_fim  = $_GET['data_fim']?? date('Y-m-d');

if ($periodo !== 'custom') {
    $data_ini = date('Y-m-d', strtotime("-$periodo days"));
    $data_fim = date('Y-m-d');
}

// Aba: vendas (comparativo de período)
if ($aba === 'vendas') {
    // Período atual
    $atual = $pdo->prepare("
        SELECT
            COUNT(*) as total_pedidos,
            COALESCE(SUM(total),0) as receita,
            COALESCE(AVG(total),0) as ticket_medio,
            COUNT(DISTINCT usuario_id) as clientes_unicos
        FROM pedidos
        WHERE status NOT IN ('cancelado')
          AND DATE(data_pedido) BETWEEN ? AND ?
    ");
    $atual->execute([$data_ini, $data_fim]);
    $atual = $atual->fetch(PDO::FETCH_ASSOC);

    // Período anterior (mesma duração)
    $dias = max(1, (int)$periodo === 0 ? (int)((strtotime($data_fim) - strtotime($data_ini)) / 86400) : (int)$periodo);
    $ant_fim = date('Y-m-d', strtotime($data_ini . ' -1 day'));
    $ant_ini = date('Y-m-d', strtotime($ant_fim . " -$dias days"));

    $anterior = $pdo->prepare("
        SELECT
            COUNT(*) as total_pedidos,
            COALESCE(SUM(total),0) as receita,
            COALESCE(AVG(total),0) as ticket_medio,
            COUNT(DISTINCT usuario_id) as clientes_unicos
        FROM pedidos
        WHERE status NOT IN ('cancelado')
          AND DATE(data_pedido) BETWEEN ? AND ?
    ");
    $anterior->execute([$ant_ini, $ant_fim]);
    $anterior = $anterior->fetch(PDO::FETCH_ASSOC);

    // Gráfico comparativo diário (atual vs anterior)
    $grafico_atual = $pdo->prepare("
        SELECT DATE(data_pedido) as dia, COALESCE(SUM(total),0) as receita
        FROM pedidos WHERE status != 'cancelado'
          AND DATE(data_pedido) BETWEEN ? AND ?
        GROUP BY dia ORDER BY dia
    ");
    $grafico_atual->execute([$data_ini, $data_fim]);
    $grafico_atual = $grafico_atual->fetchAll(PDO::FETCH_ASSOC);

    $grafico_anterior = $pdo->prepare("
        SELECT DATE(data_pedido) as dia, COALESCE(SUM(total),0) as receita
        FROM pedidos WHERE status != 'cancelado'
          AND DATE(data_pedido) BETWEEN ? AND ?
        GROUP BY dia ORDER BY dia
    ");
    $grafico_anterior->execute([$ant_ini, $ant_fim]);
    $grafico_anterior = $grafico_anterior->fetchAll(PDO::FETCH_ASSOC);
}

// Aba: desempenho de produtos
if ($aba === 'produtos') {
    $produtos_perf = $pdo->prepare("
        SELECT
            p.id, p.nome, p.preco, p.custo, p.estoque,
            COALESCE(SUM(ip.quantidade), 0) AS total_vendido,
            COALESCE(SUM(ip.quantidade * ip.preco_unitario), 0) AS receita_bruta,
            COALESCE(
                (SELECT COUNT(*) FROM devolucoes d
                 JOIN pedidos pd ON d.pedido_id = pd.id
                 JOIN itens_pedido ip2 ON ip2.pedido_id = pd.id
                 WHERE ip2.produto_id = p.id), 0) AS devolucoes,
            COALESCE(
                (SELECT ROUND(AVG(a.nota),1) FROM avaliacoes a
                 WHERE a.produto_id = p.id AND a.status='aprovado'), 0) AS nota_media,
            COALESCE(
                (SELECT COUNT(*) FROM avaliacoes a
                 WHERE a.produto_id = p.id AND a.nota <= 2
                   AND a.data >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS neg_recentes
        FROM produtos p
        LEFT JOIN itens_pedido ip ON ip.produto_id = p.id
        LEFT JOIN pedidos pd2 ON pd2.id = ip.pedido_id
            AND pd2.status != 'cancelado'
            AND DATE(pd2.data_pedido) BETWEEN ? AND ?
        WHERE p.ativo = 1
        GROUP BY p.id
        ORDER BY devolucoes DESC, nota_media ASC
    ");
    $produtos_perf->execute([$data_ini, $data_fim]);
    $produtos_perf = $produtos_perf->fetchAll(PDO::FETCH_ASSOC);
}

// Aba: margem de lucro
if ($aba === 'margem') {
    $margem_dados = $pdo->prepare("
        SELECT
            p.id, p.nome, p.preco, p.custo,
            COALESCE(SUM(ip.quantidade), 0) AS qtd_vendida,
            COALESCE(SUM(ip.quantidade * ip.preco_unitario), 0) AS receita,
            COALESCE(SUM(ip.quantidade * COALESCE(ip.custo_unitario, p.custo, 0)), 0) AS custo_total,
            COALESCE(
                (SELECT COUNT(*) FROM devolucoes d
                 JOIN pedidos pd ON d.pedido_id = pd.id
                 JOIN itens_pedido ip2 ON ip2.pedido_id = pd.id
                 WHERE ip2.produto_id = p.id), 0) AS devolucoes,
            COALESCE(
                (SELECT SUM(ip3.preco_unitario) FROM devolucoes d2
                 JOIN pedidos pd2 ON d2.pedido_id = pd2.id
                 JOIN itens_pedido ip3 ON ip3.pedido_id = pd2.id
                 WHERE ip3.produto_id = p.id AND d2.status='aprovado'), 0) AS valor_devolvido
        FROM produtos p
        LEFT JOIN itens_pedido ip ON ip.produto_id = p.id
        LEFT JOIN pedidos pd3 ON pd3.id = ip.pedido_id
            AND pd3.status NOT IN ('cancelado')
            AND DATE(pd3.data_pedido) BETWEEN ? AND ?
        WHERE p.ativo = 1
        GROUP BY p.id
        HAVING qtd_vendida > 0
        ORDER BY receita DESC
        LIMIT 30
    ");
    $margem_dados->execute([$data_ini, $data_fim]);
    $margem_dados = $margem_dados->fetchAll(PDO::FETCH_ASSOC);

    // Totais
    $total_receita  = array_sum(array_column($margem_dados, 'receita'));
    $total_custo    = array_sum(array_column($margem_dados, 'custo_total'));
    $total_dev_val  = array_sum(array_column($margem_dados, 'valor_devolvido'));
    $lucro_liquido  = $total_receita - $total_custo - $total_dev_val;
    $margem_geral   = $total_receita > 0 ? ($lucro_liquido / $total_receita) * 100 : 0;
}

function variacao(float $atual, float $anterior): string {
    if ($anterior == 0) return '<span style="color:var(--muted)">—</span>';
    $pct = (($atual - $anterior) / $anterior) * 100;
    $cor = $pct >= 0 ? 'var(--success)' : 'var(--danger)';
    $seta = $pct >= 0 ? '↑' : '↓';
    return "<span style='color:$cor;font-weight:800;font-size:12px;'>$seta " . abs(round($pct,1)) . "%</span>";
}

$c_nao_lidos     = $pdo->query("SELECT COUNT(*) FROM alertas WHERE lido=0")->fetchColumn();
$devolucoes_pend = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();
$estoque_critico = $pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque<=3 AND ativo=1")->fetchColumn();
$p_pendente_sb   = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();

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
        .aba-nav { display:flex; gap:8px; margin-bottom:24px; flex-wrap:wrap; }
        .aba-btn { padding:10px 22px; border-radius:50px; border:1px solid var(--border); background:var(--white); color:var(--text2); text-decoration:none; font-size:13px; font-weight:700; transition:var(--transition); }
        .aba-btn:hover { background:var(--grey-bg); }
        .aba-btn.active { background:var(--black); color:var(--white); border-color:var(--black); }

        .periodo-bar { background:var(--white); border:1px solid var(--border); border-radius:30px; padding:14px 22px; display:flex; gap:10px; align-items:center; margin-bottom:22px; flex-wrap:wrap; box-shadow:var(--shadow); }
        .periodo-bar select, .periodo-bar input { background:var(--grey-bg); border:1px solid var(--border); border-radius:50px; color:var(--black); padding:8px 16px; font-family:var(--font-main); font-size:13px; outline:none; }
        .periodo-bar label { font-size:11px; font-weight:800; color:var(--muted); text-transform:uppercase; }

        /* Comparativo */
        .comp-grid { display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:14px; margin-bottom:22px; }
        .comp-card { background:var(--white); border:1px solid var(--border); border-radius:20px; padding:20px; box-shadow:var(--shadow); }
        .comp-label { font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:8px; }
        .comp-atual { font-size:22px; font-weight:900; margin-bottom:4px; }
        .comp-ant   { font-size:11px; color:var(--muted); margin-bottom:6px; }

        /* Tabela de produtos */
        .perf-table { width:100%; border-collapse:collapse; font-size:13px; }
        .perf-table th { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:var(--muted); padding:12px 14px; text-align:left; border-bottom:2px solid var(--border); }
        .perf-table td { padding:14px 14px; border-bottom:1px solid var(--border); vertical-align:middle; }
        .perf-table tr:last-child td { border-bottom:none; }
        .perf-table tr:hover td { background:var(--grey-bg); }

        .score-bar { height:6px; border-radius:3px; background:var(--grey-bg); overflow:hidden; margin-top:4px; }
        .score-fill { height:100%; border-radius:3px; }

        .badge-score { display:inline-block; padding:3px 10px; border-radius:50px; font-size:10px; font-weight:800; }
        .score-ok  { background:rgba(46,125,50,.1); color:var(--success); }
        .score-med { background:rgba(245,158,11,.12); color:#b45309; }
        .score-bad { background:rgba(255,77,77,.12); color:var(--danger); }

        .stars { color:#000; letter-spacing:1px; font-size:13px; }
    </style>
</head>
<body class="admin-page">

<?php include 'sidebar.php'; ?>

<main class="admin-main">
    <div class="admin-topbar">
        <div>
            <h1>Relatórios</h1>
            <p>Análise completa do desempenho da Alto Jordão.</p>
        </div>
    </div>

    <!-- Abas -->
    <div class="aba-nav">
        <a href="?aba=vendas&periodo=<?= $periodo ?>"   class="aba-btn <?= $aba==='vendas'  ?'active':''?>">📊 Comparativo de Vendas</a>
        <a href="?aba=produtos&periodo=<?= $periodo ?>" class="aba-btn <?= $aba==='produtos'?'active':''?>">⚠️ Desempenho de Produtos</a>
        <a href="?aba=margem&periodo=<?= $periodo ?>"   class="aba-btn <?= $aba==='margem'  ?'active':''?>">💰 Margem de Lucro</a>
    </div>

    <!-- Filtro de período -->
    <form method="GET" class="periodo-bar">
        <input type="hidden" name="aba" value="<?= $aba ?>">
        <label>Período:</label>
        <select name="periodo" onchange="this.form.submit()">
            <option value="7"      <?= $periodo==='7'     ?'selected':''?>>Últimos 7 dias</option>
            <option value="30"     <?= $periodo==='30'    ?'selected':''?>>Últimos 30 dias</option>
            <option value="90"     <?= $periodo==='90'    ?'selected':''?>>Últimos 90 dias</option>
            <option value="365"    <?= $periodo==='365'   ?'selected':''?>>Último ano</option>
            <option value="custom" <?= $periodo==='custom'?'selected':''?>>Personalizado</option>
        </select>
        <?php if($periodo==='custom'): ?>
        <label>De:</label>
        <input type="date" name="data_ini" value="<?= $data_ini ?>">
        <label>Até:</label>
        <input type="date" name="data_fim" value="<?= $data_fim ?>">
        <button type="submit" class="btn-admin-primary">Aplicar</button>
        <?php endif; ?>
    </form>

    <?php if($aba === 'vendas'): ?>
    <!-- Aba: comparativo de vendas -->
    <div class="comp-grid">
        <div class="comp-card">
            <div class="comp-label">Receita</div>
            <div class="comp-atual">R$ <?= number_format($atual['receita'],2,',','.') ?></div>
            <div class="comp-ant">Anterior: R$ <?= number_format($anterior['receita'],2,',','.') ?></div>
            <?= variacao($atual['receita'], $anterior['receita']) ?>
        </div>
        <div class="comp-card">
            <div class="comp-label">Pedidos</div>
            <div class="comp-atual"><?= $atual['total_pedidos'] ?></div>
            <div class="comp-ant">Anterior: <?= $anterior['total_pedidos'] ?></div>
            <?= variacao($atual['total_pedidos'], $anterior['total_pedidos']) ?>
        </div>
        <div class="comp-card">
            <div class="comp-label">Ticket Médio</div>
            <div class="comp-atual">R$ <?= number_format($atual['ticket_medio'],2,',','.') ?></div>
            <div class="comp-ant">Anterior: R$ <?= number_format($anterior['ticket_medio'],2,',','.') ?></div>
            <?= variacao($atual['ticket_medio'], $anterior['ticket_medio']) ?>
        </div>
        <div class="comp-card">
            <div class="comp-label">Clientes Únicos</div>
            <div class="comp-atual"><?= $atual['clientes_unicos'] ?></div>
            <div class="comp-ant">Anterior: <?= $anterior['clientes_unicos'] ?></div>
            <?= variacao($atual['clientes_unicos'], $anterior['clientes_unicos']) ?>
        </div>
    </div>

    <div class="admin-card">
        <div class="card-header"><span class="card-title">Receita Diária — Período Atual vs Anterior</span></div>
        <canvas id="graficoComparativo" height="80"></canvas>
    </div>

    <script>
    const labelsAtual = <?= json_encode(array_column($grafico_atual, 'dia')) ?>;
    const valAtual    = <?= json_encode(array_map(fn($r)=>(float)$r['receita'], $grafico_atual)) ?>;
    const labelsAnt   = <?= json_encode(array_column($grafico_anterior, 'dia')) ?>;
    const valAnt      = <?= json_encode(array_map(fn($r)=>(float)$r['receita'], $grafico_anterior)) ?>;

    // Usa as labels do período atual; alinha anterior por posição
    new Chart(document.getElementById('graficoComparativo'), {
        type: 'line',
        data: {
            labels: labelsAtual.length ? labelsAtual : labelsAnt,
            datasets: [
                { label: 'Período Atual', data: valAtual, borderColor:'#000', backgroundColor:'rgba(0,0,0,.05)', tension:.3, fill:true },
                { label: 'Período Anterior', data: valAnt, borderColor:'#bbb', borderDash:[5,5], tension:.3, fill:false }
            ]
        },
        options: { plugins:{ legend:{position:'top'} }, scales:{ y:{ beginAtZero:true } } }
    });
    </script>

    <?php elseif($aba === 'produtos'): ?>
    <!-- Aba: desempenho de produtos -->
    <div class="admin-card">
        <div class="card-header">
            <span class="card-title">Ranking de Desempenho de Produtos</span>
            <span style="font-size:11px; color:var(--muted);">Ordenado por risco — score mais alto = mais problemático</span>
        </div>
        <div style="overflow-x:auto;">
        <table class="perf-table">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Vendidos</th>
                    <th>Devoluções</th>
                    <th>Taxa Dev.</th>
                    <th>Nota Média</th>
                    <th>Neg. Recentes</th>
                    <th>Score de Risco</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($produtos_perf as $pp):
                $vendido  = (int)$pp['total_vendido'];
                $devs     = (int)$pp['devolucoes'];
                $nota     = (float)$pp['nota_media'];
                $neg      = (int)$pp['neg_recentes'];
                $taxa_dev = $vendido > 0 ? ($devs/$vendido)*100 : 0;
                $score    = min(100, $taxa_dev*2 + max(0,(3-$nota)*10) + min(20,$neg*5));
                $score    = round($score);
                $classe   = $score >= 60 ? 'score-bad' : ($score >= 30 ? 'score-med' : 'score-ok');
                $cor_bar  = $score >= 60 ? '#ff4d4d' : ($score >= 30 ? '#f59e0b' : '#4caf50');
            ?>
            <tr>
                <td><strong><?= htmlspecialchars(mb_strimwidth($pp['nome'],0,40,'…')) ?></strong></td>
                <td><?= $vendido ?></td>
                <td><?= $devs ?></td>
                <td><?= round($taxa_dev,1) ?>%</td>
                <td>
                    <span class="stars"><?= str_repeat('★', (int)round($nota)) . str_repeat('☆', 5-(int)round($nota)) ?></span>
                    <small style="color:var(--muted);"> <?= $nota > 0 ? number_format($nota,1) : '—' ?></small>
                </td>
                <td><?= $neg > 0 ? "<span style='color:var(--danger);font-weight:800;'>$neg</span>" : '0' ?></td>
                <td>
                    <span class="badge-score <?= $classe ?>"><?= $score ?>/100</span>
                    <div class="score-bar"><div class="score-fill" style="width:<?= $score ?>%;background:<?= $cor_bar ?>;"></div></div>
                </td>
                <td><a href="produto.php?id=<?= $pp['id'] ?>" style="font-size:11px; font-weight:800; color:var(--black); text-decoration:none;" target="_blank">Ver →</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <?php elseif($aba === 'margem'): ?>
    <!-- Aba: margem de lucro -->
    <div class="kpi-grid" style="margin-bottom:22px;">
        <div class="kpi-card featured">
            <div class="kpi-icon">💰</div>
            <div class="kpi-label">Receita Bruta</div>
            <div class="kpi-value">R$ <?= number_format($total_receita,2,',','.') ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">🏭</div>
            <div class="kpi-label">Custo Total</div>
            <div class="kpi-value">R$ <?= number_format($total_custo,2,',','.') ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">🔄</div>
            <div class="kpi-label">Devoluções (R$)</div>
            <div class="kpi-value" style="color:var(--danger);">R$ <?= number_format($total_dev_val,2,',','.') ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">📈</div>
            <div class="kpi-label">Lucro Líquido Est.</div>
            <div class="kpi-value" style="color:<?= $lucro_liquido >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
                R$ <?= number_format($lucro_liquido,2,',','.') ?>
            </div>
            <div class="kpi-sub">Margem: <?= round($margem_geral,1) ?>%</div>
        </div>
    </div>

    <div class="admin-card">
        <div class="card-header"><span class="card-title">Margem por Produto (Top 30 por Receita)</span></div>
        <div style="overflow-x:auto;">
        <table class="perf-table">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Qtd Vendida</th>
                    <th>Receita</th>
                    <th>Custo Total</th>
                    <th>Devoluções</th>
                    <th>Lucro Est.</th>
                    <th>Margem</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($margem_dados as $m):
                $lucro = $m['receita'] - $m['custo_total'] - $m['valor_devolvido'];
                $margem_p = $m['receita'] > 0 ? ($lucro / $m['receita']) * 100 : 0;
                $cor_mg = $margem_p >= 30 ? 'var(--success)' : ($margem_p >= 15 ? 'var(--warning)' : 'var(--danger)');
            ?>
            <tr>
                <td><strong><?= htmlspecialchars(mb_strimwidth($m['nome'],0,38,'…')) ?></strong></td>
                <td><?= $m['qtd_vendida'] ?></td>
                <td>R$ <?= number_format($m['receita'],2,',','.') ?></td>
                <td>R$ <?= number_format($m['custo_total'],2,',','.') ?></td>
                <td><?= $m['devolucoes'] > 0 ? "<span style='color:var(--danger);'>{$m['devolucoes']}</span>" : '0' ?></td>
                <td style="font-weight:800; color:<?= $lucro >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
                    R$ <?= number_format($lucro,2,',','.') ?>
                </td>
                <td style="font-weight:800; color:<?= $cor_mg ?>">
                    <?= $m['custo_total'] > 0 ? round($margem_p,1).'%' : '<span style="color:var(--muted)">S/custo</span>' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($margem_dados)): ?>
            <tr><td colspan="7" style="text-align:center; padding:40px; color:var(--muted);">Nenhuma venda no período selecionado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

</main>

<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>