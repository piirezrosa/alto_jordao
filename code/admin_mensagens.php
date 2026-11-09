<?php
/** Caixa de entrada do "Fale conosco" (ajuda.php) — Alto Jordão Admin */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'contato_helpers.php';

if (!isset($_SESSION['usuario_nivel']) || !in_array($_SESSION['usuario_nivel'], ['admin', 'superadmin'], true)) {
    header("Location: login.php"); exit();
}
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
contato_garantir_schema($pdo);

$filtro = in_array($_GET['filtro'] ?? '', ['nova', 'respondida', 'todas'], true) ? $_GET['filtro'] : 'nova';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (contato_csrf_ok($_POST['csrf'] ?? null) && $id > 0) {
        $acao = $_POST['acao'] ?? '';
        if ($acao === 'respondida') {
            $pdo->prepare("UPDATE mensagens_contato SET status='respondida', respondida_em=NOW(), respondida_por=? WHERE id=?")->execute([$_SESSION['usuario_id'] ?? null, $id]);
        } elseif ($acao === 'reabrir') {
            $pdo->prepare("UPDATE mensagens_contato SET status='nova', respondida_em=NULL, respondida_por=NULL WHERE id=?")->execute([$id]);
        } elseif ($acao === 'excluir') {
            $pdo->prepare("DELETE FROM mensagens_contato WHERE id=?")->execute([$id]);
        }
    }
    header("Location: admin_mensagens.php?filtro=" . urlencode($filtro)); exit();
}

$where = $filtro === 'todas' ? '' : "WHERE status = " . $pdo->quote($filtro);
$msgs = $pdo->query("SELECT * FROM mensagens_contato $where ORDER BY data_criacao DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
$csrf = contato_csrf_token();
$n_novas = contato_novas($pdo);

define('CONTEUDO_AUTORIZADO', true);
$pagina_atual = 'mensagens';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensagens | Alto Jordão Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <style>
        .msg-card { background:var(--white); border:1px solid var(--border); border-radius:24px; padding:22px 26px; margin-bottom:14px; }
        .msg-card.nova { border-left:4px solid var(--black); }
        .msg-card.respondida { opacity:.65; }
        .msg-head { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:8px; }
        .msg-head strong { font-size:15px; }
        .msg-meta { font-size:12px; color:var(--muted); display:flex; gap:14px; flex-wrap:wrap; margin-bottom:12px; }
        .msg-body { font-size:14px; line-height:1.65; white-space:pre-wrap; word-break:break-word; margin-bottom:16px; }
        .msg-acoes { display:flex; gap:8px; flex-wrap:wrap; }
        .msg-acoes form { margin:0; }
        .msg-acoes .b { padding:8px 18px; border-radius:50px; border:1px solid var(--border); background:var(--grey-bg); color:var(--black); font:800 11px var(--font-main); text-transform:uppercase; cursor:pointer; text-decoration:none; }
        .msg-acoes .b.pri { background:var(--black); color:var(--white); border-color:var(--black); }
        .msg-acoes .b.del { color:var(--danger); }
    </style>
</head>
<body class="admin-page">
<?php include 'sidebar.php'; ?>
<main class="admin-main">
    <div class="admin-topbar">
        <div><h1>Mensagens</h1><p>Dúvidas enviadas pelos clientes na Central de ajuda. <?= $n_novas ?> aguardando resposta.</p></div>
    </div>
    <div class="tabs" style="margin-bottom:20px;">
        <?php foreach (['nova' => 'Novas', 'respondida' => 'Respondidas', 'todas' => 'Todas'] as $k => $n): ?>
        <a class="tab-btn <?= $filtro === $k ? 'active' : '' ?>" href="?filtro=<?= $k ?>"><?= $n ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (!$msgs): ?>
        <div class="admin-card" style="text-align:center;padding:50px;color:var(--muted);">Nenhuma mensagem aqui.</div>
    <?php endif; ?>

    <?php foreach ($msgs as $m):
        $assunto_resp = 'Re: ' . $m['assunto'] . ($m['pedido_numero'] ? ' (pedido ' . $m['pedido_numero'] . ')' : ''); ?>
    <article class="msg-card <?= e($m['status']) ?>">
        <div class="msg-head"><strong><?= e($m['assunto']) ?></strong><span class="msg-meta" style="margin:0"><?= date('d/m/Y H:i', strtotime($m['data_criacao'])) ?></span></div>
        <div class="msg-meta">
            <span>👤 <?= e($m['nome']) ?></span>
            <span>✉️ <?= e($m['email']) ?></span>
            <?php if ($m['pedido_numero']): ?><span>🧾 Pedido <?= e($m['pedido_numero']) ?></span><?php endif; ?>
            <?php if ($m['usuario_id']): ?><span>Cliente cadastrado</span><?php endif; ?>
        </div>
        <div class="msg-body"><?= e($m['mensagem']) ?></div>
        <div class="msg-acoes">
            <a class="b pri" href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode($assunto_resp) ?>">Responder por e-mail</a>
            <form method="POST"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                <?php if ($m['status'] === 'nova'): ?><button class="b" name="acao" value="respondida">Marcar como respondida</button>
                <?php else: ?><button class="b" name="acao" value="reabrir">Reabrir</button><?php endif; ?></form>
            <form method="POST" onsubmit="return confirm('Excluir esta mensagem?');"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                <button class="b del" name="acao" value="excluir">Excluir</button></form>
        </div>
    </article>
    <?php endforeach; ?>
</main>
</body>
</html>
