<?php
require_once 'config.php';
$produtos = $pdo->query("SELECT * FROM produtos ORDER BY id DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);

$aj_titulo = 'Lançamentos';
$aj_sub    = 'Confira as peças mais recentes da moda que te faz bem.';
$aj_vazio  = 'Nenhum lançamento por enquanto.';
include 'listagem_view.php';