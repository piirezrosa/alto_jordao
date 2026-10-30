<?php
require_once 'config.php';
$query = $pdo->prepare("SELECT * FROM produtos WHERE genero IN ('feminino', 'unissex') ORDER BY id DESC");
$query->execute();
$produtos = $query->fetchAll(PDO::FETCH_ASSOC);

$aj_titulo = 'Feminino';
$aj_sub    = 'A moda que te acompanha em todo momento, com estilo e conforto para todas as ocasiões.';
$aj_vazio  = 'Ainda não há peças femininas.';
include 'listagem_view.php';
