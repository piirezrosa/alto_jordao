<?php
require_once 'config.php';

// Se já estiver logado, manda para a index
if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit();
}

$erro = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    // Busca o usuário pelo e-mail
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verifica a senha (se você usa password_hash no cadastro, use password_verify aqui)
    if ($usuario && $senha === $usuario['senha']) {
        // CRIA A SESSÃO (Aqui é onde a "mágica" da barra superior acontece)
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_nivel'] = $usuario['nivel']; // 'admin' ou 'cliente'

        header("Location: index.php");
        exit();
    } else {
        $erro = "E-mail ou senha incorretos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>FashionShop | Login</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .login-container { max-width: 400px; margin: 100px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .login-container h2 { text-align: center; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
        .btn-login { width: 100%; padding: 10px; background: #000; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        .error-msg { color: red; text-align: center; margin-bottom: 10px; }
    </style>
</head>
<body>

    <div class="login-container">
        <h2>Entrar na FashionShop</h2>
        
        <?php if ($erro): ?>
            <div class="error-msg"><?php echo $erro; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>E-mail</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Senha</label>
                <input type="password" name="senha" required>
            </div>
            <button type="submit" class="btn-login">Entrar</button>
        </form>
        
        <p style="text-align: center; margin-top: 15px; font-size: 14px;">
            Novo por aqui? <a href="cadastro.php">Crie sua conta</a>
        </p>
    </div>

</body>
</html>