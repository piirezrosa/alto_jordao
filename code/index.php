<?php
require_once 'config.php';

$produtos = $pdo->query("SELECT * FROM produtos ORDER BY id DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
$destaque = $produtos[0] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alto Jordão | Home</title>
    <link rel="stylesheet" href="style.css?v=<?= time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="aj-page">

<?php include 'header.php'; ?>

<main>
    <section class="aj-hero">
        <div class="aj-wrap">
            <div class="aj-hero-card">
                <div class="aj-hero-copy">
                    <h1>Nova<br>coleção</h1>
                    <p>As principais peças da temporada. No PIX você paga 5% menos.</p>
                    <div class="aj-hero-cta">
                        <a class="aj-btn" href="lancamentos.php">Ver lançamentos</a>
                        <a class="aj-btn aj-btn--ghost" href="ofertas.php">Ver ofertas</a>
                    </div>
                </div>
                <?php if ($destaque): ?>
                <div class="aj-hero-pic">
                    <img src="<?= !empty($destaque['imagem']) ? 'img/produtos/' . htmlspecialchars($destaque['imagem']) : 'img/placeholder.jpg' ?>" alt="<?= htmlspecialchars($destaque['nome']) ?>">
                    <a class="aj-hero-tag" href="produto.php?id=<?= (int)$destaque['id'] ?>">
                        <b><?= htmlspecialchars($destaque['nome']) ?></b>
                        <span>R$ <?= number_format((float)$destaque['preco'], 2, ',', '.') ?></span>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="aj-section">
        <div class="aj-wrap">
            <div class="aj-tiles">
                <a class="aj-tile" href="masculino.php"><b>Masculino</b><i aria-hidden="true">&rarr;</i></a>
                <a class="aj-tile" href="feminino.php"><b>Feminino</b><i aria-hidden="true">&rarr;</i></a>
                <a class="aj-tile" href="kids.php"><b>Kids</b><i aria-hidden="true">&rarr;</i></a>
            </div>
        </div>
    </section>

    <section class="aj-section">
        <div class="aj-wrap">
            <div class="aj-section-head">
                <h2>Chegou agora</h2>
                <a href="lancamentos.php">Ver tudo</a>
            </div>
            <div class="aj-grid">
                <?php if ($produtos): foreach ($produtos as $p) { include 'card_produto.php'; } else: ?>
                    <p class="aj-none">Nenhum produto disponível no momento.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="aj-section">
        <div class="aj-wrap">
            <div class="aj-perks">
                <div class="aj-perk"><b>5% de desconto no PIX</b><span>Aplicado automaticamente nos produtos quando você escolhe PIX no checkout.</span></div>
                <div class="aj-perk"><b>Frete pelo seu CEP</b><span>Você vê as opções de entrega e o prazo antes de pagar.</span></div>
                <div class="aj-perk"><b>Troca e devolução</b><span>Peça direto na sua conta, em Meus pedidos.</span></div>
            </div>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
<script src="script.js?v=<?= time(); ?>"></script>
</body>
</html>
