<?php 
require_once 'config.php'; 

// BUSCA DINÂMICA: Pegamos todos os produtos ou apenas os destaques para a home
$query = $pdo->prepare("SELECT * FROM produtos ORDER BY id DESC LIMIT 8");
$query->execute();
$produtos = $query->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FashionShop | Home</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php include 'header.php'; ?>

    <main>
        <section class="hero" style="background-image: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('https://images.unsplash.com/photo-1441986300917-64674bd600d8?q=80&w=1600'); height: 500px; background-size: cover; background-position: center; display: flex; align-items: center; justify-content: center; text-align: center; color: white;">
            <div class="hero-content">
                <h1 style="font-size: 3.5rem; letter-spacing: 5px; margin-bottom: 10px;">NOVA COLEÇÃO</h1>
                <p style="font-size: 1.2rem; margin-bottom: 25px;">Descubra as tendências que definem a temporada.</p>
                <a href="lancamentos.php" class="btn-buy-only" style="padding: 15px 40px; background: white; color: black; text-decoration: none; font-weight: bold; border-radius: 50px;">VER AGORA</a>
            </div>
        </section>

        <section class="product-section" style="padding: 60px 20px;">
            <h2 style="text-align: center; margin-bottom: 40px; letter-spacing: 2px;">PRODUTOS EM DESTAQUE</h2>
            
            <div class="product-grid">
                
                <?php if (count($produtos) > 0): ?>
                    <?php foreach ($produtos as $p): ?>
                        <div class="product-card">
                            <div class="product-thumb">
                                <button class="btn-fav" 
                                        data-name="<?= htmlspecialchars($p['nome']) ?>" 
                                        onclick="toggleFavorito(this, '<?= addslashes($p['nome']) ?>', '<?= $p['preco'] ?>', '<?= $p['imagem'] ?>')">
                                    ♡
                                </button>
                                
                                <a href="produto.php?id=<?= $p['id'] ?>" style="text-decoration: none; color: inherit;">
                                    <div class="product-image-container">
                                        <?php echo exibirImagem($p['imagem']); ?>
                                    </div>
                                </a>
                                
                                <div class="product-actions">
                                    <button class="btn-buy-only" onclick='abrirModalCompra(<?= json_encode($p) ?>)'>
                                        Comprar
                                    </button>
                                </div>
                            </div>

                            <div class="product-details">
                                <p class="category"><?= htmlspecialchars($p['categoria']) ?></p>
                                
                                <a href="produto.php?id=<?= $p['id'] ?>" style="text-decoration: none; color: inherit;">
                                    <h4 style="cursor: pointer;"><?= htmlspecialchars($p['nome']) ?></h4>
                                </a>
                                
                                <p class="price">R$ <?= number_format($p['preco'], 2, ',', '.') ?></p>

                                <?php if (isset($_SESSION['usuario_nivel']) && $_SESSION['usuario_nivel'] === 'admin'): ?>
                                    <div style="margin-top: 10px; display: flex; gap: 5px; border-top: 1px solid #eee; padding-top: 10px;">
                                        <a href="editar_produto.php?id=<?= $p['id'] ?>" 
                                           style="flex: 1; background: #ffc107; color: #000; text-align: center; padding: 8px 5px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: bold;">
                                           EDITAR
                                        </a>
                                        
                                        <a href="excluir_produto.php?id=<?= $p['id'] ?>" 
                                           onclick="return confirm('Excluir este produto permanentemente?')" 
                                           style="flex: 1; background: #ff4d4d; color: #fff; text-align: center; padding: 8px 5px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: bold;">
                                           EXCLUIR
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 50px;">
                        <p style="color: #999;">Nenhum produto cadastrado ainda.</p>
                    </div>
                <?php endif; ?>

            </div>
        </section>
    </main>

    <footer style="background: #000; color: #fff; padding: 40px 20px; text-align: center; margin-top: 50px;">
        <p style="font-size: 14px; opacity: 0.7;">&copy; 2026 FASHIONSHOP - Todos os direitos reservados.</p>
    </footer>

    <script src="script.js?v=<?= time(); ?>"></script>
</body>
</html>