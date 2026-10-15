<?php
// Garante que a sessão esteja ativa para exibir o nome do usuário e nível de acesso
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<nav class="utility-nav">
    <div class="utility-container">
        <div class="utility-links">
            <a href="ajuda.php">Ajuda</a>            
            <?php if(isset($_SESSION['usuario_id'])): ?>
                <?php if(isset($_SESSION['usuario_nivel']) && $_SESSION['usuario_nivel'] === 'admin' || $_SESSION['usuario_nivel'] === 'superadmin'): ?>
                    <a href="admin_dashboard.php" style="margin-left: 15px; color: #ff4d4d; font-weight: 700; text-decoration: none;">⚙️ PAINEL ADMIN</a>
                <?php endif; ?>
                <span class="user-welcome" style="margin-left: 15px;">oi, <strong><?= explode(' ', $_SESSION['usuario_nome'])[0]; ?></strong></span>
            <?php endif; ?>
        </div>
    </div>
</nav>

<header>
    <div class="logo-container" onclick="window.location.href='index.php'">
        <span class="logo">ALTO JORDÃO</span>
    </div>

    <nav class="main-nav">
        <a href="ofertas.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ofertas.php' ? 'active' : '' ?>">Ofertas</a>
        <a href="lancamentos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'lancamentos.php' ? 'active' : '' ?>">Lançamentos</a>
        <a href="masculino.php" class="<?= basename($_SERVER['PHP_SELF']) == 'masculino.php' ? 'active' : '' ?>">Masculino</a>
        <a href="feminino.php" class="<?= basename($_SERVER['PHP_SELF']) == 'feminino.php' ? 'active' : '' ?>">Feminino</a>
        
        <a href="kids.php" class="<?= basename($_SERVER['PHP_SELF']) == 'kids.php' ? 'active' : '' ?>">Kids</a>
    </nav>

    <div class="header-actions">
        <div class="profile-menu-container">
            <a href="<?= isset($_SESSION['usuario_id']) ? 'usuario.php' : 'login.php' ?>" class="profile-link" title="Minha Conta">
                <span class="user-icon">👤</span>
            </a>
        </div>

        <button class="icon-btn" onclick="window.location.href='favoritos.php'" title="Meus Favoritos">❤️</button>

        <button class="icon-btn" onclick="abrirCarrinho()" title="Minha Sacola">
            🛒
            <span id="cartCountBadge" style="display: none;">0</span>
        </button>
    </div>
</header>

<div id="overlay" class="overlay" onclick="fecharTodosModais()"></div>

<div id="cartSidebar" class="sidebar">
    <div class="sidebar-header">
        <h3>Minha Sacola</h3>
        <button class="close-btn" onclick="fecharTodosModais()">&times;</button>
    </div>
    
    <div id="cartListSide" class="sidebar-content">
        </div>

    <div class="sidebar-footer">
        <div class="total-container">
            <span>Total:</span>
            <span id="totalValor">R$ 0,00</span>
        </div>
        <button class="btn-black-capsule" onclick="window.location.href='checkout.php'">FINALIZAR COMPRA</button>
    </div>
</div>