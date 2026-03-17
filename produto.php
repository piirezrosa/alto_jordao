<?php 
require_once 'config.php'; 

// 1. Pega o ID da URL e busca no banco
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$query = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
$query->execute([$id]);
$p = $query->fetch(PDO::FETCH_ASSOC);

if (!$p) { 
    header("Location: index.php"); 
    exit; 
}

$coresDisponiveis = !empty($p['cor']) ? explode(',', $p['cor']) : [];
$tamanhosDisponiveis = !empty($p['tamanho']) ? explode(',', $p['tamanho']) : [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($p['nome']) ?> | FashionShop</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
</head>
<body>

    <?php include 'header.php'; ?>

    <div id="overlay" class="modal-variantes-overlay" onclick="fecharTodosModais()"></div>

    <aside id="cartSidebar" class="sidebar">
        <div style="padding: 30px; display: flex; flex-direction: column; height: 100%;">
            <div style="display:flex; justify-content: space-between; align-items:center; margin-bottom:20px;">
                <h2 style="font-weight:900; text-transform:uppercase; font-size:18px;">Sua Sacola</h2>
                <button onclick="fecharTodosModais()" style="background:none; border:none; font-size:24px; cursor:pointer;">&times;</button>
            </div>
            
            <div id="cartListSide" style="flex:1; overflow-y:auto;">
                </div>

            <div style="margin-top:20px; border-top: 1px solid var(--border); padding-top: 20px;">
                <div style="display:flex; justify-content: space-between; font-weight:800; margin-bottom:20px;">
                    <span>TOTAL:</span>
                    <span id="totalValor">R$ 0,00</span>
                </div>
                <button class="btn-comprar-detalhe" onclick="window.location.href='checkout.php'">Finalizar Compra</button>
            </div>
        </div>
    </aside>

    <main class="produto-container">
        <section class="produto-imagens">
            <div class="img-principal-wrapper">
                <?php 
                    $caminhoImg = "img/produtos/" . $p['imagem'];
                    $src = (!empty($p['imagem']) && file_exists($caminhoImg)) ? 
                ?>
                <img src="<?= $src ?>" alt="<?= htmlspecialchars($p['nome']) ?>">
            </div>
        </section>

        <section class="produto-info">
            <span class="categoria"><?= htmlspecialchars($p['categoria'] ?? 'Coleção Exclusiva') ?></span>
            <h1><?= htmlspecialchars($p['nome']) ?></h1>
            
            <div class="preco-container">
                <p class="preco">R$ <?= number_format($p['preco'], 2, ',', '.') ?></p>
            </div>

            <div class="variantes-secao">
                <?php if(count($coresDisponiveis) > 0): ?>
                    <label>Cor: <span id="txt-cor-selecionada" style="font-weight: 400; color: var(--grey-text);">Selecione</span></label>
                    <div class="cores-grid">
                        <?php foreach ($coresDisponiveis as $corPT): 
                            $nomeLimpo = trim($corPT);
                            // Função traduzirCor deve estar no seu config ou functions.php
                            $corVisual = function_exists('traduzirCor') ? traduzirCor($nomeLimpo) : $nomeLimpo; 
                        ?>
                            <div class="cor-opcao" 
                                 style="background-color: <?= $corVisual ?>;" 
                                 title="<?= $nomeLimpo ?>"
                                 onclick="selecionarCor(this, '<?= $nomeLimpo ?>')">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <label for="select-tamanho">Tamanho</label>
                <select id="select-tamanho" style="width: 100%; padding: 15px; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 25px; background: #fff;">
                    <?php if(empty($tamanhosDisponiveis) || $tamanhosDisponiveis[0] == ''): ?>
                        <option value="Único">Tamanho Único</option>
                    <?php else: ?>
                        <option value="">Selecione o tamanho</option>
                        <?php foreach ($tamanhosDisponiveis as $t): ?>
                            <option value="<?= trim($t) ?>"><?= trim($t) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>

                <button class="btn-comprar-detalhe" onclick='adicionarAoCarrinhoDireto(<?= json_encode($p) ?>)'>
                    Adicionar à Sacola
                </button>
            </div>

            <div style="margin-top: 40px; border-top: 1px solid var(--border); padding-top: 30px;">
                <h4 style="text-transform: uppercase; font-size: 13px; font-weight: 900; margin-bottom: 10px;">Descrição</h4>
                <p style="color: var(--grey-text); font-size: 15px;">
                    <?= nl2br(htmlspecialchars($p['descricao'] ?? 'Produto de alta qualidade com acabamento premium.')) ?>
                </p>
            </div>
        </section>
    </main>

    <script src="script.js?v=<?= time(); ?>"></script>
    <script>
        function selecionarCor(elemento, nomeCor) {
            document.querySelectorAll('.cor-opcao').forEach(el => el.classList.remove('active'));
            elemento.classList.add('active');
            document.getElementById('txt-cor-selecionada').innerText = nomeCor;
        }
    </script>
</body>
</html>