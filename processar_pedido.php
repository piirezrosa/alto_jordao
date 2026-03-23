<?php
include 'config.php';
session_start();

// Recebe os dados do fetch
$dadosJSON = file_get_contents('php://input');
$carrinho = json_decode($dadosJSON, true);

if (!$carrinho || !isset($_SESSION['usuario_id'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Usuário não logado ou carrinho vazio']);
    exit;
}

$conn->begin_transaction();

try {
    // 1. Criar o pedido principal
    $total = 0;
    foreach($carrinho as $item) { $total += $item['preco'] * $item['qtd']; }
    
    $stmt = $conn->prepare("INSERT INTO pedidos (usuario_id, total) VALUES (?, ?)");
    $stmt->bind_param("id", $_SESSION['usuario_id'], $total);
    $stmt->execute();
    $pedido_id = $conn->insert_id;

    // 2. Criar os itens do pedido (Salvando Cor e Tamanho)
    $stmtItem = $conn->prepare("INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario, variacoes) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($carrinho as $item) {
        $stmtItem->bind_param("iiids", 
            $pedido_id, 
            $item['id'], 
            $item['qtd'], 
            $item['preco'], 
            $item['opcoes'] // Aqui entra o "Tam: G | Cor: Preto"
        );
        $stmtItem->execute();
    }

    $conn->commit();
    echo json_encode(['sucesso' => true, 'pedido_id' => $pedido_id]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
?>