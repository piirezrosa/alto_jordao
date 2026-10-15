<?php 
require_once 'config.php'; 

// 1. BUSCA DINÂMICA: Pegamos os 8 produtos mais recentes
$query = $pdo->prepare("SELECT * FROM produtos ORDER BY id DESC LIMIT 8");
$query->execute();
$produtos = $query->fetchAll(PDO::FETCH_ASSOC);

// 2. FUNÇÃO AUXILIAR DE IMAGEM (Garante o caminho correto para a tag <img>)
function getCaminhoImagem($img) {
    // Se o banco estiver vazio, definimos uma imagem padrão que realmente existe na sua pasta
    if (empty($img)) return 'img/produtos/cb74cbfc6e4fa08cecc6bd257fc0f000.webp'; 
    return "img/produtos/" . $img;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alto Jordão | Home</title>
    <link rel="stylesheet" href="style.css?v=<?= time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
</head>
<body>

    <?php include 'header.php'; ?>
    <main>
        <section class="hero" style="background-image: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('https://images.unsplash.com/photo-1441986300917-64674bd600d8?q=80&w=1600'); height: 500px; background-size: cover; background-position: center; display: flex; align-items: center; justify-content: center; text-align: center; color: white;">
            <div class="hero-content">
                <h1 style="font-size: 3.5rem; letter-spacing: 5px; margin-bottom: 10px; font-weight: 900;">NOVA COLEÇÃO</h1>
                <p style="font-size: 1.2rem; margin-bottom: 25px;">Sofisticação e exclusividade em cada detalhe.</p>
                <a href="lancamentos.php" class="btn-black-capsule" style="padding: 15px 40px; background: white; color: black; text-decoration: none; font-weight: bold; border-radius: 50px;">VER AGORA</a>
            </div>
        </section>

        <section class="product-section" style="padding: 60px 20px;">
            <h2 style="text-align: center; margin-bottom: 40px; letter-spacing: 2px; font-weight: 800; text-transform: uppercase;">Produtos em Destaque</h2>
            
            <div class="product-grid">
                
                <?php if (count($produtos) > 0): ?>
                    <?php foreach ($produtos as $p): 
                        $pJson = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
                    ?>
                        <div class="product-card">
                            <div class="product-thumb">
                                <button class="btn-fav" 
                                        data-id="<?= $p['id'] ?>" 
                                        onclick='toggleFavorito(<?= $pJson ?>)'>
                                    🤍
                                </button>
                                
                                <a href="produto.php?id=<?= $p['id'] ?>" class="product-link">
                                    <div class="product-image-container">
                                        <img src="<?= getCaminhoImagem($p['imagem']) ?>" 
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
                    <div style="grid-column: 1/-1; text-align: center; padding: 100px 0;">
                        <p style="color: #999; font-style: italic;">Nenhum produto disponível no momento.</p>
                    </div>
                <?php endif; ?>

            </div>
        </section>
    </main>

    <footer style="background: #000; color: #fff; padding: 60px 20px; text-align: center; margin-top: 50px;">
        <h2 style="letter-spacing: 3px; margin-bottom: 20px;">ALTO JORDÃO</h2>
        <p style="font-size: 12px; opacity: 0.5;">&copy; 2026 Alto Jordão Originals. Todos os direitos reservados.</p>
    </footer>

    <script src="script.js?v=<?= time(); ?>"></script>
</body>
</html>