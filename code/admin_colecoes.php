<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

if (!isset($_SESSION['usuario_nivel']) || !in_array($_SESSION['usuario_nivel'], ['admin','superadmin','gerente'])) {
    header("Location: login.php"); exit();
}

// ── CRIAR ─────────────────────────────────────
if (isset($_POST['acao']) && $_POST['acao'] === 'criar') {
    $nome       = trim($_POST['nome']);
    $descricao  = trim($_POST['descricao'] ?? '');
    $data_inicio= $_POST['data_inicio'] ?: null;
    $data_fim   = $_POST['data_fim']    ?: null;
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8','ASCII//TRANSLIT',$nome)));
    $existe = $pdo->prepare("SELECT COUNT(*) FROM colecoes WHERE slug=?"); $existe->execute([$slug]);
    if ($existe->fetchColumn()) $slug .= '-'.time();
    $pdo->prepare("INSERT INTO colecoes (nome, slug, descricao, data_inicio, data_fim, ativo) VALUES (?,?,?,?,?,1)")
        ->execute([$nome, $slug, $descricao, $data_inicio, $data_fim]);
    $log = $pdo->prepare("INSERT INTO logs_sistema (usuario_id, acao, tabela, registro_id, detalhes, ip) VALUES (?,?,?,?,?,?)");
    $log->execute([$_SESSION['usuario_id']??null, 'colecao_criada', 'colecoes', $pdo->lastInsertId(), $nome, $_SERVER['REMOTE_ADDR']]);
    header("Location: admin_colecoes.php?msg=criada"); exit();
}

// ── EDITAR ────────────────────────────────────
if (isset($_POST['acao']) && $_POST['acao'] === 'editar') {
    $id   = (int)$_POST['id'];
    $ativo= isset($_POST['ativo']) ? 1 : 0;
    $pdo->prepare("UPDATE colecoes SET nome=?, descricao=?, data_inicio=?, data_fim=?, ativo=? WHERE id=?")
        ->execute([trim($_POST['nome']), trim($_POST['descricao']??''), $_POST['data_inicio']?:null, $_POST['data_fim']?:null, $ativo, $id]);
    header("Location: admin_colecoes.php?msg=editada"); exit();
}

// ── TOGGLE ATIVO ──────────────────────────────
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $cur = $pdo->prepare("SELECT ativo FROM colecoes WHERE id=?"); $cur->execute([$id]);
    $novo = $cur->fetchColumn() ? 0 : 1;
    $pdo->prepare("UPDATE colecoes SET ativo=? WHERE id=?")->execute([$novo, $id]);
    header("Location: admin_colecoes.php?msg=".($novo?'ativada':'desativada')); exit();
}

