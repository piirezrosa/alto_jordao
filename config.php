<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$db   = 'alto_jordao';
$user = 'root';
$pass = ''; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

/**
 * Renderiza a imagem do produto de forma inteligente
 */
function exibirImagem($nome_imagem) {
    $caminho_upload = "uploads/" . $nome_imagem;
    
    // 1. Arquivo físico na pasta uploads
    if (!empty($nome_imagem) && file_exists($caminho_upload) && is_file($caminho_upload)) {
        return '<img src="'.$caminho_upload.'" class="img-produto-card" alt="Produto">';
    } 
    
    // 2. Link externo
    if (strpos($nome_imagem, 'http') !== false) {
        return '<img src="'.$nome_imagem.'" class="img-produto-card" alt="Produto">';
    }

    // 3. Fallback: Emoji ou Texto
    return '<div class="img-emoji-card">' . $nome_imagem . '</div>';
}

function traduzirCor($corPT) {
    $corPT = mb_strtolower(trim($corPT)); // Deixa tudo em minúsculo e limpa espaços
    
    $cores = [
        'preto'    => 'black',
        'branco'   => 'white',
        'vermelho' => 'red',
        'azul'     => 'blue',
        'verde'    => 'green',
        'amarelo'  => 'yellow',
        'cinza'    => 'gray',
        'rosa'     => 'deeppink',
        'marrom'   => 'brown',
        'laranja'  => 'orange',
        'roxo'     => 'purple',
        'bege'     => '#f5f5dc',
        'marinho'  => '#000080'
    ];

    // Se a cor existir na lista, retorna o inglês. Se não, retorna a própria string 
    // (isso permite que você ainda use #hexadecimal se quiser).
    return $cores[$corPT] ?? $corPT;
}
?>