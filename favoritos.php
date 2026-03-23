<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FashionShop | Meus Favoritos</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php include 'header.php'; ?>

    <main class="product-section">
        <div class="page-header" style="text-align: center; padding: 40px 0;">
            <span class="sub-label" style="text-transform: uppercase; font-size: 12px; letter-spacing: 2px; color: #888;">Desejos</span>
            <h1 style="font-size: 32px; font-weight: 900; margin-top: 10px;">Meus Itens Salvos</h1>
            <p id="fav-count" style="color: #666; margin-top: 10px;">Verificando lista...</p>
        </div>

        <div class="product-grid" id="favsGrid"></div>

        <div id="emptyFavs" style="display: none; text-align: center; padding: 80px 20px;">
            <span style="font-size: 50px;">❤️</span>
            <h3 style="margin-top: 20px; font-weight: 800;">Sua lista está vazia.</h3>
            <p style="color: #666; margin-bottom: 30px;">Salve seus itens favoritos para vê-los aqui mais tarde.</p>
            <a href="index.php" style="padding: 15px 40px; background: #000; color: #fff; text-decoration: none; font-weight: bold; border-radius: 4px; display: inline-block;">EXPLORAR LOJA</a>
        </div>
    </main>

    <script src="script.js"></script> 

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Pequeno delay para garantir que o script.js terminou de carregar
            setTimeout(() => {
                if (typeof renderizarPaginaFavoritos === "function") {
                    renderizarPaginaFavoritos();
                }
            }, 100);
        });
    </script>
</body>
</html>