<?php
require_once 'config.php';

// Inicia a sessão para poder salvar os dados do usuário
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

    // VERIFICAÇÃO COM SENHA SIMPLES (TEXTO PLANO)
    if ($usuario && $senha === $usuario['senha']) {
        
        // SALVA OS DADOS NA SESSÃO
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        
        // Verifique se no seu banco a coluna se chama 'nivel' ou 'usuario_nivel'
        // Ajustei para 'nivel' conforme o seu código original
        $_SESSION['usuario_nivel'] = $usuario['nivel']; 

        // REDIRECIONAMENTO INTELIGENTE
        if ($_SESSION['usuario_nivel'] === 'admin') {
            header("Location: admin_vendas.php");
        } else {
            header("Location: index.php");
        }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f4f4f4; font-family: 'Inter', sans-serif; }
        
        .login-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .login-card { 
            max-width: 400px; 
            width: 100%;
            background: #fff;
            padding: 50px 40px; 
            border-radius: 30px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.05);
        }

        .login-card h2 { 
            text-align: center; 
            font-weight: 900; 
            text-transform: uppercase;
            letter-spacing: -1px;
            margin-bottom: 10px;
        }

        .login-card p {
            text-align: center;
            color: #888;
            font-size: 14px;
            margin-bottom: 30px;
        }

        .form-group { margin-bottom: 20px; }
        .form-group label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 700; 
            font-size: 12px;
            color: #555;
        }

        .auth-input { 
            width: 100%; 
            padding: 15px; 
            border: 1px solid #eee; 
            border-radius: 12px; 
            font-family: 'Inter', sans-serif;
            transition: 0.3s;
        }

        .auth-input:focus {
            border-color: #000;
            outline: none;
        }

        .btn-black-capsule { 
            width: 100%; 
            padding: 18px; 
            background: #000; 
            color: #fff; 
            border: none; 
            border-radius: 50px; 
            font-weight: 800; 
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer; 
            transition: 0.3s;
        }

        .btn-black-capsule:hover {
            transform: scale(1.02);
            background: #333;
        }

        .error-msg { 
            background: #ffebeb;
            color: #d93025; 
            padding: 12px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            text-align: center; 
            margin-bottom: 20px; 
        }

        .footer-link {
            text-align: center;
            margin-top: 25px;
            font-size: 14px;
            color: #666;
        }

        .footer-link a {
            color: #000;
            font-weight: 700;
            text-decoration: none;
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="login-card">
            <h2>Alto Jordão</h2>
            <p>Entre para acessar sua conta premium</p>
            
            <?php if ($erro): ?>
                <div class="error-msg"><?php echo $erro; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>E-MAIL</label>
                    <input type="email" name="email" class="auth-input" placeholder="seu@email.com" required>
                </div>
                <div class="form-group">
                    <label>SENHA</label>
                    <input type="password" name="senha" class="auth-input" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn-black-capsule">ENTRAR</button>
            </form>
            
            <div class="footer-link">
                Novo por aqui? <a href="cadastro.php">Crie sua conta</a>
            </div>
        </div>
    </div>

</body>
</html>