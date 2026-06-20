<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['usuario_nivel']) || !in_array($_SESSION['usuario_nivel'], ['admin','superadmin','gerente','operador'])) {
    header("Location: login.php"); exit();
}

// ── ATUALIZAR ESTOQUE ─────────────────────────
if (isset($_POST['atualizar_estoque'])) {
    $id    = (int)$_POST['id'];
    $qtd   = (int)$_POST['quantidade'];
    $pdo->prepare("UPDATE produtos SET estoque = ? WHERE id = ?")->execute([$qtd, $id]);

    // Registra movimentação
    $motivo = $qtd > 0 ? 'ajuste_manual' : 'zerado_manual';
    $pdo->prepare("INSERT INTO estoque_movimentacoes (produto_id, tipo, quantidade, motivo, usuario_id) VALUES (?,?,?,?,?)")
        ->execute([$id, 'ajuste', $qtd, $motivo, $_SESSION['usuario_id'] ?? null]);

    $log = $pdo->prepare("INSERT INTO logs_sistema (usuario_id, acao, tabela, registro_id, detalhes, ip) VALUES (?,?,?,?,?,?)");
    $log->execute([$_SESSION['usuario_id']??null, 'estoque_ajustado', 'produtos', $id, 'Novo estoque: '.$qtd, $_SERVER['REMOTE_ADDR']]);

    header("Location: admin_estoque.php?msg=atualizado"); exit();
}

// ── FILTROS ───────────────────────────────────
$filtro  = $_GET['filtro']  ?? 'todos';
$busca   = $_GET['busca']   ?? '';

$sql = "SELECT id, nome, estoque, imagem, categoria FROM produtos WHERE ativo = 1";
if ($filtro === 'critico') $sql .= " AND estoque > 0 AND estoque <= 3";
if ($filtro === 'zero')    $sql .= " AND estoque = 0";
if ($filtro === 'ok')      $sql .= " AND estoque > 3";
if ($busca) $sql .= " AND nome LIKE ".$pdo->quote("%$busca%");
$sql .= " ORDER BY estoque ASC";

$produtos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// KPIs
$total_prod    = $pdo->query("SELECT COUNT(*) FROM produtos WHERE ativo=1")->fetchColumn();
$estoque_total = $pdo->query("SELECT COALESCE(SUM(estoque),0) FROM produtos WHERE ativo=1")->fetchColumn();
$criticos      = $pdo->query("SELECT COUNT(*) FROM produtos WHERE ativo=1 AND estoque > 0 AND estoque <= 3")->fetchColumn();
$zerados       = $pdo->query("SELECT COUNT(*) FROM produtos WHERE ativo=1 AND estoque = 0")->fetchColumn();

