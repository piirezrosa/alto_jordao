<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

if (!isset($_SESSION['usuario_nivel']) || !in_array($_SESSION['usuario_nivel'], ['admin','superadmin','gerente'])) {
    header("Location: login.php"); exit();
}

// ── APROVAR / REPROVAR ────────────────────────
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id     = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'aprovar') {
        $pdo->prepare("UPDATE avaliacoes SET status='aprovado' WHERE id=?")->execute([$id]);
    } elseif ($action === 'reprovar') {
        $pdo->prepare("UPDATE avaliacoes SET status='reprovado' WHERE id=?")->execute([$id]);
    } elseif ($action === 'excluir') {
        $pdo->prepare("DELETE FROM avaliacoes WHERE id=?")->execute([$id]);
    }

    header("Location: admin_avaliacoes.php?msg=$action&filtro=".($_GET['filtro']??'pendente')); exit();
}

// ── FILTROS ───────────────────────────────────
$filtro  = $_GET['filtro']  ?? 'pendente';
$busca   = $_GET['busca']   ?? '';
$nota    = $_GET['nota']    ?? '';
$pag     = max(1, (int)($_GET['pag'] ?? 1));
$por_pag = 15;
$offset  = ($pag - 1) * $por_pag;

$where  = ['1=1'];
$params = [];

if ($filtro !== 'todas') { $where[] = "a.status = ?"; $params[] = $filtro; }
if ($busca)  { $where[] = "(p.nome LIKE ? OR u.nome LIKE ? OR a.comentario LIKE ?)"; $params[] = "%$busca%"; $params[] = "%$busca%"; $params[] = "%$busca%"; }
if ($nota)   { $where[] = "a.nota = ?"; $params[] = (int)$nota; }

$sql_where = implode(' AND ', $where);

$total_rows = $pdo->prepare("SELECT COUNT(*) FROM avaliacoes a LEFT JOIN usuarios u ON a.usuario_id = u.id JOIN produtos p ON a.produto_id = p.id WHERE $sql_where");
$total_rows->execute($params);
$total_rows = $total_rows->fetchColumn();
$total_pags = ceil($total_rows / $por_pag);

