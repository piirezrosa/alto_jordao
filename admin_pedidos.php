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

$where  = ["DATE(ped.data_pedido) BETWEEN ? AND ?"];
$params = [$data_ini, $data_fim];

if ($status_filtro) { $where[] = "ped.status = ?"; $params[] = $status_filtro; }
if ($busca) {
    $where[] = "(u.nome LIKE ? OR ped.id LIKE ? OR ped.codigo_rastreio LIKE ?)";
    $params[] = "%$busca%"; $params[] = "%$busca%"; $params[] = "%$busca%";
}

$sql_where  = implode(' AND ', $where);

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

// Badges de devoluções e estoque para sidebar
$devolucoes_pend = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();
$estoque_critico = $pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque<=3 AND ativo=1")->fetchColumn();
$p_pendente_sb   = $contagens['pendente'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos | Alto Jordão Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <style>
        /* ── EXTRAS EXCLUSIVOS DA PÁGINA DE PEDIDOS ── */

        /* Tabs de status */
        .status-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 9px 20px;
            border-radius: 50px;
            border: 1px solid var(--border);
            background: var(--white);
            color: var(--text2);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
            white-space: nowrap;
        }

        .tab-btn:hover { background: var(--grey-bg); color: var(--black); }

        .tab-btn.active {
            background: var(--black);
            color: var(--white);
            border-color: var(--black);
        }

        /* Barra de filtros */
        .filter-bar {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 20px 28px;
            display: flex;
            gap: 16px;
            align-items: flex-end;
            margin-bottom: 22px;
            flex-wrap: wrap;
            box-shadow: var(--shadow);
        }

        .filter-group { display: flex; flex-direction: column; gap: 7px; }

        .filter-group label {
            font-size: 10px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .filter-group input,
        .filter-group select {
            background: var(--grey-bg);
            border: 1px solid var(--border);
            border-radius: 50px;
            color: var(--black);
            padding: 10px 16px;
            font-family: var(--font-main);
            font-size: 13px;
            min-width: 150px;
            outline: none;
            transition: var(--transition);
        }

        .filter-group input:focus,
        .filter-group select:focus {
            border-color: var(--black);
            background: var(--white);
        }

        /* Paginação */
        .pagination {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-top: 28px;
        }

        .page-btn {
            padding: 9px 16px;
            border-radius: 50px;
            border: 1px solid var(--border);
            background: var(--white);
            color: var(--text2);
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            transition: var(--transition);
        }

        .page-btn:hover,
        .page-btn.active {
            background: var(--black);
            color: var(--white);
            border-color: var(--black);
        }

        /* Modal de detalhes */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.35);
            backdrop-filter: blur(4px);
            z-index: 200;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.open { display: flex; }

        .modal-box {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 40px;
            max-width: 680px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 30px 80px rgba(0,0,0,0.12);
        }

        .modal-close {
            position: absolute;
            top: 20px; right: 20px;
            background: var(--grey-bg);
            border: 1px solid var(--border);
            color: var(--text2);
            width: 34px; height: 34px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .modal-close:hover { background: var(--black); color: var(--white); }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 28px;
        }

        .detail-item span {
            display: block;
            font-size: 10px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
            font-weight: 800;
        }

        .detail-item p { font-weight: 700; font-size: 13px; }

        /* Itens do pedido no modal */
        .item-row {
            display: flex;
            gap: 14px;
            align-items: center;
            padding: 13px 0;
            border-bottom: 1px solid var(--border);
        }

        .item-row:last-child { border-bottom: none; }

        .item-img {
            width: 52px; height: 52px;
            border-radius: 14px;
            background: var(--grey-bg);
            object-fit: cover;
            flex-shrink: 0;
        }

        /* Formulário de atualização de status */
        .status-form {
            background: var(--grey-bg);
            border-radius: 20px;
            padding: 22px;
            margin-top: 22px;
        }

        .form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

        .form-group-modal { display: flex; flex-direction: column; gap: 7px; }

        .form-group-modal label {
            font-size: 10px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-group-modal select,
        .form-group-modal input {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 50px;
            color: var(--black);
            padding: 12px 16px;
            font-family: var(--font-main);
            font-size: 13px;
            outline: none;
            transition: var(--transition);
        }

        .form-group-modal select:focus,
        .form-group-modal input:focus { border-color: var(--black); }

        /* Mensagem de sucesso */
        .msg-ok {
            background: #e8f5e9;
            border: 1px solid #c8e6c9;
            color: var(--success);
            padding: 13px 22px;
            border-radius: 50px;
            margin-bottom: 22px;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Linha clicável da tabela */
        .data-table tbody tr { cursor: pointer; }
    </style>
</head>
<body class="admin-page">

<!-- ── SIDEBAR ───────────────────────────────── -->
<aside class="admin-sidebar">
    <div class="sb-logo">ALTO JORDÃO</div>

    <div class="sb-section">
        <span class="sb-section-title">Visão Geral</span>
        <a href="admin_dashboard.php" class="sb-item">📊 Dashboard</a>
    </div>

    <div class="sb-section">
        <span class="sb-section-title">Vendas</span>
        <a href="admin_pedidos.php" class="sb-item active">
            🛒 Pedidos
            <?php if($p_pendente_sb > 0): ?><span class="sb-badge"><?= $p_pendente_sb ?></span><?php endif; ?>
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

<!-- ── CONTEÚDO ───────────────────────────────── -->
<main class="admin-main">

    <div class="admin-topbar">
        <div>
            <h1>Gestão de Pedidos</h1>
            <p>Gerencie, atualize e acompanhe todos os pedidos em tempo real.</p>
        </div>
        <div class="topbar-actions">
            <a href="admin_relatorios.php" class="btn-admin-ghost">📥 Exportar</a>
        </div>
    </div>

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
        $labels = [
            'pendente'     => '⏳ Pendente',
            'pago'         => '💰 Pago',
            'em_separacao' => '📦 Em Separação',
            'enviado'      => '🚀 Enviado',
            'entregue'     => '✅ Entregue',
            'cancelado'    => '❌ Cancelado',
        ];
        foreach($labels as $s => $l):
        ?>
        <a href="?status=<?= $s ?>&data_ini=<?= $data_ini ?>&data_fim=<?= $data_fim ?>"
           class="tab-btn <?= $status_filtro === $s ? 'active' : '' ?>">
            <?= $l ?> (<?= $contagens[$s] ?>)
        </a>
        <?php endforeach; ?>
    </div>

    <!-- FILTROS -->
    <form method="GET" class="filter-bar">
        <?php if($status_filtro): ?>
        <input type="hidden" name="status" value="<?= $status_filtro ?>">
        <?php endif; ?>
        <div class="filter-group">
            <label>Busca</label>
            <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" placeholder="Nome, ID ou rastreio">
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
        <a href="admin_pedidos.php" class="btn-admin-ghost">Limpar</a>
    </form>

    <!-- TABELA -->
    <div class="admin-card">
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
                <tr onclick="window.location='?id=<?= $ped['id'] ?>&data_ini=<?= $data_ini ?>&data_fim=<?= $data_fim ?>&status=<?= $status_filtro ?>'">
                    <td style="font-weight:900; color:var(--muted);">#<?= str_pad($ped['id'],4,'0',STR_PAD_LEFT) ?></td>
                    <td>
                        <div style="font-weight:700;"><?= htmlspecialchars($ped['cliente']) ?></div>
                        <div style="font-size:11px; color:var(--text2);"><?= htmlspecialchars($ped['cliente_email']) ?></div>
                    </td>
                    <td style="text-transform:uppercase; font-size:12px; color:var(--text2); font-weight:600;"><?= $ped['forma_pagamento'] ?></td>
                    <td style="font-weight:800;">R$ <?= number_format($ped['total'],2,',','.') ?></td>
                    <td><span class="badge badge-<?= $ped['status'] ?>"><?= str_replace('_',' ',$ped['status']) ?></span></td>
                    <td style="font-size:12px; color:var(--text2);"><?= $ped['codigo_rastreio'] ?: '—' ?></td>
                    <td style="font-size:12px; color:var(--text2);"><?= date('d/m/Y H:i', strtotime($ped['data_pedido'])) ?></td>
                    <td onclick="event.stopPropagation()">
                        <a href="?id=<?= $ped['id'] ?>&data_ini=<?= $data_ini ?>&data_fim=<?= $data_fim ?>&status=<?= $status_filtro ?>"
                           style="color:var(--black); font-size:11px; font-weight:800; text-decoration:none; border-bottom: 1px solid var(--black);">
                           Gerenciar →
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($pedidos)): ?>
                <tr>
                    <td colspan="8" style="text-align:center; color:var(--muted); padding:50px; font-size:13px;">
                        Nenhum pedido encontrado para o período selecionado.
                    </td>
                </tr>
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

<!-- ── MODAL DE DETALHES / EDIÇÃO ─────────────── -->
<?php if($pedido_det): ?>
<div class="modal-overlay open">
    <div class="modal-box">
        <button class="modal-close" onclick="window.location='admin_pedidos.php?status=<?= $status_filtro ?>&data_ini=<?= $data_ini ?>&data_fim=<?= $data_fim ?>'">×</button>

        <h2 style="font-weight:900; font-size:22px; letter-spacing:-0.5px; margin-bottom:4px; text-transform:uppercase;">
            Pedido #<?= str_pad($pedido_det['id'],4,'0',STR_PAD_LEFT) ?>
        </h2>
        <p style="color:var(--text2); font-size:12px; margin-bottom:24px; display:flex; align-items:center; gap:10px;">
            <?= date('d/m/Y \à\s H:i', strtotime($pedido_det['data_pedido'])) ?>
            &nbsp;&mdash;&nbsp;
            <span class="badge badge-<?= $pedido_det['status'] ?>"><?= str_replace('_',' ',$pedido_det['status']) ?></span>
        </p>

        <!-- DADOS DO CLIENTE E ENTREGA -->
        <div style="font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:14px;">Dados do Cliente</div>
        <div class="detail-grid">
            <div class="detail-item">
                <span>Nome</span>
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
                <span>Forma de Pagamento</span>
                <p style="text-transform:uppercase; font-weight:800;"><?= $pedido_det['forma_pagamento'] ?></p>
            </div>
            <div class="detail-item" style="grid-column: span 2;">
                <span>Endereço de Entrega</span>
                <p>
                    <?= htmlspecialchars($pedido_det['end_endereco'] ?? '—') ?>
                    <?= $pedido_det['end_numero']   ? ', ' . $pedido_det['end_numero']   : '' ?>
                    <?= $pedido_det['end_bairro']   ? ' — ' . $pedido_det['end_bairro']  : '' ?>,
                    <?= htmlspecialchars($pedido_det['end_cidade'] ?? '') ?>/<?= $pedido_det['end_estado'] ?? '' ?>
                    &nbsp;— CEP <?= $pedido_det['end_cep'] ?? '—' ?>
                </p>
            </div>
        </div>

        <!-- ITENS DO PEDIDO -->
        <div style="font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:14px;">Itens do Pedido</div>

        <?php foreach($itens_det as $item): ?>
        <div class="item-row">
            <img class="item-img"
                 src="img/produtos/<?= $item['imagem'] ?>"
                 onerror="this.style.background='var(--grey-bg)'; this.style.display='block';">
            <div style="flex:1;">
                <div style="font-weight:700; font-size:13px;"><?= htmlspecialchars($item['produto_nome']) ?></div>
                <div style="font-size:11px; color:var(--text2); margin-top:2px;"><?= $item['variacoes'] ?></div>
            </div>
            <div style="text-align:right;">
                <div style="font-weight:800;"><?= $item['quantidade'] ?>×</div>
                <div style="font-size:12px; color:var(--text2);">R$ <?= number_format($item['preco_unitario'],2,',','.') ?></div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- TOTAL -->
        <div style="display:flex; justify-content:space-between; align-items:center; padding:18px 0 0; margin-top:8px; border-top:1px solid var(--border);">
            <span style="font-size:12px; color:var(--text2); font-weight:700; text-transform:uppercase;">Total do Pedido</span>
            <span style="font-size:22px; font-weight:900; letter-spacing:-0.5px;">
                R$ <?= number_format($pedido_det['total'],2,',','.') ?>
            </span>
        </div>

        <!-- FORMULÁRIO DE ATUALIZAÇÃO -->
        <form method="POST" class="status-form">
            <input type="hidden" name="pedido_id" value="<?= $pedido_det['id'] ?>">

            <div style="font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:16px;">Atualizar Pedido</div>

            <div class="form-row-2">
                <div class="form-group-modal">
                    <label>Novo Status</label>
                    <select name="status">
                        <?php foreach(['pendente','pago','em_separacao','enviado','entregue','cancelado'] as $s): ?>
                        <option value="<?= $s ?>" <?= $pedido_det['status'] == $s ? 'selected' : '' ?>>
                            <?= ucfirst(str_replace('_',' ',$s)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group-modal">
                    <label>Transportadora</label>
                    <input type="text" name="transportadora" value="<?= htmlspecialchars($pedido_det['transportadora'] ?? '') ?>" placeholder="Ex: Correios, Jadlog">
                </div>
            </div>

            <div class="form-group-modal" style="margin-top:14px;">
                <label>Código de Rastreio</label>
                <input type="text" name="codigo_rastreio" value="<?= htmlspecialchars($pedido_det['codigo_rastreio'] ?? '') ?>" placeholder="Ex: BR123456789BR">
            </div>

            <button type="submit" name="atualizar_status" class="btn-black-capsule" style="margin-top:18px; font-size:12px; letter-spacing:1px;">
                💾 Salvar Alterações
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>