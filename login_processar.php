<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitização básica
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];

    // 1. Buscamos o usuário APENAS pelo e-mail primeiro
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Verificamos se o usuário existe E se a senha confere
    // Usei password_verify para bater com o hash do banco
    if ($usuario && password_verify($senha, $usuario['senha'])) {
        
        // 3. Gravamos os dados na SESSÃO
        $_SESSION['usuario_id']    = $usuario['id'];
        $_SESSION['usuario_nome']  = $usuario['nome'];
        $_SESSION['usuario_nivel'] = strtolower($usuario['nivel']); // Padroniza para minúsculo

        // 4. Redirecionamento Inteligente
        if ($_SESSION['usuario_nivel'] === 'superadmin' || $_SESSION['usuario_nivel'] === 'admin') {
            header("Location: dashboard_admin.php");
        } else {
            // Se for cliente, vai para a Home
            header("Location: index.php");
        }
        exit();
        
    } else {
        // 5. Erro: Usuário não existe ou senha errada
        header("Location: login.php?erro=1");
        exit();
    }
}