<?php
require_once 'config.php';
$produtos = $pdo->query("SELECT * FROM produtos ORDER BY id DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);

$aj_titulo = 'Lançamentos';
$aj_sub    = 'As 12 peças mais recentes da loja.';
$aj_vazio  = 'Nenhum lançamento por enquanto.';
include 'listagem_view.php';
