<?php
// Cabeçalho, sacola lateral e navegação da loja (usado por todas as páginas do cliente)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$aj_pag   = basename($_SERVER['PHP_SELF']);
$aj_nivel = $_SESSION['usuario_nivel'] ?? '';
$aj_nav   = ['ofertas.php' => 'Ofertas', 'lancamentos.php' => 'Lançamentos', 'masculino.php' => 'Masculino', 'feminino.php' => 'Feminino', 'kids.php' => 'Kids'];
?>
<link rel="stylesheet" href="loja.css?v=<?= filemtime(__DIR__ . '/loja.css') ?>">

<div class="aj-header" role="banner">
    <div class="aj-header-in">
        <button class="aj-ico aj-burger" type="button" aria-label="Abrir menu" aria-expanded="false" onclick="ajMenu(this)">
            <svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>

        <a class="aj-logo" href="index.php">Alto Jordão</a>

        <nav class="aj-nav" id="ajNav" aria-label="Principal">
            <?php foreach ($aj_nav as $arq => $rotulo): ?>
                <a href="<?= $arq ?>" class="<?= $aj_pag === $arq ? 'active' : '' ?>"><?= $rotulo ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="aj-actions">
            <?php if (in_array($aj_nivel, ['admin', 'superadmin'], true)): ?>
                <a class="aj-admin-pill" href="admin_dashboard.php">Painel admin</a>
            <?php endif; ?>
            <?php if (isset($_SESSION['usuario_id'])): ?>
                <span class="aj-hello">Oi, <?= htmlspecialchars(explode(' ', $_SESSION['usuario_nome'] ?? '')[0]) ?></span>
            <?php endif; ?>
            <a class="aj-ico" href="<?= isset($_SESSION['usuario_id']) ? 'usuario.php' : 'login.php' ?>" aria-label="Minha conta">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg>
            </a>
            <a class="aj-ico" href="favoritos.php" aria-label="Favoritos">
                <svg viewBox="0 0 24 24"><path d="M12 20.5s-7.5-4.6-9.2-9.4C1.7 7.8 3.7 4.5 7 4.5c2 0 3.5 1.1 5 3 1.5-1.9 3-3 5-3 3.3 0 5.3 3.3 4.2 6.6-1.7 4.8-9.2 9.4-9.2 9.4z"/></svg>
            </a>
            <button class="aj-ico" type="button" onclick="abrirCarrinho()" aria-label="Abrir sacola">
                <svg viewBox="0 0 24 24"><path d="M5 8h14l-1 12H6L5 8z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/></svg>
                <span id="cartCountBadge" style="display:none">0</span>
            </button>
        </div>
    </div>
</div>

<div id="overlay" class="aj-overlay" onclick="fecharTodosModais()"></div>

<aside id="cartSidebar" class="aj-drawer" aria-label="Sacola">
    <div class="aj-drawer-head">
        <h3>Sacola</h3>
        <button class="aj-ico" type="button" onclick="fecharTodosModais()" aria-label="Fechar sacola">
            <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </div>
    <div id="cartListSide" class="aj-drawer-list"></div>
    <div class="aj-drawer-foot">
        <div class="aj-drawer-total"><span>Subtotal</span><span id="totalValor">R$ 0,00</span></div>
        <p class="aj-drawer-note">O frete é calculado no checkout, pelo seu CEP.</p>
        <button class="aj-btn aj-btn--block" type="button" onclick="window.location.href='checkout.php'">Finalizar compra</button>
    </div>
</aside>

<script>
function ajMenu(b) {
    const n = document.getElementById('ajNav'), a = n.classList.toggle('open');
    b.setAttribute('aria-expanded', a ? 'true' : 'false');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') { fecharTodosModais(); } });
</script>
