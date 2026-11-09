<?php
/**
 * Cria (ou redefine a senha de) um superadmin. SÓ roda pela linha de comando:
 *   php criar_superadmin.php "Nome" email@loja.com.br
 *   docker compose exec web php criar_superadmin.php "Nome" email@loja.com.br
 * A senha é pedida no terminal (ou lida de ADMIN_SENHA). Nada fica gravado em arquivo.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/config.php';

$nome  = $argv[1] ?? '';
$email = $argv[2] ?? '';
if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php criar_superadmin.php \"Nome\" email@dominio.com\n"); exit(1);
}
$senha = getenv('ADMIN_SENHA') ?: '';
if ($senha === '') {
    fwrite(STDOUT, "Senha (mín. 10 caracteres): ");
    if (DIRECTORY_SEPARATOR === '/') system('stty -echo 2>/dev/null');
    $senha = trim((string)fgets(STDIN));
    if (DIRECTORY_SEPARATOR === '/') system('stty echo 2>/dev/null');
    fwrite(STDOUT, "\n");
}
if (strlen($senha) < 10) { fwrite(STDERR, "Senha muito curta (mínimo 10 caracteres).\n"); exit(1); }

$hash = password_hash($senha, PASSWORD_DEFAULT);
$q = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
$q->execute([$email]);
if ($id = $q->fetchColumn()) {
    $pdo->prepare("UPDATE usuarios SET nome=?, senha=?, nivel='superadmin', status='ativo' WHERE id=?")->execute([$nome, $hash, $id]);
    echo "Usuário existente atualizado para superadmin (id $id).\n";
} else {
    $pdo->prepare("INSERT INTO usuarios (nome, email, senha, nivel, status) VALUES (?,?,?,'superadmin','ativo')")->execute([$nome, $email, $hash]);
    echo "Superadmin criado (id " . $pdo->lastInsertId() . ").\n";
}
