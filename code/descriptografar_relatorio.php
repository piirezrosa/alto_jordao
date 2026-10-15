<?php
/**
 * ============================================================
 *  ALTO JORDÃO — Descriptografar e Visualizar Relatório
 *  Item 3/8
 *
 *  Fluxo:
 *  1. Admin informa a senha do relatório
 *  2. Sistema lê o .ajenc do disco
 *  3. Descriptografa em MEMÓRIA (nunca salva versão aberta)
 *  4. Exibe o relatório formatado em HTML
 *  5. Verifica hash SHA-256 para detectar adulteração
 * ============================================================
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'CriptografiaAES.php';

if (!isset($_SESSION['usuario_nivel']) ||
    !in_array($_SESSION['usuario_nivel'], ['admin','superadmin'])) {
    header("Location: login.php"); exit;
}

$id    = (int)($_GET['id'] ?? 0);
$senha = $_POST['senha'] ?? '';

if (!$id) { header("Location: gerar_relatorio_criptografado.php"); exit; }

// Busca metadados
$stmt = $pdo->prepare("
    SELECT r.*, u.nome as gerado_por_nome
    FROM relatorios_seguros r
    LEFT JOIN usuarios u ON u.id = r.gerado_por
    WHERE r.id = ?
");
$stmt->execute([$id]);
$meta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$meta) { header("Location: gerar_relatorio_criptografado.php"); exit; }

$caminho = __DIR__ . '/' . $meta['arquivo'];

if (!file_exists($caminho)) {
    $erro_fatal = 'Arquivo não encontrado no servidor. Pode ter sido removido.';
}

// Download direto do .ajenc (sem descriptografar)
if (isset($_GET['download']) && empty($erro_fatal)) {
    $binario = file_get_contents($caminho);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($caminho) . '"');
    header('Content-Length: ' . strlen($binario));
    echo $binario;
    exit;
}

$relatorio  = null;
$erro       = '';
$adulterado = false;

// Tenta descriptografar se senha foi enviada
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $senha && empty($erro_fatal)) {
    try {
        $binario = file_get_contents($caminho);
        $json    = CriptografiaAES::descriptografar($binario, $senha);

        // Verifica integridade (hash do conteúdo original)
        $hash_atual = CriptografiaAES::hashIntegridade($json);
        if (!hash_equals($meta['hash_integridade'], $hash_atual)) {
            $adulterado = true;
        }

        $relatorio = json_decode($json, true);

        if (!$relatorio) {
            throw new RuntimeException('JSON inválido após descriptografia.');
        }

        // Atualiza contador de acessos
        $pdo->prepare("
            UPDATE relatorios_seguros
            SET acessos = acessos + 1, ultimo_acesso = NOW()
            WHERE id = ?
        ")->execute([$id]);

        // Log
        $pdo->prepare("
            INSERT INTO logs_sistema (usuario_id, acao, tabela, registro_id, detalhes, ip)
            VALUES (?, 'relatorio_aberto', 'relatorios_seguros', ?, ?, ?)
        ")->execute([
            $_SESSION['usuario_id'] ?? null, $id,
            "Arquivo: " . basename($caminho) . " | Adulterado: " . ($adulterado ? 'SIM' : 'não'),
            $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

    } catch (RuntimeException $e) {
        $erro = $e->getMessage();
    }
}

// Info do arquivo para exibir antes de descriptografar
$info_arquivo = empty($erro_fatal) ? CriptografiaAES::infoArquivo(file_get_contents($caminho)) : [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório Criptografado | Alto Jordão</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <style>
        body { background:#f8f9fa; font-family:'Inter',sans-serif; }
        .wrapper { max-width:960px; margin:40px auto; padding:0 20px 80px; }

        .rel-header { background:#000; color:#fff; padding:30px 36px; border-radius:20px 20px 0 0; }
        .rel-header h1 { font-size:1.5rem; font-weight:900; margin:0 0 8px; letter-spacing:-1px; }
        .rel-header .sub { font-size:12px; opacity:.6; }
        .rel-header .badges { display:flex; gap:10px; margin-top:16px; flex-wrap:wrap; }
        .badge-info { background:rgba(255,255,255,.1); padding:5px 14px; border-radius:50px; font-size:11px; font-weight:700; font-family:monospace; }

        .rel-body { background:#fff; border:1px solid #eee; border-top:none; border-radius:0 0 20px 20px; padding:36px; }

        /* Formulário de senha */
        .senha-form { max-width:420px; margin:0 auto; text-align:center; padding:40px 0; }
        .senha-form p { font-size:13px; color:#666; line-height:1.6; margin-bottom:24px; }
        .senha-input { width:100%; padding:16px; border:1.5px solid #eee; border-radius:12px; font-size:15px; font-family:inherit; text-align:center; letter-spacing:2px; background:#fbfbfb; margin-bottom:16px; box-sizing:border-box; }
        .senha-input:focus { border-color:#000; outline:none; background:#fff; }
        .btn-abrir { width:100%; padding:16px; background:#000; color:#fff; border:none; border-radius:50px; font-weight:900; font-size:13px; letter-spacing:2px; cursor:pointer; text-transform:uppercase; }

        /* Alertas */
        .alerta { padding:14px 20px; border-radius:12px; font-size:13px; font-weight:700; margin-bottom:20px; }
        .alerta-erro     { background:#fff0f0; border:1px solid #ffc0c0; color:#c62828; }
        .alerta-adulte   { background:#fff8e1; border:1px solid #ffe082; color:#e65100; }
        .alerta-ok       { background:#e8f5e9; border:1px solid #c8e6c9; color:#2e7d32; }

        /* Seções do relatório */
        .sec-titulo { font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:2px; color:#888; margin:28px 0 14px; display:flex; align-items:center; gap:10px; }
        .sec-titulo::after { content:''; flex:1; height:1px; background:#eee; }

        /* Cards de KPI */
        .kpi-mini { display:grid; grid-template-columns:repeat(auto-fill, minmax(160px,1fr)); gap:12px; margin-bottom:20px; }
        .kpi-mini-item { background:#f8f9fa; border-radius:14px; padding:16px; }
        .kpi-mini-label { font-size:10px; font-weight:700; color:#888; text-transform:uppercase; margin-bottom:6px; }
        .kpi-mini-val   { font-size:20px; font-weight:900; }

        /* Tabela */
        .tbl { width:100%; border-collapse:collapse; font-size:13px; margin-top:10px; }
        .tbl th { font-size:10px; font-weight:800; text-transform:uppercase; color:#888; padding:10px 12px; text-align:left; border-bottom:2px solid #eee; }
        .tbl td { padding:12px 12px; border-bottom:1px solid #f5f5f5; }
        .tbl tr:last-child td { border-bottom:none; }
        .tbl tr:hover td { background:#fafafa; }

        .btn-voltar { display:inline-flex; align-items:center; gap:8px; color:#888; text-decoration:none; font-size:13px; font-weight:700; margin-bottom:20px; }
        .btn-voltar:hover { color:#000; }
        .btn-imprimir { float:right; background:#000; color:#fff; border:none; padding:10px 24px; border-radius:50px; font-size:12px; font-weight:800; cursor:pointer; text-transform:uppercase; }

        @media print {
            .btn-voltar, .btn-imprimir, .senha-form { display:none; }
            .rel-header { background:#000 !important; -webkit-print-color-adjust:exact; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <a href="gerar_relatorio_criptografado.php" class="btn-voltar">← Voltar</a>

    <?php if(isset($erro_fatal)): ?>
    <div class="alerta alerta-erro">⚠ <?= htmlspecialchars($erro_fatal) ?></div>

    <?php else: ?>

    <!-- CABEÇALHO DO ARQUIVO -->
    <div class="rel-header">
        <h1>🔐 <?= htmlspecialchars($meta['nome']) ?></h1>
        <div class="sub">
            Gerado em <?= date('d/m/Y H:i', strtotime($meta['data_geracao'])) ?>
            por <?= htmlspecialchars($meta['gerado_por_nome'] ?? 'Sistema') ?>
        </div>
        <div class="badges">
            <span class="badge-info"><?= $meta['algoritmo'] ?></span>
            <span class="badge-info">PBKDF2-SHA256 · 100.000 iterações</span>
            <span class="badge-info"><?= number_format($meta['tamanho_bytes']/1024, 1) ?> KB</span>
            <?php if($info_arquivo['valido'] ?? false): ?>
            <span class="badge-info">Formato válido ✓</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="rel-body">

        <?php if ($adulterado): ?>
        <div class="alerta alerta-adulte">
            ⚠ <strong>ATENÇÃO:</strong> O hash SHA-256 do conteúdo não corresponde ao original.
            O arquivo pode ter sido adulterado após a geração. Verifique com o gerador.
        </div>
        <?php endif; ?>

        <?php if ($relatorio): ?>
        <!-- RELATÓRIO DESCRIPTOGRAFADO -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div class="alerta alerta-ok" style="margin:0;">
                ✓ Descriptografado com sucesso · Integridade <?= $adulterado ? '⚠ suspeita' : 'verificada ✓' ?>
            </div>
            <button class="btn-imprimir" onclick="window.print()">🖨 Imprimir</button>
        </div>

        <!-- META DO RELATÓRIO -->
        <div class="sec-titulo">Metadados</div>
        <div class="kpi-mini">
            <div class="kpi-mini-item">
                <div class="kpi-mini-label">Tipo</div>
                <div class="kpi-mini-val" style="font-size:13px;"><?= htmlspecialchars($relatorio['meta']['tipo']) ?></div>
            </div>
            <div class="kpi-mini-item">
                <div class="kpi-mini-label">Período</div>
                <div class="kpi-mini-val" style="font-size:13px;">
                    <?= date('d/m/Y', strtotime($relatorio['meta']['periodo']['inicio'])) ?>
                    → <?= date('d/m/Y', strtotime($relatorio['meta']['periodo']['fim'])) ?>
                </div>
            </div>
            <div class="kpi-mini-item">
                <div class="kpi-mini-label">Gerado por</div>
                <div class="kpi-mini-val" style="font-size:13px;"><?= htmlspecialchars($relatorio['meta']['gerado_por']) ?></div>
            </div>
            <div class="kpi-mini-item">
                <div class="kpi-mini-label">Algoritmo</div>
                <div class="kpi-mini-val" style="font-size:11px; font-family:monospace;"><?= $relatorio['meta']['algoritmo'] ?></div>
            </div>
        </div>

        <?php $dados = $relatorio['dados']; ?>

        <!-- RESUMO FINANCEIRO -->
        <?php if(isset($dados['resumo'])): $r = $dados['resumo']; ?>
        <div class="sec-titulo">Resumo Financeiro</div>
        <div class="kpi-mini">
            <div class="kpi-mini-item">
                <div class="kpi-mini-label">Receita Bruta</div>
                <div class="kpi-mini-val">R$ <?= number_format($r['receita_bruta'],2,',','.') ?></div>
            </div>
            <div class="kpi-mini-item">
                <div class="kpi-mini-label">Pedidos</div>
                <div class="kpi-mini-val"><?= $r['total_pedidos'] ?></div>
            </div>
            <div class="kpi-mini-item">
                <div class="kpi-mini-label">Ticket Médio</div>
                <div class="kpi-mini-val">R$ <?= number_format($r['ticket_medio'],2,',','.') ?></div>
            </div>
            <div class="kpi-mini-item">
                <div class="kpi-mini-label">Clientes Únicos</div>
                <div class="kpi-mini-val"><?= $r['clientes_unicos'] ?></div>
            </div>
            <div class="kpi-mini-item">
                <div class="kpi-mini-label">Descontos</div>
                <div class="kpi-mini-val" style="color:#c62828;">R$ <?= number_format($r['total_descontos'],2,',','.') ?></div>
            </div>
            <div class="kpi-mini-item">
                <div class="kpi-mini-label">PIX / Cartão / Boleto</div>
                <div class="kpi-mini-val" style="font-size:13px;">
                    <?= $r['pedidos_pix'] ?> / <?= $r['pedidos_cartao'] ?> / <?= $r['pedidos_boleto'] ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- TOP PRODUTOS -->
        <?php if(!empty($dados['top_produtos'])): ?>
        <div class="sec-titulo">Top 10 Produtos por Receita</div>
        <table class="tbl">
            <thead><tr><th>#</th><th>Produto</th><th>Qtd Vendida</th><th>Receita</th></tr></thead>
            <tbody>
            <?php foreach($dados['top_produtos'] as $i => $p): ?>
            <tr>
                <td style="font-weight:800;color:#888;"><?= $i+1 ?></td>
                <td><?= htmlspecialchars($p['nome']) ?></td>
                <td><?= $p['qtd'] ?></td>
                <td style="font-weight:800;">R$ <?= number_format($p['receita'],2,',','.') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- MARGEM DE LUCRO -->
        <?php if(!empty($dados['produtos']) && isset($dados['produtos'][0]['margem'])): ?>
        <div class="sec-titulo">Margem de Lucro por Produto</div>
        <table class="tbl">
            <thead><tr><th>Produto</th><th>Vendido</th><th>Receita</th><th>Custo</th><th>Lucro</th><th>Margem</th></tr></thead>
            <tbody>
            <?php foreach($dados['produtos'] as $p):
                $cor = $p['margem'] >= 30 ? '#2e7d32' : ($p['margem'] >= 15 ? '#b45309' : '#c62828');
            ?>
            <tr>
                <td><?= htmlspecialchars($p['nome']) ?></td>
                <td><?= $p['vendido'] ?></td>
                <td>R$ <?= number_format($p['receita'],2,',','.') ?></td>
                <td>R$ <?= number_format($p['custo_total'],2,',','.') ?></td>
                <td style="font-weight:800;">R$ <?= number_format($p['lucro'],2,',','.') ?></td>
                <td style="font-weight:800;color:<?= $cor ?>;"><?= $p['custo_total']>0 ? $p['margem'].'%' : 'S/custo' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- CLIENTES -->
        <?php if(!empty($dados['clientes'])): ?>
        <div class="sec-titulo">Base de Clientes (<?= count($dados['clientes']) ?> clientes)</div>
        <table class="tbl">
            <thead><tr><th>Cliente</th><th>E-mail</th><th>Pedidos</th><th>Gasto Total</th><th>Último Pedido</th></tr></thead>
            <tbody>
            <?php foreach(array_slice($dados['clientes'],0,50) as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['nome']) ?></td>
                <td style="font-family:monospace;font-size:11px;"><?= htmlspecialchars($c['email']) ?></td>
                <td><?= $c['total_pedidos'] ?></td>
                <td style="font-weight:800;">R$ <?= number_format($c['gasto_total'],2,',','.') ?></td>
                <td><?= $c['ultimo_pedido'] ? date('d/m/Y', strtotime($c['ultimo_pedido'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(count($dados['clientes']) > 50): ?>
            <tr><td colspan="5" style="text-align:center;color:#888;font-size:12px;padding:16px;">
                + <?= count($dados['clientes'])-50 ?> clientes adicionais (exibindo top 50)
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- HASH DE INTEGRIDADE -->
        <div class="sec-titulo">Integridade do Documento</div>
        <div style="background:#f8f9fa;border-radius:12px;padding:16px;font-family:monospace;font-size:12px;word-break:break-all;color:#333;">
            <strong>SHA-256 original (geração):</strong><br>
            <?= $meta['hash_integridade'] ?><br><br>
            <strong>SHA-256 atual (verificação):</strong><br>
            <?= CriptografiaAES::hashIntegridade(json_encode($relatorio, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?><br><br>
            <strong>Status:</strong>
            <span style="color:<?= $adulterado ? '#c62828' : '#2e7d32' ?>;font-weight:800;">
                <?= $adulterado ? '⚠ DIVERGÊNCIA DETECTADA' : '✓ ÍNTEGRO — hashes idênticos' ?>
            </span>
        </div>

        <?php else: ?>
        <!-- FORMULÁRIO DE SENHA -->
        <div class="senha-form">
            <?php if ($erro): ?>
            <div class="alerta alerta-erro">⚠ <?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>
            <p>
                Este relatório está protegido com <strong>AES-256-CBC</strong>.<br>
                Informe a senha definida no momento da geração para visualizá-lo.
            </p>
            <form method="POST">
                <input type="password" name="senha" class="senha-input"
                       placeholder="••••••••" required autofocus>
                <button type="submit" class="btn-abrir">🔓 Descriptografar e Abrir</button>
            </form>
        </div>
        <?php endif; ?>

    </div>
    <?php endif; ?>
</div>
</body>
</html>