<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['usuario_nivel']) || !in_array($_SESSION['usuario_nivel'], ['admin','superadmin','gerente'])) {
    header("Location: login.php"); exit;
}

// ── APROVAR / RECUSAR ─────────────────────────
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id          = (int)$_GET['id'];
    $novo_status = ($_GET['action'] === 'aprovar') ? 'aprovado' : 'recusado';

    $pdo->prepare("UPDATE devolucoes SET status = ?, data_resolucao = NOW() WHERE id = ?")
        ->execute([$novo_status, $id]);

    $log = $pdo->prepare("INSERT INTO logs_sistema (usuario_id, acao, tabela, registro_id, detalhes, ip) VALUES (?,?,?,?,?,?)");
    $log->execute([$_SESSION['usuario_id'] ?? null, 'devolucao_'.$novo_status, 'devolucoes', $id, 'Status: '.$novo_status, $_SERVER['REMOTE_ADDR']]);

    header("Location: admin_devolucoes.php?msg=".$novo_status); exit;
}

// ── FILTRO DE STATUS ──────────────────────────
$filtro = $_GET['filtro'] ?? 'todos';

$sql = "SELECT d.*, u.nome as cliente FROM devolucoes d 
        JOIN usuarios u ON d.usuario_id = u.id";
if ($filtro !== 'todos') $sql .= " WHERE d.status = " . $pdo->quote($filtro);
$sql .= " ORDER BY d.data_solicitacao DESC";

$devolucoes = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// ── CONTAGENS POR STATUS ──────────────────────
$c_pendente  = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();
$c_aprovado  = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='aprovado'")->fetchColumn();
$c_recusado  = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='recusado'")->fetchColumn();

