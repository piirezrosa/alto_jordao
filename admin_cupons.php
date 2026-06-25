<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

if (!isset($_SESSION['usuario_nivel']) || !in_array($_SESSION['usuario_nivel'], ['admin','superadmin','gerente'])) {
    header("Location: login.php"); exit();
}

// ── CRIAR ─────────────────────────────────────
if (isset($_POST['acao']) && $_POST['acao'] === 'criar') {
    $codigo      = strtoupper(trim($_POST['codigo']));
    $tipo        = $_POST['tipo'];
    $valor       = (float)$_POST['valor'];
    $valor_min   = (float)($_POST['valor_minimo'] ?? 0);
    $limite      = !empty($_POST['limite_uso']) ? (int)$_POST['limite_uso'] : null;
    $data_inicio = $_POST['data_inicio'] ?: null;
    $data_fim    = $_POST['data_fim']    ?: null;
    $descricao   = trim($_POST['descricao'] ?? '');

    $existe = $pdo->prepare("SELECT COUNT(*) FROM cupons WHERE codigo=?"); $existe->execute([$codigo]);
    if ($existe->fetchColumn()) { header("Location: admin_cupons.php?msg=duplicado"); exit(); }

    $pdo->prepare("INSERT INTO cupons (codigo,tipo,valor,valor_minimo,limite_uso,data_inicio,data_fim,descricao,ativo) VALUES (?,?,?,?,?,?,?,?,1)")
        ->execute([$codigo,$tipo,$valor,$valor_min,$limite,$data_inicio,$data_fim,$descricao]);
    $log = $pdo->prepare("INSERT INTO logs_sistema (usuario_id,acao,tabela,registro_id,detalhes,ip) VALUES (?,?,?,?,?,?)");
    $log->execute([$_SESSION['usuario_id']??null,'cupom_criado','cupons',$pdo->lastInsertId(),$codigo,$_SERVER['REMOTE_ADDR']]);
    header("Location: admin_cupons.php?msg=criado"); exit();
}

// ── TOGGLE ATIVO ──────────────────────────────
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $cur = $pdo->prepare("SELECT ativo FROM cupons WHERE id=?"); $cur->execute([$id]);
    $novo = $cur->fetchColumn() ? 0 : 1;
    $pdo->prepare("UPDATE cupons SET ativo=? WHERE id=?")->execute([$novo,$id]);
    header("Location: admin_cupons.php?msg=".($novo?'ativado':'desativado')); exit();
}

// ── EXCLUIR ───────────────────────────────────
if (isset($_GET['excluir'])) {
    $pdo->prepare("DELETE FROM cupons WHERE id=?")->execute([(int)$_GET['excluir']]);
    header("Location: admin_cupons.php?msg=excluido"); exit();
}

// ── DADOS ─────────────────────────────────────
$filtro = $_GET['filtro'] ?? 'todos';
$sql = "SELECT * FROM cupons";
if ($filtro === 'ativos')   $sql .= " WHERE ativo=1";
if ($filtro === 'inativos') $sql .= " WHERE ativo=0";
if ($filtro === 'expirados')$sql .= " WHERE data_fim < CURDATE()";
$sql .= " ORDER BY id DESC";
$cupons = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$total_ativos   = $pdo->query("SELECT COUNT(*) FROM cupons WHERE ativo=1")->fetchColumn();
$total_inativos = $pdo->query("SELECT COUNT(*) FROM cupons WHERE ativo=0")->fetchColumn();
$total_usados   = $pdo->query("SELECT SUM(total_usado) FROM cupons")->fetchColumn() ?: 0;

$devolucoes_pend = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();
$estoque_critico = $pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque<=3 AND ativo=1")->fetchColumn();
$p_pendente_sb   = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();

function cupom_status($c) {
    if (!$c['ativo']) return ['Inativo','badge-inativo'];
    if ($c['data_fim'] && $c['data_fim'] < date('Y-m-d')) return ['Expirado','badge-expirado'];
    if ($c['limite_uso'] && $c['total_usado'] >= $c['limite_uso']) return ['Esgotado','badge-expirado'];
    return ['Ativo','badge-ativo'];
}

