<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

if (!isset($_SESSION['usuario_nivel']) ||
    !in_array($_SESSION['usuario_nivel'], ['admin','superadmin'])) {
    header("Location: login.php"); exit;
}

// ── MARCAR LIDO ───────────────────────────────────────────
if (isset($_GET['marcar_lido'])) {
    $pdo->prepare("UPDATE alertas SET lido=1, lido_por=?, lido_em=NOW() WHERE id=?")
        ->execute([$_SESSION['usuario_id'], (int)$_GET['marcar_lido']]);
    header("Location: admin_alertas.php"); exit;
}
if (isset($_GET['marcar_todos'])) {
    $pdo->prepare("UPDATE alertas SET lido=1, lido_por=?, lido_em=NOW() WHERE lido=0")
        ->execute([$_SESSION['usuario_id']]);
    header("Location: admin_alertas.php?msg=todos_lidos"); exit;
}

// ── DISPARAR ANÁLISE MANUAL ───────────────────────────────
if (isset($_GET['rodar_analise']) && $_SESSION['usuario_nivel'] === 'superadmin') {
    ob_start();
    include 'analisar_produtos.php';
    $output_analise = ob_get_clean();
    header("Location: admin_alertas.php?msg=analise_ok"); exit;
}

// ── FILTROS ───────────────────────────────────────────────
$filtro_tipo  = $_GET['tipo']   ?? '';
$filtro_nivel = $_GET['nivel']  ?? '';
$filtro_lido  = $_GET['lido']   ?? 'nao';
$pag          = max(1, (int)($_GET['pag'] ?? 1));
$por_pag      = 20;
$offset       = ($pag - 1) * $por_pag;

$where  = ['1=1'];
$params = [];
if ($filtro_tipo)  { $where[] = 'tipo = ?';  $params[] = $filtro_tipo; }
if ($filtro_nivel) { $where[] = 'nivel = ?'; $params[] = $filtro_nivel; }
if ($filtro_lido === 'nao') { $where[] = 'lido = 0'; }
if ($filtro_lido === 'sim') { $where[] = 'lido = 1'; }

$sql_where = implode(' AND ', $where);

$total_rows = $pdo->prepare("SELECT COUNT(*) FROM alertas WHERE $sql_where");
$total_rows->execute($params);
$total_rows = $total_rows->fetchColumn();
$total_pags = ceil($total_rows / $por_pag);

