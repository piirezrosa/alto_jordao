<?php
/**
 * ============================================================
 *  ALTO JORDÃO — Gerador de Relatórios Criptografados
 *  Item 3/8
 *
 *  Fluxo:
 *  1. Admin escolhe tipo, período e senha
 *  2. Sistema busca os dados no banco
 *  3. Serializa em JSON estruturado
 *  4. Criptografa com AES-256-CBC via CriptografiaAES
 *  5. Salva o .ajenc na pasta /relatorios_seguros/
 *  6. Registra metadados na tabela relatorios_seguros
 *  7. Oferece download do arquivo criptografado
 * ============================================================
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'CriptografiaAES.php';

// Proteção de acesso
if (!isset($_SESSION['usuario_nivel']) ||
    !in_array($_SESSION['usuario_nivel'], ['admin','superadmin'])) {
    header("Location: login.php"); exit;
}

$erro    = '';
$sucesso = '';

// Garante a pasta de armazenamento
$pasta = __DIR__ . '/relatorios_seguros/';
if (!is_dir($pasta)) {
    mkdir($pasta, 0750, true);

    // Bloqueia acesso HTTP direto à pasta
    file_put_contents($pasta . '.htaccess', "Deny from all\n");
}

// Garante a tabela
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS relatorios_seguros (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            nome             VARCHAR(200)  NOT NULL,
            tipo             VARCHAR(50)   NOT NULL,
            periodo_inicio   DATE          NOT NULL,
            periodo_fim      DATE          NOT NULL,
            arquivo          VARCHAR(255)  NOT NULL,
            tamanho_bytes    INT           DEFAULT 0,
            algoritmo        VARCHAR(30)   DEFAULT 'AES-256-CBC',
            hash_integridade VARCHAR(64)   NOT NULL,
            gerado_por       INT           DEFAULT NULL,
            data_geracao     DATETIME      DEFAULT CURRENT_TIMESTAMP,
            ultimo_acesso    DATETIME      DEFAULT NULL,
            acessos          INT           DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {}

// ── PROCESSAR GERAÇÃO ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gerar'])) {

    $tipo      = $_POST['tipo']        ?? '';
    $data_ini  = $_POST['data_ini']    ?? '';
    $data_fim  = $_POST['data_fim']    ?? '';
    $senha     = $_POST['senha']       ?? '';
    $confirmar = $_POST['confirmar']   ?? '';

    // Validações
    if (!$tipo || !$data_ini || !$data_fim || !$senha) {
        $erro = 'Preencha todos os campos.';
    } elseif (strlen($senha) < 8) {
        $erro = 'A senha de proteção deve ter no mínimo 8 caracteres.';
    } elseif ($senha !== $confirmar) {
        $erro = 'As senhas não coincidem.';
    } elseif ($data_fim < $data_ini) {
        $erro = 'Data final deve ser maior que a inicial.';
    } else {

        try {
            // ── COLETA DE DADOS CONFORME TIPO ─────────────
            $dados_relatorio = coletarDados($pdo, $tipo, $data_ini, $data_fim);

            // Estrutura do relatório
            $relatorio = [
                'meta' => [
                    'sistema'       => 'Alto Jordão E-commerce',
                    'versao'        => '3.0',
                    'tipo'          => $tipo,
                    'periodo'       => ['inicio' => $data_ini, 'fim' => $data_fim],
                    'gerado_em'     => date('Y-m-d H:i:s'),
                    'gerado_por'    => $_SESSION['usuario_nome'] ?? 'Sistema',
                    'algoritmo'     => 'AES-256-CBC + PBKDF2-SHA256 (100.000 iterações)',
                    'aviso'         => 'DOCUMENTO CONFIDENCIAL — uso interno',
                ],
                'dados' => $dados_relatorio,
            ];

            $json = json_encode($relatorio, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            // ── CRIPTOGRAFA ────────────────────────────────
            $binario    = CriptografiaAES::criptografar($json, $senha);
            $hash_orig  = CriptografiaAES::hashIntegridade($json);

            // ── SALVA O ARQUIVO ────────────────────────────
            $tipos_labels = [
                'financeiro_mensal'    => 'Financeiro_Mensal',
                'financeiro_anual'     => 'Financeiro_Anual',
                'produtos_desempenho'  => 'Desempenho_Produtos',
                'clientes'             => 'Clientes',
                'margem_lucro'         => 'Margem_Lucro',
            ];

            $nome_arquivo = sprintf(
                '%s_%s_ate_%s_%s.ajenc',
                $tipos_labels[$tipo] ?? $tipo,
                str_replace('-', '', $data_ini),
                str_replace('-', '', $data_fim),
                date('His')
            );

            $caminho_arquivo = $pasta . $nome_arquivo;
            file_put_contents($caminho_arquivo, $binario);

            $nome_legivel = ($tipos_labels[$tipo] ?? $tipo)
                . ' — ' . date('d/m/Y', strtotime($data_ini))
                . ' a ' . date('d/m/Y', strtotime($data_fim));

            // ── REGISTRA NO BANCO ──────────────────────────
            $pdo->prepare("
                INSERT INTO relatorios_seguros
                    (nome, tipo, periodo_inicio, periodo_fim, arquivo,
                     tamanho_bytes, hash_integridade, gerado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $nome_legivel, $tipo, $data_ini, $data_fim,
                'relatorios_seguros/' . $nome_arquivo,
                strlen($binario), $hash_orig,
                $_SESSION['usuario_id'] ?? null,
            ]);

            $relatorio_id = $pdo->lastInsertId();

            // Log
            $pdo->prepare("
                INSERT INTO logs_sistema (usuario_id, acao, tabela, registro_id, detalhes, ip)
                VALUES (?, 'relatorio_gerado', 'relatorios_seguros', ?, ?, ?)
            ")->execute([
                $_SESSION['usuario_id'] ?? null, $relatorio_id,
                "Tipo: $tipo | Arquivo: $nome_arquivo | Hash: $hash_orig",
                $_SERVER['REMOTE_ADDR'] ?? '',
            ]);

            // ── OFERECE DOWNLOAD ───────────────────────────
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $nome_arquivo . '"');
            header('Content-Length: ' . strlen($binario));
            header('X-Content-Type-Options: nosniff');
            echo $binario;
            exit;

        } catch (Exception $e) {
            $erro = 'Erro ao gerar relatório: ' . $e->getMessage();
        }
    }
}

// ── FUNÇÃO DE COLETA DE DADOS ─────────────────────────────
function coletarDados(PDO $pdo, string $tipo, string $ini, string $fim): array
{
    switch ($tipo) {

        case 'financeiro_mensal':
        case 'financeiro_anual':
            $resumo = $pdo->prepare("
                SELECT
                    COUNT(*) as total_pedidos,
                    SUM(total) as receita_bruta,
                    SUM(desconto) as total_descontos,
                    SUM(frete) as total_frete,
                    AVG(total) as ticket_medio,
                    COUNT(DISTINCT usuario_id) as clientes_unicos,
                    COUNT(CASE WHEN forma_pagamento='pix'    THEN 1 END) as pedidos_pix,
                    COUNT(CASE WHEN forma_pagamento='cartao' THEN 1 END) as pedidos_cartao,
                    COUNT(CASE WHEN forma_pagamento='boleto' THEN 1 END) as pedidos_boleto
                FROM pedidos
                WHERE status NOT IN ('cancelado')
                  AND DATE(data_pedido) BETWEEN ? AND ?
            ");
            $resumo->execute([$ini, $fim]);

            $diario = $pdo->prepare("
                SELECT DATE(data_pedido) as dia,
                       COUNT(*) as pedidos,
                       SUM(total) as receita
                FROM pedidos
                WHERE status NOT IN ('cancelado')
                  AND DATE(data_pedido) BETWEEN ? AND ?
                GROUP BY dia ORDER BY dia
            ");
            $diario->execute([$ini, $fim]);

            $top_produtos = $pdo->prepare("
                SELECT p.nome,
                       SUM(ip.quantidade) as qtd,
                       SUM(ip.quantidade * ip.preco_unitario) as receita
                FROM itens_pedido ip
                JOIN pedidos ped ON ped.id = ip.pedido_id
                JOIN produtos p  ON p.id  = ip.produto_id
                WHERE ped.status NOT IN ('cancelado')
                  AND DATE(ped.data_pedido) BETWEEN ? AND ?
                GROUP BY ip.produto_id
                ORDER BY receita DESC
                LIMIT 10
            ");
            $top_produtos->execute([$ini, $fim]);

            return [
                'resumo'       => $resumo->fetch(PDO::FETCH_ASSOC),
                'diario'       => $diario->fetchAll(PDO::FETCH_ASSOC),
                'top_produtos' => $top_produtos->fetchAll(PDO::FETCH_ASSOC),
            ];

        case 'margem_lucro':
            $stmt = $pdo->prepare("
                SELECT p.nome, p.preco, p.custo,
                       SUM(ip.quantidade) as vendido,
                       SUM(ip.quantidade * ip.preco_unitario) as receita,
                       SUM(ip.quantidade * COALESCE(p.custo,0)) as custo_total
                FROM produtos p
                JOIN itens_pedido ip ON ip.produto_id = p.id
                JOIN pedidos ped ON ped.id = ip.pedido_id
                WHERE ped.status NOT IN ('cancelado')
                  AND DATE(ped.data_pedido) BETWEEN ? AND ?
                GROUP BY p.id
                HAVING vendido > 0
                ORDER BY receita DESC
            ");
            $stmt->execute([$ini, $fim]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcula margem em PHP
            foreach ($rows as &$r) {
                $r['lucro']  = $r['receita'] - $r['custo_total'];
                $r['margem'] = $r['receita'] > 0
                    ? round(($r['lucro'] / $r['receita']) * 100, 2)
                    : 0;
            }

            return ['produtos' => $rows];

        case 'produtos_desempenho':
            $stmt = $pdo->prepare("
                SELECT p.id, p.nome, p.estoque,
                       COALESCE(SUM(ip.quantidade),0) as vendido,
                       COALESCE(AVG(a.nota),0) as nota_media,
                       COUNT(DISTINCT d.id) as devolucoes
                FROM produtos p
                LEFT JOIN itens_pedido ip ON ip.produto_id = p.id
                LEFT JOIN pedidos ped ON ped.id = ip.pedido_id
                    AND DATE(ped.data_pedido) BETWEEN ? AND ?
                LEFT JOIN avaliacoes a ON a.produto_id = p.id AND a.status = 'aprovado'
                LEFT JOIN devolucoes d ON d.pedido_id = ped.id
                WHERE p.ativo = 1
                GROUP BY p.id
                ORDER BY vendido DESC
            ");
            $stmt->execute([$ini, $fim]);
            return ['produtos' => $stmt->fetchAll(PDO::FETCH_ASSOC)];

        case 'clientes':
            $stmt = $pdo->prepare("
                SELECT u.nome, u.email, u.data_cadastro,
                       COUNT(DISTINCT ped.id) as total_pedidos,
                       COALESCE(SUM(ped.total),0) as gasto_total,
                       MAX(ped.data_pedido) as ultimo_pedido
                FROM usuarios u
                LEFT JOIN pedidos ped ON ped.usuario_id = u.id
                    AND ped.status NOT IN ('cancelado')
                    AND DATE(ped.data_pedido) BETWEEN ? AND ?
                WHERE u.nivel = 'cliente'
                GROUP BY u.id
                ORDER BY gasto_total DESC
                LIMIT 500
            ");
            $stmt->execute([$ini, $fim]);
            return ['clientes' => $stmt->fetchAll(PDO::FETCH_ASSOC)];

        default:
            return [];
    }
}

// ── LISTA RELATÓRIOS EXISTENTES ───────────────────────────
$relatorios = $pdo->query("
    SELECT r.*, u.nome as gerado_por_nome
    FROM relatorios_seguros r
    LEFT JOIN usuarios u ON u.id = r.gerado_por
    ORDER BY r.data_geracao DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$c_nao_lidos     = 0;
try { $c_nao_lidos = $pdo->query("SELECT COUNT(*) FROM alertas WHERE lido=0")->fetchColumn(); } catch(Exception $e){}
$devolucoes_pend = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();
$estoque_critico = $pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque<=3 AND ativo=1")->fetchColumn();
$p_pendente_sb   = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();

define('CONTEUDO_AUTORIZADO', true);
$pagina_atual = 'relatorio.criptografado';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios Criptografados | Alto Jordão Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <style>
        .page-grid { display:grid; grid-template-columns:1fr 380px; gap:22px; align-items:start; }

        .rel-card { background:var(--white); border:1px solid var(--border); border-radius:18px; padding:20px 22px; margin-bottom:12px; display:flex; gap:14px; align-items:flex-start; box-shadow:var(--shadow); transition:var(--transition); }
        .rel-card:hover { border-color:#ccc; transform:translateY(-2px); }

        .rel-icon { font-size:28px; flex-shrink:0; }
        .rel-info { flex:1; min-width:0; }
        .rel-nome { font-weight:800; font-size:14px; margin-bottom:4px; }
        .rel-meta { font-size:11px; color:var(--muted); display:flex; gap:14px; flex-wrap:wrap; margin-top:6px; }

        .badge-enc { background:#f0f4ff; color:#1976d2; padding:3px 10px; border-radius:50px; font-size:10px; font-weight:800; font-family:monospace; }

        .rel-actions { display:flex; flex-direction:column; gap:8px; align-items:flex-end; flex-shrink:0; }
        .btn-dec  { padding:8px 18px; border-radius:50px; background:var(--black); color:var(--white); font-size:11px; font-weight:800; text-decoration:none; text-transform:uppercase; white-space:nowrap; }
        .btn-down { padding:8px 18px; border-radius:50px; background:var(--grey-bg); color:var(--black); border:1px solid var(--border); font-size:11px; font-weight:800; text-decoration:none; text-transform:uppercase; white-space:nowrap; }

        .form-card .input-group { display:flex; flex-direction:column; gap:8px; margin-bottom:14px; }
        .form-card label { font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1px; }
        .form-card input, .form-card select { padding:12px 16px; border:1.5px solid var(--border); border-radius:50px; font-family:var(--font-main); font-size:13px; background:var(--grey-bg); color:var(--black); outline:none; transition:var(--transition); }
        .form-card input:focus, .form-card select:focus { border-color:var(--black); background:var(--white); }

        .info-box { background:var(--grey-bg); border-left:3px solid #1976d2; border-radius:0 14px 14px 0; padding:14px 18px; font-size:12px; color:var(--text2); line-height:1.7; margin-bottom:18px; }
        .info-box strong { color:var(--black); }

        .msg-ok  { background:#e8f5e9; border:1px solid #c8e6c9; color:var(--success); padding:12px 22px; border-radius:50px; margin-bottom:20px; font-size:13px; font-weight:700; display:inline-block; }
        .msg-err { background:rgba(255,77,77,.08); border:1px solid rgba(255,77,77,.25); color:var(--danger); padding:12px 22px; border-radius:50px; margin-bottom:20px; font-size:13px; font-weight:700; display:inline-block; }

        .tipos_labels_map { font-family:monospace; font-size:11px; }
    </style>
</head>
<body class="admin-page">

<?php include 'sidebar.php'; ?>

<main class="admin-main">
    <div class="admin-topbar">
        <div>
            <h1>Relatórios Criptografados</h1>
            <p>Gere relatórios protegidos com AES-256-CBC. Apenas quem tem a senha pode abrir.</p>
        </div>
    </div>

    <?php if ($erro): ?><div class="msg-err">⚠ <?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <div class="page-grid">

        <!-- LISTA DE RELATÓRIOS -->
        <div>
            <div class="info-box">
                🔐 Arquivos gerados com extensão <strong>.ajenc</strong> (Alto Jordão Encrypted).
                Criptografia <strong>AES-256-CBC</strong> com chave derivada via
                <strong>PBKDF2-SHA256 (100.000 iterações)</strong>.
                Sem a senha correta, o arquivo é completamente ilegível — nem o servidor pode abrir.
            </div>

            <?php if(empty($relatorios)): ?>
            <div class="admin-card" style="text-align:center;padding:60px;color:var(--muted);">
                Nenhum relatório criptografado gerado ainda.<br>
                Use o formulário ao lado para criar o primeiro.
            </div>
            <?php endif; ?>

            <?php
            $icones = [
                'financeiro_mensal'   => '💰',
                'financeiro_anual'    => '📅',
                'produtos_desempenho' => '📦',
                'clientes'            => '👥',
                'margem_lucro'        => '📈',
            ];
            foreach($relatorios as $r):
                $existe = file_exists(__DIR__ . '/' . $r['arquivo']);
            ?>
            <div class="rel-card" style="<?= !$existe ? 'opacity:.4' : '' ?>">
                <div class="rel-icon"><?= $icones[$r['tipo']] ?? '📄' ?></div>
                <div class="rel-info">
                    <div class="rel-nome"><?= htmlspecialchars($r['nome']) ?></div>
                    <span class="badge-enc"><?= $r['algoritmo'] ?></span>
                    <div class="rel-meta">
                        <span>📅 <?= date('d/m/Y H:i', strtotime($r['data_geracao'])) ?></span>
                        <span>👤 <?= htmlspecialchars($r['gerado_por_nome'] ?? 'Sistema') ?></span>
                        <span>💾 <?= number_format($r['tamanho_bytes'] / 1024, 1) ?> KB</span>
                        <span>🔑 <?= $r['acessos'] ?> abertura(s)</span>
                        <?php if(!$existe): ?><span style="color:var(--danger);">⚠ Arquivo não encontrado</span><?php endif; ?>
                    </div>
                    <div style="font-size:10px;color:var(--muted);margin-top:6px;font-family:monospace;">
                        SHA-256: <?= substr($r['hash_integridade'],0,32) ?>...
                    </div>
                </div>
                <?php if($existe): ?>
                <div class="rel-actions">
                    <a href="descriptografar_relatorio.php?id=<?= $r['id'] ?>" class="btn-dec">🔓 Abrir</a>
                    <a href="descriptografar_relatorio.php?id=<?= $r['id'] ?>&download=1" class="btn-down">⬇ Baixar .ajenc</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- FORMULÁRIO DE GERAÇÃO -->
        <div class="admin-card form-card">
            <div class="card-header" style="margin-bottom:18px;">
                <span class="card-title">🔐 Novo Relatório</span>
            </div>

            <form method="POST">
                <div class="input-group">
                    <label>Tipo de Relatório</label>
                    <select name="tipo" required>
                        <option value="">Selecione...</option>
                        <option value="financeiro_mensal">💰 Financeiro Mensal</option>
                        <option value="financeiro_anual">📅 Financeiro Anual</option>
                        <option value="margem_lucro">📈 Margem de Lucro</option>
                        <option value="produtos_desempenho">📦 Desempenho de Produtos</option>
                        <option value="clientes">👥 Base de Clientes</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>Data de Início</label>
                    <input type="date" name="data_ini"
                           value="<?= date('Y-m-01') ?>" required>
                </div>
                <div class="input-group">
                    <label>Data de Fim</label>
                    <input type="date" name="data_fim"
                           value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="input-group">
                    <label>Senha de Proteção *</label>
                    <input type="password" name="senha" id="senhaRel"
                           placeholder="Mínimo 8 caracteres" required minlength="8">
                    <small style="font-size:11px;color:var(--muted);">
                        Guarde esta senha — não é possível recuperar o relatório sem ela.
                    </small>
                </div>
                <div class="input-group">
                    <label>Confirmar Senha *</label>
                    <input type="password" name="confirmar"
                           placeholder="Repita a senha" required>
                </div>

                <button type="submit" name="gerar" value="1"
                        class="btn-admin-primary"
                        style="width:100%;padding:14px;border-radius:50px;justify-content:center;">
                    🔐 Gerar e Criptografar
                </button>
            </form>
        </div>
    </div>
</main>

<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>