$avaliacoes = $pdo->prepare("
    SELECT a.*,
           p.nome as produto_nome, p.imagem as produto_img,
           COALESCE(u.nome, a.titulo) as autor,
           u.email as autor_email
    FROM avaliacoes a
    JOIN produtos p ON a.produto_id = p.id
    LEFT JOIN usuarios u ON a.usuario_id = u.id
    WHERE $sql_where
    ORDER BY a.data DESC
    LIMIT $por_pag OFFSET $offset
");
$avaliacoes->execute($params);
$avaliacoes = $avaliacoes->fetchAll(PDO::FETCH_ASSOC);

// Contagens
$c_pendente  = $pdo->query("SELECT COUNT(*) FROM avaliacoes WHERE status='pendente'")->fetchColumn();
$c_aprovado  = $pdo->query("SELECT COUNT(*) FROM avaliacoes WHERE status='aprovado'")->fetchColumn();
$c_reprovado = $pdo->query("SELECT COUNT(*) FROM avaliacoes WHERE status='reprovado'")->fetchColumn();
$media_geral = $pdo->query("SELECT ROUND(AVG(nota),1) FROM avaliacoes WHERE status='aprovado'")->fetchColumn() ?: 0;

// Badges sidebar
$devolucoes_pend = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();
$estoque_critico = $pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque<=3 AND ativo=1")->fetchColumn();
$p_pendente_sb   = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();

define('CONTEUDO_AUTORIZADO', true);
$pagina_atual = 'avaliacoes';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avaliações | Alto Jordão Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <style>
        .status-tabs { display:flex; gap:8px; margin-bottom:22px; flex-wrap:wrap; }
        .tab-btn { padding:9px 20px; border-radius:50px; border:1px solid var(--border); background:var(--white); color:var(--text2); font-size:12px; font-weight:700; cursor:pointer; text-decoration:none; transition:var(--transition); white-space:nowrap; }
        .tab-btn:hover { background:var(--grey-bg); color:var(--black); }
        .tab-btn.active { background:var(--black); color:var(--white); border-color:var(--black); }

        .filter-bar { background:var(--white); border:1px solid var(--border); border-radius:30px; padding:16px 24px; display:flex; gap:12px; align-items:flex-end; margin-bottom:22px; flex-wrap:wrap; box-shadow:var(--shadow); }
        .filter-group { display:flex; flex-direction:column; gap:6px; }
        .filter-group label { font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1px; }
        .filter-group input, .filter-group select { background:var(--grey-bg); border:1px solid var(--border); border-radius:50px; color:var(--black); padding:9px 16px; font-family:var(--font-main); font-size:13px; outline:none; min-width:140px; }

        /* Card de avaliação */
        .aval-card { background:var(--white); border:1px solid var(--border); border-radius:20px; padding:22px; margin-bottom:14px; box-shadow:var(--shadow); transition:var(--transition); }
        .aval-card:hover { border-color:#ccc; transform:translateY(-2px); }

        .aval-header { display:flex; gap:14px; align-items:flex-start; margin-bottom:14px; }
        .prod-thumb  { width:50px; height:50px; border-radius:12px; object-fit:cover; background:var(--grey-bg); flex-shrink:0; }

        .aval-meta { flex:1; min-width:0; }
        .aval-produto { font-weight:800; font-size:14px; margin-bottom:2px; }
        .aval-autor   { font-size:12px; color:var(--text2); }
        .aval-data    { font-size:11px; color:var(--muted); }

        .stars { font-size:16px; letter-spacing:1px; flex-shrink:0; }

        .aval-texto { font-size:13px; color:#555; line-height:1.6; padding:12px 16px; background:var(--grey-bg); border-radius:12px; border-left:3px solid var(--black); margin-bottom:14px; }

        .aval-actions { display:flex; gap:10px; align-items:center; }

        .btn-aprovar { padding:8px 18px; border-radius:50px; background:rgba(46,125,50,.1); color:var(--success); border:1px solid rgba(46,125,50,.3); font-size:11px; font-weight:800; text-decoration:none; text-transform:uppercase; transition:var(--transition); }
        .btn-aprovar:hover { background:var(--success); color:var(--white); }

        .btn-reprovar { padding:8px 18px; border-radius:50px; background:rgba(245,158,11,.1); color:#b45309; border:1px solid rgba(245,158,11,.3); font-size:11px; font-weight:800; text-decoration:none; text-transform:uppercase; transition:var(--transition); }
        .btn-reprovar:hover { background:#f59e0b; color:var(--white); }

        .btn-excluir { padding:8px 18px; border-radius:50px; background:rgba(255,77,77,.1); color:var(--danger); border:1px solid rgba(255,77,77,.3); font-size:11px; font-weight:800; text-decoration:none; text-transform:uppercase; transition:var(--transition); }
        .btn-excluir:hover { background:var(--danger); color:var(--white); }

        .badge { display:inline-block; padding:4px 12px; border-radius:50px; font-size:10px; font-weight:800; text-transform:uppercase; }
        .badge-pendente  { background:rgba(245,158,11,.12); color:#b45309; }
        .badge-aprovado  { background:rgba(46,125,50,.12);  color:var(--success); }
        .badge-reprovado { background:rgba(255,77,77,.12);  color:var(--danger); }

        .msg-ok  { background:#e8f5e9; border:1px solid #c8e6c9; color:var(--success); padding:12px 22px; border-radius:50px; margin-bottom:20px; font-size:13px; font-weight:700; display:inline-block; }
        .msg-err { background:rgba(255,77,77,.08); border:1px solid rgba(255,77,77,.25); color:var(--danger); padding:12px 22px; border-radius:50px; margin-bottom:20px; font-size:13px; font-weight:700; display:inline-block; }

        .pagination { display:flex; gap:8px; justify-content:center; margin-top:24px; }
        .page-btn { padding:8px 14px; border-radius:50px; border:1px solid var(--border); background:var(--white); color:var(--text2); text-decoration:none; font-size:12px; font-weight:700; transition:var(--transition); }
        .page-btn:hover, .page-btn.active { background:var(--black); color:var(--white); border-color:var(--black); }
    </style>
</head>
<body class="admin-page">

<?php include 'sidebar.php'; ?>

<main class="admin-main">
    <div class="admin-topbar">
        <div>
            <h1>Avaliações</h1>
            <p>Modere e gerencie as avaliações dos produtos.</p>
        </div>
    </div>

    <?php
    $msgs = ['aprovar'=>'✓ Avaliação aprovada!','reprovar'=>'✓ Avaliação reprovada.','excluir'=>'✓ Avaliação excluída.'];
    if(isset($_GET['msg']) && isset($msgs[$_GET['msg']])):
    ?>
    <div class="msg-ok"><?= $msgs[$_GET['msg']] ?></div>
    <?php endif; ?>

    <!-- KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card featured">
            <div class="kpi-icon">⏳</div>
            <div class="kpi-label">Pendentes</div>
            <div class="kpi-value"><?= $c_pendente ?></div>
            <div class="kpi-sub">Aguardando moderação</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">✅</div>
            <div class="kpi-label">Aprovadas</div>
            <div class="kpi-value" style="color:var(--success);"><?= $c_aprovado ?></div>
            <div class="kpi-sub">Publicadas na loja</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">❌</div>
            <div class="kpi-label">Reprovadas</div>
            <div class="kpi-value" style="color:var(--danger);"><?= $c_reprovado ?></div>
            <div class="kpi-sub">Não publicadas</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">⭐</div>
            <div class="kpi-label">Nota Média Geral</div>
            <div class="kpi-value"><?= $media_geral ?>/5</div>
            <div class="kpi-sub">De avaliações aprovadas</div>
        </div>
    </div>

    <!-- TABS -->
    <div class="status-tabs">
        <a href="?filtro=pendente"  class="tab-btn <?= $filtro==='pendente' ?'active':''?>">⏳ Pendentes (<?= $c_pendente ?>)</a>
        <a href="?filtro=aprovado"  class="tab-btn <?= $filtro==='aprovado' ?'active':''?>">✅ Aprovadas (<?= $c_aprovado ?>)</a>
        <a href="?filtro=reprovado" class="tab-btn <?= $filtro==='reprovado'?'active':''?>">❌ Reprovadas (<?= $c_reprovado ?>)</a>
        <a href="?filtro=todas"     class="tab-btn <?= $filtro==='todas'    ?'active':''?>">Todas</a>
    </div>

    <!-- FILTROS -->
    <form method="GET" class="filter-bar">
        <input type="hidden" name="filtro" value="<?= $filtro ?>">
        <div class="filter-group">
            <label>Busca</label>
            <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" placeholder="Produto, autor, comentário...">
        </div>
        <div class="filter-group">
            <label>Nota</label>
            <select name="nota">
                <option value="">Todas</option>
                <?php for($n=5; $n>=1; $n--): ?>
                <option value="<?= $n ?>" <?= $nota==$n?'selected':''?>><?= str_repeat('⭐',$n) ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <button type="submit" class="btn-admin-primary">Filtrar</button>
        <a href="?filtro=<?= $filtro ?>" class="btn-admin-ghost">Limpar</a>
    </form>

    <!-- LISTA DE AVALIAÇÕES -->
    <?php if(empty($avaliacoes)): ?>
    <div class="admin-card" style="text-align:center; padding:60px; color:var(--muted);">
        Nenhuma avaliação encontrada para este filtro.
    </div>
    <?php endif; ?>

    <?php foreach($avaliacoes as $av): ?>
    <div class="aval-card">
        <div class="aval-header">
            <img class="prod-thumb"
                 src="img/produtos/<?= htmlspecialchars($av['produto_img']) ?>"
                 onerror="this.style.opacity='.2'">
            <div class="aval-meta">
                <div class="aval-produto"><?= htmlspecialchars($av['produto_nome']) ?></div>
                <div class="aval-autor">
                    <?= htmlspecialchars($av['autor'] ?: 'Anônimo') ?>
                    <?php if($av['autor_email']): ?>
                    <span style="color:var(--muted);">— <?= htmlspecialchars($av['autor_email']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="aval-data"><?= date('d/m/Y \à\s H:i', strtotime($av['data'])) ?></div>
            </div>
            <div style="display:flex; flex-direction:column; align-items:flex-end; gap:6px;">
                <span class="stars"><?= str_repeat('⭐',$av['nota']) . str_repeat('☆',5-$av['nota']) ?></span>
                <span class="badge badge-<?= $av['status'] ?>"><?= $av['status'] ?></span>
            </div>
        </div>

        <div class="aval-texto"><?= nl2br(htmlspecialchars($av['comentario'])) ?></div>

        <div class="aval-actions">
            <?php if($av['status'] !== 'aprovado'): ?>
            <a href="?action=aprovar&id=<?= $av['id'] ?>&filtro=<?= $filtro ?>"
               class="btn-aprovar"
               onclick="return confirm('Aprovar esta avaliação?')">✓ Aprovar</a>
            <?php endif; ?>

            <?php if($av['status'] !== 'reprovado'): ?>
            <a href="?action=reprovar&id=<?= $av['id'] ?>&filtro=<?= $filtro ?>"
               class="btn-reprovar"
               onclick="return confirm('Reprovar esta avaliação?')">✕ Reprovar</a>
            <?php endif; ?>

            <a href="produto.php?id=<?= $av['produto_id'] ?>" target="_blank"
               style="color:var(--muted); font-size:11px; font-weight:700; text-decoration:none; margin-left:auto;">
               Ver Produto →
            </a>

            <a href="?action=excluir&id=<?= $av['id'] ?>&filtro=<?= $filtro ?>"
               class="btn-excluir"
               onclick="return confirm('Excluir permanentemente esta avaliação?')">🗑 Excluir</a>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- PAGINAÇÃO -->
    <?php if($total_pags > 1): ?>
    <div class="pagination">
        <?php for($i=1; $i<=$total_pags; $i++): ?>
        <a href="?pag=<?=$i?>&filtro=<?=$filtro?>&busca=<?=urlencode($busca)?>&nota=<?=$nota?>"
           class="page-btn <?=$i==$pag?'active':''?>"><?=$i?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

</main>

<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>