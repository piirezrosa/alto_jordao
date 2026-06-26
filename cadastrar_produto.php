<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['usuario_nivel']) || !in_array($_SESSION['usuario_nivel'], ['admin','superadmin','gerente'])) {
    header("Location: login.php"); exit();
}

$msg_sucesso = $msg_erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome        = trim($_POST['nome']);
    $preco       = $_POST['preco'];
    $preco_antigo= !empty($_POST['preco_antigo']) ? $_POST['preco_antigo'] : null;
    $custo       = !empty($_POST['custo'])        ? $_POST['custo']        : null;
    $categoria   = $_POST['categoria'];
    $genero      = $_POST['genero'];
    $tamanho     = $_POST['tamanhos'];
    $cor         = $_POST['cores'];
    $estoque     = $_POST['estoque'];
    $descricao   = $_POST['descricao'] ?? null;
    $destaque    = isset($_POST['destaque']) ? 1 : 0;

    $imagem = "default.jpg";
    if (!empty($_FILES['imagem']['name'])) {
        $ext        = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
        $nome_img   = md5(time().rand()).".".$ext;
        if (!is_dir("img/produtos/")) mkdir("img/produtos/", 0777, true);
        move_uploaded_file($_FILES['imagem']['tmp_name'], "img/produtos/".$nome_img);
        $imagem = $nome_img;
    }

    try {
        $sql  = "INSERT INTO produtos (nome, preco, preco_antigo, custo, categoria, genero, tamanho, cor, estoque, descricao, destaque, imagem)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$nome, $preco, $preco_antigo, $custo, $categoria, $genero, $tamanho, $cor, $estoque, $descricao, $destaque, $imagem])) {
            // Log
            $log = $pdo->prepare("INSERT INTO logs_sistema (usuario_id, acao, tabela, registro_id, detalhes, ip) VALUES (?,?,?,?,?,?)");
            $log->execute([$_SESSION['usuario_id']??null, 'produto_criado', 'produtos', $pdo->lastInsertId(), $nome, $_SERVER['REMOTE_ADDR']]);
            header("Location: admin_produtos.php?msg=criado"); exit();
        }
    } catch (PDOException $e) {
        $msg_erro = $e->getMessage();
    }
}

