<?php 
require_once 'config.php'; 
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Meu Carrinho | FashionShop</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .cart-page { padding: 60px 5%; display: grid; grid-template-columns: 2fr 1fr; gap: 40px; }
        .cart-table { width: 100%; border-collapse: collapse; }
        .cart-table th { text-align: left; padding: 15px; border-bottom: 2px solid #000; text-transform: uppercase; font-size: 12px; }
        .cart-item { border-bottom: 1px solid #eee; }
        .cart-item td { padding: 20px 15px; }
        .prod-info { display: flex; align-items: center; gap: 15px; }
        .prod-info img, .prod-info .emoji { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; background: #f5f5f5; display: flex; align-items: center; justify-content: center; font-size: 30px; }
        .summary-box { background: #f9f9f9; padding: 30px; border-radius: 12px; height: fit-content; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 15px; font-weight: 600; }
        .btn-finish { width: 100%; padding: 20px; background: #000; color: #fff; border: none; font-weight: 800; border-radius: 8px; cursor: pointer; margin-top: 20px; }
        @media (max-width: 900px) { .cart-page { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<main class="cart-page">
    <div class="cart-items-section">
        <h2>Seu Carrinho</h2>
        <table class="cart-table">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Preço</th>
                    <th>Qtd</th>
                    <th>Subtotal</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="cartTableBody">
                </tbody>
        </table>
    </div>

    <div class="summary-box">
        <h3>Resumo do Pedido</h3>
        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ddd;">
        <div class="summary-row">
            <span>Subtotal</span>
            <span id="finalSubtotal">R$ 0,00</span>
        </div>
        <div class="summary-row">
            <span>Frete</span>
            <span style="color: green;">Grátis</span>
        </div>
        <div class="summary-row" style="font-size: 20px; margin-top: 20px; border-top: 2px solid #000; padding-top: 20px;">
            <span>TOTAL</span>
            <span id="finalTotal">R$ 0,00</span>
        </div>
        
        <button class="btn-finish" onclick="checkoutFinal()">FECHAR PEDIDO</button>
        <a href="index.php" style="display: block; text-align: center; margin-top: 15px; font-size: 13px; color: #777;">Continuar Comprando</a>
    </div>
</main>

<script>
// Lógica específica da página de checkout
function renderizarPaginaCarrinho() {
    const corpoTabela = document.getElementById('cartTableBody');
    const carrinho = JSON.parse(sessionStorage.getItem('fashion_cart')) || [];
    let total = 0;

    if (carrinho.length === 0) {
        corpoTabela.innerHTML = '<tr><td colspan="5" style="text-align:center; padding: 50px;">Seu carrinho está vazio.</td></tr>';
        return;
    }

    corpoTabela.innerHTML = carrinho.map(item => {
        const subtotal = item.preco * item.qtd;
        total += subtotal;
        const imgHtml = item.img.includes('/') ? `<img src="${item.img}">` : `<div class="emoji">${item.img}</div>`;

        return `
            <tr class="cart-item">
                <td>
                    <div class="prod-info">
                        ${imgHtml}
                        <div>
                            <strong style="display:block;">${item.nome}</strong>
                            <small style="color: #888;">${item.opcoes}</small>
                        </div>
                    </div>
                </td>
                <td>R$ ${item.preco.toLocaleString('pt-br', {minimumFractionDigits: 2})}</td>
                <td>${item.qtd}</td>
                <td><strong>R$ ${subtotal.toLocaleString('pt-br', {minimumFractionDigits: 2})}</strong></td>
                <td><button onclick="removerDoCarrinho('${item.cartId}'); location.reload();" style="background:none; border:none; color:red; cursor:pointer;">Remover</button></td>
            </tr>
        `;
    }).join('');

    document.getElementById('finalSubtotal').innerText = `R$ ${total.toLocaleString('pt-br', {minimumFractionDigits: 2})}`;
    document.getElementById('finalTotal').innerText = `R$ ${total.toLocaleString('pt-br', {minimumFractionDigits: 2})}`;
}

function checkoutFinal() {
    alert("Integração de pagamento (Mercado Pago / Stripe) seria o próximo passo!");
    // Aqui você enviaria o JSON do carrinho para o seu banco de dados via AJAX/Fetch
}

document.addEventListener('DOMContentLoaded', renderizarPaginaCarrinho);
</script>

</body>
</html>