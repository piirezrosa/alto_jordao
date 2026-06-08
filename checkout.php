<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php'; 

// --- TRAVA DE SEGURANÇA: SÓ ACESSA LOGADO ---
$user = null;
if (isset($_SESSION['usuario_id'])) {
    $stmt = $pdo->prepare("SELECT u.*, e.cep, e.rua, e.numero, e.bairro, e.cidade, e.estado 
                           FROM usuarios u
                           LEFT JOIN enderecos e ON u.id = e.usuario_id
                           WHERE u.id = :id");
    $stmt->execute([':id' => $_SESSION['usuario_id']]);
    $user = $stmt->fetch();
} else {
    // Redireciona caso não esteja logado
    header("Location: login.php?msg=faca_login_para_finalizar"); 
    exit; 
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Compra | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-light: #fbfbfb;
            --border-color: #efefef;
            --accent: #000;
        }

        body { background-color: #fff; color: #000; font-family: 'Inter', sans-serif; }

        .checkout-container {
            max-width: 1100px;
            margin: 40px auto 100px;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 80px;
        }

        .checkout-form h2 { 
            font-size: 2.5rem;
            font-weight: 900; 
            text-transform: uppercase; 
            margin-bottom: 50px; 
            letter-spacing: -2px;
        }

        .section-title {
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 3px;
            color: #bbb;
            margin: 40px 0 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .section-title::after { content: ""; flex: 1; height: 1px; background: var(--border-color); }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 800; font-size: 11px; margin-bottom: 8px; text-transform: uppercase; color: #000; letter-spacing: 0.5px; }
        
        .form-group input, .form-group select { 
            width: 100%; 
            padding: 16px; 
            border: 1.5px solid var(--border-color); 
            border-radius: 12px; 
            font-size: 14px;
            font-family: inherit;
            background: var(--bg-light);
            transition: all 0.3s ease;
        }
        .form-group input:focus { border-color: #000; background: #fff; outline: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .form-group input[readonly] { opacity: 0.6; cursor: not-allowed; background: #f0f0f0; }

        /* Resumo Lateral */
        .resumo-pedido {
            background: #fff;
            padding: 35px;
            border-radius: 30px;
            border: 1px solid var(--border-color);
            position: sticky;
            top: 120px;
            height: fit-content;
        }

        .item-checkout {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            align-items: center;
        }
        .item-checkout img { 
            width: 60px; 
            height: 60px; 
            object-fit: cover; 
            background: #f5f5f5;
            border-radius: 10px; 
        }
        .item-info h4 { font-size: 12px; font-weight: 800; text-transform: uppercase; margin: 0; }
        .item-info p { font-size: 10px; color: #888; margin: 2px 0; font-weight: 600; text-transform: uppercase; }

        .summary-totals { margin-top: 25px; padding-top: 25px; border-top: 1px solid #eee; }
        .total-line { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
        .total-line.final { font-size: 24px; font-weight: 900; margin-top: 15px; border-top: 2px solid #000; padding-top: 15px; }

        .btn-finalizar {
            width: 100%;
            padding: 22px;
            background: #000;
            color: #fff;
            border: none;
            border-radius: 50px;
            font-weight: 900;
            font-size: 13px;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
            transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            margin-top: 20px;
        }
        .btn-finalizar:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.2); }

        @media (max-width: 900px) {
            .checkout-container { grid-template-columns: 1fr; margin-top: 20px; }
            .resumo-pedido { position: static; margin-top: 40px; }
            .checkout-form h2 { font-size: 1.8rem; }
        }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <main class="checkout-container">
        <section class="checkout-form">
            <h2>Finalizar Pedido</h2>
            
            <form action="processar_pedido.php" method="POST" id="formCheckout">
                <span class="section-title">Dados do Comprador</span>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nome Completo</label>
                        <input type="text" name="nome" required placeholder="Seu nome completo" value="<?= htmlspecialchars($user['nome'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>E-mail da Conta</label>
                        <input type="email" name="email" required value="<?= htmlspecialchars($user['email'] ?? '') ?>" readonly title="E-mail vinculado à sua conta">
                    </div>
                </div>

                <span class="section-title">Onde entregamos?</span>
                <div class="form-row">
                    <div class="form-group">
                        <label>CEP</label>
                        <input type="text" id="cep" name="cep" required placeholder="00000-000" maxlength="9" value="<?= htmlspecialchars($user['cep'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Estado (UF)</label>
                        <input type="text" id="estado" name="estado" required placeholder="Ex: SP" maxlength="2" value="<?= htmlspecialchars($user['estado'] ?? '') ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 2.5fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Logradouro / Rua</label>
                        <input type="text" id="rua" name="rua" required placeholder="Ex: Av. Paulista" value="<?= htmlspecialchars($user['rua'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Número</label>
                        <input type="text" id="numero" name="numero" required placeholder="123" value="<?= htmlspecialchars($user['numero'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Bairro</label>
                        <input type="text" id="bairro" name="bairro" required placeholder="Seu bairro" value="<?= htmlspecialchars($user['bairro'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Cidade</label>
                        <input type="text" id="cidade" name="cidade" required placeholder="Sua cidade" value="<?= htmlspecialchars($user['cidade'] ?? '') ?>">
                    </div>
                </div>

                <span class="section-title">Pagamento</span>
                <div class="form-group">
                    <label>Escolha o método</label>
                    <select name="pagamento" required>
                        <option value="pix">PIX (Aprovação imediata + 5% OFF)</option>
                        <option value="cartao">Cartão de Crédito (Até 12x)</option>
                        <option value="boleto">Boleto Bancário</option>
                    </select>
                </div>

                <input type="hidden" name="carrinho_dados" id="inputCarrinhoDados">

                <button type="submit" class="btn-finalizar">
                    Confirmar e Pagar
                </button>
            </form>
        </section>

        <aside class="resumo-pedido">
            <h3 style="font-weight: 900; margin-bottom: 30px; text-transform: uppercase; font-size: 13px; letter-spacing: 1px;">Sua Sacola</h3>
            
            <div id="listaCheckout">
                </div>
            
            <div class="summary-totals">
                <div class="total-line">
                    <span style="color: #888;">Subtotal</span>
                    <span id="subtotalCheckout" style="font-weight: 700;">R$ 0,00</span>
                </div>
                <div class="total-line">
                    <span style="color: #888;">Frete</span>
                    <span style="color: #27ae60; font-weight: 800; font-size: 11px;">GRÁTIS (EXPRESS)</span>
                </div>
                <div class="total-line final">
                    <span>TOTAL</span>
                    <span id="totalCheckout">R$ 0,00</span>
                </div>
            </div>
        </aside>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const inputCep = document.getElementById('cep');
            
            // Máscara Automática de CEP
            inputCep.addEventListener('input', (e) => {
                let v = e.target.value.replace(/\D/g, '');
                if (v.length > 5) v = v.slice(0, 5) + '-' + v.slice(5, 8);
                e.target.value = v;
            });

            // Busca de CEP (ViaCEP)
            inputCep.addEventListener('blur', () => {
                let cep = inputCep.value.replace(/\D/g, ''); 
                if (cep.length === 8) {
                    fetch(`https://viacep.com.br/ws/${cep}/json/`)
                        .then(res => res.json())
                        .then(dados => {
                            if (!dados.erro) {
                                document.getElementById('rua').value = dados.logradouro;
                                document.getElementById('bairro').value = dados.bairro;
                                document.getElementById('cidade').value = dados.localidade;
                                document.getElementById('estado').value = dados.uf;
                                document.getElementById('numero').focus();
                            }
                        });
                }
            });

            // Lógica do Carrinho
            const carrinho = JSON.parse(sessionStorage.getItem('fashion_cart')) || [];
            if(carrinho.length === 0) {
                window.location.href = "index.php"; // Se esvaziar, volta pra loja
                return;
            }

            document.getElementById('inputCarrinhoDados').value = JSON.stringify(carrinho);
            
            let total = 0;
            const lista = document.getElementById('listaCheckout');
            
            lista.innerHTML = carrinho.map(item => {
                total += (parseFloat(item.preco) * item.qtd);
                return `
                    <div class="item-checkout">
                        <img src="${item.img}" alt="${item.nome}">
                        <div class="item-info">
                            <h4>${item.nome}</h4>
                            <p>${item.tamanho_escolhido || 'P'} | ${item.cor_escolhida || 'Original'}</p>
                            <span class="item-price">${item.qtd}x R$ ${parseFloat(item.preco).toLocaleString('pt-br', {minimumFractionDigits: 2})}</span>
                        </div>
                    </div>
                `;
            }).join('');

            const totalFormatado = total.toLocaleString('pt-br', {style: 'currency', currency: 'BRL'});
            document.getElementById('totalCheckout').innerText = totalFormatado;
            document.getElementById('subtotalCheckout').innerText = totalFormatado;
        });
    </script>
</body>
</html>