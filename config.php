<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$db   = 'alto_jordao';
$user = 'root';
$pass = ''; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=3307;dbname=$db;charset=$charset";
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