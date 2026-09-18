<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'Permissoes.php';

Permissoes::init($pdo);
Permissoes::exigir('ver_admins');

// ── CRIAR ADMIN ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['acao'] === 'criar') {
    Permissoes::exigir('criar_admin');

    $nome  = trim($_POST['nome']  ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha']      ?? '';
    $nivel = $_POST['nivel']      ?? 'operador';

    // Só superadmin pode criar superadmin ou admin
    if (in_array($nivel, ['superadmin','admin']) && Permissoes::nivel() !== 'superadmin') {
        $erro = 'Apenas superadmin pode criar admins deste nível.';
    } elseif (!$nome || !$email || strlen($senha) < 8) {
        $erro = 'Preencha todos os campos. Senha mínima: 8 caracteres.';
    } else {
        $chk = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetchColumn()) {
            $erro = 'E-mail já cadastrado no sistema.';
        } else {
            $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("
                INSERT INTO usuarios (nome, email, senha, nivel, status, data_cadastro)
                VALUES (?, ?, ?, ?, 'ativo', NOW())
            ")->execute([$nome, $email, $hash, $nivel]);

            $novo_id = $pdo->lastInsertId();

            // Permissões customizadas marcadas no form
            if (!empty($_POST['perms']) && is_array($_POST['perms'])) {
                foreach ($_POST['perms'] as $codigo => $val) {
                    Permissoes::conceder($novo_id, $codigo, (bool)(int)$val,
                        $_SESSION['usuario_id'], 'Definido no cadastro');
                }
            }

            $pdo->prepare("INSERT INTO logs_sistema (usuario_id, acao, tabela, registro_id, detalhes, ip)
                VALUES (?,?,?,?,?,?)")->execute([
                $_SESSION['usuario_id'], 'admin_criado', 'usuarios', $novo_id,
                "Nível: $nivel | Email: $email", $_SERVER['REMOTE_ADDR'] ?? ''
            ]);

            header("Location: admin_admins.php?msg=criado"); exit;
        }
    }
}

// ── EDITAR NÍVEL ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['acao'] === 'editar_nivel') {
    Permissoes::exigir('editar_admin');
    $id    = (int)$_POST['id'];
    $nivel = $_POST['nivel'] ?? 'operador';

    // Protege: não pode rebaixar superadmin se não for superadmin
    if (Permissoes::nivel() !== 'superadmin' && in_array($nivel, ['superadmin','admin'])) {
        $erro = 'Sem permissão para este nível.';
    } elseif ($id === $_SESSION['usuario_id']) {
        $erro = 'Você não pode alterar seu próprio nível.';
    } else {
        $pdo->prepare("UPDATE usuarios SET nivel = ? WHERE id = ?")->execute([$nivel, $id]);
        header("Location: admin_admins.php?msg=editado"); exit;
    }
}

// ── ALTERNAR STATUS ───────────────────────────────────────
if (isset($_GET['toggle']) && Permissoes::pode('editar_admin')) {
    $id = (int)$_GET['toggle'];
    if ($id !== $_SESSION['usuario_id']) {
        $cur = $pdo->prepare("SELECT status FROM usuarios WHERE id = ?");
        $cur->execute([$id]);
        $novo = $cur->fetchColumn() === 'ativo' ? 'bloqueado' : 'ativo';
        $pdo->prepare("UPDATE usuarios SET status = ? WHERE id = ?")->execute([$novo, $id]);
    }
    header("Location: admin_admins.php?msg=status"); exit;
}

