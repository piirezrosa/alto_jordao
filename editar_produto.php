<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Segurança: Somente admins acessam
if (!isset($_SESSION['usuario_nivel']) || $_SESSION['usuario_nivel'] !== 'admin') {
    header("Location: login.php?erro=acesso_negado");
    exit();
}

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: admin.php"); exit; }

// Busca o produto atual
$query = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
$query->execute([$id]);
$p = $query->fetch(PDO::FETCH_ASSOC);

if (!$p) { die("Produto não encontrado."); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $preco_novo = $_POST['preco_novo']; // Preço que será cobrado
    $preco_antigo = $_POST['preco_antigo']; // Preço sem desconto
    
    $categoria = $_POST['categoria'] ?? $p['categoria'];
    $genero = $_POST['genero'];
    $tamanho = $_POST['tamanhos']; 
    $cor = $_POST['cores'];

    $imagem = $p['imagem']; 
    if (!empty($_FILES['nova_imagem']['name'])) {
        $extensao = pathinfo($_FILES['nova_imagem']['name'], PATHINFO_EXTENSION);
        $novo_nome = md5(time()) . "." . $extensao;
        move_uploaded_file($_FILES['nova_imagem']['tmp_name'], "img/produtos/" . $novo_nome);
        $imagem = $novo_nome;
    }

    // SQL ATUALIZADO: Mantendo a integridade dos preços
    $sql = "UPDATE produtos SET nome=?, preco=?, preco_antigo=?, categoria=?, genero=?, tamanho=?, cor=?, imagem=? WHERE id=?";
    $update = $pdo->prepare($sql);
    
    if ($update->execute([$nome, $preco_novo, $preco_antigo, $categoria, $genero, $tamanho, $cor, $imagem, $id])) {
        echo "<script>alert('Produto atualizado com sucesso!'); window.location.href='admin.php';</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Produto #<?= $id ?> | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
</head>
<body class="admin-body">

    <?php include 'header.php'; ?>

    <div class="user-full-wrapper">
        <div class="user-main-wide" style="max-width: 1000px; margin: 40px auto;">
            
            <div class="user-content-section-wide" style="background: #fff; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
                <header class="admin-page-header" style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1 style="font-weight: 900; text-transform: uppercase; letter-spacing: -1px;">Editar Produto</h1>
                        <p style="color: #888; font-size: 13px;">ID de Referência: #<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?></p>
                    </div>
                    <div style="text-align: right;">
                        <span style="display: block; font-size: 10px; font-weight: 800; color: #bbb;">STATUS</span>
                        <span style="color: #2ecc71; font-weight: 800; font-size: 12px;">● ATIVO NO CATÁLOGO</span>
                    </div>
                </header>

                <form method="POST" enctype="multipart/form-data" class="user-form">
                    
                    <div class="profile-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px;">
                        
                        <div class="input-grupo" style="grid-column: span 2;">
                            <label style="font-weight: 700; font-size: 11px; color: #bbb; letter-spacing: 1px;">NOME DO PRODUTO</label>
                            <input type="text" name="nome" class="auth-input" value="<?= htmlspecialchars($p['nome']) ?>" required 
                                   style="width: 100%; padding: 15px; border: 1px solid #eee; border-radius: 10px; margin-top: 8px; font-weight: 600;">
                        </div>

                        <div class="input-grupo">
                            <label style="font-weight: 700; font-size: 11px; color: #bbb; letter-spacing: 1px;">PREÇO ATUAL DE VENDA (R$)</label>
                            <input type="number" step="0.01" name="preco_novo" class="auth-input" value="<?= $p['preco'] ?>" required
                                   style="width: 100%; padding: 15px; border: 1px solid #eee; border-radius: 10px; margin-top: 8px; font-weight: 800; color: #000;">
                        </div>

                        <div class="input-grupo">
                            <label style="font-weight: 700; font-size: 11px; color: #bbb; letter-spacing: 1px;">PREÇO ORIGINAL / SEM DESCONTO (R$)</label>
                            <input type="number" step="0.01" name="preco_antigo" class="auth-input" value="<?= $p['preco_antigo'] ?>"
                                   style="width: 100%; padding: 15px; border: 1px solid #eee; border-radius: 10px; margin-top: 8px; color: #888;">
                            <small style="font-size: 10px; color: #aaa;">Se o preço acima for menor que este, o item entra em <b>OFERTA</b>.</small>
                        </div>

                        <div class="input-grupo">
                            <label style="font-weight: 700; font-size: 11px; color: #bbb; letter-spacing: 1px;">TAMANHOS DISPONÍVEIS</label>
                            <input type="text" name="tamanhos" class="auth-input" value="<?= htmlspecialchars($p['tamanho'] ?? '') ?>"
                                   style="width: 100%; padding: 15px; border: 1px solid #eee; border-radius: 10px; margin-top: 8px;">
                        </div>

                        <div class="input-grupo">
                            <label style="font-weight: 700; font-size: 11px; color: #bbb; letter-spacing: 1px;">CORES</label>
                            <input type="text" name="cores" class="auth-input" value="<?= htmlspecialchars($p['cor'] ?? '') ?>"
                                   style="width: 100%; padding: 15px; border: 1px solid #eee; border-radius: 10px; margin-top: 8px;">
                        </div>

                        <div class="input-grupo">
                            <label style="font-weight: 700; font-size: 11px; color: #bbb; letter-spacing: 1px;">GÊNERO</label>
                            <select name="genero" class="auth-input" style="width: 100%; padding: 15px; border: 1px solid #eee; border-radius: 10px; margin-top: 8px; background: #fff;">
                                <option value="unissex" <?= $p['genero'] == 'unissex' ? 'selected' : '' ?>>Unissex</option>
                                <option value="masculino" <?= $p['genero'] == 'masculino' ? 'selected' : '' ?>>Masculino</option>
                                <option value="feminino" <?= $p['genero'] == 'feminino' ? 'selected' : '' ?>>Feminino</option>
                            </select>
                        </div>

                        <div class="input-grupo">
                            <label style="font-weight: 700; font-size: 11px; color: #bbb; letter-spacing: 1px;">CATEGORIA</label>
                            <input type="text" name="categoria" class="auth-input" value="<?= htmlspecialchars($p['categoria'] ?? '') ?>"
                                   style="width: 100%; padding: 15px; border: 1px solid #eee; border-radius: 10px; margin-top: 8px;">
                        </div>

                        <div class="input-grupo" style="grid-column: span 2; display: flex; align-items: center; gap: 20px; background: #f9f9f9; padding: 20px; border-radius: 15px;">
                            <img src="img/produtos/<?= $p['imagem'] ?>" style="width: 80px; height: 80px; object-fit: cover; border-radius: 10px; border: 1px solid #ddd;">
                            <div style="flex: 1;">
                                <label style="font-weight: 700; font-size: 11px; color: #bbb; letter-spacing: 1px;">ALTERAR IMAGEM PRINCIPAL</label>
                                <input type="file" name="nova_imagem" class="auth-input" style="width: 100%; margin-top: 8px; font-size: 12px;">
                            </div>
                        </div>

                        <div class="form-actions" style="grid-column: span 2; display: flex; gap: 15px; margin-top: 10px;">
                            <a href="admin.php" style="flex: 1; text-align: center; padding: 20px; border: 1px solid #ddd; border-radius: 50px; color: #666; font-weight: 700; text-decoration: none; font-size: 12px;">DESCARTAR</a>
                            <button type="submit" style="flex: 2; padding: 20px; background: #000; color: #fff; border: none; border-radius: 50px; font-weight: 900; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; font-size: 12px;">ATUALIZAR PRODUTO</button>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>