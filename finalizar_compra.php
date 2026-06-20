<?php 
require_once 'config.php'; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * LÓGICA DE FINALIZAÇÃO (Exemplo)
 * Aqui você inseriria os dados no banco (tabela pedidos)
 * e limparia o carrinho: unset($_SESSION['carrinho']);
 */

// Simulação de limpeza de carrinho para o teste
// unset($_SESSION['carrinho']); 
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compra Finalizada | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        .checkout-success {
            max-width: 800px;
            margin: 0 auto;
            padding: 120px 20px;
            text-align: center;
        }
        .order-badge {
            display: inline-block;
            background: #f0f0f0;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 30px;
        }
        .check-icon {
            font-size: 50px;
            margin-bottom: 20px;
            display: block;
        }
        .btn-black-capsule {
            display: inline-block;
            background: #000;
            color: #fff;
            padding: 18px 45px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 800;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-top: 40px;
            transition: all 0.3s ease;
        }
        .btn-black-capsule:hover {
            background: #333;
            transform: translateY(-3px);
        }
    </style>
</head>
<body>

    <?php include 'header.php'; // Topo da Loja ?>

    <main class="checkout-success">
        <span class="check-icon">📦</span>
        <div class="order-badge">Pedido Confirmado</div>
        
        <h1 style="font-size: 3rem; font-weight: 900; letter-spacing: -2px; line-height: 1; margin-bottom: 20px;">
            SUA CURADORIA <br> ESTÁ A CAMINHO.
        </h1>
        
        <p style="color: #666; max-width: 500px; margin: 0 auto; line-height: 1.8;">
            Obrigado por escolher a <strong>Alto Jordão</strong>. <br>
            O resumo da sua compra foi enviado para o seu e-mail cadastrado. <br>
            Assim que seu pacote for despachado, você receberá o código de rastreio.
        </p>

        <div style="margin-top: 50px; border-top: 1px solid #eee; padding-top: 40px;">
            <p style="font-size: 13px; color: #bbb;">Dúvidas sobre seu pedido?</p>
            <p style="font-size: 14px; font-weight: 700;">suporte@altojordao.com</p>
        </div>

        <a href="index.php" class="btn-black-capsule">Continuar Navegando</a>
    </main>

    <?php include 'header.php'; // Sua barra final/rodapé ?>

</body>
</html>