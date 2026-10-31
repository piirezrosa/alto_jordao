<?php 
require_once 'config.php'; 
require_once 'loja_helpers.php';   // LOJA_DESCONTO_PIX

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
if (!$relacionados) {   // sem outros da mesma categoria: mostra os mais recentes
    $queryRelacionados = $pdo->prepare("SELECT * FROM produtos WHERE id != ? ORDER BY id DESC LIMIT 4");
    $queryRelacionados->execute([$id]);
    $relacionados = $queryRelacionados->fetchAll(PDO::FETCH_ASSOC);
}

$strTamanho = $p['tamanho'] ?? $p['TAMANHO'] ?? '';
$strCor = $p['cor'] ?? $p['COR'] ?? '';

$tamanhosDisponiveis = array_filter(array_map('trim', explode(',', $strTamanho)));
$coresDisponiveis = array_filter(array_map('trim', explode(',', $strCor)));

$produtoJson = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');

// ── AVALIAÇÕES DO BANCO ───────────────────────────────────
$avaliacoes = [];
$total_aval  = 0;
$media_notas = 0;

try {
    // Garante que a tabela existe antes de consultar
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS avaliacoes (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            produto_id  INT             NOT NULL,
            usuario_id  INT             DEFAULT NULL,
            pedido_id   INT             DEFAULT NULL,
            nota        TINYINT         NOT NULL,
            titulo      VARCHAR(150)    DEFAULT NULL,
            comentario  TEXT            DEFAULT NULL,
            status      ENUM('pendente','aprovado','reprovado') DEFAULT 'pendente',
            data        DATETIME        DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_produto (produto_id),
            INDEX idx_status  (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $stmtAval = $pdo->prepare("
        SELECT a.*,
               COALESCE(u.nome, a.titulo, 'Anônimo') as autor
        FROM avaliacoes a
        LEFT JOIN usuarios u ON a.usuario_id = u.id
        WHERE a.produto_id = ?
          AND a.status = 'aprovado'
        ORDER BY a.data DESC
    ");
    $stmtAval->execute([$id]);
    $avaliacoes  = $stmtAval->fetchAll(PDO::FETCH_ASSOC);
    $total_aval  = count($avaliacoes);
    $media_notas = $total_aval > 0
        ? round(array_sum(array_column($avaliacoes, 'nota')) / $total_aval, 1)
        : 0;

} catch (Exception $e) {
    error_log("[PRODUTO AVALIACOES ERROR] produto_id=$id: " . $e->getMessage());
}

// Usuário logado — verifica se já avaliou
$usuario_logado = isset($_SESSION['usuario_id']);
$usuario_nome   = $_SESSION['usuario_nome'] ?? '';
$ja_avaliou     = false;

if ($usuario_logado) {
    try {
        $check = $pdo->prepare(
            "SELECT id FROM avaliacoes WHERE produto_id = ? AND usuario_id = ?"
        );
        $check->execute([$id, $_SESSION['usuario_id']]);
        $ja_avaliou = (bool)$check->fetchColumn();
    } catch (Exception $e) {}
}

$desc_pct   = ((float)($p['preco_antigo'] ?? 0) > (float)$p['preco'] && (float)$p['preco'] > 0) ? (int)round(100 - $p['preco'] * 100 / $p['preco_antigo']) : 0;
$preco_pix  = round((float)$p['preco'] * (1 - LOJA_DESCONTO_PIX), 2);
$estoque    = isset($p['estoque']) ? (int)$p['estoque'] : null;
$esgotado   = $estoque !== null && $estoque <= 0;
$img_prod   = !empty($p['imagem']) ? 'img/produtos/' . $p['imagem'] : 'img/placeholder.jpg';
$generos    = ['masculino' => ['masculino.php', 'Masculino'], 'feminino' => ['feminino.php', 'Feminino'], 'kids' => ['kids.php', 'Kids'], 'unissex' => ['lancamentos.php', 'Lançamentos']];
$crumb_g    = $generos[strtolower((string)($p['genero'] ?? ''))] ?? ['lancamentos.php', 'Lançamentos'];
$coresMap = [
    'preto' => '#000000', 'branco' => '#FFFFFF', 'off-white' => '#FDFBF7', 'bege' => '#F5F5DC', 'nude' => '#E3BC9A',
    'caramelo' => '#C68642', 'marrom' => '#8B4513', 'terracota' => '#E2725B', 'cinza-chumbo' => '#36454F',
    'cinza-claro' => '#D3D3D3', 'azul-marinho' => '#000080', 'jeans' => '#1560BD', 'verde-militar' => '#4B5320',
    'verde-oliva' => '#808000', 'vinho' => '#800020', 'mostarda' => '#E1AD01', 'rosa-blush' => '#FFD1DC',
    'lavanda' => '#E6E6FA', 'coral' => '#FF7F50', 'khaki' => '#C3B091', 'marsala' => '#955251', 'azul-bebê' => '#9BB2C7',
    'vermelho' => '#FF0000', 'azul-royal' => '#4169E1', 'jeans-claro' => '#5DADEC', 'jeans-escuro' => '#1C3F95',
];
function aj_estrelas($n) { $n = (int)round($n); return str_repeat('★', $n) . '<i>' . str_repeat('★', 5 - $n) . '</i>'; }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($p['nome'] ?? 'Produto') ?> | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="aj-page">

<?php include 'header.php'; ?>

<main class="aj-wrap">
    <nav class="aj-crumbs" aria-label="Você está em">
        <a href="index.php">Início</a><span>/</span>
        <a href="<?= $crumb_g[0] ?>"><?= $crumb_g[1] ?></a><span>/</span>
        <span><?= htmlspecialchars($p['nome']) ?></span>
    </nav>

    <section class="aj-pdp">
        <div class="aj-pdp-pic">
            <img src="<?= htmlspecialchars($img_prod) ?>" alt="<?= htmlspecialchars($p['nome']) ?>" onerror="this.style.opacity='0'">
            <?php if ($desc_pct > 0): ?><span class="aj-badge">-<?= $desc_pct ?>%</span><?php endif; ?>
            <button class="btn-fav" type="button" data-id="<?= (int)$p['id'] ?>" aria-label="Favoritar" onclick='toggleFavorito(<?= $produtoJson ?>)'></button>
        </div>

        <div class="aj-pdp-info">
            <h1><?= htmlspecialchars($p['nome']) ?></h1>

            <?php if ($total_aval > 0): ?>
                <a class="aj-rating" href="#avaliacoes"><span class="aj-stars"><?= aj_estrelas($media_notas) ?></span> <?= $media_notas ?> (<?= $total_aval ?>)</a>
            <?php endif; ?>

            <div class="aj-pdp-price">
                <b>R$ <?= number_format((float)$p['preco'], 2, ',', '.') ?></b>
                <?php if ($desc_pct > 0): ?><s>R$ <?= number_format((float)$p['preco_antigo'], 2, ',', '.') ?></s><?php endif; ?>
            </div>
            <p class="aj-pix">R$ <?= number_format($preco_pix, 2, ',', '.') ?> no PIX (<?= (int)round(LOJA_DESCONTO_PIX * 100) ?>% de desconto)</p>

            <?php if (!empty($tamanhosDisponiveis)): ?>
            <div class="aj-opt">
                <div class="aj-opt-head">Tamanho</div>
                <div class="aj-chips" id="container-tamanhos" role="radiogroup" aria-label="Tamanho">
                    <?php foreach ($tamanhosDisponiveis as $t): ?>
                        <button type="button" class="aj-size" role="radio" aria-checked="false" data-valor="<?= htmlspecialchars($t) ?>" onclick="selecionarUnico(this,'tamanho')"><?= htmlspecialchars($t) ?></button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="selected-tamanho">
            </div>
            <?php endif; ?>

            <?php if (!empty($coresDisponiveis)): ?>
            <div class="aj-opt">
                <div class="aj-opt-head">Cor <span id="corEscolhida"></span></div>
                <div class="aj-chips" id="container-cores" role="radiogroup" aria-label="Cor">
                    <?php foreach ($coresDisponiveis as $cor):
                        $corNome = trim($cor);
                        $corCss  = $coresMap[strtolower($corNome)] ?? strtolower($corNome);
                    ?>
                        <button type="button" class="aj-swatch" role="radio" aria-checked="false" aria-label="<?= htmlspecialchars(ucfirst($corNome)) ?>"
                                title="<?= htmlspecialchars(ucfirst($corNome)) ?>" data-valor="<?= htmlspecialchars($corNome) ?>"
                                style="background-color: <?= htmlspecialchars($corCss) ?>;" onclick="selecionarUnico(this,'cor')"></button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="selected-cor">
            </div>
            <?php endif; ?>

            <div class="aj-buy">
                <p class="aj-warn" id="msgEscolha" role="alert"></p>
                <button class="aj-btn aj-btn--block" type="button" <?= $esgotado ? 'disabled' : '' ?> onclick='validarEComprar(<?= $produtoJson ?>)'>
                    <?= $esgotado ? 'Esgotado' : 'Adicionar à sacola' ?>
                </button>
            </div>
            <?php if (!$esgotado && $estoque !== null && $estoque <= 3): ?>
                <p class="aj-stock">Restam só <?= $estoque ?> <?= $estoque === 1 ? 'unidade' : 'unidades' ?>.</p>
            <?php endif; ?>

            <div class="aj-more">
                <?php if (!empty(trim((string)($p['descricao'] ?? '')))): ?>
                <details open>
                    <summary>Descrição</summary>
                    <p><?= nl2br(htmlspecialchars($p['descricao'])) ?></p>
                </details>
                <?php endif; ?>
                <details>
                    <summary>Entrega e pagamento</summary>
                    <p>O frete é calculado no checkout pelo seu CEP, com prazo de cada opção. Você paga por PIX (com <?= (int)round(LOJA_DESCONTO_PIX * 100) ?>% de desconto), cartão de crédito ou boleto.</p>
                </details>
                <details>
                    <summary>Trocas e devoluções</summary>
                    <p>Depois de receber o pedido, você pode pedir a troca ou devolução em Minha conta, na página Meus pedidos.</p>
                </details>
            </div>
        </div>
    </section>

    <?php if ($relacionados): ?>
    <section class="aj-section">
        <div class="aj-section-head"><h2>Você também pode gostar</h2></div>
        <div class="aj-grid">
            <?php $p_atual = $p; foreach ($relacionados as $p) { include 'card_produto.php'; } $p = $p_atual; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="aj-section" id="avaliacoes">
        <div class="aj-section-head"><h2>Avaliações</h2></div>
        <div class="aj-reviews">
            <div>
                <?php if ($total_aval > 0): ?>
                <div class="aj-score">
                    <b><?= $media_notas ?></b>
                    <div><span class="aj-stars"><?= aj_estrelas($media_notas) ?></span><small><?= $total_aval ?> <?= $total_aval === 1 ? 'avaliação' : 'avaliações' ?></small></div>
                </div>
                <?php endif; ?>

                <div class="aj-rform">
                    <?php if ($ja_avaliou): ?>
                        <h3>Obrigado pela avaliação</h3>
                        <p class="who">Você já avaliou este produto.</p>
                    <?php else: ?>
                    <h3>Avalie este produto</h3>
                    <form id="commentForm">
                        <input type="hidden" id="produtoId" value="<?= (int)$p['id'] ?>">
                        <?php if (!$usuario_logado): ?>
                            <label class="aj-sr" for="revName">Seu nome</label>
                            <input type="text" id="revName" placeholder="Seu nome" required>
                        <?php else: ?>
                            <input type="hidden" id="revName" value="<?= htmlspecialchars($usuario_nome) ?>">
                            <p class="who">Avaliando como <strong><?= htmlspecialchars(explode(' ', $usuario_nome)[0]) ?></strong></p>
                        <?php endif; ?>
                        <label class="aj-sr" for="revStars">Nota</label>
                        <select id="revStars">
                            <option value="5">5 estrelas: excelente</option>
                            <option value="4">4 estrelas: muito bom</option>
                            <option value="3">3 estrelas: bom</option>
                            <option value="2">2 estrelas: regular</option>
                            <option value="1">1 estrela: ruim</option>
                        </select>
                        <label class="aj-sr" for="revText">Comentário</label>
                        <textarea id="revText" rows="4" placeholder="Fale sobre qualidade, tamanho e caimento." required></textarea>
                        <button type="submit" class="aj-btn aj-btn--block" id="btnEnviarAval">Publicar avaliação</button>
                        <div id="avalMsg" class="aj-msg" role="status"></div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <div id="reviewsList">
                <?php if (empty($avaliacoes)): ?>
                    <p class="aj-noreview" id="noReviewsMessage">Ainda não há avaliações aprovadas. Seja a primeira pessoa a avaliar.</p>
                <?php endif; ?>
                <?php foreach ($avaliacoes as $av): ?>
                <div class="aj-review">
                    <div class="aj-review-top">
                        <div><strong><?= htmlspecialchars($av['autor'] ?: 'Anônimo') ?></strong><small><?= date('d/m/Y', strtotime($av['data'])) ?></small></div>
                        <span class="aj-stars"><?= aj_estrelas($av['nota']) ?></span>
                    </div>
                    <p><?= nl2br(htmlspecialchars($av['comentario'])) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>

<script src="script.js?v=<?= time() ?>"></script>
<script>
    function selecionarUnico(el, tipo) {
        el.parentElement.querySelectorAll('[role=radio]').forEach(s => { s.classList.remove('active'); s.setAttribute('aria-checked', 'false'); });
        el.classList.add('active'); el.setAttribute('aria-checked', 'true');
        const valor = el.dataset.valor;
        const input = document.getElementById('selected-' + tipo);
        if (input) input.value = valor;
        if (tipo === 'cor') document.getElementById('corEscolhida').textContent = valor;
        document.getElementById('msgEscolha').textContent = '';
    }
    // Só uma opção disponível: já vem escolhida
    ['container-tamanhos', 'container-cores'].forEach((id, i) => {
        const c = document.getElementById(id);
        if (c && c.children.length === 1) selecionarUnico(c.children[0], i === 0 ? 'tamanho' : 'cor');
    });

    function validarEComprar(produto) {
        const vTam = (document.getElementById('selected-tamanho') || {}).value || '';
        const vCor = (document.getElementById('selected-cor') || {}).value || '';
        const faltaTam = !!document.getElementById('container-tamanhos') && !vTam;
        const faltaCor = !!document.getElementById('container-cores') && !vCor;
        if (faltaTam || faltaCor) {
            document.getElementById('msgEscolha').textContent =
                faltaTam && faltaCor ? 'Escolha o tamanho e a cor.' : (faltaTam ? 'Escolha o tamanho.' : 'Escolha a cor.');
            return;
        }
        const itemFinal = { ...produto, img: produto.imagem || '', tamanho_escolhido: vTam || 'N/A', cor_escolhida: vCor || 'N/A',
                            opcoes: 'Tam: ' + (vTam || 'N/A') + (vCor ? ' | Cor: ' + vCor : '') };
        if (typeof adicionarAoCarrinho === 'function') adicionarAoCarrinho(itemFinal);
    }

    // ── Envio de avaliação (AJAX) ───────────────
    const form = document.getElementById('commentForm');
    if (form) {
        const msgBox = document.getElementById('avalMsg');
        const aviso = (txt, ok) => { msgBox.className = 'aj-msg ' + (ok ? 'ok' : 'err'); msgBox.style.display = 'block'; msgBox.textContent = txt; };
        const esc = t => { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; };
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn   = document.getElementById('btnEnviarAval');
            const nome  = document.getElementById('revName').value.trim();
            const nota  = document.getElementById('revStars').value;
            const texto = document.getElementById('revText').value.trim();
            if (texto.length < 5) { aviso('Escreva pelo menos 5 caracteres.', false); return; }

            btn.disabled = true; btn.textContent = 'Enviando...';
            const fd = new FormData();
            fd.append('produto_id', document.getElementById('produtoId').value);
            fd.append('nome', nome); fd.append('estrelas', nota); fd.append('comentario', texto);
            try {
                const res  = await fetch('salvar_avaliacao.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.status === 'success') {
                    aviso(data.message, true);
                    form.reset(); btn.textContent = 'Avaliação enviada';
                    if (!data.moderacao && data.avaliacao) {
                        const av = data.avaliacao;
                        const div = document.createElement('div');
                        div.className = 'aj-review';
                        div.innerHTML = `<div class="aj-review-top"><div><strong>${esc(av.nome)}</strong><small>${esc(av.data)}</small></div>
                            <span class="aj-stars">${'★'.repeat(av.nota)}<i>${'★'.repeat(5 - av.nota)}</i></span></div><p>${esc(av.comentario)}</p>`;
                        const vazio = document.getElementById('noReviewsMessage'); if (vazio) vazio.remove();
                        document.getElementById('reviewsList').prepend(div);
                    }
                } else {
                    aviso(data.message, false);
                    btn.disabled = false; btn.textContent = 'Publicar avaliação';
                }
            } catch (err) {
                aviso('Erro de conexão. Tente novamente.', false);
                btn.disabled = false; btn.textContent = 'Publicar avaliação';
            }
        });
    }
</script>
</body>
</html>
