<?php 
require_once 'config.php'; 

$termo = isset($_GET['q']) ? $_GET['q'] : '';

// Busca no banco por nome, categoria ou descrição
$query = $pdo->prepare("SELECT * FROM produtos WHERE nome LIKE ? OR categoria LIKE ? OR descricao LIKE ? ORDER BY id DESC");
$busca = "%$termo%";
$query->execute([$busca, $busca, $busca]);
$produtos = $query->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados para: <?= htmlspecialchars($termo) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php include 'header.php'; ?>

    <main>
        <section class="page-header" style="padding: 40px 20px; text-align: center;">
            <h1>Resultados para "<?= htmlspecialchars($termo) ?>"</h1>
            <p><?= count($produtos) ?> produtos encontrados.</p>
        </section>

        <section class="product-section">
            <div class="product-grid">
                <?php if (count($produtos) > 0): ?>
                    <?php foreach ($produtos as $p): ?>
                        <div class="product-card">
                            <div class="product-thumb">
                                <button class="btn-fav" onclick="toggleFavorito(this, '<?= addslashes($p['nome']) ?>', '<?= $p['preco'] ?>', '<?= $p['imagem'] ?>')">♡</button>
                                
                                <div class="placeholder-img" style="font-size: 50px; display: flex; align-items: center; justify-content: center; height: 100%;">
                                    <?= $p['imagem'] ? $p['imagem'] : '📦' ?>
                                </div>
                                <div class="product-actions">
                                    <button class="btn-buy-only" onclick="adicionarAoCarrinho({id: <?= $p['id'] ?>, nome: '<?= addslashes($p['nome']) ?>', preco: '<?= $p['preco'] ?>', img: '<?= $p['imagem'] ?>'})">
                                        Comprar
                                    </button>
                                </div>
                            </div>
                            <div class="product-details">
                                <p class="category"><?= $p['categoria'] ?></p>
                                <h4><?= $p['nome'] ?></h4>
                                <p class="price">R$ <?= number_format($p['preco'], 2, ',', '.') ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 80px;">
                        <span style="font-size: 50px;">🔍</span>
                        <p style="margin-top: 20px; color: #666;">Nenhum produto encontrado com esse nome.</p>
                        <a href="index.php" class="btn-black" style="display: inline-block; margin-top: 20px; text-decoration: none; padding: 10px 25px; background: #000; color: #fff;">Voltar para a Home</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script src="script.js?v=<?= time(); ?>"></script>
</body>
</html>