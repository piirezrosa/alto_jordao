<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Segurança: Somente admins
if (!isset($_SESSION['usuario_nivel']) || $_SESSION['usuario_nivel'] !== 'admin') {
    header("Location: login.php?erro=acesso_negado");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $preco = $_POST['preco']; // Preço atual (com desconto, se houver)
    $preco_antigo = $_POST['preco_antigo']; // Preço original (para ofertas)
    $categoria = $_POST['categoria'];
    $genero = $_POST['genero'];
    
    $tamanho = $_POST['tamanhos']; 
    $cor = $_POST['cores'];
    $estoque = $_POST['estoque'];

    // Lógica da Imagem
    $imagem = "default.jpg"; 
    if (!empty($_FILES['imagem']['name'])) {
        $extensao = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
        $nome_imagem = md5(time() . rand()) . "." . $extensao;
        
        if (!is_dir("img/produtos/")) {
            mkdir("img/produtos/", 0777, true);
        }
        
        move_uploaded_file($_FILES['imagem']['tmp_name'], "img/produtos/" . $nome_imagem);
        $imagem = $nome_imagem;
    }

    try {
        // SQL ATUALIZADO: Incluindo preco_antigo
        $sql = "INSERT INTO produtos (nome, preco, preco_antigo, categoria, genero, tamanho, cor, estoque, imagem) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$nome, $preco, $preco_antigo, $categoria, $genero, $tamanho, $cor, $estoque, $imagem])) {
            echo "<script>alert('Produto cadastrado com sucesso!'); window.location.href='admin.php';</script>";
        }
    } catch (PDOException $e) {
        echo "<script>alert('Erro ao salvar no banco: " . addslashes($e->getMessage()) . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Novo Produto | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
</head>
<body class="admin-body">

    <?php include 'header.php'; ?>

    <div class="user-full-wrapper">
        <div class="user-main-wide" style="max-width: 1000px; margin: 40px auto;">
            
            <div class="user-content-section-wide" style="background: #fff; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
                <header class="admin-page-header" style="margin-bottom: 30px;">
                    <h1 style="font-weight: 900; text-transform: uppercase; letter-spacing: -1px;">Novo Produto</h1>
                    <p style="color: #888;">Configure os preços e detalhes para ativar ofertas automaticamente</p>
                </header>

                <form method="POST" enctype="multipart/form-data" class="user-form">
                    <div class="profile-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                        
                        <div class="input-grupo" style="grid-column: span 2;">
                            <label style="font-weight: 700; font-size: 11px; color: #bbb; letter-spacing: 1px;">NOME DO PRODUTO</label>
                            <input type="text" name="nome" class="auth-input" placeholder="Ex: Jaqueta Puffer Black Edition" required 
                                   style="width: 100%; padding: 15px; border: 1px solid #eee; border-radius: 10px; margin-top: 8px;">
                        </div>

                        <div class="input-grupo">
                            <label style="font-weight: 700; font-size: 11px; color: #bbb; letter-spacing: 1px;">PREÇO DE VENDA (R$)</label>
                            <input type="number" step="0.01" name="preco" class="auth-input" placeholder="Ex: 199.90" required
                                   style="width: 100%; padding: 15px; border: 1px solid #eee; border-radius: 10px; margin-top: 8px; font-weight: 700; color: #000;">
                        </div>

                        <div class="input-grupo">
                            <label style="font-weight: 700; font-size: 11px; color: #bbb; letter-spacing: 1px;">PREÇO ORIGINAL / SEM DESCONTO (R$)</label>
                            <input type="number" step="0.01" name="preco_antigo" class="auth-input" placeholder="Ex: 299.90 (Opcional)"
                                   style="width: 100%; padding: 15px; border: 1px solid #eee; border-radius: 10px; margin-top: 8px;">
                            <small style="color: #aaa; font-size: 10px;">Preencha apenas se o item estiver em promoção.</small>
                        </div>

                        <div class="input-grupo">
                            <label style="font-weight: 700; font-size: 11px; color: #bbb; letter-spacing: 1px;">QUANTIDADE EM ESTOQUE</label>
                            <input type="number" name="estoque" class="auth-input" placeholder="Ex: 10" required
                                   style="width: 100%; padding: 15px; border: 1px solid #eee; border-radius: 10px; margin-top: 8px;">
                        </div>

                        <div class="input-grupo">
                            <label style="font-weight: 700; font-size: 11px; color: #bbb; letter-spacing: 1px;">CATEGORIA</label>
                            <input type="text" name="categoria" class="auth-input" placeholder="Ex: Calçados, Inverno" required
                                   style="width: 100%; padding: 15px; border: 1px solid #eee; border-radius: 10px; margin-top: 8px;">
                        </div>

                        <div class="input-grupo">
                            <label style="font-weight: 700; font-size: 11px; color: #bbb; letter-spacing: 1px;">GÊNERO</label>
                            <select name="genero" class="auth-input" style="width: 100%; padding: 15px; border: 1px solid #eee; border-radius: 10px; margin-top: 8px; background: #fff;">
                                <option value="unissex">Unissex</option>
                                <option value="masculino">Masculino</option>
                                <option value="feminino">Feminino</option>
                            </select>
                        </div>

                        <div class="input-grupo">
                            <label style="font-weight: 700; font-size: 11px; color: #bbb; letter-spacing: 1px;">TAMANHOS (Ex: P, M, G)</label>
                            <input type="text" name="tamanhos" class="auth-input" placeholder="P, M, G, GG"
                                   style="width: 100%; padding: 15px; border: 1px solid #eee; border-radius: 10px; margin-top: 8px;">
                        </div>

                        <div class="input-grupo">
                            <label style="font-weight: 700; font-size: 11px; color: #bbb; letter-spacing: 1px;">CORES (Ex: Preto, Branco)</label>
                            <input type="text" name="cores" class="auth-input" placeholder="Preto, Off-White"
                                   style="width: 100%; padding: 15px; border: 1px solid #eee; border-radius: 10px; margin-top: 8px;">
                        </div>

                        <div class="input-grupo" style="grid-column: span 2;">
                            <label style="font-weight: 700; font-size: 11px; color: #bbb; letter-spacing: 1px;">FOTOGRAFIA DO PRODUTO</label>
                            <input type="file" name="imagem" class="auth-input" required
                                   style="width: 100%; padding: 15px; border: 1px solid #eee; border-radius: 10px; margin-top: 8px;">
                        </div>

                        <div class="form-actions" style="grid-column: span 2; display: flex; gap: 15px; margin-top: 20px;">
                            <a href="admin.php" style="flex: 1; text-align: center; padding: 20px; border: 1px solid #ddd; border-radius: 50px; color: #666; font-weight: 700; text-decoration: none; font-size: 12px;">CANCELAR</a>
                            <button type="submit" style="flex: 2; padding: 20px; background: #000; color: #fff; border: none; border-radius: 50px; font-weight: 900; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; font-size: 12px;">CADASTRAR ITEM PREMIUM</button>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>