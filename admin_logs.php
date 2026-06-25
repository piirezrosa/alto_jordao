<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

if (!isset($_SESSION['usuario_nivel']) || !in_array($_SESSION['usuario_nivel'], ['admin','superadmin'])) {
    header("Location: login.php"); exit();
}

// ── FILTROS ───────────────────────────────────
$busca    = $_GET['busca']    ?? '';
$acao     = $_GET['acao']     ?? '';
$data_ini = $_GET['data_ini'] ?? date('Y-m-d', strtotime('-7 days'));
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$pag      = max(1, (int)($_GET['pag'] ?? 1));
$por_pag  = 25;
$offset   = ($pag - 1) * $por_pag;

$where  = ["DATE(l.data) BETWEEN ? AND ?"];
$params = [$data_ini, $data_fim];

if ($acao)  { $where[] = "l.acao = ?"; $params[] = $acao; }
if ($busca) { $where[] = "(u.nome LIKE ? OR l.acao LIKE ? OR l.detalhes LIKE ? OR l.ip LIKE ?)";
              $params[] = "%$busca%"; $params[] = "%$busca%"; $params[] = "%$busca%"; $params[] = "%$busca%"; }

$sql_where = implode(' AND ', $where);

$total_rows = $pdo->prepare("SELECT COUNT(*) FROM logs_sistema l LEFT JOIN usuarios u ON l.usuario_id = u.id WHERE $sql_where");
$total_rows->execute($params);
$total_rows = $total_rows->fetchColumn();
$total_pags = ceil($total_rows / $por_pag);

