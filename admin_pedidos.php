<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

if (!isset($_SESSION['usuario_nivel']) || !in_array($_SESSION['usuario_nivel'], ['admin','superadmin','gerente','operador'])) {
    header("Location: login.php"); exit();
}

// ── ATUALIZAR STATUS ──────────────────────────
if (isset($_POST['atualizar_status'])) {
    $stmt = $pdo->prepare("UPDATE pedidos SET status = ?, codigo_rastreio = ?, transportadora = ? WHERE id = ?");
    $stmt->execute([$_POST['status'], $_POST['codigo_rastreio'] ?? null, $_POST['transportadora'] ?? null, $_POST['pedido_id']]);
    // Log
    $log = $pdo->prepare("INSERT INTO logs_sistema (usuario_id, acao, tabela, registro_id, detalhes, ip) VALUES (?,?,?,?,?,?)");
    $log->execute([$_SESSION['usuario_id'] ?? null, 'pedido_status_atualizado', 'pedidos', $_POST['pedido_id'], 'Novo status: '.$_POST['status'], $_SERVER['REMOTE_ADDR']]);
    header("Location: admin_pedidos.php?msg=atualizado"); exit();
}

// ── FILTROS ───────────────────────────────────
$status_filtro = $_GET['status']   ?? '';
$busca         = $_GET['busca']    ?? '';
$data_ini      = $_GET['data_ini'] ?? date('Y-m-d', strtotime('-30 days'));
$data_fim      = $_GET['data_fim'] ?? date('Y-m-d');
$pag           = max(1, (int)($_GET['pag'] ?? 1));
$por_pag       = 15;
$offset        = ($pag - 1) * $por_pag;

$where = ["DATE(ped.data_pedido) BETWEEN ? AND ?"];
$params = [$data_ini, $data_fim];

if ($status_filtro) { $where[] = "ped.status = ?"; $params[] = $status_filtro; }
if ($busca) {
    $where[] = "(u.nome LIKE ? OR ped.id LIKE ? OR ped.codigo_rastreio LIKE ?)";
    $params[] = "%$busca%"; $params[] = "%$busca%"; $params[] = "%$busca%";
}

$sql_where = implode(' AND ', $where);

$total_rows = $pdo->prepare("SELECT COUNT(*) FROM pedidos ped JOIN usuarios u ON ped.usuario_id = u.id WHERE $sql_where");
$total_rows->execute($params);
$total_rows = $total_rows->fetchColumn();
$total_pags = ceil($total_rows / $por_pag);