// Categorias e coleções para os selects
$categorias = $pdo->query("SELECT * FROM categorias WHERE pai_id IS NULL ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$colecoes   = $pdo->query("SELECT * FROM colecoes WHERE ativo=1 ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$marcas     = $pdo->query("SELECT * FROM marcas WHERE ativo=1 ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

$devolucoes_pend = $pdo->query("SELECT COUNT(*) FROM devolucoes WHERE status='pendente'")->fetchColumn();
$estoque_critico = $pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque<=3 AND ativo=1")->fetchColumn();
$p_pendente_sb   = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status='pendente'")->fetchColumn();

define('CONTEUDO_AUTORIZADO', true);
$pagina_atual = 'novo_produto';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Produto | Alto Jordão Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin_style.css?v=<?= time() ?>">
    <style>
        .form-wrap {
            max-width: 860px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }

        .full { grid-column: span 2; }

        .input-group { display: flex; flex-direction: column; gap: 8px; }

        .input-group label {
            font-size: 10px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .input-group input,
        .input-group select,
        .input-group textarea {
            padding: 14px 18px;
            border: 1.5px solid var(--border);
            border-radius: 50px;
            font-family: var(--font-main);
            font-size: 14px;
            background: var(--grey-bg);
            color: var(--black);
            transition: var(--transition);
            outline: none;
        }

        .input-group textarea {
            border-radius: 20px;
            resize: vertical;
            min-height: 100px;
        }

        .input-group input:focus,
        .input-group select:focus,
        .input-group textarea:focus {
            border-color: var(--black);
            background: var(--white);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        input[type="file"] {
            border-radius: 20px !important;
            border-style: dashed !important;
            cursor: pointer;
            padding: 18px !important;
        }

        .check-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 18px;
            background: var(--grey-bg);
            border-radius: 50px;
            border: 1.5px solid var(--border);
            cursor: pointer;
        }

        .check-group input[type="checkbox"] {
            width: 18px; height: 18px;
            accent-color: var(--black);
            border-radius: 4px;
            padding: 0;
            border: none;
            background: none;
            box-shadow: none;
        }

        .section-divider {
            grid-column: span 2;
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 10px 0 4px;
        }

        .section-divider span {
            font-size: 10px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 2px;
            white-space: nowrap;
        }

        .section-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .msg-erro {
            background: rgba(255,77,77,.08);
            border: 1px solid rgba(255,77,77,.25);
            color: var(--danger);
            padding: 13px 22px;
            border-radius: 50px;
            margin-bottom: 22px;
            font-size: 13px;
            font-weight: 700;
        }
    </style>
</head>
<body class="admin-page">

<?php include 'sidebar.php'; ?>

<main class="admin-main">
    <div class="admin-topbar">
        <div>
            <h1>Novo Produto</h1>
            <p>Expanda o catálogo da Alto Jordão com novos modelos.</p>
        </div>
        <a href="admin_produtos.php" class="btn-admin-ghost">← Voltar</a>
    </div>

    <?php if($msg_erro): ?>
    <div class="msg-erro">⚠ Erro ao salvar: <?= htmlspecialchars($msg_erro) ?></div>
    <?php endif; ?>

    <div class="admin-card form-wrap">
        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">

                <!-- IDENTIFICAÇÃO -->
                <div class="section-divider full"><span>Identificação</span></div>

                <div class="input-group full">
                    <label>Nome do Produto *</label>
                    <input type="text" name="nome" placeholder="Ex: Moletom Oversized Essentials" required>
                </div>

                <div class="input-group full">
                    <label>Descrição</label>
                    <textarea name="descricao" placeholder="Descreva o produto, materiais, diferenciais..."></textarea>
                </div>

                <!-- PRECIFICAÇÃO -->
                <div class="section-divider full"><span>Precificação</span></div>

                <div class="input-group">
                    <label>Preço de Venda (R$) *</label>
                    <input type="number" step="0.01" name="preco" placeholder="0,00" required>
                </div>

                <div class="input-group">
                    <label>Preço Original / Sem Desconto (R$)</label>
                    <input type="number" step="0.01" name="preco_antigo" placeholder="Opcional — ativa badge de oferta">
                </div>

                <div class="input-group">
                    <label>Custo de Aquisição (R$)</label>
                    <input type="number" step="0.01" name="custo" placeholder="Usado no cálculo de lucro">
                </div>

                <div class="input-group">
                    <label>Estoque Inicial *</label>
                    <input type="number" name="estoque" placeholder="Quantidade disponível" required>
                </div>

                <!-- CLASSIFICAÇÃO -->
                <div class="section-divider full"><span>Classificação</span></div>

                <div class="input-group">
                    <label>Categoria</label>
                    <select name="categoria">
                        <option value="">Selecione...</option>
                        <?php foreach($categorias as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['nome']) ?>"><?= htmlspecialchars($cat['nome']) ?></option>
                        <?php endforeach; ?>
                        <option value="outros">Outros</option>
                    </select>
                </div>

                <div class="input-group">
                    <label>Público / Gênero</label>
                    <select name="genero">
                        <option value="unissex">Unissex</option>
                        <option value="masculino">Masculino</option>
                        <option value="feminino">Feminino</option>
                        <option value="kids">Kids</option>
                    </select>
                </div>

                <?php if(!empty($marcas)): ?>
                <div class="input-group">
                    <label>Marca</label>
                    <select name="marca_id">
                        <option value="">Selecione...</option>
                        <?php foreach($marcas as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if(!empty($colecoes)): ?>
                <div class="input-group">
                    <label>Coleção</label>
                    <select name="colecao_id">
                        <option value="">Nenhuma</option>
                        <?php foreach($colecoes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- VARIAÇÕES -->
                <div class="section-divider full"><span>Variações</span></div>

                <div class="input-group">
                    <label>Tamanhos Disponíveis</label>
                    <input type="text" name="tamanhos" placeholder="Ex: PP, P, M, G, GG">
                </div>

                <div class="input-group">
                    <label>Cores Disponíveis</label>
                    <input type="text" name="cores" placeholder="Ex: Preto, Branco, Off-White">
                </div>

                <!-- IMAGEM E FLAGS -->
                <div class="section-divider full"><span>Imagem & Configurações</span></div>

                <div class="input-group full">
                    <label>Foto Principal</label>
                    <input type="file" name="imagem" accept="image/*">
                </div>

                <div class="input-group">
                    <label>Produto em Destaque?</label>
                    <label class="check-group">
                        <input type="checkbox" name="destaque" value="1">
                        Exibir na homepage
                    </label>
                </div>

                <!-- AÇÕES -->
                <div class="full" style="display:flex; flex-direction:column; gap:10px; margin-top:10px;">
                    <button type="submit" class="btn-admin-primary" style="width:100%; padding:18px; font-size:13px; border-radius:50px; justify-content:center;">
                        Publicar Produto
                    </button>
                    <a href="admin_produtos.php" style="text-align:center; padding:14px; color:var(--muted); text-decoration:none; font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:1px;">
                        Descartar Alterações
                    </a>
                </div>

            </div>
        </form>
    </div>
</main>

<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>