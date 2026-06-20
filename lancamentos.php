<?php 
require_once 'config.php'; 

// BUSCA DINÂMICA: Puxa os 12 produtos mais recentes do banco
$query = $pdo->query("SELECT * FROM produtos ORDER BY id DESC LIMIT 12");
$produtos = $query->fetchAll(PDO::FETCH_ASSOC);

// FUNÇÃO DE IMAGEM (Garante que a foto apareça mesmo se o campo estiver vazio)
function getCaminhoImagemLancamento($img) {
    if (empty($img)) return 'img/produtos/cb74cbfc6e4fa08cecc6bd257fc0f000.webp'; 
    return "img/produtos/" . $img;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alto Jordão | Lançamentos</title>
    <link rel="stylesheet" href="style.css?v=<?= time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
</head>
<body>

    <?php include 'header.php'; ?>

    <main>
        <section class="page-header" style="text-align: center; padding: 60px 20px;">
            <h1 style="font-size: 2.5rem; font-weight: 900; letter-spacing: -1px; text-transform: uppercase;">Novidades</h1>
            <p style="color: #666; max-width: 600px; margin: 10px auto 0;">As últimas tendências da Alto Jordão. Design exclusivo e qualidade premium em cada peça.</p>
        </section>

        <div class="filter-bar" style="display: flex; justify-content: center; gap: 12px; margin-bottom: 40px; flex-wrap: wrap; padding: 0 20px;">
            <button style="padding: 8px 25px; border-radius: 50px; border: 1px solid #000; background: #000; color: #fff; font-weight: 600; cursor: pointer;">Tudo</button>
            <button style="padding: 8px 25px; border-radius: 50px; border: 1px solid #eee; background: #fff; color: #000; font-weight: 600; cursor: pointer;">Calçados</button>
            <button style="padding: 8px 25px; border-radius: 50px; border: 1px solid #eee; background: #fff; color: #000; font-weight: 600; cursor: pointer;">Vestuário</button>
        </div>

        <section class="product-section" style="padding: 0 20px;">
            <div class="product-grid">
                
                <?php if (count($produtos) > 0): ?>
                    <?php foreach ($produtos as $p): 
                        $pJson = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
                    ?>
                        <div class="product-card">
                            <div class="product-thumb">
                                <span class="badge-tag" style="position: absolute; top: 15px; left: 15px; background: #000; color: #fff; padding: 5px 12px; font-size: 10px; font-weight: 900; z-index: 10; border-radius: 50px; letter-spacing: 1px;">NEW</span>

                                <button class="btn-fav" data-id="<?= $p['id'] ?>" onclick='toggleFavorito(<?= $pJson ?>)'>
                                    🤍
                                </button>
                                
                                <a href="produto.php?id=<?= $p['id'] ?>" class="product-link">
                                    <div class="product-image-container">
                                        <img src="<?= getCaminhoImagemLancamento($p['imagem']) ?>" 
                                             alt="<?= htmlspecialchars($p['nome']) ?>" 
                                             style="width: 100%; height: 100%; object-fit: contain; display: block;">
                                    </div>
                                </a>
                                
                                <button class="btn-buy-overlay" onclick="window.location.href='produto.php?id=<?= $p['id'] ?>'">
                                    Ver Detalhes
                                </button>
                            </div>

                            <div class="product-details">
                                <p class="category" style="font-size: 10px; color: #999; text-transform: uppercase; margin-bottom: 5px;">
                                    <?= htmlspecialchars($p['categoria']) ?>
                                </p>
                                
                                <a href="produto.php?id=<?= $p['id'] ?>" style="text-decoration: none; color: inherit;">
                                    <h4 style="font-weight: 700; margin-bottom: 8px;"><?= htmlspecialchars($p['nome']) ?></h4>
                                </a>
                                
                                <p class="price" style="font-weight: 800; font-size: 1.1rem; color: #000;">
                                    R$ <?= number_format($p['preco'], 2, ',', '.') ?>
                                </p>

                                <?php if (isset($_SESSION['usuario_nivel']) && $_SESSION['usuario_nivel'] === 'admin'): ?>
                                    <div class="admin-actions" style="margin-top: 15px; display: flex; gap: 8px; border-top: 1px solid #eee; padding-top: 10px;">
                                        <a href="editar_produto.php?id=<?= $p['id'] ?>" style="flex:1; background:#f1f1f1; color:#000; text-align:center; padding:8px; border-radius:5px; font-size:10px; text-decoration:none; font-weight:bold;">EDITAR</a>
                                        <a href="excluir_produto.php?id=<?= $p['id'] ?>" onclick="return confirm('Excluir este lançamento?')" style="flex:1; background:#ffebeb; color:#ff4d4d; text-align:center; padding:8px; border-radius:5px; font-size:10px; text-decoration:none; font-weight:bold;">EXCLUIR</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 100px 0;">
                        <p style="color: #999; font-style: italic;">Aguardando novos lançamentos...</p>
                    </div>
                <?php endif; ?>

            </div>
        </section>
    </main>

    <footer style="background: #000; color: #fff; padding: 60px 20px; text-align: center; margin-top: 50px;">
        <h2 style="letter-spacing: 3px; font-size: 1.2rem;">ALTO JORDÃO</h2>
        <p style="font-size: 10px; opacity: 0.5;">&copy; 2026 Alto Jordão Originals.</p>
    </footer>

    <script src="script.js?v=<?= time(); ?>"></script>
</body>
</html>