$stmt = $pdo->prepare("
    SELECT ped.*, u.nome as cliente, u.email as cliente_email
    FROM pedidos ped JOIN usuarios u ON ped.usuario_id = u.id
    WHERE $sql_where
    ORDER BY ped.data_pedido DESC
    LIMIT $por_pag OFFSET $offset
");
$stmt->execute($params);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Detalhe do pedido selecionado
$pedido_det = null;
$itens_det  = [];
if (isset($_GET['id'])) {
    $pedido_det = $pdo->prepare("SELECT ped.*, u.nome as cliente, u.email as cliente_email, u.telefone FROM pedidos ped JOIN usuarios u ON ped.usuario_id = u.id WHERE ped.id = ?");
    $pedido_det->execute([$_GET['id']]);
    $pedido_det = $pedido_det->fetch(PDO::FETCH_ASSOC);

    $itens_det = $pdo->prepare("SELECT ip.*, p.nome as produto_nome, p.imagem FROM itens_pedido ip JOIN produtos p ON ip.produto_id = p.id WHERE ip.pedido_id = ?");
    $itens_det->execute([$_GET['id']]);
    $itens_det = $itens_det->fetchAll(PDO::FETCH_ASSOC);
}

// Contagens por status (para tabs)
$contagens = [];
foreach (['pendente','pago','em_separacao','enviado','entregue','cancelado'] as $s) {
    $contagens[$s] = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='$s'")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Pedidos | Alto Jordão Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
<?php include_styles: ?>
:root {
    --sidebar-w: 270px; --bg: #0a0a0a; --surface: #111; --surface2: #1a1a1a;
    --border: #222; --accent2: #c8ff00; --muted: #555; --text: #f0f0f0;
    --text2: #888; --danger: #ff4d4d; --success: #22c55e; --warning: #f59e0b;
    --info: #3b82f6; --radius: 16px; --radius-lg: 24px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; font-size: 14px; }
.sidebar { width: var(--sidebar-w); background: var(--surface); border-right: 1px solid var(--border); position: fixed; top: 0; left: 0; bottom: 0; display: flex; flex-direction: column; padding: 28px 20px; z-index: 100; overflow-y: auto; }
.sb-logo { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 18px; letter-spacing: 3px; color: var(--text); text-transform: uppercase; padding-bottom: 28px; border-bottom: 1px solid var(--border); margin-bottom: 28px; }
.sb-logo .dot { width: 8px; height: 8px; background: var(--accent2); border-radius: 50%; display: inline-block; margin-right: 10px; }
.sb-section { margin-bottom: 24px; }
.sb-section-title { font-size: 9px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px; padding-left: 12px; }
.sb-item { display: flex; align-items: center; gap: 12px; padding: 11px 14px; border-radius: 10px; color: var(--text2); text-decoration: none; font-size: 13px; font-weight: 500; transition: all 0.2s; margin-bottom: 3px; }
.sb-item:hover { background: var(--surface2); color: var(--text); }
.sb-item.active { background: var(--accent2); color: #000; font-weight: 700; }
.sb-badge { margin-left: auto; background: var(--danger); color: #fff; font-size: 9px; font-weight: 800; padding: 2px 7px; border-radius: 20px; }
.sb-footer { margin-top: auto; padding-top: 20px; border-top: 1px solid var(--border); }

.main { margin-left: var(--sidebar-w); flex: 1; padding: 36px 40px; }
.page-title { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 26px; margin-bottom: 6px; }
.page-sub   { color: var(--text2); font-size: 13px; margin-bottom: 32px; }

/* STATUS TABS */
.status-tabs { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
.tab-btn {
    padding: 8px 18px; border-radius: 50px; border: 1px solid var(--border);
    background: var(--surface); color: var(--text2); font-size: 12px; font-weight: 600;
    cursor: pointer; text-decoration: none; transition: 0.2s; white-space: nowrap;
}
.tab-btn:hover { border-color: #444; color: var(--text); }
.tab-btn.active { background: var(--accent2); color: #000; border-color: var(--accent2); font-weight: 700; }

/* FILTROS */
.filter-bar {
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
    padding: 20px 24px; display: flex; gap: 16px; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap;
}
.filter-group { display: flex; flex-direction: column; gap: 6px; }
.filter-group label { font-size: 10px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; }
.filter-group input, .filter-group select {
    background: var(--surface2); border: 1px solid var(--border); border-radius: 10px;
    color: var(--text); padding: 10px 14px; font-family: inherit; font-size: 13px; min-width: 140px;
}
.btn-filter { background: var(--accent2); color: #000; border: none; padding: 10px 22px; border-radius: 50px; font-weight: 700; font-size: 12px; cursor: pointer; }

/* TABELA */
.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 28px; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { text-align: left; font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; padding: 0 12px 14px; border-bottom: 1px solid var(--border); }
.data-table td { padding: 16px 12px; border-bottom: 1px solid var(--border); font-size: 13px; vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover td { background: rgba(255,255,255,0.02); cursor: pointer; }

.badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
.badge-pendente    { background: rgba(245,158,11,0.15);  color: #f59e0b; }
.badge-pago        { background: rgba(59,130,246,0.15);  color: #60a5fa; }
.badge-em_separacao{ background: rgba(168,85,247,0.15);  color: #c084fc; }
.badge-enviado     { background: rgba(99,102,241,0.15);  color: #818cf8; }
.badge-entregue    { background: rgba(34,197,94,0.15);   color: #4ade80; }
.badge-cancelado   { background: rgba(255,77,77,0.15);   color: #ff6b6b; }

/* PAGINAÇÃO */
.pagination { display: flex; gap: 8px; justify-content: center; margin-top: 24px; }
.page-btn { padding: 8px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--surface); color: var(--text2); text-decoration: none; font-size: 12px; font-weight: 600; transition: 0.2s; }
.page-btn:hover, .page-btn.active { background: var(--accent2); color: #000; border-color: var(--accent2); }

/* MODAL DE DETALHES */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 200; align-items: center; justify-content: center; }
.modal-overlay.open { display: flex; }
.modal-box { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 36px; max-width: 700px; width: 95%; max-height: 90vh; overflow-y: auto; position: relative; }
.modal-close { position: absolute; top: 20px; right: 20px; background: var(--surface2); border: none; color: var(--text2); width: 32px; height: 32px; border-radius: 50%; cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center; }

.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
.detail-item span { display: block; font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
.detail-item p { font-weight: 600; font-size: 14px; }

.item-row { display: flex; gap: 14px; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border); }
.item-row:last-child { border-bottom: none; }
.item-img { width: 50px; height: 50px; border-radius: 8px; background: var(--surface2); object-fit: cover; }

/* FORM DE STATUS */
.status-form { background: var(--surface2); border-radius: var(--radius); padding: 20px; margin-top: 20px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 8px; }
.form-group label { font-size: 10px; font-weight: 700; color: var(--muted); text-transform: uppercase; }
.form-group select, .form-group input {
    background: var(--surface); border: 1px solid var(--border); border-radius: 10px;
    color: var(--text); padding: 12px 14px; font-family: inherit; font-size: 13px;
}
.btn-save { background: var(--accent2); color: #000; border: none; padding: 14px 28px; border-radius: 50px; font-weight: 700; cursor: pointer; font-size: 12px; margin-top: 16px; width: 100%; }

.msg-ok { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: #4ade80; padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 13px; font-weight: 600; }
</style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-logo"><span class="dot"></span> ALTO JORDÃO</div>
    <div class="sb-section">
        <p class="sb-section-title">Visão Geral</p>
        <a href="admin_dashboard.php" class="sb-item">📊 Dashboard</a>
    </div>
    <div class="sb-section">
        <p class="sb-section-title">Vendas</p>
        <a href="admin_pedidos.php"   class="sb-item active">🛒 Pedidos</a>
        <a href="admin_vendas.php"    class="sb-item">💰 Financeiro</a>
        <a href="entregas.php"        class="sb-item">📦 Logística</a>
        <a href="admin_devolucoes.php" class="sb-item">🔄 Devoluções</a>
    </div>
    <div class="sb-section">
        <p class="sb-section-title">Catálogo</p>
        <a href="admin_produtos.php"  class="sb-item">👕 Produtos</a>
        <a href="admin_estoque.php"   class="sb-item">📋 Estoque</a>
        <a href="cadastrar_produto.php" class="sb-item">➕ Novo Produto</a>
    </div>
    <div class="sb-section">
        <p class="sb-section-title">Usuários</p>
        <a href="admin_clientes.php"  class="sb-item">👥 Clientes</a>
        <a href="admin_admins.php"    class="sb-item">🛡️ Admins</a>
    </div>
    <div class="sb-footer">
        <a href="index.php"  class="sb-item">🏪 Ver Loja</a>
        <a href="logout.php" class="sb-item" style="color: #ff4d4d;">🚪 Sair</a>
    </div>
</aside>

<main class="main">

    <h1 class="page-title">Gestão de Pedidos</h1>
    <p class="page-sub">Gerencie, atualize e acompanhe todos os pedidos em tempo real.</p>

    <?php if(isset($_GET['msg'])): ?>
    <div class="msg-ok">✓ Status do pedido atualizado com sucesso!</div>
    <?php endif; ?>

    <!-- TABS DE STATUS -->
    <div class="status-tabs">
        <a href="?data_ini=<?= $data_ini ?>&data_fim=<?= $data_fim ?>"
           class="tab-btn <?= !$status_filtro ? 'active' : '' ?>">
            Todos (<?= array_sum($contagens) ?>)
        </a>
        <?php
        $labels = ['pendente'=>'⏳ Pendente','pago'=>'💰 Pago','em_separacao'=>'📦 Em Separação','enviado'=>'🚀 Enviado','entregue'=>'✅ Entregue','cancelado'=>'❌ Cancelado'];
        foreach($labels as $s => $l):
        ?>
        <a href="?status=<?= $s ?>&data_ini=<?= $data_ini ?>&data_fim=<?= $data_fim ?>"
           class="tab-btn <?= $status_filtro === $s ? 'active' : '' ?>">
            <?= $l ?> (<?= $contagens[$s] ?>)
        </a>
        <?php endforeach; ?>
    </div>

    <!-- BARRA DE FILTROS -->
    <form method="GET" class="filter-bar">
        <?php if($status_filtro): ?><input type="hidden" name="status" value="<?= $status_filtro ?>"><?php endif; ?>
        <div class="filter-group">
            <label>Busca</label>
            <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" placeholder="Nome, ID ou Rastreio">
        </div>
        <div class="filter-group">
            <label>Data Início</label>
            <input type="date" name="data_ini" value="<?= $data_ini ?>">
        </div>
        <div class="filter-group">
            <label>Data Fim</label>
            <input type="date" name="data_fim" value="<?= $data_fim ?>">
        </div>
        <button type="submit" class="btn-filter">Filtrar</button>
    </form>

    <!-- TABELA DE PEDIDOS -->
    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Pagamento</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Rastreio</th>
                    <th>Data</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($pedidos as $ped): ?>
                <tr onclick="abrirDetalhe(<?= $ped['id'] ?>)">
                    <td style="font-weight:800; color:var(--muted);">#<?= str_pad($ped['id'],4,'0',STR_PAD_LEFT) ?></td>
                    <td>
                        <div style="font-weight:600;"><?= htmlspecialchars($ped['cliente']) ?></div>
                        <div style="font-size:11px; color:var(--text2);"><?= htmlspecialchars($ped['cliente_email']) ?></div>
                    </td>
                    <td style="text-transform:uppercase; font-size:12px; color:var(--text2);"><?= $ped['forma_pagamento'] ?></td>
                    <td style="font-weight:700;">R$ <?= number_format($ped['total'],2,',','.') ?></td>
                    <td><span class="badge badge-<?= $ped['status'] ?>"><?= str_replace('_',' ',$ped['status']) ?></span></td>
                    <td style="font-size:12px; color:var(--text2);"><?= $ped['codigo_rastreio'] ?: '—' ?></td>
                    <td style="font-size:12px; color:var(--text2);"><?= date('d/m/Y H:i', strtotime($ped['data_pedido'])) ?></td>
                    <td onclick="event.stopPropagation()">
                        <a href="?id=<?= $ped['id'] ?>&data_ini=<?= $data_ini ?>&data_fim=<?= $data_fim ?>&status=<?= $status_filtro ?>"
                           style="color:var(--accent2); font-size:11px; font-weight:700; text-decoration:none;">
                           Gerenciar →
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($pedidos)): ?>
                <tr><td colspan="8" style="text-align:center; color:var(--muted); padding:40px;">Nenhum pedido encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- PAGINAÇÃO -->
        <?php if($total_pags > 1): ?>
        <div class="pagination">
            <?php for($i = 1; $i <= $total_pags; $i++): ?>
            <a href="?pag=<?= $i ?>&status=<?= $status_filtro ?>&busca=<?= urlencode($busca) ?>&data_ini=<?= $data_ini ?>&data_fim=<?= $data_fim ?>"
               class="page-btn <?= $i == $pag ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

</main>

<!-- ════ MODAL DE DETALHES / EDIÇÃO ════ -->
<?php if($pedido_det): ?>
<div class="modal-overlay open" id="modalDetalhe">
    <div class="modal-box">
        <button class="modal-close" onclick="fecharModal()">×</button>
        
        <h2 style="font-family:'Syne',sans-serif; font-weight:800; margin-bottom:6px;">
            Pedido #<?= str_pad($pedido_det['id'],4,'0',STR_PAD_LEFT) ?>
        </h2>
        <p style="color:var(--text2); font-size:12px; margin-bottom:24px;">
            <?= date('d/m/Y H:i', strtotime($pedido_det['data_pedido'])) ?>
            &mdash; <span class="badge badge-<?= $pedido_det['status'] ?>"><?= str_replace('_',' ',$pedido_det['status']) ?></span>
        </p>

        <!-- DADOS -->
        <div class="detail-grid">
            <div class="detail-item">
                <span>Cliente</span>
                <p><?= htmlspecialchars($pedido_det['cliente']) ?></p>
            </div>
            <div class="detail-item">
                <span>E-mail</span>
                <p><?= htmlspecialchars($pedido_det['cliente_email']) ?></p>
            </div>
            <div class="detail-item">
                <span>Telefone</span>
                <p><?= $pedido_det['telefone'] ?: '—' ?></p>
            </div>
            <div class="detail-item">
                <span>Pagamento</span>
                <p style="text-transform:uppercase;"><?= $pedido_det['forma_pagamento'] ?></p>
            </div>
            <div class="detail-item" style="grid-column: span 2;">
                <span>Endereço de Entrega</span>
                <p>
                    <?= htmlspecialchars($pedido_det['end_endereco'] ?? '') ?>
                    <?= $pedido_det['end_numero'] ? ', ' . $pedido_det['end_numero'] : '' ?>
                    — <?= htmlspecialchars($pedido_det['end_bairro'] ?? '') ?>,
                    <?= htmlspecialchars($pedido_det['end_cidade'] ?? '') ?>/<?= $pedido_det['end_estado'] ?? '' ?>
                    — CEP <?= $pedido_det['end_cep'] ?? '' ?>
                </p>
            </div>
        </div>

        <!-- ITENS -->
        <div style="font-size:10px; color:var(--muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:12px;">Itens do Pedido</div>
        <?php foreach($itens_det as $item): ?>
        <div class="item-row">
            <img class="item-img" src="img/produtos/<?= $item['imagem'] ?>" onerror="this.style.display='none'">
            <div style="flex:1;">
                <div style="font-weight:600;"><?= htmlspecialchars($item['produto_nome']) ?></div>
                <div style="font-size:11px; color:var(--text2);"><?= $item['variacoes'] ?></div>
            </div>
            <div style="text-align:right;">
                <div style="font-weight:700;"><?= $item['quantidade'] ?>x</div>
                <div style="font-size:12px; color:var(--text2);">R$ <?= number_format($item['preco_unitario'],2,',','.') ?></div>
            </div>
        </div>
        <?php endforeach; ?>

        <div style="display:flex; justify-content:space-between; padding: 16px 0 0; border-top: 1px solid var(--border); margin-top: 8px;">
            <span style="color:var(--text2);">Total</span>
            <span style="font-family:'Syne',sans-serif; font-weight:800; font-size:20px; color:var(--accent2);">
                R$ <?= number_format($pedido_det['total'],2,',','.') ?>
            </span>
        </div>

        <!-- FORMULÁRIO DE ATUALIZAÇÃO -->
        <form method="POST" class="status-form">
            <input type="hidden" name="pedido_id" value="<?= $pedido_det['id'] ?>">
            <div style="font-size:10px; color:var(--muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:14px;">Atualizar Pedido</div>
            <div class="form-row">
                <div class="form-group">
                    <label>Novo Status</label>
                    <select name="status">
                        <?php foreach(['pendente','pago','em_separacao','enviado','entregue','cancelado'] as $s): ?>
                        <option value="<?= $s ?>" <?= $pedido_det['status'] == $s ? 'selected' : '' ?>>
                            <?= str_replace('_',' ', ucfirst($s)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Transportadora</label>
                    <input type="text" name="transportadora" value="<?= htmlspecialchars($pedido_det['transportadora'] ?? '') ?>" placeholder="Ex: Correios, Jadlog">
                </div>
            </div>
            <div class="form-group" style="margin-top:12px;">
                <label>Código de Rastreio</label>
                <input type="text" name="codigo_rastreio" value="<?= htmlspecialchars($pedido_det['codigo_rastreio'] ?? '') ?>" placeholder="Ex: BR123456789BR">
            </div>
            <button type="submit" name="atualizar_status" class="btn-save">💾 Salvar Alterações</button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function abrirDetalhe(id) {
    window.location.href = '?id=' + id + '&status=<?= $status_filtro ?>&data_ini=<?= $data_ini ?>&data_fim=<?= $data_fim ?>';
}
function fecharModal() {
    window.location.href = '?status=<?= $status_filtro ?>&data_ini=<?= $data_ini ?>&data_fim=<?= $data_fim ?>';
}
</script>
</body>
</html>