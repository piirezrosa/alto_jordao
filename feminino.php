<?php 
require_once 'config.php'; 

// BUSCA DINÂMICA: Feminino ou Unissex
$query = $pdo->prepare("SELECT * FROM produtos WHERE genero IN ('feminino', 'unissex') ORDER BY id DESC");
$query->execute();
$produtos = $query->fetchAll(PDO::FETCH_ASSOC);

// FUNÇÃO DE IMAGEM PADRÃO
function getCaminhoImagemFem($img) {
    if (empty($img)) return 'img/produtos/cb74cbfc6e4fa08cecc6bd257fc0f000.webp'; 
    return "img/produtos/" . $img;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alto Jordão | Feminino</title>
    <link rel="stylesheet" href="style.css?v=<?= time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
</head>
<body>

    <?php include 'header.php'; ?>

    <main>
        <section class="hero" style="background-image: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('https://images.unsplash.com/photo-1525507119028-ed4c629a60a3?q=80&w=1600'); height: 450px; background-size: cover; background-position: center; display: flex; align-items: center; justify-content: center; text-align: center; color: white;">
            <div class="hero-content">
                <h1 style="font-size: 3.5rem; letter-spacing: 8px; margin-bottom: 10px; font-weight: 900; text-transform: uppercase;">FEMININO</h1>
                <p style="font-size: 1.1rem; margin-bottom: 25px; opacity: 0.9; letter-spacing: 1px;">Elegância e tendência em cada detalhe.</p>
                <a href="#produtos" style="padding: 15px 40px; background: white; color: black; text-decoration: none; font-weight: 800; border-radius: 50px; font-size: 13px; letter-spacing: 1px;">VER AGORA</a>
            </div>
        </section>

        <section class="product-section" id="produtos" style="padding: 60px 20px;">
            <div class="product-grid">
                <?php if (count($produtos) > 0): ?>
                    <?php foreach ($produtos as $p): 
                        $pJson = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
                    ?>
                        <div class="product-card">
                            <div class="product-thumb">
                                <button class="btn-fav" data-id="<?= $p['id'] ?>" onclick='toggleFavorito(<?= $pJson ?>)'>
                                    🤍
                                </button>
                                
                                <a href="produto.php?id=<?= $p['id'] ?>" class="product-link">
                                    <div class="product-image-container">
                                        <img src="<?= getCaminhoImagemFem($p['imagem']) ?>" 
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
                                
                                <p class="price" style="font-weight: 800; font-size: 1.1rem;">
                                    R$ <?= number_format($p['preco'], 2, ',', '.') ?>
                                </p>

                                <?php if (isset($_SESSION['usuario_nivel']) && $_SESSION['usuario_nivel'] === 'admin'): ?>
                                    <div style="margin-top: 15px; display: flex; gap: 8px; border-top: 1px solid #eee; padding-top: 10px;">
                                        <a href="editar_produto.php?id=<?= $p['id'] ?>" style="flex: 1; background: #f1f1f1; color: #000; text-align: center; padding: 8px 5px; border-radius: 5px; text-decoration: none; font-size: 10px; font-weight: bold;">EDITAR</a>
                                        <a href="excluir_produto.php?id=<?= $p['id'] ?>" onclick="return confirm('Excluir este produto?')" style="flex: 1; background: #ffebeb; color: #ff4d4d; text-align: center; padding: 8px 5px; border-radius: 5px; text-decoration: none; font-size: 10px; font-weight: bold;">EXCLUIR</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 100px 0;">
                        <p style="color: #999; font-style: italic;">Nenhum produto feminino disponível no momento.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer style="background: #000; color: #fff; padding: 60px 20px; text-align: center; margin-top: 50px;">
        <h2 style="letter-spacing: 3px; font-size: 1.2rem; margin-bottom: 10px;">ALTO JORDÃO</h2>
        <p style="font-size: 10px; opacity: 0.5;">&copy; 2026 Alto Jordão Originals. Todos os direitos reservados.</p>
    </footer>

    <script src="script.js?v=<?= time(); ?>"></script>
</body>
</html>