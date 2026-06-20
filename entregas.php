<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php'; 

if (!isset($_SESSION['usuario_nivel']) || !in_array($_SESSION['usuario_nivel'], ['admin','superadmin','gerente','operador'])) {
    header("Location: login.php"); exit();
}

// ── ATUALIZAR STATUS ──────────────────────────
if (isset($_GET['atualizar_id']) && isset($_GET['novo_status'])) {
    $id     = (int)$_GET['atualizar_id'];
    $status = strtolower(trim($_GET['novo_status']));

    $stmt = $pdo->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
    if ($stmt->execute([$status, $id])) {
        // Log da ação
        $log = $pdo->prepare("INSERT INTO logs_sistema (usuario_id, acao, tabela, registro_id, detalhes, ip) VALUES (?,?,?,?,?,?)");
        $log->execute([$_SESSION['usuario_id'] ?? null, 'pedido_status_atualizado', 'pedidos', $id, 'Status: '.$status, $_SERVER['REMOTE_ADDR']]);
        header("Location: entregas.php?sucesso=1"); exit();
    }
}

// ── FILTRO DE STATUS ──────────────────────────
$filtro = $_GET['filtro'] ?? 'todos';

$sql_base = "SELECT p.*, u.nome as cliente 
             FROM pedidos p 
             JOIN usuarios u ON p.usuario_id = u.id 
             WHERE p.status != 'cancelado'";

if ($filtro === 'pendente') $sql_base .= " AND p.status = 'pendente'";
if ($filtro === 'pago')     $sql_base .= " AND p.status = 'pago'";
if ($filtro === 'enviado')  $sql_base .= " AND p.status = 'enviado'";
if ($filtro === 'entregue') $sql_base .= " AND p.status = 'entregue'";

$sql_base .= " ORDER BY p.data_pedido DESC";

try {
    $pedidos = $pdo->query($sql_base)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pedidos = [];
}

// ── CONTAGENS POR STATUS (para KPIs e tabs) ───
$c_pendente = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();
$c_pago     = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pago'")->fetchColumn();
$c_enviado  = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='enviado'")->fetchColumn();
$c_entregue = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='entregue'")->fetchColumn();

// ── BADGES SIDEBAR ────────────────────────────
$devolucoes_pend = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();
$estoque_critico = $pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque<=3 AND ativo=1")->fetchColumn();
$p_pendente_sb   = $c_pendente;