// Badges sidebar
$devolucoes_pend = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();
$p_pendente_sb   = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estoque | Alto Jordão Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <style>
        .status-tabs { display:flex; gap:8px; margin-bottom:22px; flex-wrap:wrap; }
        .tab-btn { padding:9px 20px; border-radius:50px; border:1px solid var(--border); background:var(--white); color:var(--text2); font-size:12px; font-weight:700; cursor:pointer; text-decoration:none; transition:var(--transition); white-space:nowrap; }
        .tab-btn:hover { background:var(--grey-bg); color:var(--black); }
        .tab-btn.active { background:var(--black); color:var(--white); border-color:var(--black); }

        .filter-bar { background:var(--white); border:1px solid var(--border); border-radius:30px; padding:16px 24px; display:flex; gap:12px; align-items:center; margin-bottom:22px; box-shadow:var(--shadow); }
        .filter-bar input { background:var(--grey-bg); border:1px solid var(--border); border-radius:50px; color:var(--black); padding:10px 16px; font-family:var(--font-main); font-size:13px; flex:1; outline:none; transition:var(--transition); }
        .filter-bar input:focus { border-color:var(--black); background:var(--white); }

        .stock-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(290px, 1fr)); gap:16px; }

        .stock-card {
            background:var(--white); padding:20px; border-radius:20px;
            border:1px solid var(--border); display:flex; align-items:center;
            gap:16px; box-shadow:var(--shadow); transition:var(--transition);
        }
        .stock-card:hover { border-color:var(--black); transform:translateY(-2px); }
        .stock-card.critico { border-left:3px solid var(--warning); }
        .stock-card.zerado  { border-left:3px solid var(--danger);  }

        .prod-img { width:56px; height:56px; object-fit:cover; border-radius:12px; background:var(--grey-bg); flex-shrink:0; }

        .prod-info { flex:1; min-width:0; }
        .prod-nome { font-weight:700; font-size:13px; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .prod-cat  { font-size:10px; color:var(--muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:10px; }

        .quick-form { display:flex; gap:8px; align-items:center; }
        .stock-input {
            width:64px; padding:9px 10px; border:1.5px solid var(--border);
            border-radius:50px; font-weight:800; text-align:center; font-size:14px;
            font-family:var(--font-main); outline:none; transition:var(--transition);
            background:var(--grey-bg);
        }
        .stock-input:focus { border-color:var(--black); background:var(--white); }

        .btn-update {
            background:var(--black); color:var(--white); border:none; padding:9px 16px;
            border-radius:50px; cursor:pointer; font-size:10px; font-weight:800;
            text-transform:uppercase; transition:var(--transition);
        }
        .btn-update:hover { background:#333; }

        .alerta-tag { font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:1px; margin-top:6px; display:block; }
        .alerta-critico { color:var(--warning); }
        .alerta-zerado  { color:var(--danger); }

        .msg-ok { background:#e8f5e9; border:1px solid #c8e6c9; color:var(--success); padding:12px 22px; border-radius:50px; margin-bottom:20px; font-size:13px; font-weight:700; display:inline-flex; align-items:center; gap:8px; }
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
        <a href="admin_estoque.php"     class="sb-item active">📋 Estoque <?php if($criticos+$zerados>0): ?><span class="sb-badge"><?= $criticos+$zerados ?></span><?php endif; ?></a>
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
            <h1>Gestão de Estoque</h1>
            <p>Acompanhe e atualize as quantidades em tempo real.</p>
        </div>
    </div>

    <?php if(isset($_GET['msg'])): ?>
    <div class="msg-ok">✓ Estoque atualizado com sucesso!</div>
    <?php endif; ?>

    <!-- KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon">📦</div>
            <div class="kpi-label">Total em Estoque</div>
            <div class="kpi-value"><?= number_format($estoque_total) ?> <span style="font-size:14px;color:var(--muted)">un.</span></div>
            <div class="kpi-sub"><?= $total_prod ?> modelos ativos</div>
        </div>
        <div class="kpi-card featured">
            <div class="kpi-icon">⚠️</div>
            <div class="kpi-label">Estoque Crítico</div>
            <div class="kpi-value" style="color:var(--warning);"><?= $criticos ?></div>
            <div class="kpi-sub">Entre 1 e 3 unidades</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">🚫</div>
            <div class="kpi-label">Sem Estoque</div>
            <div class="kpi-value" style="color:var(--danger);"><?= $zerados ?></div>
            <div class="kpi-sub">Indisponíveis</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">✅</div>
            <div class="kpi-label">Em Dia</div>
            <div class="kpi-value" style="color:var(--success);"><?= $total_prod - $criticos - $zerados ?></div>
            <div class="kpi-sub">Estoque saudável (> 3 un.)</div>
        </div>
    </div>

    <!-- TABS -->
    <div class="status-tabs">
        <a href="?filtro=todos&busca=<?= urlencode($busca) ?>"   class="tab-btn <?= $filtro==='todos'  ?'active':''?>">Todos (<?= $total_prod ?>)</a>
        <a href="?filtro=critico&busca=<?= urlencode($busca) ?>" class="tab-btn <?= $filtro==='critico'?'active':''?>">⚠️ Críticos (<?= $criticos ?>)</a>
        <a href="?filtro=zero&busca=<?= urlencode($busca) ?>"    class="tab-btn <?= $filtro==='zero'   ?'active':''?>">🚫 Zerados (<?= $zerados ?>)</a>
        <a href="?filtro=ok&busca=<?= urlencode($busca) ?>"      class="tab-btn <?= $filtro==='ok'     ?'active':''?>">✅ Em Dia</a>
    </div>

    <!-- BUSCA -->
    <form method="GET" class="filter-bar">
        <input type="hidden" name="filtro" value="<?= $filtro ?>">
        <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" placeholder="🔍 Buscar produto por nome...">
        <button type="submit" class="btn-admin-primary">Buscar</button>
        <?php if($busca): ?><a href="?filtro=<?= $filtro ?>" class="btn-admin-ghost">Limpar</a><?php endif; ?>
    </form>

    <!-- GRID DE CARDS -->
    <div class="stock-grid">
        <?php foreach($produtos as $p):
            $classe = $p['estoque'] === 0 ? 'zerado' : ($p['estoque'] <= 3 ? 'critico' : '');
        ?>
        <div class="stock-card <?= $classe ?>">
            <img src="img/produtos/<?= htmlspecialchars($p['imagem']) ?>"
                 class="prod-img"
                 onerror="this.style.opacity='.2'">
            <div class="prod-info">
                <div class="prod-nome"><?= htmlspecialchars($p['nome']) ?></div>
                <div class="prod-cat"><?= htmlspecialchars($p['categoria'] ?? '—') ?></div>
                <form method="POST" class="quick-form">
                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <input type="number" name="quantidade" value="<?= $p['estoque'] ?>" class="stock-input" min="0">
                    <button type="submit" name="atualizar_estoque" class="btn-update">Salvar</button>
                </form>
                <?php if($p['estoque'] === 0): ?>
                    <span class="alerta-tag alerta-zerado">🚫 Sem estoque — repor urgente</span>
                <?php elseif($p['estoque'] <= 3): ?>
                    <span class="alerta-tag alerta-critico">⚠️ Estoque crítico — repor</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if(empty($produtos)): ?>
        <div style="grid-column:1/-1; text-align:center; padding:60px; color:var(--muted);">
            Nenhum produto encontrado para este filtro.
        </div>
        <?php endif; ?>
    </div>
</main>

<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>