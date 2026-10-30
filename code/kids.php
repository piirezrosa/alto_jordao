<?php
require_once 'config.php';
$query = $pdo->prepare("SELECT * FROM produtos WHERE genero = 'kids' ORDER BY id DESC");
$query->execute();
$produtos = $query->fetchAll(PDO::FETCH_ASSOC);

$aj_titulo = 'Kids';
$aj_sub    = 'A moda que acompanha o crescimento dos pequenos com estilo e conforto.';
$aj_vazio  = 'Ainda não há peças infantis.';
include 'listagem_view.php';