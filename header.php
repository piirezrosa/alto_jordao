<?php
// Previne erro caso a sessão não tenha sido iniciada no arquivo pai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<div class="utility-nav">
    <div class="utility-container">
        <div class="nav-links-left">
            <a href="#">Ajuda</a>
        </div>
        
        <div class="nav-links-right">
            <?php if (isset($_SESSION['usuario_id'])): ?>
                <span class="user-welcome">Oi, <strong><?php echo htmlspecialchars(explode(' ', $_SESSION['usuario_nome'])[0]); ?></strong></span>
                <span class="divider">|</span>
                <a href="logout.php" class="logout-link">Sair</a>
            <?php else: ?>
                <a href="cadastro.php">Junte-se a nós</a> 
                <span class="divider">|</span>
                <a href="login.php">Entrar</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<header>
    <div class="logo-container" onclick="location.href='index.php'">
        <div class="logo">FASHION<span>SHOP</span></div>
        <?php 
        // Exemplo: Mostrar badge apenas em lançamentos ou sempre
        if (basename($_SERVER['PHP_SELF']) == 'lancamentos.php') {
            echo '<span class="promo-badge">NOVO</span>';
        }
        ?>
    </div>
    
    <nav class="main-nav">
        <?php $paginaCorrente = basename($_SERVER['PHP_SELF']); ?>
        
        <a href="ofertas.php" class="<?= $paginaCorrente == 'ofertas.php' ? 'active' : '' ?>">Ofertas</a>
        <a href="lancamentos.php" class="<?= $paginaCorrente == 'lancamentos.php' ? 'active' : '' ?>">Lançamentos</a>
        <a href="masculino.php" class="<?= $paginaCorrente == 'masculino.php' ? 'active' : '' ?>">Masculino</a>
        <a href="feminino.php" class="<?= $paginaCorrente == 'feminino.php' ? 'active' : '' ?>">Feminino</a>

        <?php if (isset($_SESSION['usuario_nivel']) && $_SESSION['usuario_nivel'] === 'admin'): ?>
            <a href="admin.php" class="admin-link">⚙️ ADMIN</a>
        <?php endif; ?>
    </nav>

    <div class="actions-nav">
        <button class="icon-btn" onclick="location.href='favoritos.php'" title="Meus Favoritos">❤️</button>
        <button class="icon-btn" id="openCart" onclick="abrirCarrinho()" title="Meu Carrinho">
            🛒
            <span id="cartCountBadge">0</span>
        </button>
    </div>
</header>

<div class="overlay" id="overlay" onclick="fecharTodosModais()"></div>

<aside class="sidebar" id="cartSidebar">
    <div class="sidebar-header">
        <h3>Meu Carrinho</h3>
        <button class="close-btn" id="closeCart" onclick="fecharTodosModais()">&times;</button>
    </div>
    
    <div id="cartListSide" class="cart-list-container">
        <p class="cart-loading">Carregando itens...</p>
    </div>
    
    <div class="sidebar-footer">
        <div class="total-row">
            <span>TOTAL:</span>
            <span id="totalValor">R$ 0,00</span>
        </div>

        <button class="btn-checkout" onclick="location.href='carrinho.php'">
            FINALIZAR COMPRA
        </button>
        
        <button class="btn-continue" onclick="fecharTodosModais()">
            Continuar Comprando
        </button>
    </div>
</aside>

<script>
    function abrirCarrinho() {
        const sidebar = document.getElementById('cartSidebar');
        const overlay = document.getElementById('overlay');
        if(sidebar && overlay) {
            sidebar.classList.add('active');
            overlay.classList.add('active');
            if (typeof renderizarCarrinho === "function") renderizarCarrinho();
        }
    }

    function fecharTodosModais() {
        const sidebar = document.getElementById('cartSidebar');
        const overlay = document.getElementById('overlay');
        if(sidebar) sidebar.classList.remove('active');
        if(overlay) overlay.classList.remove('active');
    }
</script>