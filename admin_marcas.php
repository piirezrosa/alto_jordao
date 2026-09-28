<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

if (!isset($_SESSION['usuario_nivel']) || !in_array($_SESSION['usuario_nivel'], ['admin','superadmin','gerente'])) {
    header("Location: login.php"); exit();
}

// ── CRIAR ─────────────────────────────────────
if (isset($_POST['acao']) && $_POST['acao'] === 'criar') {
    $nome = trim($_POST['nome']);
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8','ASCII//TRANSLIT',$nome)));
    $existe = $pdo->prepare("SELECT COUNT(*) FROM marcas WHERE slug=?"); $existe->execute([$slug]);
    if ($existe->fetchColumn()) $slug .= '-'.time();
    $logo = null;
    if (!empty($_FILES['logo']['name'])) {
        $ext  = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $logo = md5(time().rand()).".".$ext;
        if (!is_dir("img/marcas/")) mkdir("img/marcas/", 0777, true);
        move_uploaded_file($_FILES['logo']['tmp_name'], "img/marcas/".$logo);
    }
    $pdo->prepare("INSERT INTO marcas (nome, slug, logo, ativo) VALUES (?,?,?,1)")->execute([$nome, $slug, $logo]);
    $log = $pdo->prepare("INSERT INTO logs_sistema (usuario_id,acao,tabela,registro_id,detalhes,ip) VALUES (?,?,?,?,?,?)");
    $log->execute([$_SESSION['usuario_id']??null,'marca_criada','marcas',$pdo->lastInsertId(),$nome,$_SERVER['REMOTE_ADDR']]);
    header("Location: admin_marcas.php?msg=criada"); exit();
}

// ── EDITAR ────────────────────────────────────
if (isset($_POST['acao']) && $_POST['acao'] === 'editar') {
    $id   = (int)$_POST['id'];
    $nome = trim($_POST['nome']);
    $ativo= isset($_POST['ativo']) ? 1 : 0;
    $logo_atual = $_POST['logo_atual'];
    if (!empty($_FILES['logo']['name'])) {
        $ext  = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $logo_atual = md5(time().rand()).".".$ext;
        if (!is_dir("img/marcas/")) mkdir("img/marcas/", 0777, true);
        move_uploaded_file($_FILES['logo']['tmp_name'], "img/marcas/".$logo_atual);
    }
    $pdo->prepare("UPDATE marcas SET nome=?, logo=?, ativo=? WHERE id=?")->execute([$nome, $logo_atual, $ativo, $id]);
    header("Location: admin_marcas.php?msg=editada"); exit();
}

