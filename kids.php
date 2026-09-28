<?php 
require_once 'config.php'; 

// 1. BUSCA DINÂMICA: Apenas categoria 'kids'
$query = $pdo->prepare("SELECT * FROM produtos WHERE genero = 'kids' ORDER BY id DESC");
$query->execute();
$produtos = $query->fetchAll(PDO::FETCH_ASSOC);

// 2. FUNÇÃO DE IMAGEM PADRÃO
function getCaminhoImagemKids($img) {
    if (empty($img)) return 'img/produtos/cb74cbfc6e4fa08cecc6bd257fc0f000.webp'; 
    return "img/produtos/" . $img;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alto Jordão | Kids</title>
    <link rel="stylesheet" href="style.css?v=<?= time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
</head>
<body>

    <?php include 'header.php'; ?>

    <main>
        <section class="hero" style="background-image: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('https://imgs.search.brave.com/HvRWEmGhMqlvJ4HzeB9Us3Kw6z-2XRIUfjJ8hsBiMCY/rs:fit:860:0:0:0/g:ce/aHR0cHM6Ly9pbWcu/ZnJlZXBpay5jb20v/Zm90b3MtZ3JhdGlz/L3VtLWdydXBvLWRl/LWFtaWdvcy1kZS1j/cmlhbmNhcy1zZW50/YWRvcy1qdW50b3Nf/MTE1MC0zOTA3Lmpw/Zz9zZW10PWFpc19o/eWJyaWQmdz03NDAm/cT04MA'); height: 450px; background-size: cover; background-position: center; display: flex; align-items: center; justify-content: center; text-align: center; color: white;">
            <div class="hero-content">
                <h1 style="font-size: 3.5rem; letter-spacing: 8px; margin-bottom: 10px; font-weight: 900; text-transform: uppercase;">KIDS</h1>
                <p style="font-size: 1.1rem; margin-bottom: 25px; opacity: 0.9; letter-spacing: 1px;">Conforto e estilo para os pequenos exploradores.</p>
                <a href="#produtos" style="padding: 15px 40px; background: white; color: black; text-decoration: none; font-weight: 800; border-radius: 50px; font-size: 13px; letter-spacing: 1px;">EXPLORAR COLEÇÃO</a>
            </div>
        </section>

        <section class="product-section" id="produtos" style="padding: 60px 20px;">
            <div class="product-grid">
                <?php if (count($produtos) > 0): ?>
                    <?php foreach ($produtos as $p): 
                        $pJson = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
                        // Verifica se tem preço antigo para mostrar oferta
                        $temDesconto = (!empty($p['preco_antigo']) && $p['preco_antigo'] > $p['preco']);
                    ?>
                        <div class="product-card">
                            <div class="product-thumb">
                                <button class="btn-fav" data-id="<?= $p['id'] ?>" onclick='toggleFavorito(<?= $pJson ?>)'>
                                    🤍
                                </button>
                                
                                <a href="produto.php?id=<?= $p['id'] ?>" class="product-link">
                                    <div class="product-image-container">
                                        <img src="<?= getCaminhoImagemKids($p['imagem']) ?>" 
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
                                    <?= htmlspecialchars($p['categoria'] ?? 'Infantil') ?>
                                </p>
                                
                                <a href="produto.php?id=<?= $p['id'] ?>" style="text-decoration: none; color: inherit;">
                                    <h4 style="font-weight: 700; margin-bottom: 8px;"><?= htmlspecialchars($p['nome']) ?></h4>
                                </a>
                                
                                <div class="price-container" style="display: flex; align-items: center; gap: 10px;">
                                    <?php if ($temDesconto): ?>
                                        <span style="font-size: 0.85rem; color: #bbb; text-decoration: line-through;">R$ <?= number_format($p['preco_antigo'], 2, ',', '.') ?></span>
                                    <?php endif; ?>
                                    <p class="price" style="font-weight: 800; font-size: 1.1rem; margin: 0;">
                                        R$ <?= number_format($p['preco'], 2, ',', '.') ?>
                                    </p>
                                </div>

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
                        <p style="color: #999; font-style: italic;">Nenhum produto infantil disponível no momento.</p>
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