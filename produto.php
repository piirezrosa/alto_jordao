<?php 
require_once 'config.php'; 

// Pega o ID da URL de forma segura
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$query = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
$query->execute([$id]);
$p = $query->fetch();

if (!$p) { 
    header("Location: index.php"); 
    exit; 
}

// Preparação de Variantes (Cores e Tamanhos)
$coresDisponiveis = !empty($p['cor']) ? explode(',', $p['cor']) : [];
$tamanhosDisponiveis = !empty($p['tamanho']) ? explode(',', $p['tamanho']) : [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($p['nome']) ?> | Alto Jordão</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
</head>
<?php include 'header.php'; ?>

<main class="produto-container">
    <div class="produto-media">
        <div class="glass-card">
            <button class="fav-icon" onclick="toggleFavorito(<?= $p['id'] ?>)">♡</button>
            <?= exibirImagem($p['imagem']) ?>
        </div>
    </div>

    <div class="produto-info">
        <span class="brand-tag">ALTO JORDÃO ORIGINALS</span>
        <h1 class="product-title"><?= htmlspecialchars($p['nome']) ?></h1>
        <p class="product-price">R$ <?= number_format($p['preco'], 2, ',', '.') ?></p>

        <div class="selection-section">
            <label>ESCOLHA O TAMANHO</label>
            <div class="chips-container">
                <?php foreach ($tamanhosDisponiveis as $t): ?>
                    <button class="chip" onclick="selecionarTamanho(this, '<?= trim($t) ?>')"><?= trim($t) ?></button>
                <?php endforeach; ?>
            </div>
            <input type="hidden" id="tamanho-selecionado">
        </div>

        <div class="action-buttons">
            <button class="btn-black" onclick='validarEAdicionar(<?= json_encode($p) ?>)'>
                ADICIONAR AO CARRINHO
            </button>
            <button class="btn-outline">GUIA DE MEDIDAS</button>
        </div>

        <div class="product-description">
            <h3>DESCRIÇÃO</h3>
            <p><?= nl2br(htmlspecialchars($p['descricao'])) ?></p>
        </div>
    </div>
</main>

<script src="script.js"></script>
</html>