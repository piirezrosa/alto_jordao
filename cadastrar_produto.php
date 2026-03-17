<?php 
require_once 'config.php'; 

// Segurança: Só o admin entra aqui
if (!isset($_SESSION['usuario_nivel']) || $_SESSION['usuario_nivel'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Lógica para Salvar no Banco
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $preco = $_POST['preco'];
    $preco_antigo = !empty($_POST['preco_antigo']) ? $_POST['preco_antigo'] : null;
    $genero = $_POST['genero'];
    $categoria = $_POST['categoria'];
    $tamanhos = $_POST['tamanhos'];
    $cores = $_POST['cores'];
    
    // Lógica Híbrida de Imagem
    $imagem_final = $_POST['imagem_texto']; 

    if (isset($_FILES['imagem_arquivo']) && $_FILES['imagem_arquivo']['error'] === 0) {
        $extensao = pathinfo($_FILES['imagem_arquivo']['name'], PATHINFO_EXTENSION);
        $novo_nome = md5(time() . rand()) . "." . $extensao;
        $diretorio = "uploads/";

        if (!is_dir($diretorio)) {
            mkdir($diretorio, 0777, true);
        }

        if (move_uploaded_file($_FILES['imagem_arquivo']['tmp_name'], $diretorio . $novo_nome)) {
            $imagem_final = $novo_nome; 
        }
    }

    // SQL atualizado com as novas colunas
    $sql = "INSERT INTO produtos (nome, preco, preco_antigo, genero, categoria, tamanhos, cores, imagem) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$nome, $preco, $preco_antigo, $genero, $categoria, $tamanhos, $cores, $imagem_final])) {
        $mensagem = "✅ Produto '$nome' cadastrado com sucesso!";
    } else {
        $mensagem = "❌ Erro ao cadastrar.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastrar Produto | Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-container { max-width: 600px; margin: 30px auto; padding: 30px; border: 1px solid #eee; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background: #fff; }
        .input-group { margin-bottom: 15px; display: flex; flex-direction: column; }
        .input-group label { font-size: 11px; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; color: #555; }
        input, select { padding: 12px; border: 1px solid #ddd; border-radius: 6px; outline: none; font-family: inherit; font-size: 14px; }
        .btn-save { background: #000; color: #fff; border: none; padding: 15px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.3s; width: 100%; margin-top: 10px;}
        .btn-save:hover { background: #333; }
        .divisor { height: 1px; background: #eee; margin: 20px 0; position: relative; }
        .divisor span { position: absolute; top: -10px; left: 50%; transform: translateX(-50%); background: #fff; padding: 0 10px; font-size: 10px; color: #999; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    </style>
</head>
<body style="background: #f9f9f9;">
    <?php include 'header.php'; ?>

    <div class="form-container">
        <h2 style="letter-spacing: 1px; margin-bottom: 20px; text-align: center;">NOVO PRODUTO</h2>
        
        <?php if(isset($mensagem)): ?>
            <div style="background: #e7f3ef; color: #2d5a4c; padding: 10px; border-radius: 6px; margin-bottom: 20px; text-align: center; font-size: 14px;">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="input-group">
                <label>Nome do Produto</label>
                <input type="text" name="nome" placeholder="Ex: Tênis Runner Pro" required>
            </div>

            <div class="grid-2">
                <div class="input-group">
                    <label>Preço Atual (R$)</label>
                    <input type="number" step="0.01" name="preco" placeholder="0.00" required>
                </div>
                <div class="input-group">
                    <label>Preço Antigo (Oferta)</label>
                    <input type="number" step="0.01" name="preco_antigo" placeholder="Ex: 299.90">
                </div>
            </div>

            <div class="grid-2">
                <div class="input-group">
                    <label>Tamanhos (P,M,G ou 38,39...)</label>
                    <input type="text" name="tamanhos" placeholder="Ex: P, M, G">
                </div>
                <div class="input-group">
                    <label>Cores (Separe por vírgula)</label>
                    <input type="text" name="cores" placeholder="Ex: Preto, Branco">
                </div>
            </div>

            <div class="input-group">
                <label>Subir Foto (Do Computador)</label>
                <input type="file" name="imagem_arquivo" accept="image/*">
            </div>

            <div class="divisor"><span>OU</span></div>

            <div class="input-group">
                <label>Emoji ou URL (Opcional)</label>
                <input type="text" name="imagem_texto" placeholder="Ex: 👟 ou link da imagem">
            </div>
            
            <div class="grid-2">
                <div class="input-group">
                    <label>Gênero</label>
                    <select name="genero" required>
                        <option value="unissex">Unissex</option>
                        <option value="masculino">Masculino</option>
                        <option value="feminino">Feminino</option>
                    </select>
                </div>

                <div class="input-group">
                    <label>Categoria</label>
                    <select name="categoria" required>
                        <option value="Roupas">Roupas</option>
                        <option value="Tenis">Tênis</option>
                        <option value="Acessorios">Acessórios</option>
                        <option value="ofertas">Ofertas</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn-save">CADASTRAR PRODUTO</button>
        </form>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="index.php" style="color: #999; font-size: 12px; text-decoration: none;">← Voltar para a Loja</a>
        </div>
    </div>
</body>
</html>