// ── EXCLUIR ───────────────────────────────────
if (isset($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    $tem = $pdo->prepare("SELECT COUNT(*) FROM produtos WHERE colecao_id=?"); $tem->execute([$id]);
    if ($tem->fetchColumn() == 0) { $pdo->prepare("DELETE FROM colecoes WHERE id=?")->execute([$id]); header("Location: admin_colecoes.php?msg=excluida"); }
    else header("Location: admin_colecoes.php?msg=erro_excluir");
    exit();
}

// ── DADOS ─────────────────────────────────────
$colecoes = $pdo->query("
    SELECT c.*, (SELECT COUNT(*) FROM produtos p WHERE p.colecao_id = c.id) as total_produtos
    FROM colecoes c ORDER BY c.ativo DESC, c.data_inicio DESC
")->fetchAll(PDO::FETCH_ASSOC);

$editando = null;
if (isset($_GET['editar'])) {
    $editando = $pdo->prepare("SELECT * FROM colecoes WHERE id=?");
    $editando->execute([$_GET['editar']]);
    $editando = $editando->fetch(PDO::FETCH_ASSOC);
}

$total_ativas   = $pdo->query("SELECT COUNT(*) FROM colecoes WHERE ativo=1")->fetchColumn();
$total_inativas = $pdo->query("SELECT COUNT(*) FROM colecoes WHERE ativo=0")->fetchColumn();

$devolucoes_pend = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();
$estoque_critico = $pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque<=3 AND ativo=1")->fetchColumn();
$p_pendente_sb   = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();

define('CONTEUDO_AUTORIZADO', true);
$pagina_atual = 'colecoes';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coleções | Alto Jordão Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <style>
        .page-grid { display:grid; grid-template-columns:1fr 380px; gap:22px; align-items:start; }

        .form-card .input-group { display:flex; flex-direction:column; gap:8px; margin-bottom:16px; }
        .form-card label { font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1px; }
        .form-card input, .form-card select, .form-card textarea {
            padding:12px 16px; border:1.5px solid var(--border); border-radius:50px;
            font-family:var(--font-main); font-size:13px; background:var(--grey-bg);
            color:var(--black); outline:none; transition:var(--transition);
        }
        .form-card textarea { border-radius:16px; resize:vertical; min-height:80px; }
        .form-card input:focus, .form-card textarea:focus { border-color:var(--black); background:var(--white); }

        .check-row { display:flex; align-items:center; gap:10px; padding:12px 16px; background:var(--grey-bg); border-radius:50px; border:1.5px solid var(--border); }
        .check-row input[type="checkbox"] { width:16px; height:16px; accent-color:var(--black); }

        /* Card de coleção */
        .col-card {
            background:var(--white); border:1px solid var(--border); border-radius:20px;
            padding:22px; margin-bottom:14px; box-shadow:var(--shadow); transition:var(--transition);
            display:flex; gap:18px; align-items:flex-start;
        }
        .col-card:hover { transform:translateY(-2px); border-color:#ccc; }
        .col-card.inativa { opacity:.6; }

        .col-icon { width:48px; height:48px; border-radius:14px; background:var(--grey-bg); display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0; }
        .col-info { flex:1; min-width:0; }
        .col-nome { font-weight:800; font-size:15px; margin-bottom:4px; }
        .col-desc { font-size:12px; color:var(--text2); margin-bottom:8px; line-height:1.5; }
        .col-meta { font-size:11px; color:var(--muted); display:flex; gap:14px; flex-wrap:wrap; }

        .badge-ativo   { background:rgba(46,125,50,.1); color:var(--success); padding:4px 12px; border-radius:50px; font-size:10px; font-weight:800; white-space:nowrap; }
        .badge-inativo { background:rgba(255,77,77,.1); color:var(--danger);  padding:4px 12px; border-radius:50px; font-size:10px; font-weight:800; white-space:nowrap; }
        .badge-count   { background:var(--grey-bg); color:var(--muted); padding:4px 12px; border-radius:50px; font-size:10px; font-weight:800; white-space:nowrap; }

        .col-actions { display:flex; flex-direction:column; gap:8px; align-items:flex-end; flex-shrink:0; }
        .btn-sm { padding:6px 14px; border-radius:50px; font-size:10px; font-weight:800; text-decoration:none; text-transform:uppercase; transition:var(--transition); white-space:nowrap; }
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
        <div>
            <h1>Coleções</h1>
            <p>Gerencie as coleções sazonais e temáticas da Alto Jordão.</p>
        </div>
    </div>

    <?php if(isset($_GET['msg'])): ?>
    <?php $msgs = ['criada'=>'✓ Coleção criada!','editada'=>'✓ Coleção atualizada!','excluida'=>'✓ Coleção excluída!','ativada'=>'✓ Coleção ativada!','desativada'=>'✓ Coleção desativada!','erro_excluir'=>'⚠ Não é possível excluir: há produtos nesta coleção.']; ?>
    <div class="<?= $_GET['msg']==='erro_excluir'?'msg-err':'msg-ok' ?>"><?= $msgs[$_GET['msg']] ?? '' ?></div>
    <?php endif; ?>

    <!-- KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card featured">
            <div class="kpi-icon">✨</div>
            <div class="kpi-label">Coleções Ativas</div>
            <div class="kpi-value"><?= $total_ativas ?></div>
            <div class="kpi-sub">Visíveis na loja</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">📁</div>
            <div class="kpi-label">Total de Coleções</div>
            <div class="kpi-value"><?= count($colecoes) ?></div>
            <div class="kpi-sub"><?= $total_inativas ?> inativas</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">👕</div>
            <div class="kpi-label">Produtos em Coleções</div>
            <div class="kpi-value"><?= array_sum(array_column($colecoes,'total_produtos')) ?></div>
            <div class="kpi-sub">Total vinculado</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">🗓️</div>
            <div class="kpi-label">Ano Atual</div>
            <div class="kpi-value"><?= date('Y') ?></div>
            <div class="kpi-sub">Temporada vigente</div>
        </div>
    </div>

    <div class="page-grid">

        <!-- LISTA DE COLEÇÕES -->
        <div>
            <?php if(empty($colecoes)): ?>
            <div class="admin-card" style="text-align:center; padding:60px; color:var(--muted);">
                Nenhuma coleção cadastrada ainda.
            </div>
            <?php endif; ?>

            <?php foreach($colecoes as $col): ?>
            <div class="col-card <?= !$col['ativo'] ? 'inativa' : '' ?>">
                <div class="col-icon">✨</div>
                <div class="col-info">
                    <div class="col-nome"><?= htmlspecialchars($col['nome']) ?></div>
                    <?php if($col['descricao']): ?>
                    <div class="col-desc"><?= htmlspecialchars(mb_strimwidth($col['descricao'],0,80,'...')) ?></div>
                    <?php endif; ?>
                    <div class="col-meta">
                        <?php if($col['data_inicio']): ?>
                        <span>📅 <?= date('d/m/Y',strtotime($col['data_inicio'])) ?> → <?= $col['data_fim'] ? date('d/m/Y',strtotime($col['data_fim'])) : '∞' ?></span>
                        <?php endif; ?>
                        <span>slug: <?= $col['slug'] ?></span>
                    </div>
                </div>
                <div class="col-actions">
                    <span class="<?= $col['ativo'] ? 'badge-ativo' : 'badge-inativo' ?>"><?= $col['ativo'] ? 'Ativa' : 'Inativa' ?></span>
                    <span class="badge-count"><?= $col['total_produtos'] ?> produtos</span>
                    <a href="?editar=<?= $col['id'] ?>" class="btn-sm btn-sm-edit">Editar</a>
                    <a href="?toggle=<?= $col['id'] ?>" class="btn-sm btn-sm-toggle"><?= $col['ativo'] ? 'Desativar' : 'Ativar' ?></a>
                    <?php if($col['total_produtos'] == 0): ?>
                    <a href="?excluir=<?= $col['id'] ?>" class="btn-sm btn-sm-del" onclick="return confirm('Excluir coleção?')">Excluir</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- FORMULÁRIO -->
        <div class="admin-card form-card">
            <?php if($editando): ?>
            <div class="editing-banner">
                ✏️ <?= htmlspecialchars($editando['nome']) ?>
                <a href="admin_colecoes.php">Cancelar ×</a>
            </div>
            <form method="POST">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id"   value="<?= $editando['id'] ?>">
                <div class="input-group">
                    <label>Nome *</label>
                    <input type="text" name="nome" value="<?= htmlspecialchars($editando['nome']) ?>" required>
                </div>
                <div class="input-group">
                    <label>Descrição</label>
                    <textarea name="descricao"><?= htmlspecialchars($editando['descricao']??'') ?></textarea>
                </div>
                <div class="input-group">
                    <label>Data de Início</label>
                    <input type="date" name="data_inicio" value="<?= $editando['data_inicio'] ?>">
                </div>
                <div class="input-group">
                    <label>Data de Fim</label>
                    <input type="date" name="data_fim" value="<?= $editando['data_fim'] ?>">
                </div>
                <div class="input-group">
                    <label>Status</label>
                    <label class="check-row">
                        <input type="checkbox" name="ativo" value="1" <?= $editando['ativo']?'checked':''?>>
                        Coleção ativa e visível
                    </label>
                </div>
                <button type="submit" class="btn-admin-primary" style="width:100%;padding:14px;border-radius:50px;justify-content:center;">Salvar Alterações</button>
            </form>

            <?php else: ?>
            <div class="card-header" style="margin-bottom:20px;">
                <span class="card-title">Nova Coleção</span>
            </div>
            <form method="POST">
                <input type="hidden" name="acao" value="criar">
                <div class="input-group">
                    <label>Nome da Coleção *</label>
                    <input type="text" name="nome" placeholder="Ex: Verão 2026, Black Friday..." required>
                </div>
                <div class="input-group">
                    <label>Descrição</label>
                    <textarea name="descricao" placeholder="Descreva a proposta desta coleção..."></textarea>
                </div>
                <div class="input-group">
                    <label>Data de Início</label>
                    <input type="date" name="data_inicio">
                </div>
                <div class="input-group">
                    <label>Data de Fim</label>
                    <input type="date" name="data_fim">
                </div>
                <button type="submit" class="btn-admin-primary" style="width:100%;padding:14px;border-radius:50px;justify-content:center;">Criar Coleção</button>
            </form>
            <?php endif; ?>
        </div>

    </div>
</main>

<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>