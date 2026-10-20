<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'financeiro_helpers.php';
require_once 'frete_helpers.php';
require_once 'melhorenvio.php';

// Apenas superadmin acessa configurações do sistema
if (!isset($_SESSION['usuario_nivel']) || !in_array($_SESSION['usuario_nivel'], ['admin','superadmin'])) {
    header("Location: login.php"); exit();
}

$msg = '';

// ── SALVAR CONFIGURAÇÕES ──────────────────────
// Teste de conexão com o Melhor Envio (cota uma caixa padrão da origem para um CEP de teste)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['testar_melhorenvio'])) {
    if (!in_array($_SESSION['usuario_nivel'] ?? '', ['admin','superadmin'], true)) { http_response_code(403); exit('Acesso restrito.'); }
    if (!fin_csrf_ok($_POST['csrf'] ?? null)) { header("Location: admin_configuracoes.php?secao=frete&msg=csrf"); exit(); }
    $origem_t = preg_replace('/\D/', '', (string)loja_cfg($pdo, 'frete_cep_origem', ''));
    if (!MelhorEnvio::configurado())      $res_t = ['ok' => false, 'erro' => 'MELHORENVIO_TOKEN não está no arquivo .env.', 'servicos' => []];
    elseif (strlen($origem_t) !== 8)      $res_t = ['ok' => false, 'erro' => 'Preencha o CEP de origem e salve antes de testar.', 'servicos' => []];
    else $res_t = MelhorEnvio::cotar($origem_t, '01001000', frete_normalizar_itens($pdo, [['id' => 'teste', 'qtd' => 1, 'preco' => 100]]));
    $_SESSION['me_teste'] = $res_t + ['quando' => date('H:i:s'), 'sandbox' => MelhorEnvio::sandbox()];
    header("Location: admin_configuracoes.php?secao=frete"); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['secao'])) {
    $secao = $_POST['secao'];

    // Só seções conhecidas (a seção vai para a chave salva e para o redirecionamento)
    $secoes_validas = ['geral','loja','emails','pagamento','frete','seguranca','sistema','financeiro'];
    if (!in_array($secao, $secoes_validas, true)) { header("Location: admin_configuracoes.php"); exit(); }

    // Financeiro (impostos, taxas, custos): SOMENTE superadmin — validado no servidor
    if ($secao === 'financeiro') {
        if (($_SESSION['usuario_nivel'] ?? '') !== 'superadmin') { http_response_code(403); exit('Acesso restrito ao superadmin.'); }
        if (!fin_csrf_ok($_POST['csrf'] ?? null)) { header("Location: admin_configuracoes.php?secao=financeiro&msg=csrf"); exit(); }
        $erro_fin = fin_salvar_configuracoes($pdo, $_POST);
        header("Location: admin_configuracoes.php?secao=financeiro&" . ($erro_fin === null ? 'msg=salvo' : 'msg=erro_fin&detalhe=' . urlencode($erro_fin)));
        exit();
    }

    // Upsert genérico: salva cada campo como chave-valor
    $campos = $_POST;
    unset($campos['secao']);

    $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
    foreach ($campos as $chave => $valor) {
        if (is_string($chave) && strlen($chave) > 0) {
            $stmt->execute([$secao.'_'.$chave, $valor]);
        }
    }

    // Log
    $log = $pdo->prepare("INSERT INTO logs_sistema (usuario_id, acao, tabela, detalhes, ip) VALUES (?,?,?,?,?)");
    $log->execute([$_SESSION['usuario_id']??null, 'configuracao_salva', 'configuracoes', 'Seção: '.$secao, $_SERVER['REMOTE_ADDR']]);

    header("Location: admin_configuracoes.php?msg=salvo&secao=$secao"); exit();
}

// ── CARREGA CONFIGURAÇÕES DO BANCO ───────────
function cfg($pdo, $chave, $default = '') {
    try {
        $s = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
        $s->execute([$chave]);
        $v = $s->fetchColumn();
        return $v !== false ? $v : $default;
    } catch (Exception $e) { return $default; }
}

// Tenta criar a tabela de configurações se não existir
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS configuracoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chave VARCHAR(100) NOT NULL UNIQUE,
        valor TEXT DEFAULT NULL,
        atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

$secao_ativa    = $_GET['secao'] ?? 'geral';
$eh_superadmin  = (($_SESSION['usuario_nivel'] ?? '') === 'superadmin');
if ($secao_ativa === 'financeiro' && !$eh_superadmin) $secao_ativa = 'geral';   // oculta a seção de quem não é superadmin

// Badges sidebar
$devolucoes_pend = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();
$estoque_critico = $pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque<=3 AND ativo=1")->fetchColumn();
$p_pendente_sb   = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();

