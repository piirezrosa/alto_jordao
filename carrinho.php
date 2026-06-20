<?php 
require_once 'config.php'; 
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrinho | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
    <style>
        .cart-page { padding: 80px 5%; display: grid; grid-template-columns: 2fr 1fr; gap: 60px; max-width: 1400px; margin: 0 auto; }
        .cart-table { width: 100%; border-collapse: collapse; }
        .cart-table th { text-align: left; padding: 15px; border-bottom: 1px solid #000; text-transform: uppercase; font-size: 11px; letter-spacing: 2px; color: #888; }
        .cart-item { border-bottom: 1px solid #eee; }
        .cart-item td { padding: 30px 15px; vertical-align: middle; }
        
        .prod-info { display: flex; align-items: center; gap: 20px; }
        .prod-info img { width: 80px; height: 100px; object-fit: contain; background: #f9f9f9; border-radius: 4px; }
        
        .summary-box { background: #fff; padding: 40px; border: 1px solid #eee; border-radius: 0px; height: fit-content; position: sticky; top: 120px; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 20px; font-weight: 600; font-size: 14px; }
        
        .btn-finish { 
            width: 100%; padding: 20px; background: #000; color: #fff; border: none; 
            font-weight: 800; font-size: 12px; letter-spacing: 2px; cursor: pointer; 
            margin-top: 20px; text-transform: uppercase; transition: opacity 0.3s;
        }
        .btn-finish:hover { opacity: 0.8; }
        
        .remove-link { background:none; border:none; color:#bbb; cursor:pointer; font-size: 11px; text-decoration: underline; text-transform: uppercase; font-weight: 700; margin-top: 5px; }
        .remove-link:hover { color: #000; }

        @media (max-width: 900px) { .cart-page { grid-template-columns: 1fr; padding-top: 40px; } }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<main class="cart-page" style="min-height: 70vh;">
    <div class="cart-items-section">
        <h2 style="font-weight: 900; font-size: 2.2rem; letter-spacing: -1.5px; margin-bottom: 40px; text-transform: uppercase;">Sua Sacola</h2>
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
        <h3 style="font-weight: 900; text-transform: uppercase; font-size: 1.2rem; margin-bottom: 25px; letter-spacing: 1px;">Resumo do Pedido</h3>
        <div class="summary-row">
            <span style="color: #888;">Subtotal</span>
            <span id="finalSubtotal">R$ 0,00</span>
        </div>
        <div class="summary-row">
            <span style="color: #888;">Frete</span>
            <span style="color: #000; font-weight: 800;">CORTESIA</span>
        </div>
        <div class="summary-row" style="font-size: 1.5rem; margin-top: 30px; border-top: 1px solid #000; padding-top: 30px; font-weight: 900;">
            <span>TOTAL</span>
            <span id="finalTotal">R$ 0,00</span>
        </div>
        
        <button class="btn-finish" onclick="checkoutFinal()">Finalizar Curadoria</button>
        <a href="index.php" style="display: block; text-align: center; margin-top: 25px; font-size: 10px; color: #aaa; text-transform: uppercase; font-weight: 800; letter-spacing: 2px; text-decoration: none;">Continuar Explorando</a>
    </div>
</main>

<?php include 'header.php'; ?>

<script>
/**
 * Renderiza os itens na tabela da página de carrinho
 */
function renderizarPaginaCarrinho() {
    const corpoTabela = document.getElementById('cartTableBody');
    const carrinho = JSON.parse(sessionStorage.getItem('fashion_cart')) || [];
    let totalGeral = 0;

    if (carrinho.length === 0) {
        corpoTabela.innerHTML = '<tr><td colspan="5" style="text-align:center; padding: 100px 0; color: #ccc; font-weight: 700; text-transform: uppercase; letter-spacing: 2px;">A curadoria está vazia.</td></tr>';
        document.getElementById('finalSubtotal').innerText = "R$ 0,00";
        document.getElementById('finalTotal').innerText = "R$ 0,00";
        return;
    }

    corpoTabela.innerHTML = carrinho.map(item => {
        const subtotal = (parseFloat(item.preco) || 0) * (parseInt(item.qtd) || 1);
        totalGeral += subtotal;
                
        // Usa a função do script global se disponível, senão fallback básico
        const imgSrc = (typeof resolverCaminhoImagem === 'function') 
            ? resolverCaminhoImagem(item.img) 
            : (item.img.includes('/') ? item.img : `img/produtos/${item.img}`);

        return `
            <tr class="cart-item">
                <td>
                    <div class="prod-info">
                        <img src="${imgSrc}" onerror="this.src='https://via.placeholder.com/80x100?text=AJ'">
                        <div>
                            <strong style="display:block; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">${item.nome}</strong>
                            <small style="color: #bbb; text-transform: uppercase; font-size: 10px; font-weight: 700; display: block; margin-top: 4px;">${item.opcoes || 'Padrão'}</small>
                            <button class="remove-link" onclick="removerItemCarrinho('${item.cartId}')">Excluir</button>
                        </div>
                    </div>
                </td>
                <td style="font-weight: 600; font-size: 14px;">R$ ${item.preco.toLocaleString('pt-br', {minimumFractionDigits: 2})}</td>
                <td style="font-weight: 600; font-size: 14px;">${item.qtd}</td>
                <td><strong style="font-weight: 900; font-size: 14px;">R$ ${subtotal.toLocaleString('pt-br', {minimumFractionDigits: 2})}</strong></td>
            </tr>
        `;
    }).join('');

    document.getElementById('finalSubtotal').innerText = `R$ ${totalGeral.toLocaleString('pt-br', {minimumFractionDigits: 2})}`;
    document.getElementById('finalTotal').innerText = `R$ ${totalGeral.toLocaleString('pt-br', {minimumFractionDigits: 2})}`;
}

/**
 * Remove um item específico do carrinho e atualiza a tela
 */
function removerItemCarrinho(cartId) {
    let carrinho = JSON.parse(sessionStorage.getItem('fashion_cart')) || [];
    carrinho = carrinho.filter(item => item.cartId !== cartId);
    sessionStorage.setItem('fashion_cart', JSON.stringify(carrinho));
    renderizarPaginaCarrinho();
    
    // Atualiza badges do header se a função global de renderizar existir
    if(typeof renderizarCarrinho === 'function') {
        renderizarCarrinho();
    }
}

/**
 * FINALIZAÇÃO: Envia via POST para que o PHP possa processar e mostrar o quadro de sucesso
 */
function checkoutFinal() {
    const carrinho = JSON.parse(sessionStorage.getItem('fashion_cart')) || [];
    
    if (carrinho.length === 0) {
        alert("Sua curadoria está vazia.");
        return;
    }

    // Criar formulário invisível para disparar o redirecionamento real
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'processar_pedido.php';

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'itens_json';
    input.value = JSON.stringify(carrinho);

    form.appendChild(input);
    document.body.appendChild(form);
    
    console.log("Alto Jordão: Finalizando pedido...");
    form.submit(); // Redireciona para o quadro de sucesso
}

document.addEventListener('DOMContentLoaded', renderizarPaginaCarrinho);
</script>

</body>
</html>