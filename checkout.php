<?php 
require_once 'config.php'; 
// Aqui você poderia verificar se o usuário está logado, caso tenha sistema de login
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Compra | Alto Jordão</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
    <style>
        .checkout-container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 5%;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 50px;
        }
        .checkout-form h2 { font-weight: 900; text-transform: uppercase; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 700; font-size: 13px; margin-bottom: 8px; text-transform: uppercase; }
        .form-group input { 
            width: 100%; padding: 15px; border: 1px solid var(--border); 
            border-radius: 8px; font-size: 15px; 
        }
        .resumo-pedido {
            background: var(--grey-light);
            padding: 30px;
            border-radius: 16px;
            height: fit-content;
        }
        .item-checkout {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }
        .item-checkout img { width: 60px; height: 80px; object-fit: cover; border-radius: 4px; }
        .item-info h4 { font-size: 14px; font-weight: 800; margin: 0; }
        .item-info p { font-size: 11px; color: var(--grey-text); margin: 4px 0; }
        
        @media (max-width: 850px) {
            .checkout-container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <main class="checkout-container">
        <section class="checkout-form">
            <h2>Finalizar Pedido</h2>
            <form action="processar_pedido.php" method="POST" id="formCheckout">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Nome Completo</label>
                        <input type="text" name="nome" required placeholder="Ex: Gustavo Geronimo">
                    </div>
                    <div class="form-group">
                        <label>E-mail</label>
                        <input type="email" name="email" required placeholder="seu@email.com">
                    </div>
                </div>

                <div class="form-group">
                    <label>Endereço de Entrega</label>
                    <input type="text" name="endereco" required placeholder="Rua, número, bairro...">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Cidade</label>
                        <input type="text" name="cidade" required value="Campos do Jordão">
                    </div>
                    <div class="form-group">
                        <label>CEP</label>
                        <input type="text" name="cep" required placeholder="00000-000">
                    </div>
                </div>

                <input type="hidden" name="carrinho_dados" id="inputCarrinhoDados">

                <button type="submit" class="btn-comprar-detalhe" style="margin-top: 20px;">
                    Confirmar e Pagar
                </button>
            </form>
        </section>

        <aside class="resumo-pedido">
            <h3 style="font-weight: 900; margin-bottom: 20px; text-transform: uppercase; font-size: 16px;">Seu Carrinho</h3>
            <div id="listaCheckout">
                </div>
            
            <div style="margin-top: 20px; border-top: 2px solid var(--black); padding-top: 20px;">
                <div style="display: flex; justify-content: space-between; font-weight: 900; font-size: 18px;">
                    <span>TOTAL</span>
                    <span id="totalCheckout">R$ 0,00</span>
                </div>
            </div>
        </aside>
    </main>

    <script src="script.js"></script>
    <script>
        // Ao carregar a página, preenche o resumo do checkout
        document.addEventListener('DOMContentLoaded', () => {
            const lista = document.getElementById('listaCheckout');
            const totalTxt = document.getElementById('totalCheckout');
            const inputDados = document.getElementById('inputCarrinhoDados');
            
            const carrinho = JSON.parse(sessionStorage.getItem('fashion_cart')) || [];
            
            if(carrinho.length === 0) {
                alert("Seu carrinho está vazio!");
                window.location.href = "index.php";
                return;
            }

            // Coloca o JSON do carrinho no input hidden para o PHP ler depois
            inputDados.value = JSON.stringify(carrinho);

            let total = 0;
            lista.innerHTML = carrinho.map(item => {
                total += (item.preco * item.qtd);
                return `
                    <div class="item-checkout">
                        <img src="${item.img}" onerror="this.src='img/placeholder.jpg'">
                        <div class="item-info">
                            <h4>${item.nome}</h4>
                            <p>${item.opcoes}</p>
                            <span style="font-weight:700;">${item.qtd}x R$ ${item.preco.toLocaleString('pt-br', {minimumFractionDigits: 2})}</span>
                        </div>
                    </div>
                `;
            }).join('');

            totalTxt.innerText = `R$ ${total.toLocaleString('pt-br', {minimumFractionDigits: 2})}`;
        });
    </script>
</body>
</html>