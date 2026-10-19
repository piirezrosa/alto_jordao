<?php
date_default_timezone_set('America/Sao_Paulo'); // horários de expiração PIX/boleto e logs
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
    die("Erro na conexão: " . $e->getMessage());
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