define('CONTEUDO_AUTORIZADO', true);
$pagina_atual = 'configuracoes';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações | Alto Jordão Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <style>
        .config-layout { display:grid; grid-template-columns:220px 1fr; gap:22px; align-items:start; }

        /* Menu lateral de seções */
        .config-menu { background:var(--white); border:1px solid var(--border); border-radius:24px; padding:16px; box-shadow:var(--shadow); position:sticky; top:30px; }
        .config-menu-title { font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1.5px; padding:8px 12px 14px; border-bottom:1px solid var(--border); margin-bottom:10px; }
        .config-nav-item { display:flex; align-items:center; gap:10px; padding:11px 14px; border-radius:50px; color:var(--text2); text-decoration:none; font-size:13px; font-weight:600; transition:var(--transition); margin-bottom:3px; }
        .config-nav-item:hover { background:var(--grey-bg); color:var(--black); }
        .config-nav-item.active { background:var(--black); color:var(--white); font-weight:700; }

        /* Painel de conteúdo */
        .config-panel { display:none; }
        .config-panel.active { display:block; }

        /* Grupos de campo */
        .config-section-title {
            font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase;
            letter-spacing:2px; margin:28px 0 18px; display:flex; align-items:center; gap:12px;
        }
        .config-section-title::after { content:''; flex:1; height:1px; background:var(--border); }

        .field-group { display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-bottom:18px; }
        .field-group.single { grid-template-columns:1fr; }
        .field-group.triple { grid-template-columns:1fr 1fr 1fr; }

        .input-group { display:flex; flex-direction:column; gap:8px; }
        .input-group label { font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1px; }
        .input-group small { font-size:11px; color:var(--muted); margin-top:2px; }

        .input-group input,
        .input-group select,
        .input-group textarea {
            padding:12px 16px; border:1.5px solid var(--border); border-radius:50px;
            font-family:var(--font-main); font-size:13px; background:var(--grey-bg);
            color:var(--black); outline:none; transition:var(--transition);
        }
        .input-group textarea { border-radius:16px; resize:vertical; min-height:90px; }
        .input-group input:focus, .input-group select:focus, .input-group textarea:focus {
            border-color:var(--black); background:var(--white);
        }

        /* Toggle switch */
        .toggle-row {
            display:flex; justify-content:space-between; align-items:center;
            padding:16px 20px; background:var(--grey-bg); border-radius:16px;
            border:1.5px solid var(--border); margin-bottom:12px;
        }
        .toggle-info strong { display:block; font-size:13px; font-weight:700; margin-bottom:2px; }
        .toggle-info span   { font-size:11px; color:var(--text2); }

        .toggle-switch { position:relative; width:44px; height:24px; flex-shrink:0; }
        .toggle-switch input { opacity:0; width:0; height:0; }
        .toggle-slider {
            position:absolute; cursor:pointer; inset:0;
            background:var(--border); border-radius:50px; transition:.3s;
        }
        .toggle-slider::before {
            content:''; position:absolute; width:18px; height:18px;
            left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.3s;
        }
        .toggle-switch input:checked + .toggle-slider { background:var(--black); }
        .toggle-switch input:checked + .toggle-slider::before { transform:translateX(20px); }

        /* Botão salvar seção */
        .save-bar {
            display:flex; justify-content:flex-end; gap:12px; align-items:center;
            margin-top:30px; padding-top:24px; border-top:1px solid var(--border);
        }

        /* Info box */
        .info-box {
            background:var(--grey-bg); border-radius:16px; padding:18px 20px;
            font-size:13px; color:var(--text2); line-height:1.6; margin-bottom:18px;
            border-left:3px solid var(--black);
        }

        .msg-ok { background:#e8f5e9; border:1px solid #c8e6c9; color:var(--success); padding:12px 22px; border-radius:50px; margin-bottom:22px; font-size:13px; font-weight:700; display:inline-flex; align-items:center; gap:8px; }

        .msg-err { background:rgba(255,77,77,.08); border:1px solid rgba(255,77,77,.25); color:var(--danger); padding:12px 22px; border-radius:50px; margin-bottom:20px; font-size:13px; font-weight:700; display:inline-block; }
        /* Danger zone */
        .danger-zone { border:1.5px solid rgba(255,77,77,.3); border-radius:20px; padding:24px; margin-top:10px; }
        .danger-zone h3 { font-size:13px; font-weight:800; color:var(--danger); margin-bottom:6px; }
        .danger-zone p  { font-size:12px; color:var(--text2); margin-bottom:16px; line-height:1.5; }
        .btn-danger { background:rgba(255,77,77,.1); color:var(--danger); border:1px solid rgba(255,77,77,.3); padding:11px 22px; border-radius:50px; font-size:12px; font-weight:800; cursor:pointer; text-transform:uppercase; transition:var(--transition); }
        .btn-danger:hover { background:var(--danger); color:var(--white); }
    </style>
</head>
<body class="admin-page">

<!-- ── SIDEBAR ───────────────────────────────── -->
<?php include 'sidebar.php'; ?>

<!-- ── CONTEÚDO ───────────────────────────────── -->
<main class="admin-main">

    <div class="admin-topbar">
        <div>
            <h1>Configurações</h1>
            <p>Gerencie as preferências gerais da plataforma Alto Jordão.</p>
        </div>
    </div>

    <?php if(isset($_GET['msg']) && $_GET['msg'] === 'salvo'): ?>
    <div class="msg-ok">✓ Configurações salvas com sucesso!</div>
    <?php endif; ?>
    <?php if(isset($_GET['msg']) && $_GET['msg'] === 'erro_fin'): ?>
    <div class="msg-err">⚠ <?= htmlspecialchars($_GET['detalhe'] ?? 'Não foi possível salvar.') ?></div>
    <?php elseif(isset($_GET['msg']) && $_GET['msg'] === 'csrf'): ?>
    <div class="msg-err">⚠ Sessão expirada. Recarregue a página e tente de novo.</div>
    <?php endif; ?>

    <div class="config-layout">

        <!-- MENU DE SEÇÕES -->
        <nav class="config-menu">
            <div class="config-menu-title">Seções</div>
            <a href="?secao=geral"     class="config-nav-item <?= $secao_ativa==='geral'     ?'active':''?>">🏪 Geral</a>
            <a href="?secao=loja"      class="config-nav-item <?= $secao_ativa==='loja'      ?'active':''?>">🛒 Loja</a>
            <a href="?secao=emails"    class="config-nav-item <?= $secao_ativa==='emails'    ?'active':''?>">📧 E-mails</a>
            <a href="?secao=pagamento" class="config-nav-item <?= $secao_ativa==='pagamento' ?'active':''?>">💳 Pagamento</a>
            <a href="?secao=frete"     class="config-nav-item <?= $secao_ativa==='frete'     ?'active':''?>">📦 Frete</a>
            <a href="?secao=seguranca" class="config-nav-item <?= $secao_ativa==='seguranca' ?'active':''?>">🔒 Segurança</a>
            <a href="?secao=sistema"   class="config-nav-item <?= $secao_ativa==='sistema'   ?'active':''?>">⚙️ Sistema</a>
            <?php if($eh_superadmin): ?>
            <a href="?secao=financeiro" class="config-nav-item <?= $secao_ativa==='financeiro' ?'active':''?>">🧾 Financeiro <span style="font-size:9px;opacity:.6;">(superadmin)</span></a>
            <?php endif; ?>
        </nav>

        <!-- PAINÉIS -->
        <div>

            <!-- ── GERAL ── -->
            <?php if($secao_ativa === 'geral'): ?>
            <div class="admin-card config-panel active">
                <div class="card-header"><span class="card-title">⚙️ Configurações Gerais</span></div>
                <form method="POST">
                    <input type="hidden" name="secao" value="geral">

                    <div class="config-section-title">Identidade da Loja</div>
                    <div class="field-group">
                        <div class="input-group">
                            <label>Nome da Loja</label>
                            <input type="text" name="nome_loja" value="<?= cfg($pdo,'geral_nome_loja','Alto Jordão') ?>">
                        </div>
                        <div class="input-group">
                            <label>CNPJ</label>
                            <input type="text" name="cnpj" value="<?= cfg($pdo,'geral_cnpj') ?>" placeholder="00.000.000/0001-00">
                        </div>
                    </div>
                    <div class="field-group single">
                        <div class="input-group">
                            <label>Descrição / Slogan</label>
                            <textarea name="descricao"><?= cfg($pdo,'geral_descricao','Streetwear premium.') ?></textarea>
                        </div>
                    </div>

                    <div class="config-section-title">Contato</div>
                    <div class="field-group">
                        <div class="input-group">
                            <label>E-mail de Suporte</label>
                            <input type="email" name="email_suporte" value="<?= cfg($pdo,'geral_email_suporte','suporte@altojordao.com.br') ?>">
                        </div>
                        <div class="input-group">
                            <label>Telefone / WhatsApp</label>
                            <input type="text" name="telefone" value="<?= cfg($pdo,'geral_telefone') ?>" placeholder="(11) 99999-9999">
                        </div>
                    </div>
                    <div class="field-group">
                        <div class="input-group">
                            <label>Instagram</label>
                            <input type="text" name="instagram" value="<?= cfg($pdo,'geral_instagram') ?>" placeholder="@altojordao">
                        </div>
                        <div class="input-group">
                            <label>TikTok</label>
                            <input type="text" name="tiktok" value="<?= cfg($pdo,'geral_tiktok') ?>" placeholder="@altojordao">
                        </div>
                    </div>

                    <div class="save-bar">
                        <button type="submit" class="btn-admin-primary">💾 Salvar Configurações Gerais</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- ── LOJA ── -->
            <?php if($secao_ativa === 'loja'): ?>
            <div class="admin-card config-panel active">
                <div class="card-header"><span class="card-title">🛒 Configurações da Loja</span></div>
                <form method="POST">
                    <input type="hidden" name="secao" value="loja">

                    <div class="config-section-title">Comportamento</div>

                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Loja Online</strong>
                            <span>Desativar coloca a loja em modo manutenção</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="hidden" name="loja_ativa" value="0">
                            <input type="checkbox" name="loja_ativa" value="1" <?= cfg($pdo,'loja_loja_ativa','1')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Exibir Estoque nos Produtos</strong>
                            <span>Mostra quantidade disponível na página do produto</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="hidden" name="exibir_estoque" value="0">
                            <input type="checkbox" name="exibir_estoque" value="1" <?= cfg($pdo,'loja_exibir_estoque','0')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Permitir Compra sem Cadastro</strong>
                            <span>Checkout como visitante (guest checkout)</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="hidden" name="guest_checkout" value="0">
                            <input type="checkbox" name="guest_checkout" value="1" <?= cfg($pdo,'loja_guest_checkout','0')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="config-section-title">Avaliações</div>

                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Ativar Avaliações de Produtos</strong>
                            <span>Clientes podem avaliar produtos após a compra</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="hidden" name="avaliacoes_ativas" value="0">
                            <input type="checkbox" name="avaliacoes_ativas" value="1" <?= cfg($pdo,'loja_avaliacoes_ativas','1')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Aprovar Avaliações Manualmente</strong>
                            <span>Avaliações ficam pendentes até aprovação do admin</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="hidden" name="moderar_avaliacoes" value="0">
                            <input type="checkbox" name="moderar_avaliacoes" value="1" <?= cfg($pdo,'loja_moderar_avaliacoes','1')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="config-section-title">Política de Devoluções</div>
                    <div class="field-group triple">
                        <div class="input-group">
                            <label>Prazo para Devolução (dias)</label>
                            <input type="number" name="prazo_devolucao" value="<?= cfg($pdo,'loja_prazo_devolucao','7') ?>" min="1" max="30">
                        </div>
                        <div class="input-group">
                            <label>Alerta de Estoque Baixo (un.)</label>
                            <input type="number" name="alerta_estoque" value="<?= cfg($pdo,'loja_alerta_estoque','3') ?>" min="1">
                            <small>Notifica admin quando estoque ≤ este valor</small>
                        </div>
                        <div class="input-group">
                            <label>Itens por Página (listagem)</label>
                            <input type="number" name="itens_por_pagina" value="<?= cfg($pdo,'loja_itens_por_pagina','12') ?>" min="4" max="48">
                        </div>
                    </div>

                    <div class="save-bar">
                        <button type="submit" class="btn-admin-primary">💾 Salvar Configurações da Loja</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- ── E-MAILS ── -->
            <?php if($secao_ativa === 'emails'): ?>
            <div class="admin-card config-panel active">
                <div class="card-header"><span class="card-title">📧 Configurações de E-mail</span></div>

                <div class="info-box">
                    Configure o servidor SMTP para envio de e-mails automáticos. Recomendamos o uso de serviços como SendGrid, Mailgun ou o próprio Gmail com senha de app.
                </div>

                <form method="POST">
                    <input type="hidden" name="secao" value="emails">

                    <div class="config-section-title">Servidor SMTP</div>
                    <div class="field-group">
                        <div class="input-group">
                            <label>Host SMTP</label>
                            <input type="text" name="smtp_host" value="<?= cfg($pdo,'emails_smtp_host','smtp.gmail.com') ?>">
                        </div>
                        <div class="input-group">
                            <label>Porta</label>
                            <input type="number" name="smtp_porta" value="<?= cfg($pdo,'emails_smtp_porta','587') ?>">
                        </div>
                    </div>
                    <div class="field-group">
                        <div class="input-group">
                            <label>Usuário / E-mail</label>
                            <input type="email" name="smtp_usuario" value="<?= cfg($pdo,'emails_smtp_usuario') ?>" placeholder="noreply@altojordao.com.br">
                        </div>
                        <div class="input-group">
                            <label>Senha / App Password</label>
                            <input type="password" name="smtp_senha" placeholder="••••••••••••">
                            <small>Deixe em branco para manter a senha atual</small>
                        </div>
                    </div>
                    <div class="field-group">
                        <div class="input-group">
                            <label>Nome do Remetente</label>
                            <input type="text" name="smtp_nome" value="<?= cfg($pdo,'emails_smtp_nome','Alto Jordão') ?>">
                        </div>
                        <div class="input-group">
                            <label>Criptografia</label>
                            <select name="smtp_cripto">
                                <option value="tls" <?= cfg($pdo,'emails_smtp_cripto','tls')==='tls'?'selected':''?>>TLS</option>
                                <option value="ssl" <?= cfg($pdo,'emails_smtp_cripto','tls')==='ssl'?'selected':''?>>SSL</option>
                                <option value="">Nenhuma</option>
                            </select>
                        </div>
                    </div>

                    <div class="config-section-title">E-mails Automáticos</div>

                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Confirmação de Pedido</strong>
                            <span>Envia e-mail ao cliente quando o pedido é criado</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="hidden" name="email_pedido" value="0">
                            <input type="checkbox" name="email_pedido" value="1" <?= cfg($pdo,'emails_email_pedido','1')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Notificação de Envio</strong>
                            <span>Avisa o cliente quando o pedido for despachado</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="hidden" name="email_envio" value="0">
                            <input type="checkbox" name="email_envio" value="1" <?= cfg($pdo,'emails_email_envio','1')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Recuperação de Senha</strong>
                            <span>Permite que clientes redefinam a senha por e-mail</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="hidden" name="email_senha" value="0">
                            <input type="checkbox" name="email_senha" value="1" <?= cfg($pdo,'emails_email_senha','1')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Alertas de Estoque Crítico para Admin</strong>
                            <span>Notifica o e-mail de suporte quando estoque baixar</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="hidden" name="email_estoque" value="0">
                            <input type="checkbox" name="email_estoque" value="1" <?= cfg($pdo,'emails_email_estoque','1')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="save-bar">
                        <button type="submit" class="btn-admin-primary">💾 Salvar Configurações de E-mail</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- ── PAGAMENTO ── -->
            <?php if($secao_ativa === 'pagamento'): ?>
            <div class="admin-card config-panel active">
                <div class="card-header"><span class="card-title">💳 Formas de Pagamento</span></div>

                <div class="info-box">
                    Ative ou desative métodos de pagamento e configure as credenciais de cada gateway. As chaves são armazenadas com segurança no banco de dados.
                </div>

                <form method="POST">
                    <input type="hidden" name="secao" value="pagamento">

                    <div class="config-section-title">Métodos Aceitos</div>

                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>PIX</strong>
                            <span>Aprovação imediata — sem taxas de processamento</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="hidden" name="pix_ativo" value="0">
                            <input type="checkbox" name="pix_ativo" value="1" <?= cfg($pdo,'pagamento_pix_ativo','1')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Cartão de Crédito</strong>
                            <span>Parcelamento em até 12x</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="hidden" name="cartao_ativo" value="0">
                            <input type="checkbox" name="cartao_ativo" value="1" <?= cfg($pdo,'pagamento_cartao_ativo','1')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Boleto Bancário</strong>
                            <span>Prazo de compensação: 1-3 dias úteis</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="hidden" name="boleto_ativo" value="0">
                            <input type="checkbox" name="boleto_ativo" value="1" <?= cfg($pdo,'pagamento_boleto_ativo','1')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="config-section-title">Configurações Financeiras</div>
                    <div class="field-group triple">
                        <div class="input-group">
                            <label>Desconto PIX (%)</label>
                            <input type="number" step="0.1" name="pix_desconto" value="<?= cfg($pdo,'pagamento_pix_desconto','5') ?>" min="0" max="30">
                            <small>Desconto aplicado em pagamentos via PIX</small>
                        </div>
                        <div class="input-group">
                            <label>Máx. Parcelas Cartão</label>
                            <input type="number" name="cartao_parcelas" value="<?= cfg($pdo,'pagamento_cartao_parcelas','12') ?>" min="1" max="24">
                        </div>
                        <div class="input-group">
                            <label>Mín. para Parcelamento (R$)</label>
                            <input type="number" step="0.01" name="cartao_min_parcela" value="<?= cfg($pdo,'pagamento_cartao_min_parcela','30') ?>">
                        </div>
                    </div>

                    <div class="config-section-title">Gateway de Pagamento</div>
                    <div class="field-group">
                        <div class="input-group">
                            <label>Gateway</label>
                            <select name="gateway">
                                <option value="mercadopago" <?= cfg($pdo,'pagamento_gateway')==='mercadopago'?'selected':''?>>Mercado Pago</option>
                                <option value="pagarme"     <?= cfg($pdo,'pagamento_gateway')==='pagarme'    ?'selected':''?>>Pagar.me</option>
                                <option value="stripe"      <?= cfg($pdo,'pagamento_gateway')==='stripe'     ?'selected':''?>>Stripe</option>
                                <option value="manual"      <?= cfg($pdo,'pagamento_gateway')==='manual'     ?'selected':''?>>Manual</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Chave Pública (Public Key)</label>
                            <input type="text" name="gateway_pub_key" value="<?= cfg($pdo,'pagamento_gateway_pub_key') ?>" placeholder="pk_test_...">
                        </div>
                    </div>
                    <div class="field-group single">
                        <div class="input-group">
                            <label>Chave Secreta (Secret Key)</label>
                            <input type="password" name="gateway_sec_key" placeholder="••••••••••••">
                            <small>Deixe em branco para manter a chave atual</small>
                        </div>
                    </div>

                    <div class="save-bar">
                        <button type="submit" class="btn-admin-primary">💾 Salvar Configurações de Pagamento</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- ── FRETE ── -->
            <?php if($secao_ativa === 'frete'): ?>
            <div class="admin-card config-panel active">
                <div class="card-header"><span class="card-title">📦 Frete e Logística</span></div>
                <form method="POST" id="formTesteME" style="display:none;"><input type="hidden" name="testar_melhorenvio" value="1"><input type="hidden" name="csrf" value="<?= htmlspecialchars(fin_csrf_token()) ?>"></form>
                <form method="POST">
                    <input type="hidden" name="secao" value="frete">

                    <div class="config-section-title">Regras Gerais</div>

                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Frete Grátis Ativo</strong>
                            <span>Aplica frete grátis acima do valor mínimo definido</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="hidden" name="frete_gratis_ativo" value="0">
                            <input type="checkbox" name="frete_gratis_ativo" value="1" <?= cfg($pdo,'frete_frete_gratis_ativo','1')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="field-group triple">
                        <div class="input-group">
                            <label>Valor Mínimo Frete Grátis (R$)</label>
                            <input type="number" step="0.01" name="frete_gratis_minimo" value="<?= cfg($pdo,'frete_frete_gratis_minimo','199') ?>">
                        </div>
                        <div class="input-group">
                            <label>CEP de Origem</label>
                            <input type="text" name="cep_origem" value="<?= cfg($pdo,'frete_cep_origem') ?>" placeholder="00000-000">
                        </div>
                        <div class="input-group">
                            <label>Prazo do Frete Fixo (dias úteis)</label>
                            <input type="number" name="prazo_padrao" value="<?= cfg($pdo,'frete_prazo_padrao','5') ?>" min="1">
                        </div>
                    </div>

                    <div class="config-section-title">Formas de entrega oferecidas no checkout</div>
                    <?php
                        $uf_origem_cfg = frete_uf_do_cep((string)cfg($pdo,'frete_cep_origem',''));
                    ?>
                    <?php if (!$uf_origem_cfg): ?>
                    <div style="background:#fff8e1;border:1px solid #ffe082;color:#8a6100;border-radius:12px;padding:12px 14px;font-size:13px;margin-bottom:14px;">
                        ⚠ Informe um <strong>CEP de origem</strong> válido acima. Sem ele, todo pedido é cotado como "outras regiões" (a zona mais cara).
                    </div>
                    <?php else: ?>
                    <p style="font-size:12px;color:#888;margin:0 0 12px;">Origem: <strong><?= $uf_origem_cfg ?></strong>. O frete é calculado no servidor pelo CEP do cliente, usando a tabela abaixo.</p>
                    <?php endif; ?>

                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Correios (PAC e SEDEX)</strong>
                            <span>Oferece as duas opções, com preço da tabela abaixo</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="hidden" name="correios_ativo" value="0">
                            <input type="checkbox" name="correios_ativo" value="1" <?= cfg($pdo,'frete_correios_ativo','1')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Jadlog</strong>
                            <span>Oferece a opção Jadlog, com preço da tabela abaixo</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="hidden" name="jadlog_ativo" value="0">
                            <input type="checkbox" name="jadlog_ativo" value="1" <?= cfg($pdo,'frete_jadlog_ativo','0')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Frete Fixo</strong>
                            <span>Adiciona uma opção com valor fixo, para qualquer CEP</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="hidden" name="frete_fixo_ativo" value="0">
                            <input type="checkbox" name="frete_fixo_ativo" value="1" <?= cfg($pdo,'frete_frete_fixo_ativo','0')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="field-group">
                        <div class="input-group">
                            <label>Valor do Frete Fixo (R$)</label>
                            <input type="number" step="0.01" min="0" name="frete_fixo_valor" value="<?= cfg($pdo,'frete_frete_fixo_valor','15') ?>">
                        </div>
                        <div class="input-group">
                            <label>Peso médio por peça (kg)</label>
                            <input type="number" step="0.01" min="0.05" name="peso_item_kg" value="<?= cfg($pdo,'frete_peso_item_kg', (string)FRETE_PESO_PADRAO_KG) ?>">
                            <small>Peso do pedido = peças × este valor (mínimo 0,3 kg).</small>
                        </div>
                    </div>
                    <div class="field-group">
                        <div class="input-group">
                            <label>Código de Rastreio URL</label>
                            <input type="text" name="rastreio_url" value="<?= cfg($pdo,'frete_rastreio_url','https://rastreamento.correios.com.br') ?>" placeholder="URL do rastreamento">
                        </div>
                    </div>

                    <div class="config-section-title">Cotação real — Melhor Envio</div>
                    <?php
                        $me_token = MelhorEnvio::configurado();
                        $me_teste = $_SESSION['me_teste'] ?? null; unset($_SESSION['me_teste']);
                    ?>
                    <div style="background:<?= $me_token ? '#e8f5e9' : '#fff8e1' ?>;border-radius:12px;padding:12px 14px;font-size:13px;margin-bottom:14px;">
                        <?php if ($me_token): ?>
                            ✔ Token encontrado no <code>.env</code> — ambiente: <strong><?= MelhorEnvio::sandbox() ? 'SANDBOX (testes)' : 'PRODUÇÃO' ?></strong>.
                        <?php else: ?>
                            ⚠ Token não configurado. Adicione <code>MELHORENVIO_TOKEN</code>, <code>MELHORENVIO_SANDBOX</code> e <code>MELHORENVIO_EMAIL</code> ao arquivo <code>.env</code> (veja <code>.env.example</code>). O token nunca é guardado no banco nem no GitHub.
                        <?php endif; ?>
                    </div>

                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Usar cotação real do Melhor Envio</strong>
                            <span>Preço e prazo vêm da API com peso/medidas dos produtos. Se a API falhar, usa a tabela abaixo.</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="hidden" name="melhorenvio_ativo" value="0">
                            <input type="checkbox" name="melhorenvio_ativo" value="1" <?= cfg($pdo,'frete_melhorenvio_ativo','0')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Oferecer também outras transportadoras do Melhor Envio</strong>
                            <span>Além de Correios e Jadlog (que seguem os interruptores acima), ex.: Azul Cargo, LATAM Cargo.</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="hidden" name="melhorenvio_outras" value="0">
                            <input type="checkbox" name="melhorenvio_outras" value="1" <?= cfg($pdo,'frete_melhorenvio_outras','0')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="field-group triple">
                        <div class="input-group">
                            <label>Medidas padrão — largura (cm)</label>
                            <input type="number" step="0.1" min="11" name="dim_largura_cm" value="<?= cfg($pdo,'frete_dim_largura_cm',(string)FRETE_DIM_PADRAO['largura_cm']) ?>">
                        </div>
                        <div class="input-group">
                            <label>altura (cm)</label>
                            <input type="number" step="0.1" min="2" name="dim_altura_cm" value="<?= cfg($pdo,'frete_dim_altura_cm',(string)FRETE_DIM_PADRAO['altura_cm']) ?>">
                        </div>
                        <div class="input-group">
                            <label>comprimento (cm)</label>
                            <input type="number" step="0.1" min="16" name="dim_comprimento_cm" value="<?= cfg($pdo,'frete_dim_comprimento_cm',(string)FRETE_DIM_PADRAO['comprimento_cm']) ?>">
                            <small>Para produtos sem peso/medidas cadastrados. Mínimos dos Correios: 11 × 2 × 16 cm.</small>
                        </div>
                    </div>

                    <?php if ($me_teste): ?>
                    <div style="border:1px solid #eee;border-radius:12px;padding:12px 14px;font-size:13px;margin-bottom:14px;">
                        <strong>Teste de conexão (<?= htmlspecialchars($me_teste['quando']) ?>, <?= $me_teste['sandbox'] ? 'sandbox' : 'produção' ?>):</strong>
                        <?php if ($me_teste['ok']): ?>
                            <span style="color:#2e7d32;">✔ API respondeu com <?= count($me_teste['servicos']) ?> serviço(s) com preço</span> (origem → 01001-000, caixa padrão):
                            <ul style="margin:6px 0 0 18px;">
                            <?php foreach ($me_teste['servicos'] as $sv): ?>
                                <li><?= htmlspecialchars($sv['nome']) ?> — R$ <?= number_format($sv['preco'], 2, ',', '.') ?> — <?= (int)$sv['prazo'] ?> dia(s)</li>
                            <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <span style="color:#c62828;">✖ <?= htmlspecialchars($me_teste['erro']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <button type="submit" form="formTesteME" class="btn-admin-ghost" style="margin-bottom:18px;">🔌 Testar conexão com o Melhor Envio</button>

                    <div class="config-section-title">Tabela de preços (valores de exemplo — ajuste aos seus contratos)</div>
                    <p style="font-size:12px;color:#888;margin:0 0 10px;">
                        Preço = <em>base</em> (até 1 kg) + <em>adicional</em> × (kg do pedido arredondado para cima − 1).
                        Zonas: <strong>1</strong> mesmo estado da origem · <strong>2</strong> mesma região do país · <strong>3</strong> demais regiões.
                        Frete grátis, quando ativo, zera só a opção mais barata.
                    </p>
                    <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead><tr style="text-align:left;font-size:11px;text-transform:uppercase;color:#888;">
                            <th style="padding:6px;">Serviço</th><th style="padding:6px;">Zona</th>
                            <th style="padding:6px;">Base (R$)</th><th style="padding:6px;">+ por kg extra (R$)</th><th style="padding:6px;">Prazo (dias úteis)</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach (FRETE_SERVICOS as $sid => $sv): foreach ([1,2,3] as $z): $pd = FRETE_TABELA_PADRAO[$sid][$z]; ?>
                            <tr style="border-top:1px solid #eee;">
                                <td style="padding:6px;font-weight:700;"><?= htmlspecialchars($sv['nome']) ?></td>
                                <td style="padding:6px;"><?= $z ?></td>
                                <td style="padding:6px;"><input type="number" step="0.01" min="0" style="width:100px;" name="tab_<?= $sid ?>_z<?= $z ?>_base" value="<?= htmlspecialchars(cfg($pdo,"frete_tab_{$sid}_z{$z}_base",(string)$pd[0])) ?>"></td>
                                <td style="padding:6px;"><input type="number" step="0.01" min="0" style="width:100px;" name="tab_<?= $sid ?>_z<?= $z ?>_kg" value="<?= htmlspecialchars(cfg($pdo,"frete_tab_{$sid}_z{$z}_kg",(string)$pd[1])) ?>"></td>
                                <td style="padding:6px;"><input type="number" step="1" min="1" style="width:80px;" name="tab_<?= $sid ?>_z<?= $z ?>_prazo" value="<?= htmlspecialchars(cfg($pdo,"frete_tab_{$sid}_z{$z}_prazo",(string)$pd[2])) ?>"></td>
                            </tr>
                        <?php endforeach; endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <p style="font-size:11px;color:#888;margin-top:10px;">
                        Quer o preço oficial? Isso exige contrato/credenciais dos Correios ou da Jadlog (API). Esta tabela é a estimativa própria da loja.
                    </p>

                    <div class="save-bar">
                        <button type="submit" class="btn-admin-primary">💾 Salvar Configurações de Frete</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- ── FINANCEIRO (somente superadmin) ── -->
            <?php if($secao_ativa === 'financeiro' && $eh_superadmin): ?>
            <div class="admin-card config-panel active">
                <div class="card-header"><span class="card-title">🧾 Financeiro — Impostos, Taxas e Custos</span></div>

                <div style="background:#fff8e1;border:1px solid #ffe082;color:#b45309;border-radius:14px;padding:14px 18px;font-size:13px;line-height:1.6;margin-bottom:22px;">
                    <strong>Valores de exemplo.</strong> Os números abaixo vêm preenchidos só para você ter por onde começar.
                    Ajuste de acordo com o seu regime tributário e o contrato com cada gateway — eles alimentam o cálculo de lucro do Dashboard e do Relatório (DRE).
                    Pedidos novos gravam a taxa e o imposto no momento da compra; mudar um valor aqui <u>não</u> reescreve o histórico já gravado.
                </div>

                <style>
                    .fin-taxas { width:100%; border-collapse:collapse; margin-bottom:22px; font-size:13px; }
                    .fin-taxas th { font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1px; text-align:left; padding:8px 6px; }
                    .fin-taxas td { padding:6px; }
                    .fin-taxas td:first-child { font-weight:800; white-space:nowrap; }
                    .fin-taxas input { width:100%; padding:11px 12px; border:1px solid var(--border); border-radius:10px; font-size:13px; }
                    .fin-taxas .gw-head { border-bottom:2px solid var(--border); text-align:center; }
                    @media (max-width:760px){ .fin-taxas { display:block; overflow-x:auto; } }
                </style>

                <form method="POST">
                    <input type="hidden" name="secao" value="financeiro">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(fin_csrf_token()) ?>">

                    <div class="config-section-title">Impostos sobre a venda</div>
                    <div class="field-group">
                        <div class="input-group">
                            <label>Regime / nome do imposto</label>
                            <input type="text" name="imposto_nome" maxlength="60" value="<?= htmlspecialchars(fin_cfg($pdo,'imposto_nome')) ?>">
                            <small>Apenas um rótulo para os relatórios (ex.: Simples Nacional, MEI, Lucro Presumido).</small>
                        </div>
                        <div class="input-group">
                            <label>Alíquota efetiva sobre o faturamento (%)</label>
                            <input type="number" step="0.01" min="0" max="100" name="imposto_percentual" value="<?= htmlspecialchars(fin_cfg($pdo,'imposto_percentual')) ?>">
                            <small>Aplicada sobre o valor cobrado do cliente em cada pedido pago. Peça ao seu contador a alíquota efetiva.</small>
                        </div>
                    </div>

                    <div class="config-section-title">Taxas dos gateways de pagamento</div>
                    <?php $gws = ['mp' => 'Mercado Pago', 'ps' => 'PagBank (PagSeguro)'];
                          $mets = ['pix' => '⚡ PIX', 'cartao' => '💳 Cartão de crédito', 'boleto' => '🏦 Boleto']; ?>
                    <table class="fin-taxas">
                        <thead>
                            <tr><th></th><?php foreach($gws as $nome): ?><th colspan="2" class="gw-head"><?= $nome ?></th><?php endforeach; ?></tr>
                            <tr><th>Forma de pagamento</th><?php foreach($gws as $_): ?><th>% do valor</th><th>R$ fixo</th><?php endforeach; ?></tr>
                        </thead>
                        <tbody>
                        <?php foreach($mets as $m => $mnome): ?>
                            <tr>
                                <td><?= $mnome ?></td>
                                <?php foreach($gws as $g => $_): ?>
                                <td><input type="number" step="0.01" min="0" max="100"    name="taxa_<?= $g ?>_<?= $m ?>_pct"  value="<?= htmlspecialchars(fin_cfg($pdo,"taxa_{$g}_{$m}_pct")) ?>"></td>
                                <td><input type="number" step="0.01" min="0" max="100000" name="taxa_<?= $g ?>_<?= $m ?>_fixa" value="<?= htmlspecialchars(fin_cfg($pdo,"taxa_{$g}_{$m}_fixa")) ?>"></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="config-section-title">Custos por pedido</div>
                    <div class="field-group">
                        <div class="input-group">
                            <label>Embalagem por pedido (R$)</label>
                            <input type="number" step="0.01" min="0" name="embalagem_pedido" value="<?= htmlspecialchars(fin_cfg($pdo,'embalagem_pedido')) ?>">
                            <small>Caixa, fita, etiqueta, enchimento etc.</small>
                        </div>
                        <div class="input-group">
                            <label>Custo médio de frete — estimativa (R$)</label>
                            <input type="number" step="0.01" min="0" name="frete_custo_medio" value="<?= htmlspecialchars(fin_cfg($pdo,'frete_custo_medio')) ?>">
                            <small>Usado enquanto o custo real (Correios/Jadlog) não é informado no pedido. Coloque 0 para assumir que o custo é igual ao frete cobrado.</small>
                        </div>
                    </div>

                    <div class="config-section-title">Devoluções</div>
                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Produto devolvido volta ao estoque</strong>
                            <span>Ligado: o custo do produto devolvido é recuperado no cálculo. Desligado: conta como perda (mais conservador).</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="hidden" name="devolucao_recupera_custo" value="0">
                            <input type="checkbox" name="devolucao_recupera_custo" value="1" <?= fin_cfg($pdo,'devolucao_recupera_custo')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="save-bar">
                        <button type="submit" class="btn-admin-primary">💾 Salvar Configurações Financeiras</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- ── SEGURANÇA ── -->
            <?php if($secao_ativa === 'seguranca'): ?>
            <div class="admin-card config-panel active">
                <div class="card-header"><span class="card-title">🔒 Segurança</span></div>
                <form method="POST">
                    <input type="hidden" name="secao" value="seguranca">

                    <div class="config-section-title">Sessão e Acesso</div>
                    <div class="field-group triple">
                        <div class="input-group">
                            <label>Timeout de Sessão (min)</label>
                            <input type="number" name="session_timeout" value="<?= cfg($pdo,'seguranca_session_timeout','120') ?>" min="15">
                            <small>Logout automático após inatividade</small>
                        </div>
                        <div class="input-group">
                            <label>Máx. Tentativas de Login</label>
                            <input type="number" name="max_tentativas" value="<?= cfg($pdo,'seguranca_max_tentativas','5') ?>" min="3">
                            <small>Bloqueia após N tentativas falhas</small>
                        </div>
                        <div class="input-group">
                            <label>Bloqueio por (min)</label>
                            <input type="number" name="bloqueio_min" value="<?= cfg($pdo,'seguranca_bloqueio_min','30') ?>" min="5">
                        </div>
                    </div>

                    <div class="config-section-title">Política de Senha</div>
                    <div class="field-group">
                        <div class="input-group">
                            <label>Mínimo de Caracteres</label>
                            <input type="number" name="senha_min_chars" value="<?= cfg($pdo,'seguranca_senha_min_chars','8') ?>" min="6">
                        </div>
                        <div class="input-group">
                            <label>Expiração de Senha (dias)</label>
                            <input type="number" name="senha_expiracao" value="<?= cfg($pdo,'seguranca_senha_expiracao','0') ?>" min="0">
                            <small>0 = nunca expira</small>
                        </div>
                    </div>

                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Autenticação em Dois Fatores (2FA)</strong>
                            <span>Exige código por e-mail no login de admins</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="hidden" name="2fa_ativo" value="0">
                            <input type="checkbox" name="2fa_ativo" value="1" <?= cfg($pdo,'seguranca_2fa_ativo','0')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Log de Acessos</strong>
                            <span>Registra todos os logins no painel de auditoria</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="hidden" name="log_acessos" value="0">
                            <input type="checkbox" name="log_acessos" value="1" <?= cfg($pdo,'seguranca_log_acessos','1')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="save-bar">
                        <button type="submit" class="btn-admin-primary">💾 Salvar Configurações de Segurança</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- ── SISTEMA ── -->
            <?php if($secao_ativa === 'sistema'): ?>
            <div class="admin-card config-panel active">
                <div class="card-header"><span class="card-title">⚙️ Sistema</span></div>

                <div class="config-section-title">Informações do Ambiente</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:24px;">
                    <?php
                    $infos = [
                        'PHP Version'   => PHP_VERSION,
                        'Servidor'      => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
                        'Banco de Dados'=> 'MySQL / MariaDB',
                        'Charset'       => 'utf8mb4',
                        'Timezone'      => date_default_timezone_get(),
                        'Memória PHP'   => ini_get('memory_limit'),
                        'Upload Máx.'   => ini_get('upload_max_filesize'),
                        'Ambiente'      => 'Produção',
                    ];
                    foreach($infos as $k => $v):
                    ?>
                    <div style="background:var(--grey-bg); padding:14px 18px; border-radius:14px;">
                        <div style="font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;"><?= $k ?></div>
                        <div style="font-weight:700; font-size:13px; font-family:monospace;"><?= $v ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="config-section-title">Manutenção</div>

                <div class="toggle-row" style="margin-bottom:20px;">
                    <div class="toggle-info">
                        <strong>Modo Manutenção</strong>
                        <span>Exibe página de manutenção para visitantes da loja</span>
                    </div>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="secao" value="sistema">
                        <label class="toggle-switch">
                            <input type="hidden" name="modo_manutencao" value="0">
                            <input type="checkbox" name="modo_manutencao" value="1"
                                   <?= cfg($pdo,'sistema_modo_manutencao','0')==='1'?'checked':''?>
                                   onchange="this.form.submit()">
                            <span class="toggle-slider"></span>
                        </label>
                    </form>
                </div>

                <div class="config-section-title">Zona de Perigo</div>
                <div class="danger-zone">
                    <h3>⚠️ Limpar Cache do Sistema</h3>
                    <p>Remove arquivos temporários e força a reconstrução do cache. Não afeta dados de clientes ou pedidos.</p>
                    <button class="btn-danger" onclick="return confirm('Limpar cache?')">🗑️ Limpar Cache</button>
                </div>

                <div class="danger-zone" style="margin-top:16px;">
                    <h3>⚠️ Exportar Dados (LGPD)</h3>
                    <p>Exporta todos os dados de clientes em formato CSV para fins de conformidade com a LGPD.</p>
                    <a href="admin_relatorios.php?tipo=clientes" class="btn-danger" style="text-decoration:none; display:inline-block;">📥 Exportar Clientes</a>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /painel -->
    </div><!-- /config-layout -->

</main>

<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>