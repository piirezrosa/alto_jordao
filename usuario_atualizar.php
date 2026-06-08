<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$id = $_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['nome'])) {
        $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, cpf = ?, telefone = ? WHERE id = ?");
        $stmt->execute([$_POST['nome'], $_POST['cpf'], $_POST['telefone'], $id]);
    } elseif (isset($_POST['cep'])) {
        $check = $pdo->prepare("SELECT id FROM enderecos WHERE usuario_id = ?"); 
        $check->execute([$id]);
        $existe = $check->fetchColumn();
        if ($existe){
            $stmt = $pdo->prepare("UPDATE enderecos SET cep = ?, rua = ?, numero = ?, bairro = ?, cidade = ?, estado = ? WHERE usuario_id = ?");
            $stmt->execute([
                $_POST['cep'], 
                $_POST['rua'], 
                $_POST['numero'], 
                $_POST['bairro'], 
            $_POST['cidade'], // Campo adicionado
            $_POST['estado'], 
            $id
        ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO enderecos (usuario_id, cep, rua, numero, bairro, cidade, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $id,
                $_POST['cep'],
                $_POST['rua'],
                $_POST['numero'],
                $_POST['bairro'],
                $_POST['cidade'],
                $_POST['estado']
            ]);
        }
    }
    // Redireciona de volta para a página do usuário com sinal de sucesso
    header("Location: usuario.php?sucesso=1");
    exit();
}