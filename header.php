<?php
// Previne erro caso a sessão não tenha sido iniciada no arquivo pai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<div class="utility-nav">
    <div class="nav-links">
        <a href="#">Ajuda</a> <span>|</span>
        
        <?php if (isset($_SESSION['usuario_id'])): ?>
            <span style="color: #666; font-size: 12px;">Oi, <strong><?php echo htmlspecialchars(explode(' ', $_SESSION['usuario_nome'])[0]); ?></strong></span>
            <span>|</span>
            <a href="logout.php" style="color: #ff4d4d; font-weight: bold;">Sair</a>
        <?php else: ?>
            <a href="cadastro.php">Junte-se a nós</a> <span>|</span>
            <a href="login.php">Entrar</a>
        <?php endif; ?>
    </div>
</div>

<header>
    <div class="logo" onclick="location.href='index.php'" style="cursor: pointer;">
        FASHION<span>SHOP</span>
    </div>
    
    <nav class="main-nav">
        <?php $paginaCorrente = basename($_SERVER['PHP_SELF']); ?>
        
        <a href="ofertas.php" class="<?= $paginaCorrente == 'ofertas.php' ? 'active' : '' ?>">Ofertas</a>
        <a href="lancamentos.php" class="<?= $paginaCorrente == 'lancamentos.php' ? 'active' : '' ?>">Lançamentos</a>
        <a href="masculino.php" class="<?= $paginaCorrente == 'masculino.php' ? 'active' : '' ?>">Masculino</a>
        <a href="feminino.php" class="<?= $paginaCorrente == 'feminino.php' ? 'active' : '' ?>">Feminino</a>

        <?php if (isset($_SESSION['usuario_nivel']) && $_SESSION['usuario_nivel'] === 'admin'): ?>
            <a href="admin.php" style="color: #d9534f; font-weight: bold; border-left: 1px solid #ddd; padding-left: 15px; margin-left: 10px;">
                ⚙️ ADMIN
            </a>
        <?php endif; ?>
    </nav>

    <div class="actions-nav">
        <button class="icon-btn" onclick="location.href='favoritos.php'" title="Meus Favoritos">❤️</button>
        <button class="icon-btn" id="openCart" onclick="abrirCarrinho()" title="Meu Carrinho" style="position: relative;">
            🛒
            <span id="cartCountBadge" style="display:none; position: absolute; top: -5px; right: -5px; background: #ff4d4d; color: white; font-size: 10px; padding: 2px 6px; border-radius: 50%; font-weight: bold;">0</span>
        </button>
    </div>
</header>

<div class="overlay" id="overlay" onclick="fecharTodosModais()"></div>

<aside class="sidebar" id="cartSidebar">
    <div class="sidebar-header">
        <h3>Meu Carrinho</h3>
        <button class="close-btn" id="closeCart" onclick="fecharTodosModais()">&times;</button>
    </div>
    
    <div id="cartListSide" class="cart-list-container" style="flex: 1; overflow-y: auto; padding: 15px;">
        <p style="text-align:center; color:#999; padding-top:20px;">Carregando itens...</p>
    </div>
    
    <div class="sidebar-footer" style="padding: 20px; border-top: 1px solid #eee; background: #fff;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <span style="font-weight: bold; color: #555;">TOTAL:</span>
            <span id="totalValor" style="font-size: 20px; font-weight: 900; color: #000;">R$ 0,00</span>
        </div>

        <button class="btn-checkout" onclick="location.href='carrinho.php'">
            FINALIZAR COMPRA
        </button>
        
        <p style="text-align: center; margin-top: 10px;">
            <a href="#" onclick="fecharTodosModais(); return false;" style="font-size: 12px; color: #999; text-decoration: none;">Continuar Comprando</a>
        </p>
    </div>
</aside>

<style>
/* Estilos Essenciais do Header e Sidebar */
.overlay { 
    position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
    background: rgba(0,0,0,0.5); z-index: 1500; 
    display: none; opacity: 0; transition: 0.3s; backdrop-filter: blur(4px);
}
.overlay.active { display: block; opacity: 1; }

.sidebar {
    position: fixed; top: 0; right: -100%; width: 100%; max-width: 400px; 
    height: 100vh; background: #fff; z-index: 2000; 
    transition: 0.3s cubic-bezier(0.23, 1, 0.32, 1); 
    box-shadow: -10px 0 40px rgba(0,0,0,0.1);
    display: flex; flex-direction: column;
}
.sidebar.active { right: 0; }

.sidebar-header { padding: 25px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
.close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: #999; }

.btn-checkout { 
    width: 100%; padding: 18px; background: #000; color: #fff; 
    border: none; font-weight: 800; border-radius: 4px; cursor: pointer; 
    text-transform: uppercase; letter-spacing: 1px;
}
</style>

<script>
    function abrirCarrinho() {
        const sidebar = document.getElementById('cartSidebar');
        const overlay = document.getElementById('overlay');
        if(sidebar && overlay) {
            sidebar.classList.add('active');
            overlay.classList.add('active');
            // Chama a função global de renderização do carrinho se existir no script.js
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

<script src="script.js?v=<?= time(); ?>"></script>