<?php
require_once 'config.php';

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: index.php"); exit; }

// Busca o produto atual
$query = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
$query->execute([$id]);
$p = $query->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $preco = $_POST['preco'];
    $preco_antigo = !empty($_POST['preco_antigo']) ? $_POST['preco_antigo'] : null;
    $categoria = $_POST['categoria'];
    $genero = $_POST['genero'];
    $tamanhos = $_POST['tamanhos'];
    $cores = $_POST['cores'];

    // Lógica simples: se mudar a imagem, sobe nova, senão mantém a antiga
    $imagem = $p['imagem']; 
    if (!empty($_FILES['nova_imagem']['name'])) {
        $extensao = pathinfo($_FILES['nova_imagem']['name'], PATHINFO_EXTENSION);
        $novo_nome = md5(time()) . "." . $extensao;
        move_uploaded_file($_FILES['nova_imagem']['tmp_name'], "uploads/" . $novo_nome);
        $imagem = $novo_nome;
    }

    $update = $pdo->prepare("UPDATE produtos SET nome=?, preco=?, preco_antigo=?, categoria=?, genero=?, tamanhos=?, cores=?, imagem=? WHERE id=?");
    if ($update->execute([$nome, $preco, $preco_antigo, $categoria, $genero, $tamanhos, $cores, $imagem, $id])) {
        echo "<script>alert('Produto atualizado!'); window.location.href='index.php';</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <title>Editar Produto | Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .edit-container { max-width: 600px; margin: 50px auto; padding: 20px; background: #f9f9f9; border-radius: 8px; }
        .form-group { margin-bottom: 15px; }
        input, select { width: 100%; padding: 8px; margin-top: 5px; }
        .btn-salvar { background: #28a745; color: white; border: none; padding: 10px 20px; cursor: pointer; width: 100%; }
    </style>
</head>
<body>
    <div class="edit-container">
        <h2>Editar Produto #<?= $p['id'] ?></h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Nome do Produto</label>
                <input type="text" name="nome" value="<?= $p['nome'] ?>" required>
            </div>
            <div class="form-group">
                <label>Preço Atual (R$)</label>
                <input type="number" step="0.01" name="preco" value="<?= $p['preco'] ?>" required>
            </div>
            <div class="form-group">
                <label>Preço Antigo (Para Oferta)</label>
                <input type="number" step="0.01" name="preco_antigo" value="<?= $p['preco_antigo'] ?>">
            </div>
            <div class="form-group">
                <label>Tamanhos (P, M, G...)</label>
                <input type="text" name="tamanhos" value="<?= $p['tamanhos'] ?>">
            </div>
            <div class="form-group">
                <label>Cores (Azul, Preto...)</label>
                <input type="text" name="cores" value="<?= $p['cores'] ?>">
            </div>
            <div class="form-group">
                <label>Gênero</label>
                <select name="genero">
                    <option value="unissex" <?= $p['genero'] == 'unissex' ? 'selected' : '' ?>>Unissex</option>
                    <option value="masculino" <?= $p['genero'] == 'masculino' ? 'selected' : '' ?>>Masculino</option>
                    <option value="feminino" <?= $p['genero'] == 'feminino' ? 'selected' : '' ?>>Feminino</option>
                </select>
            </div>
            <div class="form-group">
                <label>Substituir Imagem (Opcional)</label>
                <input type="file" name="nova_imagem">
            </div>
            <button type="submit" class="btn-salvar">SALVAR ALTERAÇÕES</button>
            <a href="index.php" style="display:block; text-align:center; margin-top:10px; color:#666;">Cancelar</a>
        </form>
    </div>
</body>
</html>