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
$voltar = ((($_GET['msg'] ?? '') === 'faca_login_para_finalizar') || (($_POST['voltar'] ?? '') === 'checkout.php')) ? 'checkout.php' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    // Busca o usuário pelo e-mail
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // Senhas novas são bcrypt (cadastro.php). Contas antigas em texto puro ainda entram
    // e são convertidas para hash no primeiro login.
    $ok = false;
    if ($usuario) {
        $guardada = (string)$usuario['senha'];
        if (password_verify($senha, $guardada)) {
            $ok = true;
            if (password_needs_rehash($guardada, PASSWORD_DEFAULT)) {
                try { $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?")->execute([password_hash($senha, PASSWORD_DEFAULT), $usuario['id']]); } catch (Throwable $e) { error_log('[LOGIN] não foi possível atualizar o hash: ' . $e->getMessage()); }
            }
        } elseif (strpos($guardada, '$2') !== 0 && hash_equals($guardada, $senha)) {
            $ok = true;
            try { $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?")->execute([password_hash($senha, PASSWORD_DEFAULT), $usuario['id']]); } catch (Throwable $e) { error_log('[LOGIN] não foi possível atualizar o hash: ' . $e->getMessage()); }
        }
    }
    if ($ok) {
        session_regenerate_id(true);

        // SALVA OS DADOS NA SESSÃO
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        
        $_SESSION['usuario_nivel'] = $usuario['nivel']; 

        // REDIRECIONAMENTO INTELIGENTE
        if ($_SESSION['usuario_nivel'] === 'superadmin' || $_SESSION['usuario_nivel'] === 'admin') {
            header("Location: admin_dashboard.php");
        } else {
            // Quem veio do checkout volta para ele (só destino permitido)
            header("Location: " . (($_POST['voltar'] ?? '') === 'checkout.php' ? 'checkout.php' : 'index.php'));
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
    <title>Entrar | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="aj-page">

<?php include 'header.php'; ?>

<main class="aj-auth">
    <div class="aj-auth-card">
        <h1>Entrar</h1>
        <p>Acesse sua conta para ver pedidos e finalizar compras.</p>

        <?php if ($erro): ?><div class="aj-alert err" role="alert"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
        <?php if ($voltar === 'checkout.php' && !$erro): ?><div class="aj-alert info">Entre na sua conta para finalizar a compra. Sua sacola continua guardada.</div><?php endif; ?>

        <form method="POST" class="aj-form">
            <input type="hidden" name="voltar" value="<?= htmlspecialchars($voltar) ?>">
            <div class="aj-field">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" placeholder="seu@email.com" autocomplete="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="aj-field">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" autocomplete="current-password" required>
                <button type="button" class="aj-pass-toggle" onclick="ajSenha(this)">Mostrar</button>
            </div>
            <button type="submit" class="aj-btn aj-btn--block">Entrar</button>
        </form>

        <p class="aj-auth-alt">Primeira vez aqui? <a href="cadastro.php">Criar conta</a></p>
    </div>
</main>

<script>
function ajSenha(b) {
    const i = document.getElementById('senha'), v = i.type === 'password';
    i.type = v ? 'text' : 'password'; b.textContent = v ? 'Ocultar' : 'Mostrar';
}
</script>
<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>
