<?php
/**
 * Despesas fixas — Alto Jordão Admin
 * Salários, hospedagem, marketing, contador etc. Entram no cálculo do lucro líquido (DRE)
 * rateadas por dia (ver fin_ratear_despesas em financeiro_helpers.php).
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'financeiro_helpers.php';

if (!isset($_SESSION['usuario_nivel']) || !in_array($_SESSION['usuario_nivel'], ['admin', 'superadmin'])) {
    header("Location: login.php"); exit();
}
fin_garantir_schema($pdo);

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function brl($v) { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
function data_valida(?string $d): bool { return $d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int)substr($d,5,2), (int)substr($d,8,2), (int)substr($d,0,4)); }

$erro = '';
$uid  = $_SESSION['usuario_id'] ?? null;
$log  = function (string $acao, $id, string $det) use ($pdo, $uid) {
    $pdo->prepare("INSERT INTO logs_sistema (usuario_id, acao, tabela, registro_id, detalhes, ip) VALUES (?,?,?,?,?,?)")
        ->execute([$uid, $acao, 'despesas', $id, mb_substr($det, 0, 250), $_SERVER['REMOTE_ADDR'] ?? '']);
};

// ── AÇÕES (todas por POST + token) ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!fin_csrf_ok($_POST['csrf'] ?? null)) {
        header("Location: admin_despesas.php?msg=csrf"); exit();
    }
    $acao = $_POST['acao'] ?? '';
    $id   = (int)($_POST['id'] ?? 0);

    if ($acao === 'salvar') {
        $descricao   = trim((string)($_POST['descricao'] ?? ''));
        $categoria   = $_POST['categoria'] ?? 'outros';
        $recorrencia = $_POST['recorrencia'] ?? 'mensal';
        $valor_raw   = str_replace(',', '.', trim((string)($_POST['valor'] ?? '')));
        $valor       = is_numeric($valor_raw) ? round((float)$valor_raw, 2) : 0;
        $data_inicio = $_POST['data_inicio'] ?? '';
        $data_fim    = ($_POST['data_fim'] ?? '') !== '' ? $_POST['data_fim'] : null;
        $observacao  = mb_substr(trim((string)($_POST['observacao'] ?? '')), 0, 255);

        if ($descricao === '' || mb_strlen($descricao) > 120)          $erro = 'Informe a descrição (até 120 caracteres).';
        elseif (!isset(FIN_CATEGORIAS[$categoria]))                    $erro = 'Categoria inválida.';
        elseif (!isset(FIN_RECORRENCIAS[$recorrencia]))                $erro = 'Recorrência inválida.';
        elseif ($valor <= 0 || $valor > 10000000)                      $erro = 'Informe um valor maior que zero.';
        elseif (!data_valida($data_inicio))                            $erro = 'Informe a data de início.';
        elseif ($data_fim !== null && !data_valida($data_fim))         $erro = 'Data de término inválida.';
        elseif ($data_fim !== null && $data_fim < $data_inicio)        $erro = 'O término não pode ser antes do início.';

        if ($erro === '') {
            if ($recorrencia === 'unica') $data_fim = null;   // avulsa vale só na data de início
            if ($id > 0) {
                $pdo->prepare("UPDATE despesas SET descricao=?, categoria=?, valor=?, recorrencia=?, data_inicio=?, data_fim=?, observacao=? WHERE id=?")
                    ->execute([$descricao, $categoria, $valor, $recorrencia, $data_inicio, $data_fim, $observacao ?: null, $id]);
                $log('despesa_editada', $id, "$descricao | " . brl($valor) . " | $recorrencia");
                header("Location: admin_despesas.php?msg=editada"); exit();
            }
            $pdo->prepare("INSERT INTO despesas (descricao, categoria, valor, recorrencia, data_inicio, data_fim, observacao, criado_por) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$descricao, $categoria, $valor, $recorrencia, $data_inicio, $data_fim, $observacao ?: null, $uid]);
            $log('despesa_criada', (int)$pdo->lastInsertId(), "$descricao | " . brl($valor) . " | $recorrencia");
            header("Location: admin_despesas.php?msg=criada"); exit();
        }
    } elseif ($acao === 'toggle' && $id > 0) {
        $pdo->prepare("UPDATE despesas SET ativo = 1 - ativo WHERE id = ?")->execute([$id]);
        $log('despesa_ativacao_alterada', $id, 'toggle');
        header("Location: admin_despesas.php?msg=atualizada"); exit();
    } elseif ($acao === 'excluir' && $id > 0) {
        $pdo->prepare("DELETE FROM despesas WHERE id = ?")->execute([$id]);
        $log('despesa_excluida', $id, 'excluída');
        header("Location: admin_despesas.php?msg=excluida"); exit();
    }
}

// ── DADOS ────────────────────────────────────────────────
$filtro = $_GET['filtro'] ?? 'ativas';
$where  = $filtro === 'inativas' ? 'WHERE ativo = 0' : ($filtro === 'todas' ? '' : 'WHERE ativo = 1');
$despesas = $pdo->query("SELECT * FROM despesas $where ORDER BY ativo DESC, valor DESC")->fetchAll(PDO::FETCH_ASSOC);

$edit = null;
if (isset($_GET['editar'])) {
    $st = $pdo->prepare("SELECT * FROM despesas WHERE id = ?");
    $st->execute([(int)$_GET['editar']]);
    $edit = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
// Reexibe o que foi digitado quando houve erro de validação
$f = $edit ?? [];
if ($erro !== '') {
    $f = ['id' => (int)($_POST['id'] ?? 0), 'descricao' => $_POST['descricao'] ?? '', 'categoria' => $_POST['categoria'] ?? 'outros',
          'valor' => $_POST['valor'] ?? '', 'recorrencia' => $_POST['recorrencia'] ?? 'mensal',
          'data_inicio' => $_POST['data_inicio'] ?? '', 'data_fim' => $_POST['data_fim'] ?? '', 'observacao' => $_POST['observacao'] ?? ''];
}

$mes_ini = date('Y-m-01'); $mes_fim = date('Y-m-t');
$mes     = fin_ratear_despesas($pdo, $mes_ini, $mes_fim);
$ano     = fin_ratear_despesas($pdo, date('Y-01-01'), date('Y-12-31'));
$n_ativas = (int)$pdo->query("SELECT COUNT(*) FROM despesas WHERE ativo = 1")->fetchColumn();

function vigencia(array $d): string {
    if ($d['recorrencia'] === 'unica') return date('d/m/Y', strtotime($d['data_inicio']));
    return 'desde ' . date('m/Y', strtotime($d['data_inicio'])) . (!empty($d['data_fim']) ? ' até ' . date('m/Y', strtotime($d['data_fim'])) : '');
}

define('CONTEUDO_AUTORIZADO', true);
$pagina_atual = 'despesas';
$csrf = fin_csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Despesas Fixas | Alto Jordão Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <style>
        .page-grid { display:grid; grid-template-columns:1fr 380px; gap:22px; align-items:start; }
        .tabs { display:flex; gap:8px; margin-bottom:18px; flex-wrap:wrap; }
        .tab-btn { padding:9px 20px; border-radius:50px; border:1px solid var(--border); background:var(--white); color:var(--text2); font-size:12px; font-weight:700; text-decoration:none; }
        .tab-btn.active { background:var(--black); color:var(--white); border-color:var(--black); }
        .form-card .input-group { display:flex; flex-direction:column; gap:8px; margin-bottom:16px; }
        .form-card label.lbl { font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1px; }
        .form-card input, .form-card select, .form-card textarea { padding:12px 16px; border:1.5px solid var(--border); border-radius:50px; font-family:var(--font-main); font-size:13px; background:var(--grey-bg); color:var(--black); outline:none; }
        .form-card textarea { border-radius:16px; resize:vertical; min-height:60px; }
        .form-card input:focus, .form-card select:focus { border-color:var(--black); background:var(--white); }
        .form-card small { font-size:11px; color:var(--muted); }
        .cat-bar { display:flex; align-items:center; gap:10px; margin:9px 0; font-size:12px; }
        .cat-bar .nome { width:140px; font-weight:700; color:var(--text2); }
        .cat-bar .barra { flex:1; height:8px; background:var(--grey-bg); border-radius:8px; overflow:hidden; }
        .cat-bar .barra span { display:block; height:100%; background:var(--black); border-radius:8px; }
        .cat-bar .val { width:100px; text-align:right; font-weight:800; }
        .acoes { display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end; }
        .acoes form { margin:0; }
        .btn-sm { padding:6px 14px; border-radius:50px; font-size:10px; font-weight:800; text-transform:uppercase; border:1px solid var(--border); background:var(--grey-bg); color:var(--text2); cursor:pointer; text-decoration:none; }
        .btn-sm.del { background:rgba(255,77,77,.1); color:var(--danger); border-color:rgba(255,77,77,.25); }
        tr.inativa td { opacity:.5; }
        .msg-ok  { background:#e8f5e9; border:1px solid #c8e6c9; color:var(--success); padding:12px 22px; border-radius:50px; margin-bottom:20px; font-size:13px; font-weight:700; display:inline-block; }
        .msg-err { background:rgba(255,77,77,.08); border:1px solid rgba(255,77,77,.25); color:var(--danger); padding:12px 22px; border-radius:50px; margin-bottom:20px; font-size:13px; font-weight:700; display:inline-block; }
        .dica { background:#f0f7ff; border:1px solid #c8dff7; color:#1565c0; border-radius:14px; padding:12px 16px; font-size:12px; line-height:1.6; margin-bottom:18px; }
        @media (max-width:1100px){ .page-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body class="admin-page">

<?php include 'sidebar.php'; ?>

<main class="admin-main">
    <div class="admin-topbar">
        <div><h1>Despesas Fixas</h1><p>Salários, hospedagem, marketing, contador… entram no lucro líquido, rateadas por dia.</p></div>
        <div class="topbar-actions"><a href="admin_relatorios.php?aba=dre&periodo=30" class="btn-admin-ghost">📊 Ver DRE</a></div>
    </div>

    <?php
    $msgs = ['criada' => '✓ Despesa cadastrada!', 'editada' => '✓ Despesa atualizada!', 'atualizada' => '✓ Despesa atualizada!',
             'excluida' => '✓ Despesa excluída!', 'csrf' => '⚠ Sessão expirada. Tente de novo.'];
    if (isset($_GET['msg']) && isset($msgs[$_GET['msg']])): ?>
        <div class="<?= $_GET['msg'] === 'csrf' ? 'msg-err' : 'msg-ok' ?>"><?= $msgs[$_GET['msg']] ?></div>
    <?php endif; ?>
    <?php if ($erro): ?><div class="msg-err">⚠ <?= e($erro) ?></div><?php endif; ?>

    <div class="kpi-grid">
        <div class="kpi-card featured"><div class="kpi-icon">🧾</div><div class="kpi-label">Despesas do Mês</div><div class="kpi-value"><?= brl($mes['total']) ?></div><div class="kpi-sub"><?= date('m/Y') ?> (rateado por dia)</div></div>
        <div class="kpi-card"><div class="kpi-icon">📆</div><div class="kpi-label">Despesas do Ano</div><div class="kpi-value"><?= brl($ano['total']) ?></div><div class="kpi-sub"><?= date('Y') ?></div></div>
        <div class="kpi-card"><div class="kpi-icon">📁</div><div class="kpi-label">Despesas Ativas</div><div class="kpi-value"><?= $n_ativas ?></div><div class="kpi-sub">cadastradas</div></div>
    </div>

    <?php if ($mes['por_categoria']): ?>
    <div class="admin-card" style="margin-bottom:22px;">
        <div class="card-header"><span class="card-title">Este mês por categoria</span></div>
        <?php $max = max($mes['por_categoria']); foreach ($mes['por_categoria'] as $cat => $v): ?>
        <div class="cat-bar">
            <span class="nome"><?= e(explode(' (', FIN_CATEGORIAS[$cat] ?? $cat)[0]) ?></span>
            <span class="barra"><span style="width:<?= $max > 0 ? round($v / $max * 100) : 0 ?>%"></span></span>
            <span class="val"><?= brl($v) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="page-grid">
        <div>
            <div class="tabs">
                <?php foreach (['ativas' => 'Ativas', 'inativas' => 'Inativas', 'todas' => 'Todas'] as $k => $n): ?>
                <a class="tab-btn <?= $filtro === $k ? 'active' : '' ?>" href="?filtro=<?= $k ?>"><?= $n ?></a>
                <?php endforeach; ?>
            </div>

            <div class="admin-card" style="padding:0; overflow:hidden;">
                <table class="data-table">
                    <thead><tr><th>Despesa</th><th>Categoria</th><th>Valor</th><th>Vigência</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($despesas as $d): ?>
                        <tr class="<?= $d['ativo'] ? '' : 'inativa' ?>">
                            <td><strong><?= e($d['descricao']) ?></strong>
                                <?php if ($d['observacao']): ?><br><small style="color:var(--muted)"><?= e($d['observacao']) ?></small><?php endif; ?></td>
                            <td><?= e(explode(' (', FIN_CATEGORIAS[$d['categoria']] ?? $d['categoria'])[0]) ?></td>
                            <td style="font-weight:800;"><?= brl($d['valor']) ?><br><small style="color:var(--muted);font-weight:600;"><?= e(FIN_RECORRENCIAS[$d['recorrencia']] ?? $d['recorrencia']) ?></small></td>
                            <td style="font-size:12px;color:var(--text2);"><?= e(vigencia($d)) ?></td>
                            <td>
                                <div class="acoes">
                                    <a class="btn-sm" href="?editar=<?= (int)$d['id'] ?>&filtro=<?= e($filtro) ?>">Editar</a>
                                    <form method="POST"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="toggle"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                        <button class="btn-sm"><?= $d['ativo'] ? 'Desativar' : 'Ativar' ?></button></form>
                                    <form method="POST" onsubmit="return confirm('Excluir esta despesa? Isso muda o lucro dos períodos passados. Para só encerrar, use Editar e defina a data de término.');">
                                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                        <button class="btn-sm del">Excluir</button></form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$despesas): ?>
                        <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--muted);">Nenhuma despesa cadastrada ainda. Use o formulário ao lado.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="admin-card form-card">
            <div class="card-header" style="margin-bottom:16px;"><span class="card-title"><?= !empty($f['id']) ? 'Editar Despesa' : 'Nova Despesa' ?></span></div>
            <div class="dica">
                💡 <strong>Salários:</strong> informe o custo total para a empresa (salário + encargos como INSS, FGTS, férias e 13º).<br>
                Quando um valor mudar (ex.: reajuste), <strong>defina a data de término</strong> da despesa antiga e cadastre uma nova — assim os meses passados continuam corretos.
            </div>
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" value="<?= (int)($f['id'] ?? 0) ?>">
                <div class="input-group"><label class="lbl">Descrição *</label>
                    <input type="text" name="descricao" maxlength="120" placeholder="Ex: Salário — Maria (estoque)" value="<?= e($f['descricao'] ?? '') ?>" required></div>
                <div class="input-group"><label class="lbl">Categoria *</label>
                    <select name="categoria"><?php foreach (FIN_CATEGORIAS as $k => $n): ?>
                        <option value="<?= $k ?>" <?= ($f['categoria'] ?? 'outros') === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></div>
                <div class="input-group"><label class="lbl">Valor (R$) *</label>
                    <input type="number" step="0.01" min="0.01" name="valor" placeholder="0,00" value="<?= e($f['valor'] ?? '') ?>" required>
                    <small>Mensal: valor de cada mês. Anual: valor do ano todo (rateado em 12). Avulsa: valor da cobrança.</small></div>
                <div class="input-group"><label class="lbl">Recorrência *</label>
                    <select name="recorrencia"><?php foreach (FIN_RECORRENCIAS as $k => $n): ?>
                        <option value="<?= $k ?>" <?= ($f['recorrencia'] ?? 'mensal') === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></div>
                <div class="input-group"><label class="lbl">Início *</label>
                    <input type="date" name="data_inicio" value="<?= e(substr($f['data_inicio'] ?? date('Y-m-01'), 0, 10)) ?>" required></div>
                <div class="input-group"><label class="lbl">Término (opcional)</label>
                    <input type="date" name="data_fim" value="<?= e(substr($f['data_fim'] ?? '', 0, 10)) ?>">
                    <small>Vazio = continua valendo. Ignorado em despesas avulsas.</small></div>
                <div class="input-group"><label class="lbl">Observação</label>
                    <textarea name="observacao" maxlength="255" placeholder="Opcional"><?= e($f['observacao'] ?? '') ?></textarea></div>
                <button type="submit" class="btn-admin-primary" style="width:100%;padding:14px;border-radius:50px;justify-content:center;"><?= !empty($f['id']) ? 'Salvar Alterações' : 'Cadastrar Despesa' ?></button>
                <?php if (!empty($f['id'])): ?><a href="admin_despesas.php" style="display:block;text-align:center;margin-top:12px;font-size:12px;color:var(--muted);">Cancelar edição</a><?php endif; ?>
            </form>
        </div>
    </div>
</main>
<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>
