<?php 
// 1. Iniciar a sessão no topo para poder logar o usuário
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php'; 

$mensagem = "";

require_once 'csrf_helpers.php';
require_once 'ratelimit_helpers.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_exigir();
    $nome  = trim((string)($_POST['nome']  ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $senha = (string)($_POST['senha'] ?? '');
    $nivel = 'cliente';   // nunca vem do formulário

    if (mb_strlen($nome) < 2 || mb_strlen($nome) > 100) {
        $mensagem = "Informe seu nome.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $mensagem = "Informe um e-mail válido.";
    } elseif (strlen($senha) < 8 || strlen($senha) > 72 || !preg_match('/[A-Za-z]/', $senha) || !preg_match('/\d/', $senha)) {
        $mensagem = "A senha precisa ter de 8 a 72 caracteres, com letras e números.";
    } elseif (rl_cadastros_ip($pdo) >= RL_MAX_CADASTRO) {
        $mensagem = "Muitos cadastros deste dispositivo. Tente novamente mais tarde.";
    } else {
        $check = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $check->execute([$email]);

        if ($check->fetchColumn()) {
            // Mensagem neutra: não confirma se o e-mail já tem conta (evita descobrir clientes)
            rl_falha($pdo, 'cadastro', $email);
            $mensagem = "Não foi possível criar a conta com estes dados. Se você já é cliente, entre com seu e-mail e senha.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, nivel) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), $nivel])) {
                rl_falha($pdo, 'cadastro', $email);   // conta como 1 cadastro deste IP
                session_regenerate_id(true);          // sessão nova: evita fixação de sessão
                $_SESSION['usuario_id']    = $pdo->lastInsertId();
                $_SESSION['usuario_nome']  = $nome;
                $_SESSION['usuario_nivel'] = $nivel;
                header("Location: index.php");
                exit();
            }
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

        <form action="" method="POST" class="aj-form"><?= csrf_campo() ?>
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
