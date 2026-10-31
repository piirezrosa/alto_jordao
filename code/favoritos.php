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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="aj-page">

<?php include 'header.php'; ?>

<main class="aj-wrap">
    <section class="aj-pagehead">
        <h1>Favoritos</h1>
        <p id="fav-count">Carregando...</p>
    </section>

    <div class="aj-toolbar"></div>
    <div class="aj-grid" id="favsGrid"></div>

    <div id="emptyFavs" style="display:none">
        <div class="aj-emptypage">
            <h2>Você ainda não favoritou nada</h2>
            <p>Toque no coração de uma peça para guardá-la aqui.</p>
            <a class="aj-btn" href="lancamentos.php">Ver lançamentos</a>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<script src="script.js?v=<?= time() ?>"></script>
<script>
    // Contagem e estado vazio (sobrescreve a versão genérica do script.js nesta página)
    function atualizarInterfaceFavoritos() {
        const favs = JSON.parse(localStorage.getItem('fashion_favs')) || [];
        document.getElementById('fav-count').textContent =
            favs.length === 0 ? 'Nenhuma peça salva.' : (favs.length === 1 ? '1 peça salva.' : favs.length + ' peças salvas.');
        document.getElementById('favsGrid').style.display = favs.length ? 'grid' : 'none';
        document.getElementById('emptyFavs').style.display = favs.length ? 'none' : 'block';
    }
    document.addEventListener('DOMContentLoaded', () => {
        renderizarPaginaFavoritos();
        atualizarInterfaceFavoritos();
    });
</script>
</body>
</html>
