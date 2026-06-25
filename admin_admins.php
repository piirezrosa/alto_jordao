<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

// Apenas superadmin acessa esta tela
if (!isset($_SESSION['usuario_nivel']) || $_SESSION['usuario_nivel'] !== 'superadmin') {
    header("Location: admin_dashboard.php?msg=sem_permissao"); exit();
}

// ── CRIAR ADMIN ───────────────────────────────
if (isset($_POST['acao']) && $_POST['acao'] === 'criar') {
    $nome  = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $nivel = $_POST['nivel'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
    $perms = isset($_POST['permissoes']) ? json_encode($_POST['permissoes']) : '[]';

    $existe = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email=?"); $existe->execute([$email]);
    if ($existe->fetchColumn()) { header("Location: admin_admins.php?msg=duplicado"); exit(); }

    $pdo->prepare("INSERT INTO usuarios (nome,email,senha,nivel,status,permissoes) VALUES (?,?,?,?,?,?)")
        ->execute([$nome,$email,$senha,$nivel,'ativo',$perms]);
    $log = $pdo->prepare("INSERT INTO logs_sistema (usuario_id,acao,tabela,registro_id,detalhes,ip) VALUES (?,?,?,?,?,?)");
    $log->execute([$_SESSION['usuario_id']??null,'admin_criado','usuarios',$pdo->lastInsertId(),$nivel.': '.$nome,$_SERVER['REMOTE_ADDR']]);
    header("Location: admin_admins.php?msg=criado"); exit();
}

// ── TOGGLE STATUS ─────────────────────────────
if (isset($_GET['toggle']) && (int)$_GET['toggle'] !== (int)$_SESSION['usuario_id']) {
    $id = (int)$_GET['toggle'];
    $cur = $pdo->prepare("SELECT status FROM usuarios WHERE id=? AND nivel != 'cliente'"); $cur->execute([$id]);
    $cur_status = $cur->fetchColumn();
    if ($cur_status !== false) {
        $novo = $cur_status === 'ativo' ? 'bloqueado' : 'ativo';
        $pdo->prepare("UPDATE usuarios SET status=? WHERE id=?")->execute([$novo,$id]);
        $log = $pdo->prepare("INSERT INTO logs_sistema (usuario_id,acao,tabela,registro_id,detalhes,ip) VALUES (?,?,?,?,?,?)");
        $log->execute([$_SESSION['usuario_id']??null,'admin_'.$novo,'usuarios',$id,'Status: '.$novo,$_SERVER['REMOTE_ADDR']]);
    }
    header("Location: admin_admins.php?msg=atualizado"); exit();
}

// ── EXCLUIR ───────────────────────────────────
if (isset($_GET['excluir']) && (int)$_GET['excluir'] !== (int)$_SESSION['usuario_id']) {
    $id = (int)$_GET['excluir'];
    $pdo->prepare("UPDATE usuarios SET nivel='cliente' WHERE id=? AND nivel != 'superadmin'")->execute([$id]);
    header("Location: admin_admins.php?msg=rebaixado"); exit();
}

// ── DADOS ─────────────────────────────────────
$admins = $pdo->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM logs_sistema l WHERE l.usuario_id=u.id) as total_acoes,
           (SELECT MAX(data) FROM logs_sistema l WHERE l.usuario_id=u.id) as ultimo_acesso
    FROM usuarios u
    WHERE u.nivel IN ('admin','superadmin','gerente','operador')
    ORDER BY FIELD(u.nivel,'superadmin','admin','gerente','operador'), u.nome
")->fetchAll(PDO::FETCH_ASSOC);

$devolucoes_pend = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();
$estoque_critico = $pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque<=3 AND ativo=1")->fetchColumn();
$p_pendente_sb   = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();

$nivel_labels = ['superadmin'=>'Super Admin','admin'=>'Admin','gerente'=>'Gerente','operador'=>'Operador'];
$nivel_cores  = ['superadmin'=>'var(--black)','admin'=>'#3b82f6','gerente'=>'var(--warning)','operador'=>'var(--muted)'];

$modulos = ['Produtos','Pedidos','Financeiro','Clientes','Estoque','Devoluções','Relatórios','Cupons'];

