<?php 
require_once 'config.php'; 

/**
 * LOGICA DE BUSCA:
 * Tenta buscar por preço antigo (ofertas reais) ou categoria 'ofertas'.
 */
try {
    $query = $pdo->query("SELECT * FROM produtos WHERE (preco_antigo > preco OR categoria = 'ofertas') ORDER BY id DESC");
} catch (PDOException $e) {
    // Caso a coluna preco_antigo não exista no seu banco ainda
    $query = $pdo->query("SELECT * FROM produtos WHERE categoria = 'ofertas' ORDER BY id DESC");
}
$produtos = $query->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FashionShop | Ofertas</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php include 'header.php'; ?>

    <main>
        <section class="page-header" style="text-align: center; padding: 40px 20px;">
            <span class="sub-label" style="color: #ff4d4d; font-weight: bold; letter-spacing: 2px; text-transform: uppercase;">Preços Especiais</span>
            <h1>Ofertas Imperdíveis</h1>
            <p>Produtos selecionados com as melhores condições.</p>
        </section>

        <div class="filter-bar" style="display: flex; justify-content: center; gap: 10px; margin-bottom: 30px;">
            <button class="filter-tag">Tudo</button>
            <button class="filter-tag">Tênis</button>
            <button class="filter-tag">Vestuário</button>
        </div>

        <section class="product-section">
            <div class="product-grid">
                
                <?php if (count($produtos) > 0): ?>
                    <?php foreach ($produtos as $p): ?>
                        <div class="product-card">
                            <div class="product-thumb">
                                <button class="btn-fav" onclick="toggleFavorito(this, '<?= addslashes($p['nome']) ?>', '<?= $p['preco'] ?>', '<?= $p['imagem'] ?>')">♡</button>
                                
                                <span class="badge-tag sale" style="position: absolute; top: 10px; left: 10px; background: #ff4d4d; color: #fff; padding: 4px 8px; font-size: 10px; font-weight: bold; z-index: 10;">OFERTA</span>

                                <div class="product-image-container">
                                    <?php 
                                        // Chama a função centralizada no config.php
                                        echo exibirImagem($p['imagem']); 
                                    ?>
                                </div>
                                
                                <div class="product-actions">
                                    <button class="btn-buy-only" onclick="adicionarAoCarrinho({id: <?= $p['id'] ?>, nome: '<?= addslashes($p['nome']) ?>', preco: '<?= $p['preco'] ?>', img: '<?= $p['imagem'] ?>'})">
                                        Comprar
                                    </button>
                                </div>
                            </div>

                            <div class="product-details">
                                <p class="category"><?= htmlspecialchars($p['categoria']) ?></p>
                                <h4><?= htmlspecialchars($p['nome']) ?></h4>
                                
                                <div class="price-container" style="display: flex; align-items: center; gap: 10px; margin-top: 5px;">
                                    <?php if (isset($p['preco_antigo']) && $p['preco_antigo'] > $p['preco']): ?>
                                        <span class="old-price" style="text-decoration: line-through; color: #999; font-size: 13px;">
                                            R$ <?= number_format($p['preco_antigo'], 2, ',', '.') ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="price discount" style="color: #ff4d4d; font-weight: bold; font-size: 18px;">
                                        R$ <?= number_format($p['preco'], 2, ',', '.') ?>
                                    </span>
                                </div>

                                <?php if (isset($_SESSION['usuario_nivel']) && $_SESSION['usuario_nivel'] === 'admin'): ?>
                                    <div style="margin-top: 12px; display: flex; gap: 5px; border-top: 1px solid #eee; padding-top: 10px;">
                                        <a href="editar_produto.php?id=<?= $p['id'] ?>" style="flex:1; background:#ffc107; color:#000; text-align:center; padding:5px; border-radius:4px; font-size:11px; text-decoration:none; font-weight:bold;">EDITAR</a>
                                        <a href="excluir_produto.php?id=<?= $p['id'] ?>" onclick="return confirm('Excluir esta oferta?')" style="flex:1; background:#ff4d4d; color:white; text-align:center; padding:5px; border-radius:4px; font-size:11px; text-decoration:none; font-weight:bold;">EXCLUIR</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 80px 20px;">
                        <p style="color: #666; font-size: 18px;">Nenhuma oferta encontrada no momento.</p>
                        <a href="index.php" style="color: #000; text-decoration: underline; font-weight: bold;">Voltar para a página inicial</a>
                    </div>
                <?php endif; ?>

            </div>
        </section>
    </main>

    </body>
</html>