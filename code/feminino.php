<?php
require_once 'config.php';
$query = $pdo->prepare("SELECT * FROM produtos WHERE genero IN ('feminino', 'unissex') ORDER BY id DESC");
$query->execute();
$produtos = $query->fetchAll(PDO::FETCH_ASSOC);

$aj_titulo = 'Feminino';
$aj_sub    = 'Peças confortáveis para usar do trabalho ao fim de semana.';
$aj_vazio  = 'Ainda não há peças femininas.';
include 'listagem_view.php';
