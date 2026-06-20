<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

if (!isset($_SESSION['usuario_nivel']) || !in_array($_SESSION['usuario_nivel'], ['admin','superadmin','gerente'])) {
    header("Location: login.php"); exit();
}

$msg = '';

// ── CRIAR ─────────────────────────────────────
if (isset($_POST['acao']) && $_POST['acao'] === 'criar') {
    $nome   = trim($_POST['nome']);
    $pai_id = !empty($_POST['pai_id']) ? (int)$_POST['pai_id'] : null;
    $slug   = strtolower(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8','ASCII//TRANSLIT',$nome)));

    // Garante slug único
    $existe = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE slug = ?");
    $existe->execute([$slug]);
    if ($existe->fetchColumn()) $slug .= '-'.time();

    $pdo->prepare("INSERT INTO categorias (nome, slug, pai_id, ativo) VALUES (?,?,?,1)")
        ->execute([$nome, $slug, $pai_id]);
    $log = $pdo->prepare("INSERT INTO logs_sistema (usuario_id, acao, tabela, registro_id, detalhes, ip) VALUES (?,?,?,?,?,?)");
    $log->execute([$_SESSION['usuario_id']??null, 'categoria_criada', 'categorias', $pdo->lastInsertId(), $nome, $_SERVER['REMOTE_ADDR']]);
    header("Location: admin_categorias.php?msg=criada"); exit();
}

// ── EDITAR ────────────────────────────────────
if (isset($_POST['acao']) && $_POST['acao'] === 'editar') {
    $id   = (int)$_POST['id'];
    $nome = trim($_POST['nome']);
    $ativo= isset($_POST['ativo']) ? 1 : 0;
    $pai_id = !empty($_POST['pai_id']) ? (int)$_POST['pai_id'] : null;
    $pdo->prepare("UPDATE categorias SET nome=?, pai_id=?, ativo=? WHERE id=?")
        ->execute([$nome, $pai_id, $ativo, $id]);
    header("Location: admin_categorias.php?msg=editada"); exit();
}

// ── EXCLUIR ───────────────────────────────────
if (isset($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    // Só exclui se não tiver produtos vinculados
    $tem_produtos = $pdo->prepare("SELECT COUNT(*) FROM produtos WHERE categoria_id = ?");
    $tem_produtos->execute([$id]);
    if ($tem_produtos->fetchColumn() == 0) {
        $pdo->prepare("DELETE FROM categorias WHERE id=?")->execute([$id]);
        header("Location: admin_categorias.php?msg=excluida"); exit();
    } else {
        header("Location: admin_categorias.php?msg=erro_excluir"); exit();
    }
}

// ── DADOS ─────────────────────────────────────
$categorias = $pdo->query("
    SELECT c.*, p.nome as pai_nome,
           (SELECT COUNT(*) FROM produtos pr WHERE pr.categoria_id = c.id) as total_produtos
    FROM categorias c
    LEFT JOIN categorias p ON c.pai_id = p.id
    ORDER BY c.pai_id IS NOT NULL, c.pai_id, c.nome
")->fetchAll(PDO::FETCH_ASSOC);

$categorias_pai = $pdo->query("SELECT id, nome FROM categorias WHERE pai_id IS NULL AND ativo=1 ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// Edição em andamento
$editando = null;
if (isset($_GET['editar'])) {
    $editando = $pdo->prepare("SELECT * FROM categorias WHERE id=?");
    $editando->execute([$_GET['editar']]);
    $editando = $editando->fetch(PDO::FETCH_ASSOC);
}

// Badges sidebar
$devolucoes_pend = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();
$estoque_critico = $pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque<=3 AND ativo=1")->fetchColumn();
$p_pendente_sb   = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorias | Alto Jordão Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <style>
        .page-grid { display:grid; grid-template-columns:1fr 380px; gap:22px; align-items:start; }

        .form-card .input-group { display:flex; flex-direction:column; gap:8px; margin-bottom:16px; }
        .form-card label { font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1px; }
        .form-card input, .form-card select {
            padding:12px 16px; border:1.5px solid var(--border); border-radius:50px;
            font-family:var(--font-main); font-size:13px; background:var(--grey-bg);
            color:var(--black); outline:none; transition:var(--transition);
        }
        .form-card input:focus, .form-card select:focus { border-color:var(--black); background:var(--white); }

        .check-row { display:flex; align-items:center; gap:10px; padding:12px 16px; background:var(--grey-bg); border-radius:50px; border:1.5px solid var(--border); }
        .check-row input[type="checkbox"] { width:16px; height:16px; accent-color:var(--black); }

        .cat-row { display:flex; align-items:center; gap:14px; padding:14px 0; border-bottom:1px solid var(--border); }
        .cat-row:last-child { border-bottom:none; }

        .cat-icon { width:36px; height:36px; border-radius:10px; background:var(--grey-bg); display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
        .cat-info { flex:1; min-width:0; }
        .cat-nome { font-weight:700; font-size:13px; }
        .cat-sub  { font-size:11px; color:var(--muted); margin-top:2px; }
        .cat-sub-item { padding-left:16px; border-left:2px solid var(--border); }

        .badge-ativo    { background:rgba(46,125,50,.1); color:var(--success); padding:3px 10px; border-radius:50px; font-size:10px; font-weight:800; }
        .badge-inativo  { background:rgba(255,77,77,.1); color:var(--danger);  padding:3px 10px; border-radius:50px; font-size:10px; font-weight:800; }
        .badge-count    { background:var(--grey-bg); color:var(--muted); padding:3px 10px; border-radius:50px; font-size:10px; font-weight:800; }

        .row-actions { display:flex; gap:10px; }
        .btn-edit-sm { color:var(--black); font-size:11px; font-weight:800; text-decoration:none; border-bottom:1px solid var(--black); }
        .btn-del-sm  { color:var(--danger); font-size:11px; font-weight:800; text-decoration:none; border-bottom:1px solid var(--danger); }

        .msg-ok  { background:#e8f5e9; border:1px solid #c8e6c9; color:var(--success); padding:12px 22px; border-radius:50px; margin-bottom:20px; font-size:13px; font-weight:700; display:inline-block; }
        .msg-err { background:rgba(255,77,77,.08); border:1px solid rgba(255,77,77,.25); color:var(--danger); padding:12px 22px; border-radius:50px; margin-bottom:20px; font-size:13px; font-weight:700; display:inline-block; }

        .editing-banner { background:var(--black); color:var(--white); padding:12px 22px; border-radius:14px; font-size:13px; font-weight:700; margin-bottom:18px; display:flex; justify-content:space-between; align-items:center; }
        .editing-banner a { color:#aaa; font-size:11px; text-decoration:none; }
    </style>
</head>
<body class="admin-page">

<aside class="admin-sidebar">
    <div class="sb-logo">ALTO JORDÃO</div>
    <div class="sb-section">
        <span class="sb-section-title">Visão Geral</span>
        <a href="admin_dashboard.php" class="sb-item">📊 Dashboard</a>
    </div>
    <div class="sb-section">
        <span class="sb-section-title">Vendas</span>
        <a href="admin_pedidos.php" class="sb-item">🛒 Pedidos <?php if($p_pendente_sb>0): ?><span class="sb-badge"><?= $p_pendente_sb ?></span><?php endif; ?></a>
        <a href="admin_vendas.php"     class="sb-item">💰 Financeiro</a>
        <a href="entregas.php"         class="sb-item">📦 Logística</a>
        <a href="admin_devolucoes.php" class="sb-item">🔄 Devoluções <?php if($devolucoes_pend>0): ?><span class="sb-badge"><?= $devolucoes_pend ?></span><?php endif; ?></a>
    </div>
    <div class="sb-section">
        <span class="sb-section-title">Catálogo</span>
        <a href="admin_produtos.php"    class="sb-item">👕 Produtos</a>
        <a href="admin_estoque.php"     class="sb-item">📋 Estoque <?php if($estoque_critico>0): ?><span class="sb-badge"><?= $estoque_critico ?></span><?php endif; ?></a>
        <a href="admin_categorias.php"  class="sb-item active">🏷️ Categorias</a>
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
        <a href="admin_cupons.php"     class="sb-item">🎟️ Cupons</a>
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
            <h1>Categorias</h1>
            <p>Organize os produtos da Alto Jordão em categorias e subcategorias.</p>
        </div>
    </div>

    <?php if(isset($_GET['msg'])): ?>
    <?php $msgs = ['criada'=>'✓ Categoria criada!','editada'=>'✓ Categoria atualizada!','excluida'=>'✓ Categoria excluída!','erro_excluir'=>'⚠ Não é possível excluir: há produtos nesta categoria.']; ?>
    <div class="<?= $_GET['msg']==='erro_excluir'?'msg-err':'msg-ok' ?>"><?= $msgs[$_GET['msg']] ?? '' ?></div>
    <?php endif; ?>

    <div class="page-grid">

        <!-- LISTA -->
        <div class="admin-card">
            <div class="card-header">
                <span class="card-title">Todas as Categorias (<?= count($categorias) ?>)</span>
            </div>

            <?php foreach($categorias as $cat): ?>
            <div class="cat-row <?= $cat['pai_id'] ? 'cat-sub-item' : '' ?>">
                <div class="cat-icon"><?= $cat['pai_id'] ? '↳' : '🏷️' ?></div>
                <div class="cat-info">
                    <div class="cat-nome"><?= htmlspecialchars($cat['nome']) ?></div>
                    <div class="cat-sub">
                        <?= $cat['pai_nome'] ? 'Sub de: '.$cat['pai_nome'].' · ' : '' ?>
                        slug: <?= $cat['slug'] ?>
                    </div>
                </div>
                <span class="badge-count"><?= $cat['total_produtos'] ?> produtos</span>
                <span class="<?= $cat['ativo'] ? 'badge-ativo' : 'badge-inativo' ?>"><?= $cat['ativo'] ? 'Ativa' : 'Inativa' ?></span>
                <div class="row-actions">
                    <a href="?editar=<?= $cat['id'] ?>" class="btn-edit-sm">Editar</a>
                    <?php if($cat['total_produtos'] == 0): ?>
                    <a href="?excluir=<?= $cat['id'] ?>" class="btn-del-sm" onclick="return confirm('Excluir categoria?')">Excluir</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if(empty($categorias)): ?>
            <p style="text-align:center;color:var(--muted);padding:40px;font-size:13px;">Nenhuma categoria cadastrada.</p>
            <?php endif; ?>
        </div>

        <!-- FORMULÁRIO -->
        <div class="admin-card form-card">
            <?php if($editando): ?>
            <div class="editing-banner">
                ✏️ Editando: <?= htmlspecialchars($editando['nome']) ?>
                <a href="admin_categorias.php">Cancelar ×</a>
            </div>
            <form method="POST">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id"   value="<?= $editando['id'] ?>">
                <div class="input-group">
                    <label>Nome da Categoria</label>
                    <input type="text" name="nome" value="<?= htmlspecialchars($editando['nome']) ?>" required>
                </div>
                <div class="input-group">
                    <label>Categoria Pai (Opcional)</label>
                    <select name="pai_id">
                        <option value="">Nenhuma (categoria principal)</option>
                        <?php foreach($categorias_pai as $cp): if($cp['id']==$editando['id']) continue; ?>
                        <option value="<?= $cp['id'] ?>" <?= $editando['pai_id']==$cp['id']?'selected':''?>><?= htmlspecialchars($cp['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input-group">
                    <label>Status</label>
                    <label class="check-row">
                        <input type="checkbox" name="ativo" value="1" <?= $editando['ativo']?'checked':''?>>
                        Categoria ativa e visível
                    </label>
                </div>
                <button type="submit" class="btn-admin-primary" style="width:100%;padding:14px;border-radius:50px;justify-content:center;">Salvar Alterações</button>
            </form>

            <?php else: ?>
            <div class="card-header" style="margin-bottom:20px;">
                <span class="card-title">Nova Categoria</span>
            </div>
            <form method="POST">
                <input type="hidden" name="acao" value="criar">
                <div class="input-group">
                    <label>Nome da Categoria *</label>
                    <input type="text" name="nome" placeholder="Ex: Camisetas, Moletons..." required>
                </div>
                <div class="input-group">
                    <label>Categoria Pai (Subcategoria)</label>
                    <select name="pai_id">
                        <option value="">Nenhuma (categoria principal)</option>
                        <?php foreach($categorias_pai as $cp): ?>
                        <option value="<?= $cp['id'] ?>"><?= htmlspecialchars($cp['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-admin-primary" style="width:100%;padding:14px;border-radius:50px;justify-content:center;">Criar Categoria</button>
            </form>
            <?php endif; ?>
        </div>

    </div>
</main>

<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>