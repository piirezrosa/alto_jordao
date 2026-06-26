<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

// Apenas superadmin acessa configurações do sistema
if (!isset($_SESSION['usuario_nivel']) || !in_array($_SESSION['usuario_nivel'], ['admin','superadmin'])) {
    header("Location: login.php"); exit();
}

$msg = '';

// ── SALVAR CONFIGURAÇÕES ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['secao'])) {
    $secao = $_POST['secao'];

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

$secao_ativa = $_GET['secao'] ?? 'geral';

// Badges sidebar
$devolucoes_pend = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();
$estoque_critico = $pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque<=3 AND ativo=1")->fetchColumn();
$p_pendente_sb   = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();
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
<aside class="admin-sidebar">
    <div class="sb-logo">ALTO JORDÃO</div>
    <div class="sb-section">
        <span class="sb-section-title">Visão Geral</span>
        <a href="admin_dashboard.php" class="sb-item">📊 Dashboard</a>
    </div>
    <div class="sb-section">
        <span class="sb-section-title">Vendas</span>
        <a href="admin_pedidos.php" class="sb-item">🛒 Pedidos <?php if($p_pendente_sb>0): ?><span class="sb-badge"><?= $p_pendente_sb ?></span><?php endif; ?></a>
        <a href="admin_vendas.php"     class="sb-item">💰 Financeiro</a>
        <a href="entregas.php"         class="sb-item">📦 Logística</a>
        <a href="admin_devolucoes.php" class="sb-item">🔄 Devoluções <?php if($devolucoes_pend>0): ?><span class="sb-badge"><?= $devolucoes_pend ?></span><?php endif; ?></a>
    </div>
    <div class="sb-section">
        <span class="sb-section-title">Catálogo</span>
        <a href="admin_produtos.php"    class="sb-item">👕 Produtos</a>
        <a href="admin_estoque.php"     class="sb-item">📋 Estoque <?php if($estoque_critico>0): ?><span class="sb-badge"><?= $estoque_critico ?></span><?php endif; ?></a>
        <a href="admin_categorias.php"  class="sb-item">🏷️ Categorias</a>
        <a href="admin_colecoes.php"    class="sb-item">✨ Coleções</a>
        <a href="admin_marcas.php"      class="sb-item">🔖 Marcas</a>
        <a href="cadastrar_produto.php" class="sb-item">➕ Novo Produto</a>
    </div>
    <div class="sb-section">
        <span class="sb-section-title">Usuários</span>
        <a href="admin_clientes.php" class="sb-item">👥 Clientes</a>
        <a href="admin_admins.php"   class="sb-item">🛡️ Administradores</a>
    </div>
    <div class="sb-section">
        <span class="sb-section-title">Marketing</span>
        <a href="admin_cupons.php"     class="sb-item">🎟️ Cupons</a>
        <a href="admin_avaliacoes.php" class="sb-item">⭐ Avaliações</a>
    </div>
    <div class="sb-section">
        <span class="sb-section-title">Sistema</span>
        <a href="admin_relatorios.php"    class="sb-item">📈 Relatórios</a>
        <a href="admin_logs.php"          class="sb-item">🔍 Logs & Auditoria</a>
        <a href="admin_configuracoes.php" class="sb-item active">⚙️ Configurações</a>
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
                <form method="POST">
                    <input type="hidden" name="secao" value="frete">

                    <div class="config-section-title">Regras Gerais</div>

                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Frete Grátis Ativo</strong>
                            <span>Aplica frete grátis acima do valor mínimo definido</span>
                        </div>
                        <label class="toggle-switch">
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
                            <label>Prazo Padrão (dias úteis)</label>
                            <input type="number" name="prazo_padrao" value="<?= cfg($pdo,'frete_prazo_padrao','5') ?>" min="1">
                        </div>
                    </div>

                    <div class="config-section-title">Transportadoras</div>

                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Correios (PAC / SEDEX)</strong>
                            <span>Cálculo automático via API dos Correios</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="correios_ativo" value="1" <?= cfg($pdo,'frete_correios_ativo','1')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Jadlog</strong>
                            <span>Entrega expressa para capitais</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="jadlog_ativo" value="1" <?= cfg($pdo,'frete_jadlog_ativo','0')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="toggle-row">
                        <div class="toggle-info">
                            <strong>Frete Fixo</strong>
                            <span>Usa um valor fixo para todos os pedidos</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="frete_fixo_ativo" value="1" <?= cfg($pdo,'frete_frete_fixo_ativo','0')==='1'?'checked':''?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="field-group">
                        <div class="input-group">
                            <label>Valor do Frete Fixo (R$)</label>
                            <input type="number" step="0.01" name="frete_fixo_valor" value="<?= cfg($pdo,'frete_frete_fixo_valor','15') ?>">
                        </div>
                        <div class="input-group">
                            <label>Código de Rastreio URL</label>
                            <input type="text" name="rastreio_url" value="<?= cfg($pdo,'frete_rastreio_url','https://rastreamento.correios.com.br') ?>" placeholder="URL do rastreamento">
                        </div>
                    </div>

                    <div class="save-bar">
                        <button type="submit" class="btn-admin-primary">💾 Salvar Configurações de Frete</button>
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