// ── BADGES SIDEBAR ────────────────────────────
$devolucoes_pend = $c_pendente;
$estoque_critico = $pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque<=3 AND ativo=1")->fetchColumn();
$p_pendente_sb   = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devoluções | Alto Jordão Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <style>
        /* ── EXTRAS EXCLUSIVOS DA PÁGINA DE DEVOLUÇÕES ── */

        .status-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 22px;
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
        .tab-btn.active { background: var(--black); color: var(--white); border-color: var(--black); }

        /* Card de devolução */
        .dev-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 30px;
            margin-bottom: 20px;
            display: flex;
            gap: 28px;
            align-items: flex-start;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .dev-card:hover { transform: translateY(-3px); box-shadow: 0 12px 35px rgba(0,0,0,0.07); }

        /* Foto do defeito */
        .dev-photo-wrapper { flex-shrink: 0; }

        .dev-photo {
            width: 180px; height: 180px;
            border-radius: 20px;
            object-fit: cover;
            border: 1px solid var(--border);
            cursor: zoom-in;
            transition: var(--transition);
            display: block;
        }

        .dev-photo:hover { filter: brightness(0.85); }

        .dev-photo-empty {
            width: 180px; height: 180px;
            border-radius: 20px;
            background: var(--grey-bg);
            border: 1px dashed var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 8px;
            color: var(--muted);
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
            padding: 16px;
        }

        /* Área de info */
        .dev-info { flex: 1; min-width: 0; }

        .dev-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .dev-order-id {
            font-size: 11px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .dev-client {
            font-weight: 900;
            font-size: 18px;
            margin: 6px 0 3px;
            letter-spacing: -0.5px;
        }

        .dev-date {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
        }

        /* Caixa de motivo */
        .reason-box {
            background: var(--grey-bg);
            border-left: 3px solid var(--black);
            border-radius: 0 14px 14px 0;
            padding: 14px 18px;
            margin: 16px 0;
            font-size: 13px;
            line-height: 1.6;
        }

        .reason-label {
            display: block;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--black);
            margin-bottom: 6px;
        }

        /* Status badges */
        .status-badge {
            padding: 5px 14px;
            border-radius: 50px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pendente  { background: rgba(245,158,11,0.12); color: #b45309; }
        .status-aprovado  { background: rgba(46,125,50,0.12);  color: var(--success); }
        .status-recusado  { background: rgba(255,77,77,0.12);  color: var(--danger); }
        .status-concluido { background: rgba(0,0,0,0.06);      color: var(--black); }

        /* Botões de ação */
        .action-btns { display: flex; gap: 12px; align-items: center; margin-top: 20px; }

        .btn-aprovar {
            padding: 10px 22px;
            border-radius: 50px;
            background: rgba(46,125,50,0.1);
            color: var(--success);
            border: 1px solid rgba(46,125,50,0.3);
            font-size: 11px;
            font-weight: 800;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: var(--transition);
        }

        .btn-aprovar:hover { background: var(--success); color: var(--white); }

        .btn-recusar {
            padding: 10px 22px;
            border-radius: 50px;
            background: rgba(255,77,77,0.1);
            color: var(--danger);
            border: 1px solid rgba(255,77,77,0.3);
            font-size: 11px;
            font-weight: 800;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: var(--transition);
        }

        .btn-recusar:hover { background: var(--danger); color: var(--white); }

        /* Mensagens de feedback */
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

        .msg-err {
            background: rgba(255,77,77,0.08);
            border: 1px solid rgba(255,77,77,0.25);
            color: var(--danger);
            padding: 13px 22px;
            border-radius: 50px;
            margin-bottom: 22px;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Estado vazio */
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            color: var(--muted);
        }

        .empty-state .empty-icon { font-size: 48px; margin-bottom: 16px; display: block; }
        .empty-state p { font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
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
        <a href="admin_pedidos.php" class="sb-item">
            🛒 Pedidos
            <?php if($p_pendente_sb > 0): ?><span class="sb-badge"><?= $p_pendente_sb ?></span><?php endif; ?>
        </a>
        <a href="admin_vendas.php"     class="sb-item">💰 Financeiro</a>
        <a href="entregas.php"         class="sb-item">📦 Logística</a>
        <a href="admin_devolucoes.php" class="sb-item active">
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
            <h1>Trocas e Devoluções</h1>
            <p>Gerencie solicitações de troca e analise evidências de defeitos.</p>
        </div>
    </div>

    <!-- FEEDBACK -->
    <?php if(isset($_GET['msg'])): ?>
        <?php if($_GET['msg'] === 'aprovado'): ?>
        <div class="msg-ok">✓ Devolução autorizada com sucesso!</div>
        <?php elseif($_GET['msg'] === 'recusado'): ?>
        <div class="msg-err">✕ Solicitação recusada.</div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card featured">
            <div class="kpi-icon">⏳</div>
            <div class="kpi-label">Pendentes</div>
            <div class="kpi-value"><?= $c_pendente ?></div>
            <div class="kpi-sub">Aguardando análise</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">✅</div>
            <div class="kpi-label">Aprovadas</div>
            <div class="kpi-value" style="color: var(--success);"><?= $c_aprovado ?></div>
            <div class="kpi-sub">Estornos/trocas autorizados</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">❌</div>
            <div class="kpi-label">Recusadas</div>
            <div class="kpi-value" style="color: var(--danger);"><?= $c_recusado ?></div>
            <div class="kpi-sub">Solicitações negadas</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">📊</div>
            <div class="kpi-label">Total de Solicitações</div>
            <div class="kpi-value"><?= $c_pendente + $c_aprovado + $c_recusado ?></div>
            <div class="kpi-sub">Histórico completo</div>
        </div>
    </div>

    <!-- TABS DE FILTRO -->
    <div class="status-tabs">
        <a href="?filtro=todos"    class="tab-btn <?= $filtro === 'todos'    ? 'active' : '' ?>">Todas (<?= $c_pendente + $c_aprovado + $c_recusado ?>)</a>
        <a href="?filtro=pendente" class="tab-btn <?= $filtro === 'pendente' ? 'active' : '' ?>">⏳ Pendentes (<?= $c_pendente ?>)</a>
        <a href="?filtro=aprovado" class="tab-btn <?= $filtro === 'aprovado' ? 'active' : '' ?>">✅ Aprovadas (<?= $c_aprovado ?>)</a>
        <a href="?filtro=recusado" class="tab-btn <?= $filtro === 'recusado' ? 'active' : '' ?>">❌ Recusadas (<?= $c_recusado ?>)</a>
    </div>

    <!-- CARDS DE DEVOLUÇÃO -->
    <?php if(empty($devolucoes)): ?>
    <div class="admin-card">
        <div class="empty-state">
            <span class="empty-icon">✅</span>
            <p>Nenhuma solicitação encontrada para este filtro</p>
        </div>
    </div>
    <?php endif; ?>

    <?php foreach($devolucoes as $d): ?>
    <div class="dev-card">

        <!-- FOTO DO DEFEITO -->
        <div class="dev-photo-wrapper">
            <?php if($d['foto_defeito']): ?>
                <a href="img/devolucoes/<?= htmlspecialchars($d['foto_defeito']) ?>" target="_blank">
                    <img src="img/devolucoes/<?= htmlspecialchars($d['foto_defeito']) ?>"
                         class="dev-photo"
                         title="Clique para ampliar">
                </a>
            <?php else: ?>
                <div class="dev-photo-empty">
                    <span style="font-size:28px; opacity:.3;">📷</span>
                    Sem foto enviada
                </div>
            <?php endif; ?>
        </div>

        <!-- INFORMAÇÕES -->
        <div class="dev-info">
            <div class="dev-header">
                <span class="dev-order-id">Pedido #<?= str_pad($d['pedido_id'],4,'0',STR_PAD_LEFT) ?></span>
                <span class="status-badge status-<?= $d['status'] ?>"><?= $d['status'] ?></span>
            </div>

            <h3 class="dev-client"><?= htmlspecialchars($d['cliente']) ?></h3>
            <span class="dev-date"><?= date('d/m/Y \à\s H:i', strtotime($d['data_solicitacao'])) ?></span>

            <div class="reason-box">
                <strong class="reason-label">Motivo: <?= htmlspecialchars($d['motivo']) ?></strong>
                <?= nl2br(htmlspecialchars($d['detalhes'] ?? '')) ?>
            </div>

            <?php if($d['status'] === 'pendente'): ?>
            <div class="action-btns">
                <a href="?action=aprovar&id=<?= $d['id'] ?>&filtro=<?= $filtro ?>"
                   class="btn-aprovar"
                   onclick="return confirm('Autorizar esta devolução/troca?')">
                    ✓ Autorizar Estorno/Troca
                </a>
                <a href="?action=recusar&id=<?= $d['id'] ?>&filtro=<?= $filtro ?>"
                   class="btn-recusar"
                   onclick="return confirm('Recusar esta solicitação?')">
                    ✕ Recusar Solicitação
                </a>
            </div>
            <?php elseif($d['status'] === 'aprovado'): ?>
            <p style="margin-top:16px; font-size:12px; font-weight:700; color:var(--success);">
                ✓ Estorno/troca autorizado
                <?= $d['data_resolucao'] ? '— ' . date('d/m/Y', strtotime($d['data_resolucao'])) : '' ?>
            </p>
            <?php elseif($d['status'] === 'recusado'): ?>
            <p style="margin-top:16px; font-size:12px; font-weight:700; color:var(--danger);">
                ✕ Solicitação recusada
                <?= $d['data_resolucao'] ? '— ' . date('d/m/Y', strtotime($d['data_resolucao'])) : '' ?>
            </p>
            <?php endif; ?>
        </div>

    </div>
    <?php endforeach; ?>

</main>

<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>