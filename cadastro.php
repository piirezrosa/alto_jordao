<?php 
require_once 'config.php'; 

$mensagem = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = $_POST['senha'];
    $nivel = 'cliente'; // Todo cadastro pelo site começa como cliente

    // Verificar se o e-mail já existe
    $check = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $check->execute([$email]);

    if ($check->rowCount() > 0) {
        $mensagem = "Este e-mail já está cadastrado!";
    } else {
        // Inserir no banco
        $sql = "INSERT INTO usuarios (nome, email, senha, nivel) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$nome, $email, $senha, $nivel])) {
            // Sucesso! Vamos logar o usuário automaticamente e mandar para a index
            $_SESSION['usuario_id'] = $pdo->lastInsertId();
            $_SESSION['usuario_nome'] = $nome;
            $_SESSION['usuario_nivel'] = $nivel;
            
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
    <title>FashionShop | Cadastro</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <div class="logo" onclick="location.href='index.php'">
            FASHION<span>SHOP</span>
        </div>
    </header>

    <main class="auth-container">
        <div class="auth-box">
            <h1>CRIE SUA CONTA</h1>
            <p>Junte-se a nós para uma experiência personalizada.</p>
            
            <?php if($mensagem !== ""): ?>
                <p style="color: red; background: #ffeeee; padding: 10px; border-radius: 4px; font-size: 14px; text-align: center;">
                    <?php echo $mensagem; ?>
                </p>
            <?php endif; ?>
            
            <form action="cadastro.php" method="POST">
                <div class="input-group">
                    <label>Nome Completo</label>
                    <input type="text" name="nome" placeholder="Como quer que te chamemos?" required>
                </div>
                <div class="input-group">
                    <label>E-mail</label>
                    <input type="email" name="email" placeholder="seu@email.com" required>
                </div>
                <div class="input-group">
                    <label>Senha</label>
                    <input type="password" name="senha" placeholder="Mínimo 8 caracteres" required>
                </div>
                
                <button type="submit" class="btn-auth">Criar Conta</button>
            </form>
            
            <p class="auth-footer">Já tem uma conta? <a href="login.php">Entrar</a></p>
        </div>
    </main>

</body>
</html>