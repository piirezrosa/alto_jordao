<?php
require_once 'config.php'; // Certifique-se de que este arquivo existe com a conexão PDO

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    // Voltamos a buscar pela coluna 'email'
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND senha = ?");
    $stmt->execute([$email, $senha]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        // Sucesso: Criamos a sessão
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_nivel'] = $usuario['nivel'];

        // Redirecionamento por permissão
        if ($_SESSION['usuario_nivel'] === 'admin') {
            header("Location: dashboard_admin.php");
        } else {
            header("Location: index.php");
        }
        exit();
    } else {
        // Erro: Volta para o login com aviso
        header("Location: login.php?erro=1");
        exit();
    }
}
?>