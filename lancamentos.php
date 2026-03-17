<?php 
require_once 'config.php'; 

// BUSCA DINÂMICA: Puxa os produtos mais recentes do banco
$query = $pdo->query("SELECT * FROM produtos ORDER BY id DESC LIMIT 12");
$produtos = $query->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FashionShop | Lançamentos</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php include 'header.php'; ?>

    <main>
        <section class="page-header" style="text-align: center; padding: 40px 20px;">
            <h1 style="letter-spacing: 2px; text-transform: uppercase;">Novidades</h1>
            <p style="color: #666;">Explore as últimas tendências e as novas coleções.</p>
        </section>

        <div class="filter-bar" style="display: flex; justify-content: center; gap: 10px; margin-bottom: 30px;">
            <button class="filter-tag">Tudo</button>
            <button class="filter-tag">Calçados</button>
            <button class="filter-tag">Vestuário</button>
            <button class="filter-tag">Equipamentos</button>
        </div>

        <section class="product-section">
            <div class="product-grid">
                
                <?php if (count($produtos) > 0): ?>
                    <?php foreach ($produtos as $p): ?>
                        <div class="product-card">
                            <div class="product-thumb">
                                <button class="btn-fav" onclick="toggleFavorito(this, '<?= addslashes($p['nome']) ?>', '<?= $p['preco'] ?>', '<?= $p['imagem'] ?>')">♡</button>
                                
                                <span class="badge-tag" style="position: absolute; top: 10px; left: 10px; background: #000; color: #fff; padding: 4px 8px; font-size: 10px; font-weight: bold; z-index: 10;">NOVO</span>

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
                                <p class="price">R$ <?= number_format($p['preco'], 2, ',', '.') ?></p>

                                <?php if (isset($_SESSION['usuario_nivel']) && $_SESSION['usuario_nivel'] === 'admin'): ?>
                                    <div style="margin-top: 10px; display: flex; gap: 5px; border-top: 1px solid #eee; padding-top: 10px;">
                                        <a href="editar_produto.php?id=<?= $p['id'] ?>" style="flex:1; background:#ffc107; color:#000; text-align:center; padding:5px; border-radius:4px; font-size:11px; text-decoration:none; font-weight:bold;">EDITAR</a>
                                        <a href="excluir_produto.php?id=<?= $p['id'] ?>" onclick="return confirm('Deseja realmente excluir este lançamento?')" style="flex:1; background:#ff4d4d; color:white; text-align:center; padding:5px; border-radius:4px; font-size:11px; text-decoration:none; font-weight:bold;">EXCLUIR</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="grid-column: 1/-1; text-align: center; padding: 50px; color: #666;">Aguardando novos lançamentos...</p>
                <?php endif; ?>

            </div>
        </section>
    </main>

    </body>
</html>