define('CONTEUDO_AUTORIZADO', true);
$pagina_atual = 'dashboard';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logística | Alto Jordão Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <style>
        /* ── EXTRAS EXCLUSIVOS DA PÁGINA DE ENTREGAS ── */

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

        .tab-btn.active {
            background: var(--black);
            color: var(--white);
            border-color: var(--black);
        }

        /* Botões de ação inline na tabela */
        .action-group { display: flex; gap: 8px; justify-content: flex-end; }

        .btn-ship {
            padding: 7px 16px;
            border-radius: 50px;
            border: none;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--black);
            color: var(--white);
        }

        .btn-deliver {
            padding: 7px 16px;
            border-radius: 50px;
            border: none;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(46,125,50,0.1);
            color: var(--success);
            border: 1px solid rgba(46,125,50,0.3);
        }

        .btn-ship:hover    { background: #333; transform: translateY(-2px); }
        .btn-deliver:hover { background: var(--success); color: var(--white); transform: translateY(-2px); }

        .concluido-tag {
            color: var(--success);
            font-weight: 800;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

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
    </style>
</head>
<body class="admin-page">

<?php include 'sidebar.php'; ?>

<!-- ── CONTEÚDO ───────────────────────────────── -->
<main class="admin-main">

    <div class="admin-topbar">
        <div>
            <h1>Gestão de Logística</h1>
            <p>Atualize o status de entrega dos pedidos em tempo real.</p>
        </div>
    </div>

    <?php if(isset($_GET['sucesso'])): ?>
    <div class="msg-ok">✓ Status do pedido atualizado com sucesso!</div>
    <?php endif; ?>

    <!-- KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon">⏳</div>
            <div class="kpi-label">Pendentes</div>
            <div class="kpi-value"><?= $c_pendente ?></div>
            <div class="kpi-sub">Aguardando pagamento</div>
        </div>
        <div class="kpi-card featured">
            <div class="kpi-icon">💰</div>
            <div class="kpi-label">Pagos / A Despachar</div>
            <div class="kpi-value"><?= $c_pago ?></div>
            <div class="kpi-sub">Prontos para envio</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">🚀</div>
            <div class="kpi-label">Em Trânsito</div>
            <div class="kpi-value"><?= $c_enviado ?></div>
            <div class="kpi-sub">Pedidos despachados</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">✅</div>
            <div class="kpi-label">Entregues</div>
            <div class="kpi-value" style="color: var(--success);"><?= $c_entregue ?></div>
            <div class="kpi-sub">Concluídos</div>
        </div>
    </div>

    <!-- TABS DE FILTRO -->
    <div class="status-tabs">
        <a href="?filtro=todos"    class="tab-btn <?= $filtro === 'todos'    ? 'active' : '' ?>">Todos (<?= $c_pendente + $c_pago + $c_enviado + $c_entregue ?>)</a>
        <a href="?filtro=pendente" class="tab-btn <?= $filtro === 'pendente' ? 'active' : '' ?>">⏳ Pendentes (<?= $c_pendente ?>)</a>
        <a href="?filtro=pago"     class="tab-btn <?= $filtro === 'pago'     ? 'active' : '' ?>">💰 A Despachar (<?= $c_pago ?>)</a>
        <a href="?filtro=enviado"  class="tab-btn <?= $filtro === 'enviado'  ? 'active' : '' ?>">🚀 Em Trânsito (<?= $c_enviado ?>)</a>
        <a href="?filtro=entregue" class="tab-btn <?= $filtro === 'entregue' ? 'active' : '' ?>">✅ Entregues (<?= $c_entregue ?>)</a>
    </div>

    <!-- TABELA -->
    <div class="admin-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Valor</th>
                    <th>Status Atual</th>
                    <th>Data do Pedido</th>
                    <th style="text-align:right;">Ações de Entrega</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($pedidos as $p):
                    $st = strtolower(trim($p['status']));
                ?>
                <tr>
                    <td style="font-weight:900; color:var(--muted);">#<?= str_pad($p['id'],4,'0',STR_PAD_LEFT) ?></td>
                    <td style="font-weight:700;"><?= htmlspecialchars($p['cliente']) ?></td>
                    <td style="font-weight:800;">R$ <?= number_format($p['total'], 2, ',', '.') ?></td>
                    <td><span class="badge badge-<?= $st ?>"><?= str_replace('_',' ', $p['status']) ?></span></td>
                    <td style="color:var(--text2); font-size:12px;"><?= date('d/m/Y H:i', strtotime($p['data_pedido'])) ?></td>
                    <td>
                        <div class="action-group">
                            <?php if($st === 'entregue'): ?>
                                <span class="concluido-tag">✓ Concluído</span>

                            <?php else: ?>
                                <?php if($st !== 'enviado'): ?>
                                <a href="?atualizar_id=<?= $p['id'] ?>&novo_status=enviado&filtro=<?= $filtro ?>"
                                   class="btn-ship"
                                   onclick="return confirm('Despachar pedido #<?= str_pad($p['id'],4,'0',STR_PAD_LEFT) ?>?')">
                                    🚀 Despachar
                                </a>
                                <?php endif; ?>

                                <a href="?atualizar_id=<?= $p['id'] ?>&novo_status=entregue&filtro=<?= $filtro ?>"
                                   class="btn-deliver"
                                   onclick="return confirm('Confirmar entrega do pedido #<?= str_pad($p['id'],4,'0',STR_PAD_LEFT) ?>?')">
                                    ✅ Entregue
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if(empty($pedidos)): ?>
                <tr>
                    <td colspan="6" style="text-align:center; color:var(--muted); padding:50px; font-size:13px;">
                        Nenhum pedido encontrado para este filtro.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>

<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>