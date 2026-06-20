<?php 
require_once 'config.php'; 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Favoritos | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* Estilo específico para a página de favoritos */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 40px;
            padding: 40px 0;
        }

        .fav-header {
            text-align: center;
            padding: 80px 20px 40px;
            background: #fff;
        }

        .fav-header h1 {
            font-size: clamp(32px, 5vw, 48px);
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: -2px;
            margin: 15px 0;
        }

        .sub-label { 
            letter-spacing: 5px; 
            font-weight: 700; 
            color: #bbb; 
            font-size: 11px; 
            display: block;
        }

        .empty-state {
            max-width: 500px;
            margin: 100px auto;
            text-align: center;
            padding: 40px;
        }

        .empty-icon {
            font-size: 60px;
            margin-bottom: 25px;
            display: block;
            filter: grayscale(1);
            opacity: 0.3;
        }

        /* Garantir que os cards de produtos sigam o padrão premium */
        .product-card {
            background: #fff;
            transition: transform 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        }

        .product-card:hover {
            transform: translateY(-10px);
        }
    </style>
</head>
<body class="bg-light">

    <?php include 'header.php'; ?>

    <main class="container">
        <section class="fav-header">
            <span class="sub-label">WISHLIST</span>
            <h1>Sua Seleção</h1>
            <p id="fav-count" style="color: #888; font-size: 13px; font-weight: 600; text-transform: uppercase;">VERIFICANDO ITENS...</p>
        </section>

        <div class="product-grid" id="favsGrid"></div>
        
        <div id="emptyFavs" style="display: none;">
            <div class="empty-state">
                <span class="empty-icon">🖤</span>
                <h2 style="font-weight: 900; text-transform: uppercase; margin-bottom: 15px;">Nada por aqui</h2>
                <p style="color: #666; line-height: 1.6; margin-bottom: 40px;">Sua lista de desejos está aguardando por peças exclusivas da nossa coleção.</p>
                <a href="index.php" class="btn-black-capsule" style="padding: 20px 50px; background: #000; color: #fff; text-decoration: none; border-radius: 50px; font-weight: 800; font-size: 12px; display: inline-block;">EXPLORAR COLEÇÃO</a>
            </div>
        </div>
    </main>


    <script src="script.js?v=<?= time() ?>"></script> 

    <script>
        /**
         * Atualiza visualmente a contagem e visibilidade dos itens
         */
        function atualizarInterfaceFavoritos() {
            // "fashion_favs" deve ser o mesmo nome usado no seu script.js principal
            const favs = JSON.parse(localStorage.getItem('fashion_favs')) || [];
            const contador = document.getElementById('fav-count');
            const grid = document.getElementById('favsGrid');
            const msgVazia = document.getElementById('emptyFavs');

            if (contador) {
                contador.innerText = favs.length === 0 ? "NENHUM ITEM SALVO" : 
                                   (favs.length === 1 ? "1 ITEM EXCLUSIVO SALVO" : `${favs.length} ITENS EXCLUSIVOS SALVOS`);
            }

            if (favs.length === 0) {
                if (grid) grid.style.display = 'none';
                if (msgVazia) msgVazia.style.display = 'block';
            } else {
                if (grid) grid.style.display = 'grid';
                if (msgVazia) msgVazia.style.display = 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Executa a função do script.js que desenha os produtos na tela
            if (typeof renderizarPaginaFavoritos === "function") {
                renderizarPaginaFavoritos();
                atualizarInterfaceFavoritos();
            } else {
                console.error("A função renderizarPaginaFavoritos() não foi detectada no script.js");
            }
        });

        // Atualiza a tela se o usuário clicar no ícone de remover coração
        window.addEventListener('click', (e) => {
            // Se o clique foi num botão de desfavoritar (ajuste a classe conforme seu script.js)
            if (e.target.closest('.btn-fav') || e.target.closest('.remove-fav')) {
                setTimeout(atualizarInterfaceFavoritos, 150);
            }
        });
    </script>
</body>
</html>