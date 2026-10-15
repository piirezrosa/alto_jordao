<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

if (!isset($_SESSION['usuario_nivel']) ||
    !in_array($_SESSION['usuario_nivel'], ['admin','superadmin'])) {
    header("Location: login.php"); exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Salvar configurações de alertas 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campos = [
        'alerta_produto_problematico' => isset($_POST['alerta_produto_problematico']) ? 1 : 0,
        'alerta_estoque_critico'      => isset($_POST['alerta_estoque_critico'])      ? 1 : 0,
        'alerta_pedido_parado'        => isset($_POST['alerta_pedido_parado'])        ? 1 : 0,
        'alerta_avaliacao_negativa'   => isset($_POST['alerta_avaliacao_negativa'])   ? 1 : 0,
        'alerta_devolucao_alta'       => isset($_POST['alerta_devolucao_alta'])       ? 1 : 0,
        'alerta_margem_baixa'         => isset($_POST['alerta_margem_baixa'])         ? 1 : 0,
        'limiar_taxa_devolucao'       => (float)($_POST['limiar_taxa_devolucao']      ?? 10),
        'limiar_nota_media'           => (float)($_POST['limiar_nota_media']          ?? 3.0),
        'limiar_dias_pedido_parado'   => (int)($_POST['limiar_dias_pedido_parado']    ?? 7),
        'limiar_estoque_critico'      => (int)($_POST['limiar_estoque_critico']       ?? 3),
        'limiar_margem_minima'        => (float)($_POST['limiar_margem_minima']       ?? 20),
        'notificar_email'             => isset($_POST['notificar_email'])              ? 1 : 0,
        'email_alternativo'           => trim($_POST['email_alternativo']             ?? ''),
    ];

    // Upsert
    $pdo->prepare("
        INSERT INTO alerta_configuracoes
            (usuario_id, alerta_produto_problematico, alerta_estoque_critico,
             alerta_pedido_parado, alerta_avaliacao_negativa, alerta_devolucao_alta,
             alerta_margem_baixa, limiar_taxa_devolucao, limiar_nota_media,
             limiar_dias_pedido_parado, limiar_estoque_critico, limiar_margem_minima,
             notificar_email, email_alternativo)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            alerta_produto_problematico = VALUES(alerta_produto_problematico),
            alerta_estoque_critico      = VALUES(alerta_estoque_critico),
            alerta_pedido_parado        = VALUES(alerta_pedido_parado),
            alerta_avaliacao_negativa   = VALUES(alerta_avaliacao_negativa),
            alerta_devolucao_alta       = VALUES(alerta_devolucao_alta),
            alerta_margem_baixa         = VALUES(alerta_margem_baixa),
            limiar_taxa_devolucao       = VALUES(limiar_taxa_devolucao),
            limiar_nota_media           = VALUES(limiar_nota_media),
            limiar_dias_pedido_parado   = VALUES(limiar_dias_pedido_parado),
            limiar_estoque_critico      = VALUES(limiar_estoque_critico),
            limiar_margem_minima        = VALUES(limiar_margem_minima),
            notificar_email             = VALUES(notificar_email),
            email_alternativo           = VALUES(email_alternativo)
    ")->execute(array_merge([$usuario_id], array_values($campos)));

    header("Location: admin_configuracoes_alertas.php?msg=salvo"); exit;
}

// Carregar configurações do user
$cfg = $pdo->prepare("SELECT * FROM alerta_configuracoes WHERE usuario_id = ?");
$cfg->execute([$usuario_id]);
$cfg = $cfg->fetch(PDO::FETCH_ASSOC);

// Defaults se ainda não tem configuração
if (!$cfg) {
    $cfg = [
        'alerta_produto_problematico' => 1,
        'alerta_estoque_critico'      => 1,
        'alerta_pedido_parado'        => 1,
        'alerta_avaliacao_negativa'   => 1,
        'alerta_devolucao_alta'       => 1,
        'alerta_margem_baixa'         => 0,
        'limiar_taxa_devolucao'       => 10,
        'limiar_nota_media'           => 3.0,
        'limiar_dias_pedido_parado'   => 7,
        'limiar_estoque_critico'      => 3,
        'limiar_margem_minima'        => 20,
        'notificar_email'             => 0,
        'email_alternativo'           => '',
    ];
}

$c_nao_lidos     = $pdo->query("SELECT COUNT(*) FROM alertas WHERE lido=0")->fetchColumn();
$devolucoes_pend = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();
$estoque_critico = $pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque<=3 AND ativo=1")->fetchColumn();
$p_pendente_sb   = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();

define('CONTEUDO_AUTORIZADO', true);
$pagina_atual = 'config.alertas';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Alertas | Alto Jordão Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <style>
        .config-card { max-width:720px; }

        .alerta-toggle-row {
            display:flex; justify-content:space-between; align-items:flex-start;
            padding:18px 20px; background:var(--grey-bg); border-radius:16px;
            border:1.5px solid var(--border); margin-bottom:12px; gap:16px;
        }
        .toggle-info strong { display:block; font-size:13px; font-weight:800; margin-bottom:4px; }
        .toggle-info p { font-size:12px; color:var(--text2); margin:0; line-height:1.5; }

        .toggle-switch { position:relative; width:44px; height:24px; flex-shrink:0; margin-top:2px; }
        .toggle-switch input { opacity:0; width:0; height:0; }
        .toggle-slider { position:absolute; cursor:pointer; inset:0; background:var(--border); border-radius:50px; transition:.3s; }
        .toggle-slider::before { content:''; position:absolute; width:18px; height:18px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.3s; }
        .toggle-switch input:checked + .toggle-slider { background:var(--black); }
        .toggle-switch input:checked + .toggle-slider::before { transform:translateX(20px); }

        .limiar-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:16px; }
        .limiar-group { display:flex; flex-direction:column; gap:8px; }
        .limiar-group label { font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1px; }
        .limiar-group input {
            padding:11px 16px; border:1.5px solid var(--border); border-radius:50px;
            font-family:var(--font-main); font-size:13px; background:var(--grey-bg);
            color:var(--black); outline:none; transition:var(--transition);
        }
        .limiar-group input:focus { border-color:var(--black); background:var(--white); }
        .limiar-group small { font-size:11px; color:var(--muted); }

        .section-divider { font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:2px; margin:28px 0 16px; display:flex; align-items:center; gap:12px; }
        .section-divider::after { content:''; flex:1; height:1px; background:var(--border); }

        .msg-ok { background:#e8f5e9; border:1px solid #c8e6c9; color:var(--success); padding:12px 22px; border-radius:50px; margin-bottom:20px; font-size:13px; font-weight:700; display:inline-block; }
    </style>
</head>
<body class="admin-page">

<?php include 'sidebar.php'; ?>

<main class="admin-main">
    <div class="admin-topbar">
        <div>
            <h1>Configurar Alertas</h1>
            <p>Defina quais notificações você quer receber e com qual sensibilidade.</p>
        </div>
    </div>

    <?php if(isset($_GET['msg'])): ?>
    <div class="msg-ok">✓ Configurações salvas com sucesso!</div>
    <?php endif; ?>

    <div class="admin-card config-card">
        <form method="POST">

            <!-- Tipos de Alerta -->
            <div class="section-divider">Tipos de Alerta</div>

            <div class="alerta-toggle-row">
                <div class="toggle-info">
                    <strong>⚠️ Produto Problemático</strong>
                    <p>Notifica quando um produto atinge score alto de problemas (combinação de devoluções, avaliações ruins e problemas de entrega).</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="alerta_produto_problematico" value="1" <?= $cfg['alerta_produto_problematico']?'checked':''?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div class="alerta-toggle-row">
                <div class="toggle-info">
                    <strong>📦 Estoque Crítico</strong>
                    <p>Notifica quando o estoque de um produto fica abaixo do limiar configurado.</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="alerta_estoque_critico" value="1" <?= $cfg['alerta_estoque_critico']?'checked':''?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div class="alerta-toggle-row">
                <div class="toggle-info">
                    <strong>⏰ Pedido Parado</strong>
                    <p>Notifica quando um pedido fica dias sem atualização de status.</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="alerta_pedido_parado" value="1" <?= $cfg['alerta_pedido_parado']?'checked':''?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div class="alerta-toggle-row">
                <div class="toggle-info">
                    <strong>⭐ Avaliação Negativa</strong>
                    <p>Notifica quando a nota média de um produto cai abaixo do limiar configurado.</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="alerta_avaliacao_negativa" value="1" <?= $cfg['alerta_avaliacao_negativa']?'checked':''?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div class="alerta-toggle-row">
                <div class="toggle-info">
                    <strong>🔄 Taxa de Devolução Alta</strong>
                    <p>Notifica quando a porcentagem de devoluções de um produto supera o limiar.</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="alerta_devolucao_alta" value="1" <?= $cfg['alerta_devolucao_alta']?'checked':''?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div class="alerta-toggle-row">
                <div class="toggle-info">
                    <strong>💰 Margem Baixa</strong>
                    <p>Notifica quando a margem de lucro estimada de um produto cai abaixo do limiar. Requer que o campo "custo" esteja preenchido nos produtos.</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="alerta_margem_baixa" value="1" <?= $cfg['alerta_margem_baixa']?'checked':''?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <!-- Limiares de sensibilidade -->
            <div class="section-divider">Sensibilidade dos Limiares</div>

            <div class="limiar-row">
                <div class="limiar-group">
                    <label>Taxa de Devolução (%)</label>
                    <input type="number" step="0.5" min="1" max="100"
                           name="limiar_taxa_devolucao"
                           value="<?= $cfg['limiar_taxa_devolucao'] ?>">
                    <small>Alertar se devolução ultrapassar este % das vendas</small>
                </div>
                <div class="limiar-group">
                    <label>Nota Média Mínima</label>
                    <input type="number" step="0.1" min="1" max="5"
                           name="limiar_nota_media"
                           value="<?= $cfg['limiar_nota_media'] ?>">
                    <small>Alertar se nota média cair abaixo deste valor (1 a 5)</small>
                </div>
            </div>

            <div class="limiar-row">
                <div class="limiar-group">
                    <label>Pedido parado há (dias)</label>
                    <input type="number" min="1" max="60"
                           name="limiar_dias_pedido_parado"
                           value="<?= $cfg['limiar_dias_pedido_parado'] ?>">
                    <small>Alertar se pedido ficar N dias sem atualização</small>
                </div>
                <div class="limiar-group">
                    <label>Estoque Crítico (unidades)</label>
                    <input type="number" min="1" max="50"
                           name="limiar_estoque_critico"
                           value="<?= $cfg['limiar_estoque_critico'] ?>">
                    <small>Alertar se estoque ficar igual ou abaixo deste número</small>
                </div>
            </div>

            <div class="limiar-row">
                <div class="limiar-group">
                    <label>Margem Mínima Aceitável (%)</label>
                    <input type="number" step="0.5" min="1" max="99"
                           name="limiar_margem_minima"
                           value="<?= $cfg['limiar_margem_minima'] ?>">
                    <small>Alertar se margem estimada cair abaixo deste %</small>
                </div>
            </div>

            <!-- E-mail -->
            <div class="section-divider">Notificação por E-mail</div>

            <div class="alerta-toggle-row">
                <div class="toggle-info">
                    <strong>📧 Receber alertas críticos por e-mail</strong>
                    <p>Além da notificação no painel, envia um e-mail quando alertas críticos forem gerados.</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="notificar_email" value="1" <?= $cfg['notificar_email']?'checked':''?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div class="limiar-group" style="margin-bottom:28px;">
                <label>E-mail alternativo (opcional)</label>
                <input type="email" name="email_alternativo"
                       value="<?= htmlspecialchars($cfg['email_alternativo'] ?? '') ?>"
                       placeholder="Deixe vazio para usar o e-mail da sua conta">
                <small>Se preenchido, os alertas por e-mail serão enviados para este endereço.</small>
            </div>

            <button type="submit" class="btn-admin-primary" style="width:100%; padding:16px; border-radius:50px; justify-content:center; font-size:13px;">
                💾 Salvar Configurações de Alertas
            </button>
        </form>
    </div>
</main>

<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>