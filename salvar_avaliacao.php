<?php 
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $produto_id = isset($_POST['produto_id']) ? (int)$_POST['produto_id'] : 0;
    $nome = isset($_POST['nome']) ? strip_tags(trim($_POST['nome'])) : '';
    $estrelas = isset($_POST['estrelas']) ? (int)$_POST['estrelas'] : 5;
    $comentario = isset($_POST['comentario']) ? strip_tags(trim($_POST['comentario'])) : '';

    if ($produto_id > 0 && !empty($nome) && !empty($comentario)) {
        try {
            $sql = "INSERT INTO avaliacoes (produto_id, nome, estrelas, comentario) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$produto_id, $nome, $estrelas, $comentario]);

            if (isset($_GET['ajax'])) {
                echo json_encode(['status' => 'success', 'message' => 'Avaliação enviada com sucesso!']);
            }

            header("Location: produto.php?id=" . $produto_id);
            exit();
        } catch (PDOException $e) {
            die("Erro ao salvar avaliação: " . $e->getMessage()); 
        }
    } else {
        echo "Dados inválidos. Por favor, preencha todos os campos corretamente.";
    }
}