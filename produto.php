<?php 
require_once 'config.php'; 

// 1. Pegar ID e validar
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$query = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
$query->execute([$id]);

$p = $query->fetch(PDO::FETCH_ASSOC);

if (!$p) { 
    header("Location: index.php"); 
    exit; 
}

// BUSCA PRODUTOS PARECIDOS (Mesma categoria)
$queryRelacionados = $pdo->prepare("SELECT * FROM produtos WHERE categoria = ? AND id != ? LIMIT 4");
$queryRelacionados->execute([$p['categoria'] ?? '', $id]);
$relacionados = $queryRelacionados->fetchAll(PDO::FETCH_ASSOC);

$strTamanho = $p['tamanho'] ?? $p['TAMANHO'] ?? '';
$strCor = $p['cor'] ?? $p['COR'] ?? '';

$tamanhosDisponiveis = array_filter(array_map('trim', explode(',', $strTamanho)));
$coresDisponiveis = array_filter(array_map('trim', explode(',', $strCor)));

$produtoJson = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');

// ── AVALIAÇÕES DO BANCO ───────────────────────
$avaliacoes = $pdo->prepare("
    SELECT a.*, 
           COALESCE(u.nome, a.titulo) as autor
    FROM avaliacoes a
    LEFT JOIN usuarios u ON a.usuario_id = u.id
    WHERE a.produto_id = ? AND a.status = 'aprovado'
    ORDER BY a.data_envio DESC
");
$avaliacoes->execute([$id]);
$avaliacoes = $avaliacoes->fetchAll(PDO::FETCH_ASSOC);

// Média e total
$total_aval  = count($avaliacoes);
$media_notas = $total_aval > 0 ? round(array_sum(array_column($avaliacoes, 'nota')) / $total_aval, 1) : 0;

// Usuário logado
$usuario_logado   = isset($_SESSION['usuario_id']);
$usuario_nome     = $_SESSION['usuario_nome'] ?? '';
$ja_avaliou       = false;
if ($usuario_logado) {
    $check = $pdo->prepare("SELECT id FROM avaliacoes WHERE produto_id = ? AND usuario_id = ?");
    $check->execute([$id, $_SESSION['usuario_id']]);
    $ja_avaliou = (bool)$check->fetchColumn();
}
?>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($p['nome'] ?? 'Produto') ?> | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* Seus estilos originais mantidos */
        .produto-container { display: flex; flex-wrap: wrap; gap: 60px; max-width: 1200px; margin: 60px auto; padding: 0 20px; align-items: flex-start; }
        .produto-media { flex: 1.2; min-width: 350px; }
        .produto-info { flex: 0.8; min-width: 350px; }
        .glass-card { background: #fff; border-radius: 40px; padding: 50px; position: relative; box-shadow: 0 20px 60px rgba(0,0,0,0.03); text-align: center; border: 1px solid #f0f0f0; }
        .glass-card img { width: 100%; max-height: 550px; object-fit: contain; }
        .brand-tag { color: #bbb; font-size: 11px; font-weight: 900; letter-spacing: 3px; text-transform: uppercase; }
        .product-title { font-size: 2.8rem; font-weight: 900; margin: 15px 0; text-transform: uppercase; letter-spacing: -1.5px; line-height: 1; }
        .product-price { font-size: 1.8rem; font-weight: 400; color: #000; margin-bottom: 40px; font-family: 'Inter', sans-serif; }
        .selection-label { font-size: 10px; font-weight: 900; letter-spacing: 1.5px; margin-bottom: 15px; display: block; color: #888; }
        .chips-container { display: flex; gap: 12px; margin-bottom: 35px; flex-wrap: wrap; min-height: 20px; }
        .chip-size { min-width: 55px; height: 55px; display: flex; align-items: center; justify-content: center; border: 2px solid #eee; background: #fff; border-radius: 15px; font-weight: 800; font-size: 14px; cursor: pointer; transition: 0.3s; }
        .chip-size.active { border-color: #000; background: #000; color: #fff; }
        .chip-color { width: 40px; height: 40px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 0 0 2px #eee; cursor: pointer; transition: 0.3s; position: relative; }
        .chip-color.active { box-shadow: 0 0 0 2px #000; transform: scale(1.15); }
        .chip-color span { position: absolute; bottom: -25px; left: 50%; transform: translateX(-50%); font-size: 9px; font-weight: 800; text-transform: uppercase; white-space: nowrap; opacity: 0; transition: 0.3s; }
        .chip-color.active span { opacity: 1; }
        .btn-add-cart { width: 100%; padding: 25px; background: #000; color: #fff; border: none; border-radius: 50px; font-weight: 900; font-size: 15px; letter-spacing: 2px; cursor: pointer; transition: 0.4s; margin-top: 30px; text-transform: uppercase; }
        .btn-add-cart:hover { background: #333; transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.2); }

        /* Estilos Novos para Comentários e Relacionados */
        .divider { max-width: 1200px; margin: 80px auto 40px; padding: 0 20px; display: flex; align-items: center; gap: 20px; }
        .divider h2 { font-weight: 900; text-transform: uppercase; letter-spacing: 2px; font-size: 1.2rem; white-space: nowrap; }
        .divider .line { flex: 1; height: 1px; background: #eee; }

        .relacionados-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 30px; max-width: 1200px; margin: 0 auto 80px; padding: 0 20px; }
        
        .reviews-section { display: grid; grid-template-columns: 1fr 1.5fr; gap: 60px; max-width: 1200px; margin: 0 auto 100px; padding: 0 20px; }
        .form-review { background: #f9f9f9; padding: 40px; border-radius: 30px; }
        .form-review input, .form-review textarea, .form-review select { width: 100%; padding: 15px; border-radius: 12px; border: 1px solid #ddd; margin-bottom: 15px; font-family: inherit; }
        .review-item { padding: 25px 0; border-bottom: 1px solid #eee; animation: fadeIn 0.5s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body> 

<?php include 'header.php'; ?>

<main class="produto-container">
    <div class="produto-media">
        <div class="glass-card">
            <button class="btn-fav" data-id="<?= $p['id'] ?>" style="position: absolute; right: 40px; top: 40px; font-size: 28px; background: none; border: none; cursor: pointer;" 
                    onclick='toggleFavorito(<?= $produtoJson ?>)'>🤍</button>
            <img src="<?= !empty($p['imagem']) ? 'img/produtos/'.$p['imagem'] : 'img/produtos/default.png' ?>" alt="<?= htmlspecialchars($p['nome']) ?>">
        </div>
    </div>

    <div class="produto-info">
        <span class="brand-tag">Alto Jordão • Originals</span>
        <h1 class="product-title"><?= htmlspecialchars($p['nome']) ?></h1>
        <p class="product-price">R$ <?= number_format($p['preco'], 2, ',', '.') ?></p>

        <?php if(!empty($tamanhosDisponiveis)): ?>
        <div class="selection-section">
            <label class="selection-label">TAMANHO DISPONÍVEL</label>
            <div class="chips-container" id="container-tamanhos">
                <?php foreach ($tamanhosDisponiveis as $t): ?>
                    <button type="button" class="chip-size" onclick="selecionarUnico(this, 'tamanho', '<?= $t ?>')">
                        <?= $t ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <input type="hidden" id="selected-tamanho">
        </div>
        <?php endif; ?>

        <?php if(!empty($coresDisponiveis)): ?>
        <div class="selection-section" style="margin-top: 20px;">
            <label class="selection-label">COR</label>
            <div class="chips-container" id="container-cores">
                <?php foreach ($coresDisponiveis as $cor): 
                    $corNome = trim($cor);
                    $corChave = strtolower($corNome);
                    $coresMap = [
                        'preto'=>'black', 'branco'=>'white', 'azul'=>'blue', 'vermelho'=>'red', 
                        'cinza'=>'#808080', 'marrom'=>'#8B4513', 'verde'=>'green', 'amarelo'=>'yellow'
                    ];
                    $corCss = $coresMap[$corChave] ?? $corChave;
                ?>
                    <div class="chip-color" 
                         style="background-color: <?= $corCss ?>;" 
                         onclick="selecionarUnico(this, 'cor', '<?= $corNome ?>')"
                         title="<?= ucfirst($corNome) ?>">
                         <span><?= ucfirst($corNome) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" id="selected-cor">
        </div>
        <?php endif; ?>

        <button class="btn-add-cart" onclick='validarEComprar(<?= $produtoJson ?>)'>
            Adicionar à Sacola
        </button>
    </div>
</main>

<div class="divider">
    <h2>Quem viu, também gostou</h2>
    <div class="line"></div>
</div>

<section class="relacionados-grid">
    <?php foreach ($relacionados as $rel): ?>
        <div class="product-card" onclick="window.location.href='produto.php?id=<?= $rel['id'] ?>'" style="cursor: pointer;">
            <div style="background: #f9f9f9; border-radius: 25px; padding: 20px; text-align: center;">
                <img src="img/produtos/<?= $rel['imagem'] ?>" style="width: 100%; height: 180px; object-fit: contain;">
            </div>
            <h4 style="margin: 15px 0 5px; font-weight: 800; text-transform: uppercase; font-size: 14px;"><?= htmlspecialchars($rel['nome']) ?></h4>
            <p style="font-weight: 500;">R$ <?= number_format($rel['preco'], 2, ',', '.') ?></p>
        </div>
    <?php endforeach; ?>
</section>

<!-- ── SEÇÃO DE AVALIAÇÕES ───────────────────── -->
<div class="divider">
    <h2>Avaliações</h2>
    <div class="line"></div>
</div>

<section class="reviews-section">

    <!-- FORMULÁRIO -->
    <div class="form-review">
        <h3 style="font-weight:900; margin-bottom:6px;">DEIXE SUA OPINIÃO</h3>

        <?php if($total_aval > 0): ?>
        <!-- Resumo de nota média -->
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:24px; padding:16px; background:#f9f9f9; border-radius:16px;">
            <span style="font-size:36px; font-weight:900; letter-spacing:-1px;"><?= $media_notas ?></span>
            <div>
                <div style="color:#000; font-size:18px; letter-spacing:2px;">
                    <?php for($i=1;$i<=5;$i++) echo $i <= round($media_notas) ? '⭐' : '☆'; ?>
                </div>
                <small style="color:#999; font-size:11px; font-weight:700;"><?= $total_aval ?> avaliação(ões)</small>
            </div>
        </div>
        <?php endif; ?>

        <?php if($ja_avaliou): ?>
        <div style="background:#f0fdf4; border:1px solid #c8e6c9; padding:16px; border-radius:14px; color:#2e7d32; font-size:13px; font-weight:700;">
            ✓ Você já avaliou este produto. Obrigado!
        </div>

        <?php else: ?>
        <form id="commentForm">
            <input type="hidden" id="produtoId" value="<?= $p['id'] ?>">

            <?php if(!$usuario_logado): ?>
            <input type="text" id="revName" placeholder="Seu nome" required
                   style="width:100%; padding:14px; border-radius:12px; border:1px solid #ddd; margin-bottom:14px; font-family:inherit;">
            <?php else: ?>
            <input type="hidden" id="revName" value="<?= htmlspecialchars($usuario_nome) ?>">
            <p style="font-size:12px; color:#999; margin-bottom:14px; font-weight:600;">
                Avaliando como <strong style="color:#000;"><?= htmlspecialchars(explode(' ',$usuario_nome)[0]) ?></strong>
            </p>
            <?php endif; ?>

            <select id="revStars" style="width:100%; padding:14px; border-radius:12px; border:1px solid #ddd; margin-bottom:14px; font-family:inherit; background:#fff;">
                <option value="5">⭐⭐⭐⭐⭐ — Excelente</option>
                <option value="4">⭐⭐⭐⭐ — Muito bom</option>
                <option value="3">⭐⭐⭐ — Bom</option>
                <option value="2">⭐⭐ — Regular</option>
                <option value="1">⭐ — Péssimo</option>
            </select>

            <textarea id="revText" rows="4"
                      placeholder="O que você achou do produto? Qualidade, tamanho, entrega..."
                      required
                      style="width:100%; padding:14px; border-radius:12px; border:1px solid #ddd; margin-bottom:14px; font-family:inherit; resize:vertical;"></textarea>

            <button type="submit" class="btn-add-cart" style="margin-top:0; padding:15px;" id="btnEnviarAval">
                PUBLICAR AVALIAÇÃO
            </button>

            <div id="avalMsg" style="display:none; margin-top:14px; padding:14px; border-radius:12px; font-size:13px; font-weight:700;"></div>
        </form>
        <?php endif; ?>
    </div>

    <!-- LISTA DE AVALIAÇÕES -->
    <div id="reviewsList">
        <?php if(empty($avaliacoes)): ?>
        <p id="noReviewsMessage" style="color:#999; font-style:italic;">
            Nenhuma avaliação aprovada ainda. Seja o primeiro a avaliar!
        </p>
        <?php endif; ?>

        <?php foreach($avaliacoes as $av): ?>
        <div class="review-item">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                <div>
                    <strong style="text-transform:uppercase; font-size:12px; letter-spacing:1px; display:block;">
                        <?= htmlspecialchars($av['autor'] ?: 'Anônimo') ?>
                    </strong>
                    <small style="color:#bbb; font-size:11px;">
                        <?= date('d/m/Y', strtotime($av['data_envio'])) ?>
                    </small>
                </div>
                <span style="color:#000; font-size:16px; letter-spacing:1px;">
                    <?php for($i=1;$i<=5;$i++) echo $i <= $av['nota'] ? '⭐' : '☆'; ?>
                </span>
            </div>
            <p style="color:#555; font-size:14px; line-height:1.6; margin:0;">
                <?= nl2br(htmlspecialchars($av['comentario'])) ?>
            </p>
        </div>
        <?php endforeach; ?>
    </div>

</section>

<script src="script.js?v=<?= time() ?>"></script>
<script>
    function selecionarUnico(el, tipo, valor) {
        const parent = el.parentElement;
        const selector = el.classList.contains('chip-size') ? '.chip-size' : '.chip-color';
        parent.querySelectorAll(selector).forEach(s => s.classList.remove('active'));
        el.classList.add('active');
        const input = document.getElementById('selected-' + tipo);
        if(input) input.value = valor;
    }

    function validarEComprar(produto) {
        const inputTam = document.getElementById('selected-tamanho');
        const inputCor = document.getElementById('selected-cor');
        const temOpcaoTam = !!document.getElementById('container-tamanhos');
        const temOpcaoCor = !!document.getElementById('container-cores');
        const vTam = inputTam ? inputTam.value : '';
        const vCor = inputCor ? inputCor.value : '';

        if ((temOpcaoTam && !vTam) || (temOpcaoCor && !vCor)) {
            alert("Por favor, selecione o tamanho e a cor.");
            return;
        }

        const itemFinal = { ...produto, tamanho_escolhido: vTam || 'N/A', cor_escolhida: vCor || 'N/A' };
        if (typeof adicionarAoCarrinho === "function") {
            adicionarAoCarrinho(itemFinal);
        } else {
            alert("Produto adicionado!");
        }
    }

    // ── ENVIO DE AVALIAÇÃO VIA AJAX ─────────────
    const form = document.getElementById('commentForm');
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const btn     = document.getElementById('btnEnviarAval');
            const msgBox  = document.getElementById('avalMsg');
            const nome    = document.getElementById('revName').value.trim();
            const nota    = document.getElementById('revStars').value;
            const texto   = document.getElementById('revText').value.trim();
            const prodId  = document.getElementById('produtoId').value;

            if (texto.length < 5) {
                msgBox.style.display = 'block';
                msgBox.style.background = '#fff0f0';
                msgBox.style.color = '#c62828';
                msgBox.textContent = '⚠ Escreva pelo menos 5 caracteres.';
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Enviando...';

            const formData = new FormData();
            formData.append('produto_id', prodId);
            formData.append('nome',       nome);
            formData.append('estrelas',   nota);
            formData.append('comentario', texto);

            try {
                const res  = await fetch('salvar_avaliacao.php', { method: 'POST', body: formData });
                const data = await res.json();

                msgBox.style.display = 'block';

                if (data.status === 'success') {
                    msgBox.style.background = '#f0fdf4';
                    msgBox.style.color      = '#2e7d32';
                    msgBox.textContent      = '✓ ' + data.message;
                    form.reset();
                    btn.textContent = 'ENVIADO ✓';

                    // Se aprovação automática, renderiza na tela sem recarregar
                    if (!data.moderacao && data.avaliacao) {
                        const av  = data.avaliacao;
                        const div = document.createElement('div');
                        div.className = 'review-item';
                        const stars = '⭐'.repeat(av.nota) + '☆'.repeat(5 - av.nota);
                        div.innerHTML = `
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                                <div>
                                    <strong style="text-transform:uppercase; font-size:12px; letter-spacing:1px; display:block;">${av.nome}</strong>
                                    <small style="color:#bbb; font-size:11px;">${av.data_envio}</small>
                                </div>
                                <span style="color:#000; font-size:16px; letter-spacing:1px;">${stars}</span>
                            </div>
                            <p style="color:#555; font-size:14px; line-height:1.6; margin:0;">${av.comentario}</p>
                        `;
                        const noMsg = document.getElementById('noReviewsMessage');
                        if (noMsg) noMsg.remove();
                        document.getElementById('reviewsList').prepend(div);
                    }
                } else {
                    msgBox.style.background = '#fff0f0';
                    msgBox.style.color      = '#c62828';
                    msgBox.textContent      = '⚠ ' + data.message;
                    btn.disabled    = false;
                    btn.textContent = 'PUBLICAR AVALIAÇÃO';
                }

            } catch (err) {
                msgBox.style.display    = 'block';
                msgBox.style.background = '#fff0f0';
                msgBox.style.color      = '#c62828';
                msgBox.textContent      = '⚠ Erro de conexão. Tente novamente.';
                btn.disabled    = false;
                btn.textContent = 'PUBLICAR AVALIAÇÃO';
            }
        });
    }
</script>
</body>
</html>