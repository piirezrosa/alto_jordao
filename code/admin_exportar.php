<?php
/**
 * Central de relatórios — Alto Jordão Admin
 * Prévia na tela e exportação em CSV (Excel/Planilhas) dos relatórios de relatorios_catalogo.php.
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'relatorios_catalogo.php';

$niveis = ['admin', 'superadmin'];
if (!isset($_SESSION['usuario_nivel']) || !in_array($_SESSION['usuario_nivel'], $niveis, true)) {
    header("Location: login.php"); exit();
}
$super = $_SESSION['usuario_nivel'] === 'superadmin';

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$catalogo = array_filter(rel_catalogo(), fn($r) => $super || empty($r['somente_super']));
$sel      = $_GET['r'] ?? '';
$previa   = null; $erro = '';

if ($sel !== '' && isset($catalogo[$sel])) {
    $rel = $catalogo[$sel];
    $par = rel_parametros($rel['filtros'], $_GET);
    try {
        $dados = $rel['gerar']($pdo, $par, $super);
    } catch (Throwable $ex) {
        error_log('[RELATORIO ' . $sel . '] ' . $ex->getMessage());
        $dados = null; $erro = 'Não foi possível gerar este relatório agora. Veja o log do servidor.';
    }

    if ($dados && isset($_GET['baixar'])) {
        // Registro de auditoria (quem exportou o quê)
        try {
            $pdo->prepare("INSERT INTO logs_sistema (usuario_id, acao, tabela, registro_id, detalhes, ip) VALUES (?,?,?,?,?,?)")
                ->execute([$_SESSION['usuario_id'] ?? null, 'relatorio_exportado', 'relatorios', null,
                           mb_substr($sel . ' ' . json_encode($par) . ' linhas=' . count($dados['linhas']), 0, 250), $_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (Throwable $ex) {}

        $nome = 'relatorio_' . $sel . '_' . date('Ymd_Hi') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nome . '"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");   // BOM: o Excel reconhece os acentos
        fputcsv($out, $dados['colunas'], ';', '"', '');
        foreach ($dados['linhas'] as $l) fputcsv($out, array_map('rel_celula', $l), ';', '"', '');
        fclose($out);
        exit();
    }
    if ($dados) $previa = ['rel' => $rel, 'id' => $sel, 'dados' => $dados];
}

$grupos = [];
foreach ($catalogo as $id => $r) $grupos[$r['grupo']][$id] = $r;

define('CONTEUDO_AUTORIZADO', true);
$pagina_atual = 'exportar';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportar relatórios | Alto Jordão Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <style>
        .grupo-titulo { font-size:11px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1.5px; margin:30px 0 14px; }
        .rel-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:18px; }
        .rel-card { background:var(--white); border:1px solid var(--border); border-radius:28px; padding:24px; display:flex; flex-direction:column; gap:14px; }
        .rel-card.ativo { border-color:var(--black); }
        .rel-card h3 { font-size:17px; font-weight:800; letter-spacing:-.3px; }
        .rel-card p { font-size:13px; line-height:1.55; color:var(--text2); }
        .rel-card form { display:flex; flex-direction:column; gap:12px; margin-top:auto; }
        .rel-campos { display:flex; flex-wrap:wrap; gap:10px; }
        .rel-campos label { display:flex; flex-direction:column; gap:5px; font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1px; flex:1 1 120px; }
        .rel-campos input { padding:11px 14px; border:1.5px solid var(--border); border-radius:50px; font:600 13px var(--font-main); background:var(--grey-bg); color:var(--black); outline:none; min-width:0; }
        .rel-campos input:focus { border-color:var(--black); background:var(--white); }
        .rel-acoes { display:flex; gap:8px; flex-wrap:wrap; }
        .rel-acoes button { flex:1 1 auto; cursor:pointer; }
        .previa-info { font-size:12px; color:var(--muted); margin:-6px 0 14px; }
        .msg-err { background:rgba(255,77,77,.08); border:1px solid rgba(255,77,77,.25); color:var(--danger); padding:12px 22px; border-radius:50px; margin-bottom:20px; font-size:13px; font-weight:700; display:inline-block; }
        .dica { background:#f0f7ff; border:1px solid #c8dff7; color:#1565c0; border-radius:14px; padding:12px 16px; font-size:12px; line-height:1.6; margin-bottom:6px; }
        .tbl-previa td, .tbl-previa th { white-space:nowrap; }
        .previa-scroll { overflow-x:auto; max-width:100%; border-radius:14px; }
        .previa-scroll .tbl-previa { min-width:max-content; }
    </style>
</head>
<body class="admin-page">

<?php include 'sidebar.php'; ?>

<main class="admin-main">
    <div class="admin-topbar">
        <div><h1>Exportar relatórios</h1><p>Veja a prévia na tela e baixe em CSV para abrir no Excel ou no Google Planilhas.</p></div>
        <div class="topbar-actions"><a href="admin_relatorios.php" class="btn-admin-ghost">Painel de relatórios</a></div>
    </div>

    <?php if ($erro): ?><div class="msg-err"><?= e($erro) ?></div><?php endif; ?>
    <?php if (!$super): ?><div class="dica">Colunas de custo, lucro e margem aparecem apenas para o superadmin.</div><?php endif; ?>

    <?php if ($previa): ?>
    <div class="admin-card" style="margin-top:18px;">
        <div class="card-header">
            <span class="card-title">Prévia: <?= e($previa['rel']['titulo']) ?></span>
            <a class="card-link" href="?<?= e(http_build_query(array_merge($_GET, ['baixar' => 1]))) ?>">Baixar CSV</a>
        </div>
        <?php $n = count($previa['dados']['linhas']); ?>
        <p class="previa-info"><?= $n ?> <?= $n === 1 ? 'linha' : 'linhas' ?><?= $n > 100 ? ' (mostrando as 100 primeiras; o CSV traz todas)' : '' ?></p>
        <?php if ($n === 0): ?>
            <p style="color:var(--muted);font-size:14px;padding:12px 0 6px;">Nenhum dado para esses filtros.</p>
        <?php else: ?>
        <div class="previa-scroll"><table class="data-table tbl-previa">
            <thead><tr><?php foreach ($previa['dados']['colunas'] as $c): ?><th><?= e($c) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php foreach (array_slice($previa['dados']['linhas'], 0, 100) as $l): ?>
                <tr><?php foreach ($l as $v): ?><td><?= e(rel_celula($v)) ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php foreach ($grupos as $nomeGrupo => $itens): ?>
        <div class="grupo-titulo"><?= e($nomeGrupo) ?></div>
        <div class="rel-grid">
        <?php foreach ($itens as $id => $r):
            $par = rel_parametros($r['filtros'], $sel === $id ? $_GET : []); ?>
            <section class="rel-card <?= $sel === $id ? 'ativo' : '' ?>">
                <h3><?= e($r['titulo']) ?></h3>
                <p><?= e($r['desc']) ?></p>
                <form method="get">
                    <input type="hidden" name="r" value="<?= e($id) ?>">
                    <?php if ($r['filtros']): ?><div class="rel-campos">
                        <?php if (isset($par['data_ini'])): ?>
                            <label>De<input type="date" name="data_ini" value="<?= e($par['data_ini']) ?>"></label>
                            <label>Até<input type="date" name="data_fim" value="<?= e($par['data_fim']) ?>"></label>
                        <?php endif; ?>
                        <?php if (isset($par['dias'])): ?>
                            <label>Janela de vendas (dias)<input type="number" name="dias" min="1" max="730" value="<?= (int)$par['dias'] ?>"></label>
                        <?php endif; ?>
                        <?php if (isset($par['limite'])): ?>
                            <label>Estoque até (un.)<input type="number" name="limite" min="0" max="9999" value="<?= (int)$par['limite'] ?>"></label>
                        <?php endif; ?>
                    </div><?php endif; ?>
                    <div class="rel-acoes">
                        <button type="submit" class="btn-admin-ghost">Ver prévia</button>
                        <button type="submit" name="baixar" value="1" class="btn-admin-primary">Baixar CSV</button>
                    </div>
                </form>
            </section>
        <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</main>
</body>
</html>