define('CONTEUDO_AUTORIZADO', true);
$pagina_atual = 'admins';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administradores | Alto Jordão Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <style>
        .page-grid { display:grid; grid-template-columns:1fr 380px; gap:22px; align-items:start; }
        .form-card .input-group { display:flex; flex-direction:column; gap:8px; margin-bottom:16px; }
        .form-card label.lbl { font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1px; }
        .form-card input, .form-card select {
            padding:12px 16px; border:1.5px solid var(--border); border-radius:50px;
            font-family:var(--font-main); font-size:13px; background:var(--grey-bg); color:var(--black); outline:none; transition:var(--transition);
        }
        .form-card input:focus, .form-card select:focus { border-color:var(--black); background:var(--white); }

        .admin-row { display:flex; align-items:center; gap:16px; padding:16px 0; border-bottom:1px solid var(--border); }
        .admin-row:last-child { border-bottom:none; }
        .admin-avatar { width:44px; height:44px; border-radius:50%; background:var(--black); color:var(--white); display:flex; align-items:center; justify-content:center; font-weight:900; font-size:16px; flex-shrink:0; }
        .admin-info { flex:1; min-width:0; }
        .admin-nome { font-weight:800; font-size:14px; }
        .admin-email { font-size:11px; color:var(--muted); margin-top:2px; }
        .nivel-pill { padding:3px 12px; border-radius:50px; font-size:10px; font-weight:800; text-transform:uppercase; white-space:nowrap; }
        .badge-ativo   { background:rgba(46,125,50,.1); color:var(--success); padding:3px 10px; border-radius:50px; font-size:10px; font-weight:800; }
        .badge-bloqueado { background:rgba(255,77,77,.1); color:var(--danger); padding:3px 10px; border-radius:50px; font-size:10px; font-weight:800; }
        .row-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .btn-sm { padding:6px 14px; border-radius:50px; font-size:10px; font-weight:800; text-decoration:none; text-transform:uppercase; transition:var(--transition); }
        .btn-sm-toggle { background:var(--grey-bg); color:var(--text2); border:1px solid var(--border); }
        .btn-sm-del    { background:rgba(255,77,77,.1); color:var(--danger); border:1px solid rgba(255,77,77,.25); }
        .btn-sm:hover  { opacity:.8; transform:translateY(-1px); }
        .voce-tag { background:var(--black); color:var(--white); padding:3px 10px; border-radius:50px; font-size:9px; font-weight:800; }

        .permissoes-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:4px; }
        .perm-check { display:flex; align-items:center; gap:8px; padding:8px 12px; background:var(--grey-bg); border-radius:50px; font-size:12px; font-weight:600; cursor:pointer; }
        .perm-check input { accent-color:var(--black); }

        .aviso-acesso { background:rgba(245,158,11,.08); border:1px solid rgba(245,158,11,.3); border-radius:14px; padding:14px 18px; margin-bottom:20px; font-size:13px; color:#b45309; font-weight:600; }

        .msg-ok  { background:#e8f5e9; border:1px solid #c8e6c9; color:var(--success); padding:12px 22px; border-radius:50px; margin-bottom:20px; font-size:13px; font-weight:700; display:inline-block; }
        .msg-err { background:rgba(255,77,77,.08); border:1px solid rgba(255,77,77,.25); color:var(--danger); padding:12px 22px; border-radius:50px; margin-bottom:20px; font-size:13px; font-weight:700; display:inline-block; }
    </style>
</head>
<body class="admin-page">

<?php include 'sidebar.php'; ?>

<main class="admin-main">
    <div class="admin-topbar">
        <div><h1>Administradores</h1><p>Gerencie os usuários administrativos e seus níveis de acesso.</p></div>
    </div>

    <div class="aviso-acesso">🔐 Esta área é restrita ao <strong>Super Admin</strong>. Alterações aqui afetam diretamente quem pode acessar o painel.</div>

    <?php if(isset($_GET['msg'])): ?>
    <?php $msgs=['criado'=>'✓ Administrador criado!','atualizado'=>'✓ Status atualizado!','rebaixado'=>'✓ Usuário rebaixado para cliente.','duplicado'=>'⚠ Este e-mail já está em uso.']; ?>
    <div class="<?= $_GET['msg']==='duplicado'?'msg-err':'msg-ok' ?>"><?= $msgs[$_GET['msg']]??'' ?></div>
    <?php endif; ?>

    <div class="kpi-grid">
        <?php foreach(['superadmin'=>'👑','admin'=>'🛡️','gerente'=>'📋','operador'=>'🔧'] as $nivel => $icone):
            $count = count(array_filter($admins,fn($a)=>$a['nivel']===$nivel)); ?>
        <div class="kpi-card <?= $nivel==='superadmin'?'featured':'' ?>">
            <div class="kpi-icon"><?= $icone ?></div>
            <div class="kpi-label"><?= $nivel_labels[$nivel] ?></div>
            <div class="kpi-value"><?= $count ?></div>
            <div class="kpi-sub">usuário<?= $count!=1?'s':'' ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="page-grid">
        <!-- LISTA -->
        <div class="admin-card">
            <div class="card-header"><span class="card-title">Equipe Administrativa (<?= count($admins) ?>)</span></div>
            <?php foreach($admins as $a): $eh_voce = $a['id']==$_SESSION['usuario_id']; ?>
            <div class="admin-row">
                <div class="admin-avatar" style="background:<?= $nivel_cores[$a['nivel']] ?>"><?= strtoupper(substr($a['nome'],0,1)) ?></div>
                <div class="admin-info">
                    <div class="admin-nome">
                        <?= htmlspecialchars($a['nome']) ?>
                        <?php if($eh_voce): ?><span class="voce-tag">Você</span><?php endif; ?>
                    </div>
                    <div class="admin-email"><?= htmlspecialchars($a['email']) ?></div>
                    <div style="font-size:11px; color:var(--muted); margin-top:3px;">
                        <?= $a['total_acoes'] ?> ações · Último acesso: <?= $a['ultimo_acesso'] ? date('d/m/Y',strtotime($a['ultimo_acesso'])) : 'nunca' ?>
                    </div>
                </div>
                <span class="nivel-pill" style="background:<?= $nivel_cores[$a['nivel']] ?>1a; color:<?= $nivel_cores[$a['nivel']] ?>">
                    <?= $nivel_labels[$a['nivel']] ?>
                </span>
                <span class="<?= $a['status']==='ativo'?'badge-ativo':'badge-bloqueado' ?>"><?= $a['status'] ?></span>
                <?php if(!$eh_voce && $a['nivel']!=='superadmin'): ?>
                <div class="row-actions">
                    <a href="?toggle=<?= $a['id'] ?>" class="btn-sm btn-sm-toggle" onclick="return confirm('Alterar status deste admin?')">
                        <?= $a['status']==='ativo'?'Bloquear':'Desbloquear' ?>
                    </a>
                    <a href="?excluir=<?= $a['id'] ?>" class="btn-sm btn-sm-del" onclick="return confirm('Rebaixar para cliente?')">Rebaixar</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- FORMULÁRIO -->
        <div class="admin-card form-card">
            <div class="card-header" style="margin-bottom:20px;"><span class="card-title">Novo Administrador</span></div>
            <form method="POST">
                <input type="hidden" name="acao" value="criar">
                <div class="input-group"><label class="lbl">Nome Completo *</label><input type="text" name="nome" placeholder="Nome do administrador" required></div>
                <div class="input-group"><label class="lbl">E-mail *</label><input type="email" name="email" placeholder="email@altojordao.com" required></div>
                <div class="input-group"><label class="lbl">Senha Provisória *</label><input type="password" name="senha" placeholder="Mínimo 8 caracteres" minlength="8" required></div>
                <div class="input-group"><label class="lbl">Nível de Acesso *</label>
                    <select name="nivel">
                        <option value="operador">Operador — acesso básico</option>
                        <option value="gerente">Gerente — sem financeiro/logs</option>
                        <option value="admin">Admin — acesso completo</option>
                    </select>
                </div>
                <div class="input-group">
                    <label class="lbl">Módulos Permitidos</label>
                    <div class="permissoes-grid">
                        <?php foreach($modulos as $mod): ?>
                        <label class="perm-check">
                            <input type="checkbox" name="permissoes[]" value="<?= $mod ?>">
                            <?= $mod ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" class="btn-admin-primary" style="width:100%;padding:14px;border-radius:50px;justify-content:center;margin-top:8px;">Criar Administrador</button>
            </form>
        </div>
    </div>
</main>
<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>