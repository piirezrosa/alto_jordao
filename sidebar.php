<?php
// Proteção do arquivo
if (!defined('CONTEUDO_AUTORIZADO')) { exit('Acesso negado.'); }

// Se a conexão com o banco ($pdo) existir, buscamos os badges de forma centralizada
$sb_p_pendente      = 0;
$sb_devolucoes_pend = 0;
$sb_estoque_critico = 0;

if (isset($pdo)) {
    try {
        // Buscas rápidas apenas para alimentar os Badges da Sidebar
        $sb_p_pendente      = (int) $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();
        $sb_devolucoes_pend = (int) $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();
        $sb_estoque_critico = (int) $pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque<=3 AND ativo=1")->fetchColumn();
    } catch (PDOException $e) {
        // Silencia ou trata o erro para não quebrar o layout caso o banco falhe
    }
}

// Garante que a variável da página atual exista para evitar warnings
$pag_ativa = $pagina_atual ?? '';
?>

<aside class="admin-sidebar">
    <div class="sb-logo">ALTO JORDÃO</div>

    <div class="sb-section">
        <span class="sb-section-title">Visão Geral</span>
        <a href="admin_dashboard.php" class="sb-item <?= $pag_ativa === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
    </div>

    <div class="sb-section">
        <span class="sb-section-title">Vendas</span>
        <a href="admin_pedidos.php" class="sb-item <?= $pag_ativa === 'pedidos' ? 'active' : '' ?>">
            🛒 Pedidos
            <?php if($sb_p_pendente > 0): ?><span class="sb-badge"><?= $sb_p_pendente ?></span><?php endif; ?>
        </a>
        <a href="admin_vendas.php" class="sb-item <?= $pag_ativa === 'financeiro' ? 'active' : '' ?>">💰 Financeiro</a>
        <a href="entregas.php" class="sb-item <?= $pag_ativa === 'logistica' ? 'active' : '' ?>">📦 Logística</a>
        <a href="admin_devolucoes.php" class="sb-item <?= $pag_ativa === 'devolucoes' ? 'active' : '' ?>">
            🔄 Devoluções
            <?php if($sb_devolucoes_pend > 0): ?><span class="sb-badge"><?= $sb_devolucoes_pend ?></span><?php endif; ?>
        </a>
    </div>

    <div class="sb-section">
        <span class="sb-section-title">Catálogo</span>
        <a href="admin_produtos.php" class="sb-item <?= $pag_ativa === 'produtos' ? 'active' : '' ?>">👕 Produtos</a>
        <a href="admin_estoque.php" class="sb-item <?= $pag_ativa === 'estoque' ? 'active' : '' ?>">
            📋 Estoque
            <?php if($sb_estoque_critico > 0): ?><span class="sb-badge"><?= $sb_estoque_critico ?></span><?php endif; ?>
        </a>
        <a href="admin_categorias.php" class="sb-item <?= $pag_ativa === 'categorias' ? 'active' : '' ?>">🏷️ Categorias</a>
        <a href="admin_colecoes.php" class="sb-item <?= $pag_ativa === 'colecoes' ? 'active' : '' ?>">✨ Coleções</a>
        <a href="admin_marcas.php" class="sb-item <?= $pag_ativa === 'marcas' ? 'active' : '' ?>">🔖 Marcas</a>
        <a href="cadastrar_produto.php" class="sb-item <?= $pag_ativa === 'novo_produto' ? 'active' : '' ?>">➕ Novo Produto</a>
    </div>

    <div class="sb-section">
        <span class="sb-section-title">Usuários</span>
        <a href="admin_clientes.php" class="sb-item <?= $pag_ativa === 'clientes' ? 'active' : '' ?>">👥 Clientes</a>
        <a href="admin_admins.php" class="sb-item <?= $pag_ativa === 'admins' ? 'active' : '' ?>">🛡️ Administradores</a>
    </div>

    <div class="sb-section">
        <span class="sb-section-title">Marketing</span>
        <a href="admin_cupons.php" class="sb-item <?= $pag_ativa === 'cupons' ? 'active' : '' ?>">🎟️ Cupons</a>
        <a href="admin_avaliacoes.php" class="sb-item <?= $pag_ativa === 'avaliacoes' ? 'active' : '' ?>">⭐ Avaliações</a>
    </div>

    <div class="sb-section">
        <span class="sb-section-title">Sistema</span>
        <a href="admin_relatorios.php" class="sb-item <?= $pag_ativa === 'relatorios' ? 'active' : '' ?>">📈 Relatórios</a>
        <a href="admin_logs.php" class="sb-item <?= $pag_ativa === 'logs' ? 'active' : '' ?>">🔍 Logs & Auditoria</a>
        <a href="admin_configuracoes.php" class="sb-item <?= $pag_ativa === 'configuracoes' ? 'active' : '' ?>">⚙️ Configurações</a>
    </div>

    <div class="sb-footer">
        <div class="sb-user">
            <div class="sb-avatar"><?= strtoupper(substr($_SESSION['usuario_nome'] ?? 'A', 0, 1)) ?></div>
            <div class="sb-user-info">
                <small><?= strtoupper($_SESSION['usuario_nivel'] ?? 'admin') ?></small>
                <strong><?= explode(' ', $_SESSION['usuario_nome'] ?? 'Admin')[0] ?></strong>
            </div>
        </div>
        <a href="index.php" class="sb-item">🏪 Ver Loja</a>
        <a href="logout.php" class="sb-item" style="color: var(--danger, #ff4d4d);">🚪 Sair</a>
    </div>
</aside>