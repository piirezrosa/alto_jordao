<?php
require_once 'config.php';
$termo = trim((string)($_GET['q'] ?? ''));

if ($termo === '') {
    $produtos = [];
} else {
    $query = $pdo->prepare("SELECT * FROM produtos WHERE nome LIKE ? OR categoria LIKE ? OR descricao LIKE ? ORDER BY id DESC");
    $b = '%' . $termo . '%';
    $query->execute([$b, $b, $b]);
    $produtos = $query->fetchAll(PDO::FETCH_ASSOC);
}

$aj_busca  = true;
$aj_titulo = $termo === '' ? 'Buscar' : 'Resultados para "' . $termo . '"';
$aj_sub    = $termo === '' ? 'Digite o nome, a categoria ou uma palavra da descrição.' : '';
$aj_vazio  = $termo === '' ? 'Digite algo para buscar.' : 'Nenhum produto encontrado para "' . $termo . '".';
include 'listagem_view.php';
