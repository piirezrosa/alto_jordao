<?php
// Card de produto da vitrine. Espera $p (linha de `produtos`).
$cp_json  = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
$cp_img   = !empty($p['imagem']) ? 'img/produtos/' . $p['imagem'] : 'img/placeholder.jpg';
$cp_off   = (!empty($p['preco_antigo']) && (float)$p['preco_antigo'] > (float)$p['preco'] && (float)$p['preco'] > 0)
            ? (int)round(100 - ($p['preco'] * 100 / $p['preco_antigo'])) : 0;
$cp_admin = in_array($_SESSION['usuario_nivel'] ?? '', ['admin', 'superadmin'], true);
?>
<article class="aj-card">
    <a class="aj-card-pic" href="produto.php?id=<?= (int)$p['id'] ?>" aria-label="<?= htmlspecialchars($p['nome']) ?>">
        <img src="<?= htmlspecialchars($cp_img) ?>" alt="<?= htmlspecialchars($p['nome']) ?>" loading="lazy" onerror="this.style.opacity='0'">
        <?php if ($cp_off > 0): ?><span class="aj-badge">-<?= $cp_off ?>%</span><?php endif; ?>
    </a>
    <button class="btn-fav" type="button" data-id="<?= (int)$p['id'] ?>" aria-label="Favoritar" onclick='toggleFavorito(<?= $cp_json ?>)'></button>
    <div class="aj-card-info">
        <?php if (!empty($p['categoria'])): ?><p class="aj-card-cat"><?= htmlspecialchars($p['categoria']) ?></p><?php endif; ?>
        <a class="aj-card-name" href="produto.php?id=<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></a>
        <p class="aj-card-price">
            R$ <?= number_format((float)$p['preco'], 2, ',', '.') ?>
            <?php if ($cp_off > 0): ?><s>R$ <?= number_format((float)$p['preco_antigo'], 2, ',', '.') ?></s><?php endif; ?>
        </p>
        <?php if ($cp_admin): ?>
            <div class="aj-card-admin">
                <a href="editar_produto.php?id=<?= (int)$p['id'] ?>">Editar</a>
                <a class="del" href="excluir_produto.php?id=<?= (int)$p['id'] ?>" onclick="return confirm('Deseja excluir este produto?')">Excluir</a>
            </div>
        <?php endif; ?>
    </div>
</article>