$alertas = $pdo->prepare("
    SELECT a.*, u.nome as lido_por_nome
    FROM alertas a
    LEFT JOIN usuarios u ON a.lido_por = u.id
    WHERE $sql_where
    ORDER BY
        a.lido ASC,
        FIELD(a.nivel,'critico','aviso','info'),
        a.data_criacao DESC
    LIMIT $por_pag OFFSET $offset
");
$alertas->execute($params);
$alertas = $alertas->fetchAll(PDO::FETCH_ASSOC);

// KPIs
$c_critico   = $pdo->query("SELECT COUNT(*) FROM alertas WHERE nivel='critico' AND lido=0")->fetchColumn();
$c_aviso     = $pdo->query("SELECT COUNT(*) FROM alertas WHERE nivel='aviso'   AND lido=0")->fetchColumn();
$c_nao_lidos = $pdo->query("SELECT COUNT(*) FROM alertas WHERE lido=0")->fetchColumn();
$c_hoje      = $pdo->query("SELECT COUNT(*) FROM alertas WHERE DATE(data_criacao)=CURDATE()")->fetchColumn();

// Badges sidebar
$devolucoes_pend = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();
$estoque_critico = $pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque<=3 AND ativo=1")->fetchColumn();
$p_pendente_sb   = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();

$meta_tipo = [
    'produto_problematico' => ['label'=>'Produto Problemático', 'icone'=>'⚠️'],
    'estoque_critico'      => ['label'=>'Estoque Crítico',      'icone'=>'📦'],
    'pedido_parado'        => ['label'=>'Pedido Parado',        'icone'=>'⏰'],
    'avaliacao_negativa'   => ['label'=>'Avaliação Negativa',   'icone'=>'⭐'],
    'devolucao_alta'       => ['label'=>'Devolução Alta',       'icone'=>'🔄'],
    'margem_baixa'         => ['label'=>'Margem Baixa',         'icone'=>'💰'],
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertas | Alto Jordão Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <style>
        .filter-bar { background:var(--white); border:1px solid var(--border); border-radius:30px; padding:16px 24px; display:flex; gap:12px; align-items:flex-end; margin-bottom:22px; flex-wrap:wrap; box-shadow:var(--shadow); }
        .filter-group { display:flex; flex-direction:column; gap:6px; }
        .filter-group label { font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1px; }
        .filter-group select { background:var(--grey-bg); border:1px solid var(--border); border-radius:50px; color:var(--black); padding:9px 16px; font-family:var(--font-main); font-size:13px; outline:none; }

        .alerta-card { background:var(--white); border:1px solid var(--border); border-radius:20px; padding:20px 24px; margin-bottom:12px; display:flex; gap:16px; align-items:flex-start; box-shadow:var(--shadow); transition:var(--transition); }
        .alerta-card:hover { transform:translateY(-2px); }
        .alerta-card.lido { opacity:.5; }
        .alerta-card.critico { border-left:4px solid var(--danger); }
        .alerta-card.aviso   { border-left:4px solid var(--warning); }
        .alerta-card.info    { border-left:4px solid #1976d2; }

        .alerta-icon { font-size:28px; flex-shrink:0; width:44px; text-align:center; }

        .alerta-body { flex:1; min-width:0; }
        .alerta-titulo { font-weight:800; font-size:14px; margin-bottom:4px; }
        .alerta-desc   { font-size:12px; color:var(--text2); line-height:1.6; margin-bottom:8px; }
        .alerta-meta   { font-size:11px; color:var(--muted); display:flex; gap:14px; flex-wrap:wrap; }

        .badge-nivel { display:inline-block; padding:3px 10px; border-radius:50px; font-size:10px; font-weight:800; text-transform:uppercase; }
        .badge-critico { background:rgba(255,77,77,.12); color:var(--danger); }
        .badge-aviso   { background:rgba(245,158,11,.12); color:#b45309; }
        .badge-info    { background:rgba(25,118,210,.1);  color:#1976d2; }

        .alerta-actions { display:flex; flex-direction:column; gap:8px; align-items:flex-end; flex-shrink:0; }
        .btn-lido { padding:7px 16px; border-radius:50px; background:var(--grey-bg); color:var(--text2); border:1px solid var(--border); font-size:11px; font-weight:800; text-decoration:none; text-transform:uppercase; transition:var(--transition); white-space:nowrap; }
        .btn-lido:hover { background:var(--black); color:var(--white); }
        .btn-ver { padding:7px 16px; border-radius:50px; background:var(--black); color:var(--white); font-size:11px; font-weight:800; text-decoration:none; text-transform:uppercase; transition:var(--transition); white-space:nowrap; }
        .btn-ver:hover { opacity:.8; }

        .status-tabs { display:flex; gap:8px; margin-bottom:22px; flex-wrap:wrap; }
        .tab-btn { padding:9px 20px; border-radius:50px; border:1px solid var(--border); background:var(--white); color:var(--text2); font-size:12px; font-weight:700; text-decoration:none; transition:var(--transition); white-space:nowrap; }
        .tab-btn:hover { background:var(--grey-bg); }
        .tab-btn.active { background:var(--black); color:var(--white); border-color:var(--black); }

        .pagination { display:flex; gap:8px; justify-content:center; margin-top:24px; }
        .page-btn { padding:8px 14px; border-radius:50px; border:1px solid var(--border); background:var(--white); color:var(--text2); text-decoration:none; font-size:12px; font-weight:700; transition:var(--transition); }
        .page-btn:hover, .page-btn.active { background:var(--black); color:var(--white); border-color:var(--black); }

        .msg-ok { background:#e8f5e9; border:1px solid #c8e6c9; color:var(--success); padding:12px 22px; border-radius:50px; margin-bottom:20px; font-size:13px; font-weight:700; display:inline-block; }

        .btn-analise { background:var(--black); color:var(--white); padding:11px 22px; border-radius:50px; font-size:12px; font-weight:800; text-decoration:none; text-transform:uppercase; letter-spacing:1px; transition:var(--transition); }
        .btn-analise:hover { opacity:.8; }
        .btn-marcar-todos { background:var(--grey-bg); color:var(--text2); border:1px solid var(--border); padding:11px 22px; border-radius:50px; font-size:12px; font-weight:800; text-decoration:none; text-transform:uppercase; transition:var(--transition); }
        .btn-marcar-todos:hover { background:var(--black); color:var(--white); }
    </style>
</head>
<body class="admin-page">

<aside class="admin-sidebar">
    <div class="sb-logo">ALTO JORDÃO</div>
    <div class="sb-section">
        <span class="sb-section-title">Visão Geral</span>
        <a href="admin_dashboard.php" class="sb-item">📊 Dashboard</a>
        <a href="admin_alertas.php"   class="sb-item active">🔔 Alertas <?php if($c_nao_lidos>0): ?><span class="sb-badge"><?= $c_nao_lidos ?></span><?php endif; ?></a>
    </div>
    <div class="sb-section">
        <span class="sb-section-title">Vendas</span>
        <a href="admin_pedidos.php"    class="sb-item">🛒 Pedidos <?php if($p_pendente_sb>0): ?><span class="sb-badge"><?= $p_pendente_sb ?></span><?php endif; ?></a>
        <a href="admin_vendas.php"     class="sb-item">💰 Financeiro</a>
        <a href="entregas.php"         class="sb-item">📦 Logística</a>
        <a href="admin_devolucoes.php" class="sb-item">🔄 Devoluções <?php if($devolucoes_pend>0): ?><span class="sb-badge"><?= $devolucoes_pend ?></span><?php endif; ?></a>
    </div>
    <div class="sb-section">
        <span class="sb-section-title">Catálogo</span>
        <a href="admin_produtos.php"   class="sb-item">👕 Produtos</a>
        <a href="admin_estoque.php"    class="sb-item">📋 Estoque <?php if($estoque_critico>0): ?><span class="sb-badge"><?= $estoque_critico ?></span><?php endif; ?></a>
        <a href="admin_categorias.php" class="sb-item">🏷️ Categorias</a>
        <a href="admin_colecoes.php"   class="sb-item">✨ Coleções</a>
        <a href="admin_marcas.php"     class="sb-item">🔖 Marcas</a>
    </div>
    <div class="sb-section">
        <span class="sb-section-title">Usuários</span>
        <a href="admin_clientes.php" class="sb-item">👥 Clientes</a>
        <a href="admin_admins.php"   class="sb-item">🛡️ Administradores</a>
    </div>
    <div class="sb-section">
        <span class="sb-section-title">Marketing</span>
        <a href="admin_cupons.php"              class="sb-item">🎟️ Cupons</a>
        <a href="admin_campanhas_sazonais.php"  class="sb-item">🎄 Campanhas</a>
        <a href="admin_avaliacoes.php"          class="sb-item">⭐ Avaliações</a>
    </div>
    <div class="sb-section">
        <span class="sb-section-title">Sistema</span>
        <a href="admin_relatorios.php"           class="sb-item">📈 Relatórios</a>
        <a href="admin_logs.php"                 class="sb-item">🔍 Logs</a>
        <a href="admin_configuracoes.php"        class="sb-item">⚙️ Configurações</a>
        <a href="admin_configuracoes_alertas.php"class="sb-item">🔔 Config. Alertas</a>
    </div>
    <div class="sb-footer">
        <div class="sb-user">
            <div class="sb-avatar"><?= strtoupper(substr($_SESSION['usuario_nome']??'A',0,1)) ?></div>
            <div class="sb-user-info">
                <small><?= strtoupper($_SESSION['usuario_nivel']??'admin') ?></small>
                <strong><?= explode(' ',$_SESSION['usuario_nome']??'Admin')[0] ?></strong>
            </div>
        </div>
        <a href="index.php"  class="sb-item">🏪 Ver Loja</a>
        <a href="logout.php" class="sb-item" style="color:var(--danger);">🚪 Sair</a>
    </div>
</aside>

<main class="admin-main">
    <div class="admin-topbar">
        <div>
            <h1>Central de Alertas</h1>
            <p>Notificações geradas automaticamente pelo motor de análise.</p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <?php if($_SESSION['usuario_nivel']==='superadmin'): ?>
            <a href="?rodar_analise=1" class="btn-analise"
               onclick="return confirm('Rodar análise de produtos agora?')">
                ⚡ Rodar Análise Agora
            </a>
            <?php endif; ?>
            <?php if($c_nao_lidos > 0): ?>
            <a href="?marcar_todos=1" class="btn-marcar-todos"
               onclick="return confirm('Marcar todos os alertas como lidos?')">
                ✓ Marcar Todos como Lidos
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if(isset($_GET['msg'])): ?>
    <div class="msg-ok">
        <?php
        $msgs = [
            'todos_lidos' => '✓ Todos os alertas marcados como lidos.',
            'analise_ok'  => '✓ Análise executada! Novos alertas gerados.',
        ];
        echo $msgs[$_GET['msg']] ?? '';
        ?>
    </div>
    <?php endif; ?>

    <!-- KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card featured">
            <div class="kpi-icon">🔔</div>
            <div class="kpi-label">Não Lidos</div>
            <div class="kpi-value"><?= $c_nao_lidos ?></div>
            <div class="kpi-sub">Alertas pendentes</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">🚨</div>
            <div class="kpi-label">Críticos</div>
            <div class="kpi-value" style="color:var(--danger);"><?= $c_critico ?></div>
            <div class="kpi-sub">Requerem ação imediata</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">⚠️</div>
            <div class="kpi-label">Avisos</div>
            <div class="kpi-value" style="color:var(--warning);"><?= $c_aviso ?></div>
            <div class="kpi-sub">Requerem atenção</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">📅</div>
            <div class="kpi-label">Gerados Hoje</div>
            <div class="kpi-value"><?= $c_hoje ?></div>
            <div class="kpi-sub">Última análise</div>
        </div>
    </div>

    <!-- Tabs lido/não lido -->
    <div class="status-tabs">
        <a href="?lido=nao&tipo=<?= $filtro_tipo ?>&nivel=<?= $filtro_nivel ?>"
           class="tab-btn <?= $filtro_lido==='nao'?'active':''?>">
            🔔 Não Lidos (<?= $c_nao_lidos ?>)
        </a>
        <a href="?lido=sim&tipo=<?= $filtro_tipo ?>&nivel=<?= $filtro_nivel ?>"
           class="tab-btn <?= $filtro_lido==='sim'?'active':''?>">
            ✓ Lidos
        </a>
        <a href="?lido=todos&tipo=<?= $filtro_tipo ?>&nivel=<?= $filtro_nivel ?>"
           class="tab-btn <?= $filtro_lido==='todos'?'active':''?>">
            Todos
        </a>
    </div>

    <!-- Filtros -->
    <form method="GET" class="filter-bar">
        <input type="hidden" name="lido" value="<?= $filtro_lido ?>">
        <div class="filter-group">
            <label>Tipo</label>
            <select name="tipo">
                <option value="">Todos os tipos</option>
                <?php foreach($meta_tipo as $k => $v): ?>
                <option value="<?= $k ?>" <?= $filtro_tipo===$k?'selected':''?>>
                    <?= $v['icone'] ?> <?= $v['label'] ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Nível</label>
            <select name="nivel">
                <option value="">Todos</option>
                <option value="critico" <?= $filtro_nivel==='critico'?'selected':''?>>🚨 Crítico</option>
                <option value="aviso"   <?= $filtro_nivel==='aviso'  ?'selected':''?>>⚠️ Aviso</option>
                <option value="info"    <?= $filtro_nivel==='info'   ?'selected':''?>>ℹ️ Info</option>
            </select>
        </div>
        <button type="submit" class="btn-admin-primary">Filtrar</button>
        <a href="admin_alertas.php" class="btn-admin-ghost">Limpar</a>
    </form>

    <!-- Lista de alertas -->
    <?php if(empty($alertas)): ?>
    <div class="admin-card" style="text-align:center; padding:60px; color:var(--muted);">
        <div style="font-size:48px; margin-bottom:16px;">✅</div>
        <strong>Nenhum alerta encontrado para este filtro.</strong>
    </div>
    <?php endif; ?>

    <?php foreach($alertas as $al):
        $m = $meta_tipo[$al['tipo']] ?? ['label'=>$al['tipo'], 'icone'=>'🔔'];
        $nivel_badge = ['critico'=>'badge-critico','aviso'=>'badge-aviso','info'=>'badge-info'][$al['nivel']] ?? '';

        // Link "Ver" baseado no tipo de referência
        $link_ver = null;
        if ($al['referencia_tipo'] === 'produto' && $al['referencia_id']) {
            $link_ver = 'produto.php?id=' . $al['referencia_id'];
        } elseif ($al['referencia_tipo'] === 'pedido' && $al['referencia_id']) {
            $link_ver = 'admin_pedidos.php?busca=' . $al['referencia_id'];
        }
    ?>
    <div class="alerta-card <?= $al['nivel'] ?> <?= $al['lido']?'lido':'' ?>">
        <div class="alerta-icon"><?= $m['icone'] ?></div>
        <div class="alerta-body">
            <div class="alerta-titulo"><?= htmlspecialchars($al['titulo']) ?></div>
            <div class="alerta-desc"><?= htmlspecialchars($al['descricao']) ?></div>
            <div class="alerta-meta">
                <span class="badge-nivel <?= $nivel_badge ?>"><?= $al['nivel'] ?></span>
                <span><?= $m['label'] ?></span>
                <span>📅 <?= date('d/m/Y H:i', strtotime($al['data_criacao'])) ?></span>
                <?php if($al['lido'] && $al['lido_por_nome']): ?>
                <span style="color:var(--success);">✓ Lido por <?= htmlspecialchars($al['lido_por_nome']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="alerta-actions">
            <?php if($link_ver): ?>
            <a href="<?= $link_ver ?>" class="btn-ver" target="_blank">Ver →</a>
            <?php endif; ?>
            <?php if(!$al['lido']): ?>
            <a href="?marcar_lido=<?= $al['id'] ?>&lido=<?= $filtro_lido ?>&tipo=<?= $filtro_tipo ?>"
               class="btn-lido">Marcar lido</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Paginação -->
    <?php if($total_pags > 1): ?>
    <div class="pagination">
        <?php for($i=1;$i<=$total_pags;$i++): ?>
        <a href="?pag=<?=$i?>&lido=<?=$filtro_lido?>&tipo=<?=$filtro_tipo?>&nivel=<?=$filtro_nivel?>"
           class="page-btn <?=$i===$pag?'active':''?>"><?=$i?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</main>

<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>