<?php 
// 1. Iniciar a sessão no topo para poder logar o usuário
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php'; 

$mensagem = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = $_POST['senha'];
    $nivel = 'cliente'; 

    // Verificar se o e-mail já existe
    $check = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $check->execute([$email]);

    if ($check->rowCount() > 0) {
        $mensagem = "Este e-mail já está cadastrado!";
    } else {
        // Criptografar a senha antes de salvar
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

        // Inserir no banco
        $sql = "INSERT INTO usuarios (nome, email, senha, nivel) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$nome, $email, $senhaHash, $nivel])) {
            // SUCESSO! Logar o usuário automaticamente preenchendo a sessão
            $_SESSION['usuario_id'] = $pdo->lastInsertId();
            $_SESSION['usuario_nome'] = $nome;
            $_SESSION['usuario_nivel'] = $nivel;
            
            // REDIRECIONAMENTO para a index
            header("Location: index.php");
            exit();
        } else {
            $mensagem = "Erro ao criar conta. Tente novamente.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alto Jordão | Cadastro</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<main class="main-centralizada">
    <div class="container-cadastro">
        <header class="cadastro-header">
            <h1>CRIE SUA CONTA</h1>
            <p>Junte-se a nós para uma experiência personalizada.</p>
        </header>

        <?php if ($mensagem): ?>
            <p style="color: #e74c3c; font-weight: bold; text-align: center; margin-bottom: 20px;">
                <?= $mensagem ?>
            </p>
        <?php endif; ?>

        <form action="" method="POST" class="form-cadastro">
            <div class="input-grupo">
                <label for="nome">NOME COMPLETO</label>
                <input type="text" id="nome" name="nome" placeholder="Como quer que te chamemos?" required>
            </div>

            <div class="input-grupo">
                <label for="email">E-MAIL</label>
                <input type="email" id="email" name="email" placeholder="seu@email.com" required>
            </div>

            <div class="input-grupo grupo-senha">
                <label for="senha">SENHA</label>
                <input type="password" id="senha" name="senha" placeholder="Mínimo 8 caracteres" required minlength="8">
                <button type="button" class="btn-show-pass" onclick="toggleSenha()">👁️</button>
            </div>

            <button type="submit" class="btn-black-capsule">CRIAR CONTA</button>
        </form>

        <footer class="cadastro-footer">
            <p>Já tem uma conta? <a href="login.php">Entrar</a></p>
        </footer>
    </div>
</main>

<script>
// Função simples caso não esteja no seu script.js
function toggleSenha() {
    var x = document.getElementById("senha");
    if (x.type === "password") {
        x.type = "text";
    } else {
        x.type = "password";
    }
}
</script>
</body>
</html>