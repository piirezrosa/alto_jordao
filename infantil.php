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
                                <p class="category" style="font-size: 10px; color: #999; text-transform: uppercase; margin-bottom: 5px;">
                                    <?= htmlspecialchars($p['categoria'] ?? 'Originals') ?>
                                </p>
                                
                                <a href="produto.php?id=<?= $p['id'] ?>" style="text-decoration: none; color: inherit;">
                                    <h4 style="font-weight: 700; margin-bottom: 8px;"><?= htmlspecialchars($p['nome']) ?></h4>
                                </a>
                                
                                <p class="price" style="font-weight: 800; font-size: 1.1rem;">
                                    R$ <?= number_format($p['preco'], 2, ',', '.') ?>
                                </p>

                                <?php if (isset($_SESSION['usuario_nivel']) && ($_SESSION['usuario_nivel'] === 'admin' || $_SESSION['usuario_nivel'] === 'superadmin')): ?>
                                    <div class="admin-actions" style="margin-top: 15px; display: flex; gap: 8px; border-top: 1px solid #eee; padding-top: 10px;">
                                        <a href="editar_produto.php?id=<?= $p['id'] ?>" 
                                           style="flex: 1; background: #f1f1f1; color: #000; text-align: center; padding: 10px; border-radius: 5px; text-decoration: none; font-size: 11px; font-weight: bold;">
                                           EDITAR
                                        </a>
                                        <a href="excluir_produto.php?id=<?= $p['id'] ?>" 
                                           onclick="return confirm('Deseja excluir este produto?')" 
                                           style="flex: 1; background: #ffebeb; color: #ff4d4d; text-align: center; padding: 10px; border-radius: 5px; text-decoration: none; font-size: 11px; font-weight: bold;">
                                           EXCLUIR
                                        </a>
                                    </div>
                                <?php endif; ?>
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