// ── SALVAR PERMISSÕES CUSTOMIZADAS ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['acao'] === 'salvar_perms') {
    Permissoes::exigir('conceder_permissoes');
    $alvo_id = (int)$_POST['usuario_id'];

    // Remove todas as customizações do usuário
    $pdo->prepare("
        DELETE up FROM usuario_permissoes up
        WHERE up.usuario_id = ?
    ")->execute([$alvo_id]);

    // Reinsere as marcadas
    if (!empty($_POST['perms']) && is_array($_POST['perms'])) {
        foreach ($_POST['perms'] as $codigo => $val) {
            Permissoes::conceder($alvo_id, $codigo, (int)$val === 1,
                $_SESSION['usuario_id'], $_POST['obs'][$codigo] ?? '');
        }
    }

    $pdo->prepare("INSERT INTO logs_sistema (usuario_id, acao, tabela, registro_id, detalhes, ip)
        VALUES (?,?,?,?,?,?)")->execute([
        $_SESSION['usuario_id'], 'permissoes_alteradas', 'usuarios', $alvo_id,
        "Permissões customizadas atualizadas", $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    header("Location: admin_admins.php?msg=perms&editando=$alvo_id"); exit;
}

// ── DADOS ─────────────────────────────────────────────────
$admins = $pdo->query("
    SELECT u.id, u.nome, u.email, u.nivel, u.status, u.data_cadastro, u.ultimo_acesso,
           (SELECT COUNT(*) FROM usuario_permissoes up WHERE up.usuario_id = u.id) as n_custom
    FROM usuarios u
    WHERE u.nivel IN ('superadmin','admin','gerente','operador')
    ORDER BY FIELD(u.nivel,'superadmin','admin','gerente','operador'), u.nome
")->fetchAll(PDO::FETCH_ASSOC);

$todas_perms = Permissoes::todas();
$modulos     = array_unique(array_column($todas_perms, 'modulo'));

// Admin sendo editado (permissões customizadas)
$editando      = null;
$perms_custom  = [];
if (isset($_GET['editando'])) {
    $editando = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? AND nivel != 'cliente'");
    $editando->execute([(int)$_GET['editando']]);
    $editando = $editando->fetch(PDO::FETCH_ASSOC);

    if ($editando) {
        $stmt = $pdo->prepare("
            SELECT p.codigo, up.concedida, up.observacao
            FROM usuario_permissoes up
            JOIN permissoes p ON p.id = up.permissao_id
            WHERE up.usuario_id = ?
        ");
        $stmt->execute([$editando['id']]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $perms_custom[$r['codigo']] = $r;
        }
    }
}

$c_nao_lidos    = 0;
try { $c_nao_lidos = $pdo->query("SELECT COUNT(*) FROM alertas WHERE lido=0")->fetchColumn(); } catch(Exception $e){}
$devolucoes_pend = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();
$estoque_critico = $pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque<=3 AND ativo=1")->fetchColumn();
$p_pendente_sb   = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();

$nivel_cores = [
    'superadmin' => ['bg'=>'#000',     'txt'=>'#fff'],
    'admin'      => ['bg'=>'#1a1a1a',  'txt'=>'#fff'],
    'gerente'    => ['bg'=>'#1976d2',  'txt'=>'#fff'],
    'operador'   => ['bg'=>'#f59e0b',  'txt'=>'#000'],
];
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
        .page-grid { display:grid; grid-template-columns:1fr <?= $editando ? '420px' : '380px' ?>; gap:22px; align-items:start; }

        .admin-row { display:flex; align-items:center; gap:14px; padding:16px 0; border-bottom:1px solid var(--border); }
        .admin-row:last-child { border-bottom:none; }

        .admin-avatar { width:42px; height:42px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:900; font-size:16px; flex-shrink:0; }

        .admin-info { flex:1; min-width:0; }
        .admin-nome  { font-weight:800; font-size:14px; margin-bottom:2px; }
        .admin-email { font-size:11px; color:var(--muted); }
        .admin-meta  { font-size:10px; color:var(--muted); margin-top:3px; }

        .nivel-badge { display:inline-block; padding:4px 12px; border-radius:50px; font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:1px; }
        .badge-ativo    { background:rgba(46,125,50,.1); color:var(--success); padding:3px 10px; border-radius:50px; font-size:10px; font-weight:800; }
        .badge-bloqueado{ background:rgba(255,77,77,.1);  color:var(--danger);  padding:3px 10px; border-radius:50px; font-size:10px; font-weight:800; }

        .row-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .btn-sm { padding:6px 14px; border-radius:50px; font-size:11px; font-weight:800; text-decoration:none; text-transform:uppercase; transition:var(--transition); cursor:pointer; border:none; }
        .btn-sm-perm  { background:var(--grey-bg); color:var(--black); border:1px solid var(--border); }
        .btn-sm-block { background:rgba(255,77,77,.1); color:var(--danger); border:1px solid rgba(255,77,77,.3); }
        .btn-sm:hover { opacity:.8; transform:translateY(-1px); }

        /* Formulário */
        .form-card .input-group { display:flex; flex-direction:column; gap:7px; margin-bottom:14px; }
        .form-card label { font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1px; }
        .form-card input, .form-card select { padding:11px 16px; border:1.5px solid var(--border); border-radius:50px; font-family:var(--font-main); font-size:13px; background:var(--grey-bg); color:var(--black); outline:none; transition:var(--transition); }
        .form-card input:focus, .form-card select:focus { border-color:var(--black); background:var(--white); }

        /* Permissões customizadas */
        .perm-modulo { font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:2px; color:var(--muted); padding:14px 0 8px; border-bottom:1px solid var(--border); margin-bottom:10px; display:flex; align-items:center; gap:10px; }
        .perm-row { display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px dashed var(--border); }
        .perm-row:last-child { border-bottom:none; }
        .perm-info { flex:1; min-width:0; }
        .perm-codigo { font-size:11px; font-weight:800; font-family:monospace; }
        .perm-desc   { font-size:11px; color:var(--text2); }
        .perm-critico { font-size:9px; background:rgba(255,77,77,.1); color:var(--danger); padding:2px 8px; border-radius:50px; font-weight:800; margin-left:4px; }

        .perm-select { padding:5px 10px; border-radius:50px; border:1px solid var(--border); font-size:11px; font-family:var(--font-main); background:var(--grey-bg); }
        .perm-select.concedida { border-color:var(--success); background:rgba(46,125,50,.08); color:var(--success); }
        .perm-select.negada    { border-color:var(--danger);  background:rgba(255,77,77,.08);  color:var(--danger); }
        .perm-select.padrao    { color:var(--muted); }

        .editing-banner { background:var(--black); color:var(--white); padding:14px 18px; border-radius:16px; font-size:13px; font-weight:700; margin-bottom:18px; display:flex; justify-content:space-between; align-items:center; }
        .editing-banner a { color:#aaa; font-size:11px; text-decoration:none; }
        .editing-banner small { display:block; font-size:11px; opacity:.6; margin-top:2px; font-weight:400; }

        .msg-ok  { background:#e8f5e9; border:1px solid #c8e6c9; color:var(--success); padding:12px 22px; border-radius:50px; margin-bottom:20px; font-size:13px; font-weight:700; display:inline-block; }
        .msg-err { background:rgba(255,77,77,.08); border:1px solid rgba(255,77,77,.25); color:var(--danger); padding:12px 22px; border-radius:50px; margin-bottom:20px; font-size:13px; font-weight:700; display:inline-block; }

        .nivel-legenda { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px; }
        .nivel-chip    { padding:5px 14px; border-radius:50px; font-size:11px; font-weight:800; text-transform:uppercase; }
    </style>
</head>
<body class="admin-page">

<aside class="admin-sidebar">
    <div class="sb-logo">ALTO JORDÃO</div>
    <div class="sb-section">
        <span class="sb-section-title">Visão Geral</span>
        <a href="admin_dashboard.php" class="sb-item">📊 Dashboard</a>
        <a href="admin_alertas.php"   class="sb-item">🔔 Alertas <?php if($c_nao_lidos>0): ?><span class="sb-badge"><?= $c_nao_lidos ?></span><?php endif; ?></a>
    </div>
    <div class="sb-section">
        <span class="sb-section-title">Vendas</span>
        <a href="admin_pedidos.php"    class="sb-item">🛒 Pedidos <?php if($p_pendente_sb>0): ?><span class="sb-badge"><?= $p_pendente_sb ?></span><?php endif; ?></a>
        <a href="admin_vendas.php"     class="sb-item">💰 Financeiro</a>
        <a href="entregas.php"         class="sb-item">📦 Logística</a>
        <a href="admin_devolucoes.php" class="sb-item">🔄 Devoluções <?php if($devolucoes_pend>0): ?><span class="sb-badge"><?= $devolucoes_pend ?></span><?php endif; ?></a>
    </div>
    <div class="sb-section">
        <span class="sb-section-title">Catálogo</span>
        <a href="admin_produtos.php"   class="sb-item">👕 Produtos</a>
        <a href="admin_estoque.php"    class="sb-item">📋 Estoque <?php if($estoque_critico>0): ?><span class="sb-badge"><?= $estoque_critico ?></span><?php endif; ?></a>
        <a href="admin_categorias.php" class="sb-item">🏷️ Categorias</a>
        <a href="admin_colecoes.php"   class="sb-item">✨ Coleções</a>
    </div>
    <div class="sb-section">
        <span class="sb-section-title">Usuários</span>
        <a href="admin_clientes.php" class="sb-item">👥 Clientes</a>
        <a href="admin_admins.php"   class="sb-item active">🛡️ Administradores</a>
    </div>
    <div class="sb-section">
        <span class="sb-section-title">Marketing</span>
        <a href="admin_cupons.php"             class="sb-item">🎟️ Cupons</a>
        <a href="admin_campanhas_sazonais.php" class="sb-item">🎄 Campanhas</a>
        <a href="admin_avaliacoes.php"         class="sb-item">⭐ Avaliações</a>
    </div>
    <div class="sb-section">
        <span class="sb-section-title">Sistema</span>
        <a href="admin_relatorios_v2.php"        class="sb-item">📈 Relatórios</a>
        <a href="admin_logs.php"                 class="sb-item">🔍 Logs</a>
        <a href="admin_configuracoes.php"        class="sb-item">⚙️ Configurações</a>
        <a href="admin_configuracoes_alertas.php"class="sb-item">🔔 Config. Alertas</a>
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
            <h1>Administradores</h1>
            <p>Gerencie acessos, níveis e permissões customizadas.</p>
        </div>
    </div>

    <?php
    $msgs = ['criado'=>'✓ Administrador criado!','editado'=>'✓ Nível atualizado!','status'=>'✓ Status alterado!','perms'=>'✓ Permissões salvas!'];
    if(isset($_GET['msg'])): ?>
    <div class="msg-ok"><?= $msgs[$_GET['msg']] ?? '' ?></div>
    <?php endif; ?>
    <?php if(!empty($erro)): ?>
    <div class="msg-err">⚠ <?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <!-- LEGENDA DE NÍVEIS -->
    <div class="nivel-legenda">
        <?php foreach($nivel_cores as $nv => $cor): ?>
        <span class="nivel-chip" style="background:<?= $cor['bg'] ?>;color:<?= $cor['txt'] ?>;"><?= $nv ?></span>
        <?php endforeach; ?>
        <span class="nivel-chip" style="background:var(--grey-bg);color:var(--muted);">
            operador — só tarefas do dia a dia
        </span>
    </div>

    <div class="page-grid">

        <!-- LISTA + FORM EDIÇÃO DE NÍVEL -->
        <div>
            <div class="admin-card">
                <div class="card-header">
                    <span class="card-title">Equipe Admin (<?= count($admins) ?>)</span>
                </div>

                <?php foreach($admins as $a):
                    $cor = $nivel_cores[$a['nivel']] ?? ['bg'=>'#eee','txt'=>'#000'];
                    $eh_eu = $a['id'] == $_SESSION['usuario_id'];
                ?>
                <div class="admin-row">
                    <div class="admin-avatar"
                         style="background:<?= $cor['bg'] ?>;color:<?= $cor['txt'] ?>;">
                        <?= strtoupper(substr($a['nome'],0,1)) ?>
                    </div>
                    <div class="admin-info">
                        <div class="admin-nome">
                            <?= htmlspecialchars($a['nome']) ?>
                            <?= $eh_eu ? '<span style="font-size:10px;color:var(--muted);">(você)</span>' : '' ?>
                        </div>
                        <div class="admin-email"><?= htmlspecialchars($a['email']) ?></div>
                        <div class="admin-meta">
                            Último acesso: <?= $a['ultimo_acesso'] ? date('d/m/Y H:i', strtotime($a['ultimo_acesso'])) : 'Nunca' ?>
                            <?= $a['n_custom'] > 0 ? " · <strong>{$a['n_custom']} permissão(ões) customizada(s)</strong>" : '' ?>
                        </div>
                    </div>

                    <?php if(Permissoes::pode('editar_admin') && !$eh_eu): ?>
                    <!-- Select de nível inline -->
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="acao" value="editar_nivel">
                        <input type="hidden" name="id"   value="<?= $a['id'] ?>">
                        <select name="nivel" onchange="this.form.submit()"
                                style="padding:6px 12px;border-radius:50px;border:1px solid var(--border);font-size:12px;font-family:var(--font-main);background:var(--grey-bg);">
                            <?php foreach(array_keys($nivel_cores) as $nv): ?>
                            <?php
                                // Operadores não conseguem dar níveis acima de si mesmos
                                if (!in_array($nv,['superadmin','admin']) || Permissoes::nivel()==='superadmin'):
                            ?>
                            <option value="<?= $nv ?>" <?= $a['nivel']===$nv?'selected':''?>><?= $nv ?></option>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php else: ?>
                    <span class="nivel-badge" style="background:<?= $cor['bg'] ?>;color:<?= $cor['txt'] ?>;"><?= $a['nivel'] ?></span>
                    <?php endif; ?>

                    <span class="<?= $a['status']==='ativo'?'badge-ativo':'badge-bloqueado' ?>"><?= $a['status'] ?></span>

                    <div class="row-actions">
                        <?php if(Permissoes::pode('conceder_permissoes') && !$eh_eu): ?>
                        <a href="?editando=<?= $a['id'] ?>" class="btn-sm btn-sm-perm">🔑 Permissões</a>
                        <?php endif; ?>
                        <?php if(Permissoes::pode('editar_admin') && !$eh_eu): ?>
                        <a href="?toggle=<?= $a['id'] ?>" class="btn-sm btn-sm-block"
                           onclick="return confirm('Alterar status de <?= htmlspecialchars($a['nome']) ?>?')">
                            <?= $a['status']==='ativo' ? 'Bloquear' : 'Ativar' ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- PAINEL DIREITO: CRIAR OU EDITAR PERMISSÕES -->
        <div class="admin-card form-card">

            <?php if($editando && Permissoes::pode('conceder_permissoes')): ?>
            <!-- EDITAR PERMISSÕES CUSTOMIZADAS -->
            <div class="editing-banner">
                <div>
                    🔑 Permissões de <?= htmlspecialchars(explode(' ',$editando['nome'])[0]) ?>
                    <small>Nível base: <?= $editando['nivel'] ?> — customizações sobrepõem o padrão</small>
                </div>
                <a href="admin_admins.php">✕</a>
            </div>

            <form method="POST">
                <input type="hidden" name="acao"       value="salvar_perms">
                <input type="hidden" name="usuario_id" value="<?= $editando['id'] ?>">

                <?php foreach($modulos as $modulo): ?>
                <div class="perm-modulo">
                    <?= strtoupper($modulo) ?>
                </div>
                <?php
                $perms_mod = array_filter($todas_perms, fn($p) => $p['modulo'] === $modulo);
                foreach($perms_mod as $perm):
                    $custom = $perms_custom[$perm['codigo']] ?? null;
                    $val_atual = $custom !== null ? (int)$custom['concedida'] : 'padrao';
                ?>
                <div class="perm-row">
                    <div class="perm-info">
                        <div class="perm-codigo">
                            <?= $perm['codigo'] ?>
                            <?= $perm['critico'] ? '<span class="perm-critico">CRÍTICO</span>' : '' ?>
                        </div>
                        <div class="perm-desc"><?= htmlspecialchars($perm['descricao']) ?></div>
                    </div>
                    <select name="perms[<?= $perm['codigo'] ?>]"
                            class="perm-select <?= $val_atual === 1 ? 'concedida' : ($val_atual === 0 ? 'negada' : 'padrao') ?>"
                            <?= $perm['critico'] && Permissoes::nivel() !== 'superadmin' ? 'disabled title="Requer superadmin"' : '' ?>
                            onchange="this.className='perm-select '+(this.value=='1'?'concedida':(this.value=='0'?'negada':'padrao'))">
                        <option value="padrao" <?= $val_atual==='padrao'?'selected':''?>>— Padrão do nível</option>
                        <option value="1"      <?= $val_atual===1?'selected':''?>>✓ Concedida</option>
                        <option value="0"      <?= $val_atual===0?'selected':''?>>✕ Negada</option>
                    </select>
                </div>
                <?php endforeach; ?>
                <?php endforeach; ?>

                <button type="submit" class="btn-admin-primary"
                        style="width:100%;padding:14px;border-radius:50px;justify-content:center;margin-top:20px;">
                    Salvar Permissões
                </button>
            </form>

            <?php elseif(Permissoes::pode('criar_admin')): ?>
            <!-- CRIAR NOVO ADMIN -->
            <div class="card-header" style="margin-bottom:18px;">
                <span class="card-title">Novo Administrador</span>
            </div>
            <form method="POST">
                <input type="hidden" name="acao" value="criar">
                <div class="input-group">
                    <label>Nome Completo *</label>
                    <input type="text" name="nome" placeholder="Nome do colaborador" required>
                </div>
                <div class="input-group">
                    <label>E-mail *</label>
                    <input type="email" name="email" placeholder="email@empresa.com.br" required>
                </div>
                <div class="input-group">
                    <label>Senha temporária * (mín. 8 chars)</label>
                    <input type="password" name="senha" required minlength="8">
                </div>
                <div class="input-group">
                    <label>Nível de Acesso</label>
                    <select name="nivel">
                        <option value="operador">Operador — só tarefas do dia a dia</option>
                        <option value="gerente">Gerente — acesso operacional completo</option>
                        <?php if(Permissoes::nivel() === 'superadmin'): ?>
                        <option value="admin">Admin — quase tudo, sem config. do sistema</option>
                        <option value="superadmin">Superadmin — acesso total</option>
                        <?php endif; ?>
                    </select>
                </div>
                <button type="submit" class="btn-admin-primary"
                        style="width:100%;padding:14px;border-radius:50px;justify-content:center;">
                    Criar Administrador
                </button>
            </form>
            <?php else: ?>
            <div style="text-align:center;padding:40px;color:var(--muted);">
                <p style="font-size:32px;margin-bottom:12px;">🔒</p>
                <strong>Sem permissão para criar administradores.</strong>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>