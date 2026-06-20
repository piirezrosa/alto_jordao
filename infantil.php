<?php
    require_once 'config.php';

    $query = $pdo->prepare("SELECT * FROM produtos WHERE categoria = 'infantil' ORDER BY id DESC");
    $query->execute();
    $produtos = $query->fetchAll(PDO::FETCH_ASSOC);

    function getCaminhImagem($img) {
        if (empty($img)) return 'img/placeholder.jpg';
        return "img/produtos/".$img;
    }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alto Jordão | Kids</title>
    <link rel="stylesheet" href="style.css?v=</= time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
</head>
<body class="admin-body"> <?php include 'header.php'; ?>
    <main>
        <section class="kids-hero">
            <div class="kids-hero-content">
                <span class="brand-tag">Alto Jordão Junior</span>
                <h1>Pequenos passos,<br><span class="light-text">grande estilo.</span></h1>
                <p>A estética minimalista que você ama, agora para eles.</p>
            </div>
        </section>

        <section class="filter-section">
            <div class="filter-container">
                <span class="filter-label">Filtrar idade:</span>
                <div class="filter-chips">
                    <div class="chip active" data-age="todos">todos</div>
                    <div class="chip" data-age="0-2">0-2 anos</div>
                    <div class="chip" data-age="3-7">3-7 anos</div>
                    <div class="chip" data-age="8-12">8-12 anos</div>
                </div>
            </div>
        </section>

        <section class="product-grid" id="kidsGrid">
            <?php if (count($produtos) > 0): ?>
                <?php foreach ($products as $p): ?>
                    <div class="product-card" data-age-group="<?= $p['faixa_etaria'] ?>">
                        <div class="product-thumb">
                            <button class="btn-fav" data-id="<?= $p['id'] ?>">
                                <i class="fa-regular fa-heart"></i>
                            </button>
                            <img src="<?= getCaminhoImagem($p['imagem']) ?>" alt="<?= $p['nome'] ?>">
                            <button class="btn-buy-overlay" onclick="addToCart(<?= $p['id'] ?>)">
                                Adicionar
                            </button>
                        </div>
                        <div class="product-details">
                            <span class="category"><?= htmlspecialchars($p['categoria']) ?></span>
                            <h4><?= htmlspecialchars($p['nome']) ?></h4>
                            <p class="price">R$ <?= number_format($p['preco'], 2, ',', '.') ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>Nenhum produto infantil encontrado no momento.</p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php include 'footer.php'; ?>

    <script src="script.js?v=<?= time(); ?>"></script>
</body>         
</html>