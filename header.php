<?php
// Inicia a sessão apenas se não houver uma ativa para evitar erros de duplicidade
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
                <span class="user-welcome">Oi, <strong><?php echo htmlspecialchars(explode(' ', $_SESSION['usuario_nome'] ?? 'Usuário')[0]); ?></strong></span>
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
    <div class="logo-container" onclick="location.href='index.php'" style="cursor:pointer;">
        <div class="logo">ALTO<span>JORDÃO</span></div>
        <?php 
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
            <a href="admin.php" class="admin-link" style="color: #d4af37; font-weight: 800; border: 1px solid #d4af37; padding: 6px 15px; border-radius: 30px; margin-left: 10px; text-decoration: none; font-size: 12px;">⚙️ ADMIN</a>
        <?php endif; ?>
    </nav>

    <div class="actions-nav">
        <button class="icon-btn" onclick="location.href='favoritos.php'" title="Meus Favoritos" style="position: relative; background: none; border: none; cursor: pointer;">
            <span id="favHeaderIcon">❤️</span>
        </button>
        
        <button class="icon-btn" id="openCart" onclick="abrirCarrinho()" title="Meu Carrinho" style="position: relative; background: none; border: none; cursor: pointer;">
            🛒
            <span id="cartCountBadge" style="display: none; position: absolute; top: -5px; right: -8px; background: #000; color: #fff; font-size: 10px; padding: 2px 6px; border-radius: 50%; font-weight: bold; border: 1px solid #fff;">0</span>
        </button>
    </div>
</header>

<div class="overlay" id="overlay" onclick="fecharTodosModais()"></div>

<aside class="sidebar" id="cartSidebar">
    <div class="sidebar-header" style="border-bottom: 1px solid #eee; padding: 20px; display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-weight: 800; letter-spacing: -0.5px; margin: 0;">Meu Carrinho</h3>
        <button class="close-btn" onclick="fecharTodosModais()" style="background:none; border:none; font-size:30px; cursor:pointer; line-height: 1;">&times;</button>
    </div>
    
    <div id="cartListSide" class="cart-list-container" style="padding: 20px; flex-grow: 1; overflow-y: auto;">
        <p class="cart-loading">Carregando itens...</p>
    </div>
    
    <div class="sidebar-footer" style="border-top: 1px solid #eee; padding: 25px; background: #fff;">
        <div class="total-row" style="display: flex; justify-content: space-between; margin-bottom: 20px; font-weight: 900; font-size: 18px;">
            <span>TOTAL:</span>
            <span id="totalValor">R$ 0,00</span>
        </div>

        <button class="btn-checkout" onclick="location.href='carrinho.php'" style="width: 100%; background: #000; color: #fff; border: none; padding: 18px; border-radius: 35px; font-weight: 800; cursor: pointer; transition: 0.3s; text-transform: uppercase; letter-spacing: 1px;">
            FINALIZAR COMPRA
        </button>
        
        <button class="btn-continue" onclick="fecharTodosModais()" style="background:none; border:none; color:#999; text-decoration:underline; width:100%; margin-top:15px; cursor:pointer; font-size:12px; font-weight: 500;">
            Continuar Comprando
        </button>
    </div>
</aside>