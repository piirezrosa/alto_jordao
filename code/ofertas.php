<?php 
require_once 'config.php'; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $query = $pdo->query("SELECT * FROM produtos WHERE (preco_antigo > preco AND preco > 0) OR categoria = 'ofertas' ORDER BY id DESC");
} catch (PDOException $e) {
    $query = $pdo->query("SELECT * FROM produtos WHERE categoria = 'ofertas' ORDER BY id DESC");
}
$produtos = $query->fetchAll(PDO::FETCH_ASSOC);

function getCaminhoImagemOferta($img) {
    if (empty($img)) return 'img/produtos/default.jpg'; 
    return "img/produtos/" . $img;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ofertas Especiais | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>

    <?php include 'header.php'; // Barra do Topo ?>

    <main style="min-height: 70vh;">
        <section class="page-header" style="text-align: center; padding: 80px 20px 40px;">
            <h1 style="font-weight: 900; text-transform: uppercase; letter-spacing: -1px;">Ofertas</h1>
            <div style="width: 30px; height: 2px; background: #000; margin: 15px auto;"></div>
        </section>

        <section class="container" style="margin-bottom: 80px;">
            <div class="product-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 30px; padding: 20px;">
                
                <?php if (count($produtos) > 0): ?>
                    <?php foreach ($produtos as $p): 
                        $pJson = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
                        
                        $porcentagem = 0;
                        if ($p['preco_antigo'] > $p['preco']) {
                            $porcentagem = round(100 - (($p['preco'] * 100) / $p['preco_antigo']));
                        }
                    ?>
                        <div class="product-card" style="position: relative;">
                            <div class="product-thumb" style="position: relative; overflow: hidden;">
                                
                                <?php if ($porcentagem > 0): ?>
                                    <span style="position: absolute; top: 15px; left: 15px; background: #000; color: #fff; padding: 5px 12px; font-size: 10px; font-weight: 900; z-index: 10; border-radius: 50px;">
                                        -<?= $porcentagem ?>% OFF
                                    </span>
                                <?php elseif ($p['categoria'] === 'ofertas'): ?>
                                    <span style="position: absolute; top: 15px; left: 15px; background: #ff4d4d; color: #fff; padding: 5px 12px; font-size: 10px; font-weight: 900; z-index: 10; border-radius: 50px;">
                                        SALE
                                    </span>
                                <?php endif; ?>

                                <button class="btn-fav" onclick='toggleFavorito(<?= $pJson ?>)' style="position: absolute; top: 15px; right: 15px; background: none; border: none; z-index: 10; cursor: pointer;">
                                    🤍
                                </button>
                                
                                <a href="produto.php?id=<?= $p['id'] ?>">
                                    <div class="product-image-container" style="background: #f9f9f9; height: 320px; display: flex; align-items: center; justify-content: center;">
                                        <img src="<?= getCaminhoImagemOferta($p['imagem']) ?>" 
                                             alt="<?= htmlspecialchars($p['nome']) ?>" 
                                             style="max-width: 85%; max-height: 85%; object-fit: contain;">
                                    </div>
                                </a>
                                  <button class="btn-buy-overlay" onclick="window.location.href='produto.php?id=<?= $p['id'] ?>'">
                                    Ver Detalhes
                                </button>
                            </div>

                            <div class="product-details" style="padding: 15px 0;">
                                <h4 style="font-weight: 700; margin-bottom: 5px;"><?= htmlspecialchars($p['nome']) ?></h4>
                                
                                <div class="price-container">
                                    <?php if ($porcentagem > 0): ?>
                                        <span style="text-decoration: line-through; color: #bbb; font-size: 12px; margin-right: 8px;">
                                            R$ <?= number_format($p['preco_antigo'], 2, ',', '.') ?>
                                        </span>
                                    <?php endif; ?>
                                    <span style="font-weight: 900; color: #000;">
                                        R$ <?= number_format($p['preco'], 2, ',', '.') ?>
                                    </span>
                                </div>

                                <?php if (isset($_SESSION['usuario_nivel']) && $_SESSION['usuario_nivel'] === 'admin'): ?>
                                    <div style="margin-top: 15px; display: flex; gap: 8px; border-top: 1px solid #eee; padding-top: 12px;">
                                        <a href="editar_produto.php?id=<?= $p['id'] ?>" style="flex: 1; background: #000; color: #fff; text-align: center; padding: 8px; border-radius: 5px; font-size: 10px; text-decoration: none; font-weight: 800; text-transform: uppercase;">Editar</a>
                                        <a href="excluir_produto.php?id=<?= $p['id'] ?>" onclick="return confirm('Excluir oferta?')" style="flex: 1; border: 1px solid #ff4d4d; color: #ff4d4d; text-align: center; padding: 8px; border-radius: 5px; font-size: 10px; text-decoration: none; font-weight: 800; text-transform: uppercase;">Excluir</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
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