define('CONTEUDO_AUTORIZADO', true);
$pagina_atual = 'cupons';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cupons | Alto Jordão Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <style>
        .page-grid { display:grid; grid-template-columns:1fr 380px; gap:22px; align-items:start; }
        .status-tabs { display:flex; gap:8px; margin-bottom:22px; flex-wrap:wrap; }
        .tab-btn { padding:9px 20px; border-radius:50px; border:1px solid var(--border); background:var(--white); color:var(--text2); font-size:12px; font-weight:700; cursor:pointer; text-decoration:none; transition:var(--transition); }
        .tab-btn:hover { background:var(--grey-bg); color:var(--black); }
        .tab-btn.active { background:var(--black); color:var(--white); border-color:var(--black); }

        .form-card .input-group { display:flex; flex-direction:column; gap:8px; margin-bottom:16px; }
        .form-card label.lbl { font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1px; }
        .form-card input, .form-card select, .form-card textarea {
            padding:12px 16px; border:1.5px solid var(--border); border-radius:50px;
            font-family:var(--font-main); font-size:13px; background:var(--grey-bg); color:var(--black); outline:none; transition:var(--transition);
        }
        .form-card textarea { border-radius:16px; resize:vertical; min-height:70px; }
        .form-card input:focus, .form-card select:focus { border-color:var(--black); background:var(--white); }

        .cupom-card { background:var(--white); border:1px solid var(--border); border-radius:20px; padding:22px; margin-bottom:14px; box-shadow:var(--shadow); transition:var(--transition); }
        .cupom-card:hover { transform:translateY(-2px); }
        .cupom-card.inativo { opacity:.55; }

        .cupom-header { display:flex; align-items:center; gap:14px; margin-bottom:12px; }
        .cupom-codigo { font-family:monospace; font-size:18px; font-weight:900; letter-spacing:2px; background:var(--grey-bg); padding:8px 16px; border-radius:10px; }
        .cupom-valor  { font-size:22px; font-weight:900; margin-left:auto; }

        .cupom-meta { display:flex; gap:16px; flex-wrap:wrap; font-size:12px; color:var(--text2); margin-bottom:14px; }
        .cupom-actions { display:flex; gap:8px; }

        .badge-ativo    { background:rgba(46,125,50,.1); color:var(--success); padding:4px 12px; border-radius:50px; font-size:10px; font-weight:800; }
        .badge-inativo  { background:rgba(0,0,0,.06);    color:var(--muted);   padding:4px 12px; border-radius:50px; font-size:10px; font-weight:800; }
        .badge-expirado { background:rgba(255,77,77,.1); color:var(--danger);  padding:4px 12px; border-radius:50px; font-size:10px; font-weight:800; }

        .progress-uso { margin-top:10px; }
        .progress-label { display:flex; justify-content:space-between; font-size:11px; color:var(--muted); margin-bottom:4px; }

        .btn-sm { padding:7px 16px; border-radius:50px; font-size:10px; font-weight:800; text-decoration:none; text-transform:uppercase; transition:var(--transition); }
        .btn-sm-toggle { background:var(--grey-bg); color:var(--text2); border:1px solid var(--border); }
        .btn-sm-del    { background:rgba(255,77,77,.1); color:var(--danger); border:1px solid rgba(255,77,77,.25); }
        .btn-sm:hover  { opacity:.8; transform:translateY(-1px); }

        .msg-ok  { background:#e8f5e9; border:1px solid #c8e6c9; color:var(--success); padding:12px 22px; border-radius:50px; margin-bottom:20px; font-size:13px; font-weight:700; display:inline-block; }
        .msg-err { background:rgba(255,77,77,.08); border:1px solid rgba(255,77,77,.25); color:var(--danger); padding:12px 22px; border-radius:50px; margin-bottom:20px; font-size:13px; font-weight:700; display:inline-block; }
    </style>
</head>
<body class="admin-page">

<?php include 'sidebar.php'; ?>

<main class="admin-main">
    <div class="admin-topbar">
        <div><h1>Cupons de Desconto</h1><p>Crie e gerencie cupons promocionais da Alto Jordão.</p></div>
    </div>

    <?php if(isset($_GET['msg'])): ?>
    <?php $msgs=['criado'=>'✓ Cupom criado!','excluido'=>'✓ Cupom excluído!','ativado'=>'✓ Cupom ativado!','desativado'=>'✓ Cupom desativado!','duplicado'=>'⚠ Esse código de cupom já existe.']; ?>
    <div class="<?= $_GET['msg']==='duplicado'?'msg-err':'msg-ok' ?>"><?= $msgs[$_GET['msg']]??'' ?></div>
    <?php endif; ?>

    <div class="kpi-grid">
        <div class="kpi-card featured"><div class="kpi-icon">🎟️</div><div class="kpi-label">Cupons Ativos</div><div class="kpi-value"><?= $total_ativos ?></div><div class="kpi-sub">Disponíveis para uso</div></div>
        <div class="kpi-card"><div class="kpi-icon">📁</div><div class="kpi-label">Total de Cupons</div><div class="kpi-value"><?= count($cupons) ?></div><div class="kpi-sub"><?= $total_inativos ?> inativos</div></div>
        <div class="kpi-card"><div class="kpi-icon">✅</div><div class="kpi-label">Usos Registrados</div><div class="kpi-value"><?= $total_usados ?></div><div class="kpi-sub">Total histórico</div></div>
        <div class="kpi-card"><div class="kpi-icon">📅</div><div class="kpi-label">Expirados</div><div class="kpi-value"><?= $pdo->query("SELECT COUNT(*) FROM cupons WHERE data_fim < CURDATE()")->fetchColumn() ?></div><div class="kpi-sub">Fora do prazo</div></div>
    </div>

    <div class="status-tabs">
        <a href="?filtro=todos"    class="tab-btn <?= $filtro==='todos'    ?'active':''?>">Todos (<?= $total_ativos+$total_inativos ?>)</a>
        <a href="?filtro=ativos"   class="tab-btn <?= $filtro==='ativos'   ?'active':''?>">✅ Ativos (<?= $total_ativos ?>)</a>
        <a href="?filtro=inativos" class="tab-btn <?= $filtro==='inativos' ?'active':''?>">⏸ Inativos (<?= $total_inativos ?>)</a>
        <a href="?filtro=expirados"class="tab-btn <?= $filtro==='expirados'?'active':''?>">⌛ Expirados</a>
    </div>

    <div class="page-grid">
        <!-- LISTA -->
        <div>
            <?php if(empty($cupons)): ?>
            <div class="admin-card" style="text-align:center;padding:60px;color:var(--muted);">Nenhum cupom encontrado.</div>
            <?php endif; ?>

            <?php foreach($cupons as $c):
                [$label_status, $badge_class] = cupom_status($c);
                $pct_uso = ($c['limite_uso'] && $c['limite_uso']>0) ? min(100, round($c['total_usado']/$c['limite_uso']*100)) : null;
            ?>
            <div class="cupom-card <?= !$c['ativo']?'inativo':'' ?>">
                <div class="cupom-header">
                    <span class="cupom-codigo"><?= htmlspecialchars($c['codigo']) ?></span>
                    <span class="<?= $badge_class ?>"><?= $label_status ?></span>
                    <span class="cupom-valor">
                        <?= $c['tipo']==='percentual' ? $c['valor'].'%' : 'R$ '.number_format($c['valor'],2,',','.') ?> OFF
                    </span>
                </div>

                <div class="cupom-meta">
                    <span>🏷️ <?= $c['tipo']==='percentual'?'Percentual':'Valor Fixo' ?></span>
                    <?php if($c['valor_minimo']>0): ?><span>Mín.: R$ <?= number_format($c['valor_minimo'],2,',','.') ?></span><?php endif; ?>
                    <?php if($c['data_inicio']): ?><span>📅 <?= date('d/m/Y',strtotime($c['data_inicio'])) ?> → <?= $c['data_fim']?date('d/m/Y',strtotime($c['data_fim'])):'∞' ?></span><?php endif; ?>
                    <span>Usos: <strong><?= $c['total_usado'] ?><?= $c['limite_uso']?' / '.$c['limite_uso']:'' ?></strong></span>
                </div>

                <?php if($c['descricao']): ?><p style="font-size:12px;color:var(--text2);margin-bottom:12px;"><?= htmlspecialchars($c['descricao']) ?></p><?php endif; ?>

                <?php if($pct_uso !== null): ?>
                <div class="progress-uso">
                    <div class="progress-label"><span>Uso do limite</span><span><?= $pct_uso ?>%</span></div>
                    <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct_uso ?>%; background:<?= $pct_uso>=100?'var(--danger)':($pct_uso>=80?'var(--warning)':'var(--black)') ?>"></div></div>
                </div>
                <?php endif; ?>

                <div class="cupom-actions" style="margin-top:14px;">
                    <a href="?toggle=<?= $c['id'] ?>&filtro=<?= $filtro ?>" class="btn-sm btn-sm-toggle"><?= $c['ativo']?'Desativar':'Ativar' ?></a>
                    <a href="?excluir=<?= $c['id'] ?>&filtro=<?= $filtro ?>" class="btn-sm btn-sm-del" onclick="return confirm('Excluir cupom <?= $c['codigo'] ?>?')">Excluir</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- FORMULÁRIO -->
        <div class="admin-card form-card">
            <div class="card-header" style="margin-bottom:20px;"><span class="card-title">Novo Cupom</span></div>
            <form method="POST">
                <input type="hidden" name="acao" value="criar">
                <div class="input-group"><label class="lbl">Código *</label><input type="text" name="codigo" placeholder="Ex: VERAO20" style="text-transform:uppercase;" required></div>
                <div class="input-group"><label class="lbl">Tipo de Desconto *</label>
                    <select name="tipo">
                        <option value="percentual">Percentual (%)</option>
                        <option value="fixo">Valor Fixo (R$)</option>
                    </select>
                </div>
                <div class="input-group"><label class="lbl">Valor do Desconto *</label><input type="number" step="0.01" name="valor" placeholder="Ex: 15 (para 15% ou R$15)" required></div>
                <div class="input-group"><label class="lbl">Valor Mínimo do Pedido (R$)</label><input type="number" step="0.01" name="valor_minimo" placeholder="0 = sem mínimo"></div>
                <div class="input-group"><label class="lbl">Limite de Usos</label><input type="number" name="limite_uso" placeholder="Vazio = ilimitado"></div>
                <div class="input-group"><label class="lbl">Válido De</label><input type="date" name="data_inicio"></div>
                <div class="input-group"><label class="lbl">Válido Até</label><input type="date" name="data_fim"></div>
                <div class="input-group"><label class="lbl">Descrição interna</label><textarea name="descricao" placeholder="Ex: Cupom campanha Black Friday..."></textarea></div>
                <button type="submit" class="btn-admin-primary" style="width:100%;padding:14px;border-radius:50px;justify-content:center;">Criar Cupom</button>
            </form>
        </div>
    </div>
</main>
<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>