// ── TOGGLE / EXCLUIR ──────────────────────────
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $cur = $pdo->prepare("SELECT ativo FROM marcas WHERE id=?"); $cur->execute([$id]);
    $novo = $cur->fetchColumn() ? 0 : 1;
    $pdo->prepare("UPDATE marcas SET ativo=? WHERE id=?")->execute([$novo,$id]);
    header("Location: admin_marcas.php?msg=".($novo?'ativada':'desativada')); exit();
}
if (isset($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    $tem = $pdo->prepare("SELECT COUNT(*) FROM produtos WHERE marca_id=?"); $tem->execute([$id]);
    if ($tem->fetchColumn()==0) { $pdo->prepare("DELETE FROM marcas WHERE id=?")->execute([$id]); header("Location: admin_marcas.php?msg=excluida"); }
    else header("Location: admin_marcas.php?msg=erro_excluir");
    exit();
}

// ── DADOS ─────────────────────────────────────
$marcas = $pdo->query("
    SELECT m.*, (SELECT COUNT(*) FROM produtos p WHERE p.marca_id=m.id) as total_produtos
    FROM marcas m ORDER BY m.ativo DESC, m.nome
")->fetchAll(PDO::FETCH_ASSOC);

$editando = null;
if (isset($_GET['editar'])) {
    $editando = $pdo->prepare("SELECT * FROM marcas WHERE id=?");
    $editando->execute([$_GET['editar']]);
    $editando = $editando->fetch(PDO::FETCH_ASSOC);
}

$devolucoes_pend = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();
$estoque_critico = $pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque<=3 AND ativo=1")->fetchColumn();
$p_pendente_sb   = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();

define('CONTEUDO_AUTORIZADO', true);
$pagina_atual = 'marcas';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marcas | Alto Jordão Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <style>
        .page-grid { display:grid; grid-template-columns:1fr 360px; gap:22px; align-items:start; }
        .form-card .input-group { display:flex; flex-direction:column; gap:8px; margin-bottom:16px; }
        .form-card label { font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1px; }
        .form-card input, .form-card select {
            padding:12px 16px; border:1.5px solid var(--border); border-radius:50px;
            font-family:var(--font-main); font-size:13px; background:var(--grey-bg); color:var(--black); outline:none; transition:var(--transition);
        }
        .form-card input[type="file"] { border-radius:16px; border-style:dashed; padding:14px; cursor:pointer; }
        .form-card input:focus { border-color:var(--black); background:var(--white); }
        .check-row { display:flex; align-items:center; gap:10px; padding:12px 16px; background:var(--grey-bg); border-radius:50px; border:1.5px solid var(--border); }
        .check-row input[type="checkbox"] { width:16px; height:16px; accent-color:var(--black); }

        .marca-row { display:flex; align-items:center; gap:16px; padding:16px 0; border-bottom:1px solid var(--border); }
        .marca-row:last-child { border-bottom:none; }
        .marca-logo { width:48px; height:48px; border-radius:12px; object-fit:contain; background:var(--grey-bg); padding:6px; flex-shrink:0; }
        .marca-logo-placeholder { width:48px; height:48px; border-radius:12px; background:var(--grey-bg); display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
        .marca-info { flex:1; }
        .marca-nome { font-weight:800; font-size:14px; }
        .marca-slug { font-size:11px; color:var(--muted); margin-top:2px; }
        .badge-ativo   { background:rgba(46,125,50,.1); color:var(--success); padding:3px 10px; border-radius:50px; font-size:10px; font-weight:800; }
        .badge-inativo { background:rgba(255,77,77,.1); color:var(--danger); padding:3px 10px; border-radius:50px; font-size:10px; font-weight:800; }
        .badge-count   { background:var(--grey-bg); color:var(--muted); padding:3px 10px; border-radius:50px; font-size:10px; font-weight:800; }
        .row-actions { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
        .btn-sm { padding:6px 14px; border-radius:50px; font-size:10px; font-weight:800; text-decoration:none; text-transform:uppercase; transition:var(--transition); }
        .btn-sm-edit   { background:var(--black); color:var(--white); }
        .btn-sm-toggle { background:var(--grey-bg); color:var(--text2); border:1px solid var(--border); }
        .btn-sm-del    { background:rgba(255,77,77,.1); color:var(--danger); border:1px solid rgba(255,77,77,.25); }
        .btn-sm:hover  { opacity:.8; transform:translateY(-1px); }
        .msg-ok  { background:#e8f5e9; border:1px solid #c8e6c9; color:var(--success); padding:12px 22px; border-radius:50px; margin-bottom:20px; font-size:13px; font-weight:700; display:inline-block; }
        .msg-err { background:rgba(255,77,77,.08); border:1px solid rgba(255,77,77,.25); color:var(--danger); padding:12px 22px; border-radius:50px; margin-bottom:20px; font-size:13px; font-weight:700; display:inline-block; }
        .editing-banner { background:var(--black); color:var(--white); padding:12px 18px; border-radius:14px; font-size:13px; font-weight:700; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; }
        .editing-banner a { color:#aaa; font-size:11px; text-decoration:none; }
    </style>
</head>
<body class="admin-page">

<?php include 'sidebar.php'; ?>

<main class="admin-main">
    <div class="admin-topbar">
        <div><h1>Marcas</h1><p>Gerencie as marcas do catálogo da Alto Jordão.</p></div>
    </div>

    <?php if(isset($_GET['msg'])): ?>
    <?php $msgs=['criada'=>'✓ Marca criada!','editada'=>'✓ Marca atualizada!','excluida'=>'✓ Marca excluída!','ativada'=>'✓ Marca ativada!','desativada'=>'✓ Marca desativada!','erro_excluir'=>'⚠ Não é possível excluir: há produtos nesta marca.']; ?>
    <div class="<?= $_GET['msg']==='erro_excluir'?'msg-err':'msg-ok' ?>"><?= $msgs[$_GET['msg']]??'' ?></div>
    <?php endif; ?>

    <div class="kpi-grid">
        <div class="kpi-card featured"><div class="kpi-icon">🔖</div><div class="kpi-label">Total de Marcas</div><div class="kpi-value"><?= count($marcas) ?></div><div class="kpi-sub">No catálogo</div></div>
        <div class="kpi-card"><div class="kpi-icon">✅</div><div class="kpi-label">Ativas</div><div class="kpi-value"><?= count(array_filter($marcas,fn($m)=>$m['ativo'])) ?></div><div class="kpi-sub">Visíveis na loja</div></div>
        <div class="kpi-card"><div class="kpi-icon">👕</div><div class="kpi-label">Produtos Vinculados</div><div class="kpi-value"><?= array_sum(array_column($marcas,'total_produtos')) ?></div><div class="kpi-sub">Total com marca</div></div>
        <div class="kpi-card"><div class="kpi-icon">⭐</div><div class="kpi-label">Marca Principal</div>
            <div class="kpi-value" style="font-size:16px;"><?= !empty($marcas) ? htmlspecialchars(array_reduce($marcas,fn($c,$m)=>($m['total_produtos']>($c['total_produtos']??0)?$m:$c),$marcas[0])['nome']) : '—' ?></div>
            <div class="kpi-sub">Mais produtos</div>
        </div>
    </div>

    <div class="page-grid">
        <div class="admin-card">
            <div class="card-header"><span class="card-title">Todas as Marcas (<?= count($marcas) ?>)</span></div>
            <?php foreach($marcas as $m): ?>
            <div class="marca-row" style="<?= !$m['ativo']?'opacity:.55':'' ?>">
                <?php if($m['logo']): ?>
                <img src="img/marcas/<?= $m['logo'] ?>" class="marca-logo" onerror="this.style.opacity='.2'">
                <?php else: ?>
                <div class="marca-logo-placeholder">🔖</div>
                <?php endif; ?>
                <div class="marca-info">
                    <div class="marca-nome"><?= htmlspecialchars($m['nome']) ?></div>
                    <div class="marca-slug">slug: <?= $m['slug'] ?></div>
                </div>
                <span class="badge-count"><?= $m['total_produtos'] ?> produtos</span>
                <span class="<?= $m['ativo']?'badge-ativo':'badge-inativo' ?>"><?= $m['ativo']?'Ativa':'Inativa' ?></span>
                <div class="row-actions">
                    <a href="?editar=<?= $m['id'] ?>" class="btn-sm btn-sm-edit">Editar</a>
                    <a href="?toggle=<?= $m['id'] ?>" class="btn-sm btn-sm-toggle"><?= $m['ativo']?'Desativar':'Ativar' ?></a>
                    <?php if($m['total_produtos']==0): ?><a href="?excluir=<?= $m['id'] ?>" class="btn-sm btn-sm-del" onclick="return confirm('Excluir marca?')">Excluir</a><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($marcas)): ?><p style="text-align:center;color:var(--muted);padding:40px;font-size:13px;">Nenhuma marca cadastrada.</p><?php endif; ?>
        </div>

        <div class="admin-card form-card">
            <?php if($editando): ?>
            <div class="editing-banner">✏️ <?= htmlspecialchars($editando['nome']) ?><a href="admin_marcas.php">Cancelar ×</a></div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id"   value="<?= $editando['id'] ?>">
                <input type="hidden" name="logo_atual" value="<?= $editando['logo'] ?>">
                <div class="input-group"><label>Nome da Marca *</label><input type="text" name="nome" value="<?= htmlspecialchars($editando['nome']) ?>" required></div>
                <div class="input-group"><label>Novo Logo (opcional)</label><input type="file" name="logo" accept="image/*"></div>
                <?php if($editando['logo']): ?><img src="img/marcas/<?= $editando['logo'] ?>" style="width:60px;height:60px;object-fit:contain;border-radius:10px;background:var(--grey-bg);padding:6px;margin-bottom:14px;"><?php endif; ?>
                <div class="input-group"><label>Status</label><label class="check-row"><input type="checkbox" name="ativo" value="1" <?= $editando['ativo']?'checked':''?>>Marca ativa e visível</label></div>
                <button type="submit" class="btn-admin-primary" style="width:100%;padding:14px;border-radius:50px;justify-content:center;">Salvar Alterações</button>
            </form>
            <?php else: ?>
            <div class="card-header" style="margin-bottom:20px;"><span class="card-title">Nova Marca</span></div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="acao" value="criar">
                <div class="input-group"><label>Nome da Marca *</label><input type="text" name="nome" placeholder="Ex: Alto Jordão, Collab..." required></div>
                <div class="input-group"><label>Logo (opcional)</label><input type="file" name="logo" accept="image/*"></div>
                <button type="submit" class="btn-admin-primary" style="width:100%;padding:14px;border-radius:50px;justify-content:center;">Criar Marca</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</main>
<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>