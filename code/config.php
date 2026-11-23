<?php
date_default_timezone_set('America/Sao_Paulo'); // horários de expiração PIX/boleto e logs
if (session_status() === PHP_SESSION_NONE) {
    // Cookie de sessão: só HTTP (sem JavaScript), mesmo site e, quando o acesso é HTTPS, só por HTTPS
    $aj_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    if ($aj_https) ini_set('session.cookie_secure', '1');
    session_start();
}

// Sessão com login expira após 2 h sem uso (chamadas automáticas de fundo definem AJ_SEM_ATIVIDADE e não renovam)
if (isset($_SESSION['usuario_id'])) {
    if (isset($_SESSION['ultimo_acesso']) && time() - (int)$_SESSION['ultimo_acesso'] > 7200) {
        $_SESSION = [];
        session_destroy();
        session_start();
        session_regenerate_id(true);
    } elseif (!defined('AJ_SEM_ATIVIDADE')) {
        $_SESSION['ultimo_acesso'] = time();
    }
}

require_once __DIR__ . '/env.php';

// Valores padrão = XAMPP local; em produção defina no .env (veja .env.example)
$host = (string)env('DB_HOST', 'localhost');
$port = (string)env('DB_PORT', '3306');
$db   = (string)env('DB_NAME', 'alto_jordao');
$user = (string)env('DB_USER', 'root');
$pass = (string)env('DB_PASS', '');
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $conn = $pdo; // Cria um apelido para manter compatibilidade
} catch (\PDOException $e) {
    // Detalhe só no log do servidor: a mensagem do PDO mostra host e usuário do banco
    error_log('[DB CONEXAO] ' . $e->getMessage());
    http_response_code(503);
    die("Loja temporariamente indisponível. Tente novamente em instantes.");
}

/**
 * Renderiza a imagem do produto de forma inteligente
 */
function exibirImagem($nome_imagem) {
    $caminho_upload = "img/produtos/" . $nome_imagem;
    
    if (!empty($nome_imagem) && file_exists($caminho_upload) && is_file($caminho_upload)) {
        return '<img src="'.$caminho_upload.'" class="img-produto-card" alt="Produto">';
    } 
    
    if (strpos($nome_imagem, 'http') !== false) {
        return '<img src="'.$nome_imagem.'" class="img-produto-card" alt="Produto">';
    }

    return '<div class="img-emoji-card">' . $nome_imagem . '</div>';
}

function traduzirCor($corPT) {
    $corPT = mb_strtolower(trim($corPT));
    $cores = [
        'preto'     => 'black',
        'branco'    => 'white',
        'vermelho'  => 'red',
        'azul'      => 'blue',
        'verde'     => 'green',
        'amarelo'   => 'yellow',
        'cinza'     => 'gray',
        'rosa'      => 'deeppink',
        'marrom'    => 'brown',
        'laranja'   => 'orange',
        'roxo'      => 'purple',
        'bege'      => '#f5f5dc',
        'marinho'   => '#000080'
    ];
    return $cores[$corPT] ?? $corPT;
}
?>