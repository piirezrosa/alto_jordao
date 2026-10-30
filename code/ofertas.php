<?php
require_once 'config.php';
try {
    $query = $pdo->query("SELECT * FROM produtos WHERE (preco_antigo > preco AND preco > 0) OR categoria = 'ofertas' ORDER BY id DESC");
} catch (PDOException $e) {
    $query = $pdo->query("SELECT * FROM produtos WHERE categoria = 'ofertas' ORDER BY id DESC");
}
$produtos = $query->fetchAll(PDO::FETCH_ASSOC);

$aj_titulo = 'Ofertas';
$aj_sub    = 'O desconto que você procura está aqui, aproveite as melhores ofertas da loja.';
$aj_vazio  = 'Não há ofertas no momento.';
include 'listagem_view.php';
