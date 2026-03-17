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
            <span class="sub-label">Desejos</span>
            <h1>Meus Itens Salvos</h1>
            <p id="fav-count" style="color: #666; margin-top: 10px;">Carregando sua lista...</p>
        </div>

        <div class="product-grid" id="favsGrid">
            </div>

        <div id="emptyFavs" style="display: none; text-align: center; padding: 80px 20px;">
            <span style="font-size: 50px;">❤️</span>
            <h3 style="margin-top: 20px;">Sua lista de favoritos está vazia.</h3>
            <p style="color: #666; margin-bottom: 30px;">Salve seus itens favoritos para vê-los aqui mais tarde.</p>
            <a href="index.php" class="btn-black" style="padding: 12px 30px; background: #000; color: #fff; text-decoration: none; font-weight: bold; border-radius: 4px;">Explorar Loja</a>
        </div>
    </main>

    <script>
        // Forçamos a renderização assim que a página carrega
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof renderizarPaginaFavoritos === "function") {
                renderizarPaginaFavoritos();
            } else {
                console.error("Erro: A função renderizarPaginaFavoritos não foi encontrada no script.js");
            }
        });
    </script>
</body>
</html>