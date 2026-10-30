<?php
require_once 'config.php';
$query = $pdo->prepare("SELECT * FROM produtos WHERE genero IN ('masculino', 'unissex') ORDER BY id DESC");
$query->execute();
$produtos = $query->fetchAll(PDO::FETCH_ASSOC);

$aj_titulo = 'Masculino';
$aj_sub    = 'A moda que te acompanha com estilo e conforto, para todas as ocasiões.';
$aj_vazio  = 'Ainda não há peças masculinas.';
include 'listagem_view.php';
