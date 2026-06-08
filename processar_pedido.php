<?php
include 'config.php'; // Já possui o session_start() e a conexão $pdo

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php?erro=necessario_login");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$carrinho = [];

if (isset($_POST['carrinho_dados'])) {
    $carrinho = json_decode($_POST['carrinho_dados'], true);
} 

if (empty($carrinho)) {
    header("Location: index.php?erro=vazio");
    exit;
}

try {
    $pdo->beginTransaction();

    $nome     = $_POST['nome'] ?? '';
    $pdo->prepare("UPDATE usuarios SET nome = ? WHERE id = ?")
        ->execute([$nome, $usuario_id]);
    
    $cep      = $_POST['cep'] ?? '';
    $rua      = $_POST['rua'] ?? '';
    $numero   = $_POST['numero'] ?? '';
    $bairro   = $_POST['bairro'] ?? '';
    $cidade   = $_POST['cidade'] ?? '';
    $estado   = $_POST['estado'] ?? '';

    $check = $pdo->prepare("SELECT id FROM enderecos WHERE usuario_id = ?");
    $check->execute([$usuario_id]);
    $enderecoExiste = $check->fetchColumn();

    if ($enderecoExiste) {
        $pdo->prepare("UPDATE enderecos SET cep = ?, rua = ?, numero = ?, bairro = ?, cidade = ?, estado = ? WHERE usuario_id = ?")
            ->execute([$cep, $rua, $numero, $bairro, $cidade, $estado, $usuario_id]);
    } else {
        $pdo->prepare("INSERT INTO enderecos (usuario_id, cep, rua, numero, bairro, cidade, estado) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$usuario_id, $cep, $rua, $numero, $bairro, $cidade, $estado]);
    }

    // --- 2. CALCULAR TOTAL DO PEDIDO ---
    $total = 0;
    foreach($carrinho as $item) { 
        $total += (float)$item['preco'] * (int)($item['qtd'] ?? 1); 
    }
    
    // --- 3. INSERIR PEDIDO ---
    $sqlPedido = "INSERT INTO pedidos (usuario_id, total, data_pedido, status) VALUES (:user, :total, NOW(), 'Pendente')";
    $stmt = $pdo->prepare($sqlPedido);
    $stmt->execute([
        ':user'  => $usuario_id,
        ':total' => $total
    ]);
    
    $pedido_id = $pdo->lastInsertId();

    // --- 4. INSERIR ITENS DO PEDIDO ---
    $sqlItem = "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario, variacoes) 
                VALUES (:pid, :prod, :qtd, :price, :vars)";
    $stmtItem = $pdo->prepare($sqlItem);
    
    foreach ($carrinho as $item) {
        // Formata as variações (Tamanho e Cor) para salvar no banco
        $vars = ($item['tamanho_escolhido'] ?? 'P') . " | " . ($item['cor_escolhida'] ?? 'Padrão');
        
        $stmtItem->execute([
            ':pid'   => $pedido_id,
            ':prod'  => (int)$item['id'],
            ':qtd'   => (int)($item['qtd'] ?? 1),
            ':price' => (float)$item['preco'],
            ':vars'  => $vars
        ]);
    }

    $pdo->commit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Erro Crítico no Banco: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Sucesso | Alto Jordão</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #fcfcfc; margin: 0; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .card { background: #fff; padding: 50px; border: 1px solid #000; text-align: center; max-width: 450px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .icon { font-size: 50px; margin-bottom: 20px; display: block; }
        .btn { background: #000; color: #fff; text-decoration: none; padding: 18px 25px; display: block; margin-top: 25px; font-weight: 800; text-transform: uppercase; font-size: 11px; letter-spacing: 2px; border-radius: 50px; transition: 0.3s; }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .btn-pedidos { background: #fff; color: #000; border: 1px solid #000; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="card">
        <span class="icon">✅</span>
        <h1 style="text-transform: uppercase; font-size: 22px; font-weight: 900; letter-spacing: -1px; margin-bottom: 15px;">Pedido Confirmado!</h1>
        <p style="color: #666; line-height: 1.6;">Obrigado pela confiança. Seus dados de entrega foram atualizados e seu pedido já está em processamento.</p>
        
        <a href="index.php" class="btn">Continuar Comprando</a>
        <a href="pedidos.php" class="btn btn-pedidos">Acompanhar meus Pedidos</a>
    </div>

    <script>
        // Limpa o carrinho após a compra ter sido gravada no banco com sucesso
        sessionStorage.removeItem('fashion_cart');
    </script>
</body>
</html>