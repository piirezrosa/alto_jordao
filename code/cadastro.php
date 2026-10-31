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
    <title>Criar conta | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="aj-page">

<?php include 'header.php'; ?>

<main class="aj-auth">
    <div class="aj-auth-card">
        <h1>Criar conta</h1>
        <p>Com a conta você acompanha pedidos, salva seu endereço e compra mais rápido.</p>

        <?php if ($mensagem): ?><div class="aj-alert err" role="alert"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>

        <form action="" method="POST" class="aj-form">
            <div class="aj-field">
                <label for="nome">Nome completo</label>
                <input type="text" id="nome" name="nome" autocomplete="name" required value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">
            </div>
            <div class="aj-field">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" placeholder="seu@email.com" autocomplete="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="aj-field">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" autocomplete="new-password" required minlength="8">
                <button type="button" class="aj-pass-toggle" onclick="ajSenha(this)">Mostrar</button>
                <p class="aj-hint">Use pelo menos 8 caracteres.</p>
            </div>
            <button type="submit" class="aj-btn aj-btn--block">Criar conta</button>
        </form>

        <p class="aj-auth-alt">Já tem conta? <a href="login.php">Entrar</a></p>
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
