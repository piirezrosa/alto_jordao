<?php
/**
 * Layout padrão das listagens da loja.
 * A página que inclui este arquivo define antes:
 *   $produtos (array), $aj_titulo, $aj_sub (opcional), $aj_vazio (texto), $aj_busca (bool, opcional)
 */
$aj_ordens = [
    'recentes' => 'Mais recentes',
    'menor'    => 'Menor preço',
    'maior'    => 'Maior preço',
    'nome'     => 'Nome (A–Z)',
];
$aj_ordem = $_GET['ordem'] ?? 'recentes';
if (!isset($aj_ordens[$aj_ordem])) $aj_ordem = 'recentes';
if ($aj_ordem === 'menor') usort($produtos, fn($a, $b) => (float)$a['preco'] <=> (float)$b['preco']);
if ($aj_ordem === 'maior') usort($produtos, fn($a, $b) => (float)$b['preco'] <=> (float)$a['preco']);
if ($aj_ordem === 'nome')  usort($produtos, fn($a, $b) => strcasecmp($a['nome'], $b['nome']));
$aj_n = count($produtos);
$aj_busca = $aj_busca ?? false;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($aj_titulo) ?> | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="aj-page">

<?php include 'header.php'; ?>

<main>
    <div class="aj-wrap">
        <section class="aj-pagehead">
            <h1><?= htmlspecialchars($aj_titulo) ?></h1>
            <?php if (!empty($aj_sub)): ?><p><?= htmlspecialchars($aj_sub) ?></p><?php endif; ?>
            <?php if ($aj_busca): ?>
                <form class="aj-search" action="busca.php" method="get" role="search">
                    <label class="aj-sr" for="ajQ">Buscar produtos</label>
                    <input id="ajQ" type="search" name="q" value="<?= htmlspecialchars($termo ?? '') ?>" placeholder="Nome, categoria ou descrição" autofocus>
                    <button class="aj-btn" type="submit">Buscar</button>
                </form>
            <?php endif; ?>
        </section>

        <div class="aj-toolbar">
            <span class="aj-count"><?= $aj_n ?> <?= $aj_n === 1 ? 'produto' : 'produtos' ?></span>
            <?php if ($aj_n > 1): ?>
            <form class="aj-sort" method="get">
                <?php foreach ($_GET as $k => $v): if ($k === 'ordem' || is_array($v)) continue; ?>
                    <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
                <?php endforeach; ?>
                <label class="aj-sr" for="ajOrdem">Ordenar por</label>
                <select id="ajOrdem" name="ordem" onchange="this.form.submit()">
                    <?php foreach ($aj_ordens as $v => $r): ?>
                        <option value="<?= $v ?>" <?= $v === $aj_ordem ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
        </div>

        <div class="aj-grid">
            <?php if ($aj_n): foreach ($produtos as $p) { include 'card_produto.php'; } else: ?>
                <div class="aj-emptybox">
                    <strong><?= htmlspecialchars($aj_vazio ?? 'Nenhum produto por aqui ainda.') ?></strong>
                    <p>Veja o que chegou de novo na loja.</p>
                    <a class="aj-btn" href="lancamentos.php">Ver lançamentos</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
<script src="script.js?v=<?= time(); ?>"></script>
</body>
</html>