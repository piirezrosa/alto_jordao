<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'mercadopago.php';

header('Content-Type: application/json');

if (!isset($_POST['usuario_id'])){
    echo json_encode(['sucesso' => false, 'erro' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

// LER JSON DO BODY
$dados = json_decode(file_get_contents('php://input'), true);
?>