$logs = $pdo->prepare("
    SELECT l.*, u.nome as usuario_nome, u.nivel as usuario_nivel
    FROM logs_sistema l
    LEFT JOIN usuarios u ON l.usuario_id = u.id
    WHERE $sql_where
    ORDER BY l.data DESC
    LIMIT $por_pag OFFSET $offset
");
$logs->execute($params);
$logs = $logs->fetchAll(PDO::FETCH_ASSOC);

// Tipos de ação para o filtro
$acoes_disponiveis = $pdo->query("SELECT DISTINCT acao FROM logs_sistema ORDER BY acao")->fetchAll(PDO::FETCH_COLUMN);

// Estatísticas rápidas
$logs_hoje  = $pdo->query("SELECT COUNT(*) FROM logs_sistema WHERE DATE(data) = CURDATE()")->fetchColumn();
$logs_total = $pdo->query("SELECT COUNT(*) FROM logs_sistema")->fetchColumn();
$acoes_unicas = $pdo->query("SELECT COUNT(DISTINCT acao) FROM logs_sistema")->fetchColumn();

// Ações mais frequentes (últimos 7 dias)
$top_acoes = $pdo->query("
    SELECT acao, COUNT(*) as total FROM logs_sistema
    WHERE data >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY acao ORDER BY total DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Badges sidebar
$devolucoes_pend = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();
$estoque_critico = $pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque<=3 AND ativo=1")->fetchColumn();
$p_pendente_sb   = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();

// Mapeia ações para ícones e cores
function acao_style($acao) {
    $map = [
        'login'                    => ['🔐', 'info'],
        'logout'                   => ['🚪', 'muted'],
        'produto_criado'           => ['➕', 'success'],
        'produto_editado'          => ['✏️', 'warning'],
        'pedido_status_atualizado' => ['🚀', 'info'],
        'cliente_bloqueado'        => ['🔒', 'danger'],
        'cliente_ativo'            => ['🔓', 'success'],
        'devolucao_aprovado'       => ['✅', 'success'],
        'devolucao_recusado'       => ['❌', 'danger'],
    ];
    return $map[$acao] ?? ['📋', 'muted'];
}

define('CONTEUDO_AUTORIZADO', true);
$pagina_atual = 'logs';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs & Auditoria | Alto Jordão Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <style>
        .filter-bar {
            background:var(--white); border:1px solid var(--border); border-radius:30px;
            padding:18px 26px; display:flex; gap:14px; align-items:flex-end;
            margin-bottom:22px; flex-wrap:wrap; box-shadow:var(--shadow);
        }
        .filter-group { display:flex; flex-direction:column; gap:6px; }
        .filter-group label { font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1px; }
        .filter-group input, .filter-group select {
            background:var(--grey-bg); border:1px solid var(--border); border-radius:50px;
            color:var(--black); padding:10px 16px; font-family:var(--font-main); font-size:13px; outline:none; min-width:140px;
        }

        .pagination { display:flex; gap:8px; justify-content:center; margin-top:24px; }
        .page-btn { padding:8px 14px; border-radius:50px; border:1px solid var(--border); background:var(--white); color:var(--text2); text-decoration:none; font-size:12px; font-weight:700; transition:var(--transition); }
        .page-btn:hover, .page-btn.active { background:var(--black); color:var(--white); border-color:var(--black); }

        .two-col { display:grid; grid-template-columns:2fr 1fr; gap:20px; margin-bottom:22px; }

        /* Linha de log */
        .log-acao {
            display:inline-flex;
            align-items:center;
            gap:6px;
            font-size:11px;
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:0.5px;
            padding:4px 12px;
            border-radius:50px;
        }
        .log-info    { background:rgba(59,130,246,.10); color:#3b82f6; }
        .log-success { background:rgba(46,125,50,.10);  color:var(--success); }
        .log-danger  { background:rgba(255,77,77,.10);  color:var(--danger); }
        .log-warning { background:rgba(245,158,11,.10); color:#b45309; }
        .log-muted   { background:var(--grey-bg);       color:var(--muted); }

        .top-acao-row { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border); }
        .top-acao-row:last-child { border-bottom:none; }
        .top-acao-name { font-size:12px; font-weight:700; text-transform:uppercase; }
        .top-acao-count { font-family:var(--font-main); font-weight:900; font-size:16px; }
    </style>
</head>
<body class="admin-page">

<?php include 'sidebar.php'; ?>

<main class="admin-main">
    <div class="admin-topbar">
        <div>
            <h1>Logs & Auditoria</h1>
            <p>Rastreie todas as ações realizadas no sistema.</p>
        </div>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card featured">
            <div class="kpi-icon">📋</div>
            <div class="kpi-label">Logs Hoje</div>
            <div class="kpi-value"><?= $logs_hoje ?></div>
            <div class="kpi-sub">Ações registradas</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">📚</div>
            <div class="kpi-label">Total de Registros</div>
            <div class="kpi-value"><?= number_format($logs_total) ?></div>
            <div class="kpi-sub">No histórico completo</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">🔎</div>
            <div class="kpi-label">Tipos de Ação</div>
            <div class="kpi-value"><?= $acoes_unicas ?></div>
            <div class="kpi-sub">Eventos distintos</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">🗂️</div>
            <div class="kpi-label">Exibindo</div>
            <div class="kpi-value"><?= $total_rows ?></div>
            <div class="kpi-sub">Resultados com filtro atual</div>
        </div>
    </div>

    <!-- FILTROS -->
    <form method="GET" class="filter-bar">
        <div class="filter-group">
            <label>Busca</label>
            <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" placeholder="Usuário, ação, IP...">
        </div>
        <div class="filter-group">
            <label>Tipo de Ação</label>
            <select name="acao">
                <option value="">Todas</option>
                <?php foreach($acoes_disponiveis as $a): ?>
                <option value="<?= htmlspecialchars($a) ?>" <?= $acao===$a?'selected':''?>><?= htmlspecialchars($a) ?></option>
                <?php endforeach; ?>
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
        <button type="submit" class="btn-admin-primary">Filtrar</button>
        <a href="admin_logs.php" class="btn-admin-ghost">Limpar</a>
    </form>

    <!-- TABELA + TOP AÇÕES -->
    <div class="two-col">
        <div class="admin-card">
            <div class="card-header">
                <span class="card-title">Registros</span>
                <span style="font-size:12px; color:var(--muted);">Página <?= $pag ?> de <?= max(1,$total_pags) ?></span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Data / Hora</th>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th>Detalhes</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($logs as $log):
                        [$icone, $cor] = acao_style($log['acao']);
                    ?>
                    <tr>
                        <td style="font-size:12px; color:var(--text2); white-space:nowrap;">
                            <?= date('d/m/Y', strtotime($log['data'])) ?><br>
                            <span style="color:var(--muted);"><?= date('H:i:s', strtotime($log['data'])) ?></span>
                        </td>
                        <td>
                            <?php if($log['usuario_nome']): ?>
                            <div style="font-weight:700; font-size:13px;"><?= htmlspecialchars($log['usuario_nome']) ?></div>
                            <div style="font-size:10px; color:var(--muted); text-transform:uppercase;"><?= $log['usuario_nivel'] ?? '' ?></div>
                            <?php else: ?>
                            <span style="color:var(--muted); font-size:12px;">Sistema</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="log-acao log-<?= $cor ?>">
                                <?= $icone ?> <?= htmlspecialchars($log['acao']) ?>
                            </span>
                        </td>
                        <td style="font-size:12px; color:var(--text2); max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            <?= htmlspecialchars($log['detalhes'] ?? '—') ?>
                        </td>
                        <td style="font-size:11px; color:var(--muted); font-family:monospace;"><?= htmlspecialchars($log['ip'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($logs)): ?>
                    <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:50px;">Nenhum log encontrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if($total_pags > 1): ?>
            <div class="pagination">
                <?php for($i=1; $i<=$total_pags; $i++): ?>
                <a href="?pag=<?=$i?>&busca=<?=urlencode($busca)?>&acao=<?=urlencode($acao)?>&data_ini=<?=$data_ini?>&data_fim=<?=$data_fim?>"
                   class="page-btn <?=$i==$pag?'active':''?>"><?=$i?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- TOP AÇÕES -->
        <div class="admin-card" style="height:fit-content;">
            <div class="card-header">
                <span class="card-title">Ações Frequentes</span>
                <span style="font-size:11px;color:var(--muted);">Últimos 7 dias</span>
            </div>
            <?php foreach($top_acoes as $ta):
                [$icone, $cor] = acao_style($ta['acao']);
            ?>
            <div class="top-acao-row">
                <div>
                    <span class="log-acao log-<?= $cor ?>" style="margin-bottom:2px;"><?= $icone ?> <?= htmlspecialchars($ta['acao']) ?></span>
                </div>
                <span class="top-acao-count"><?= $ta['total'] ?></span>
            </div>
            <?php endforeach; ?>
            <?php if(empty($top_acoes)): ?>
            <p style="color:var(--muted);font-size:13px;padding:20px 0;">Sem atividade nos últimos 7 dias.</p>
            <?php endif; ?>

            <div style="margin-top:24px; padding-top:20px; border-top:1px solid var(--border);">
                <div style="font-size:10px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:14px;">Acesso restrito</div>
                <p style="font-size:12px;color:var(--text2);line-height:1.6;">
                    Logs visíveis apenas para <strong>Admin</strong> e <strong>Super Admin</strong>. 
                    Gerentes e operadores não têm acesso a esta área.
                </p>
            </div>
        </div>
    </div>

</main>

<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>