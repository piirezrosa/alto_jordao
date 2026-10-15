<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit; }

$pedido_id = $_GET['pedido_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motivo = $_POST['motivo'];
    $detalhes = $_POST['detalhes'];
    $foto_nome = null;

    // Lógica de Upload da Foto do Defeito
    if (!empty($_FILES['foto']['name'])) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $foto_nome = "dev_" . md5(time() . rand()) . "." . $ext;
        
        if (!is_dir("img/devolucoes/")) { mkdir("img/devolucoes/", 0777, true); }
        move_uploaded_file($_FILES['foto']['tmp_name'], "img/devolucoes/" . $foto_nome);
    }
    
    $stmt = $pdo->prepare("INSERT INTO devolucoes (pedido_id, usuario_id, motivo, detalhes, foto_defeito) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$pedido_id, $_SESSION['usuario_id'], $motivo, $detalhes, $foto_nome]);
    
    header("Location: meus_pedidos.php?msg=devolucao_solicitada");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Solicitar Devolução | Alto Jordão</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #fbfbfb; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .dev-container { max-width: 500px; width: 90%; background: #fff; padding: 40px; border-radius: 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.05); border: 1px solid #eee; }
        h2 { font-weight: 900; text-transform: uppercase; letter-spacing: -1.5px; margin-bottom: 5px; }
        p { color: #888; font-size: 14px; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; display: flex; flex-direction: column; gap: 8px; }
        label { font-weight: 800; font-size: 11px; text-transform: uppercase; color: #000; letter-spacing: 1px; }
        select, textarea, input[type="file"] { padding: 15px; border: 1.5px solid #f0f0f0; border-radius: 12px; font-family: inherit; font-size: 14px; background: #fdfdfd; }
        textarea { resize: none; }
        .btn-black { background: #000; color: #fff; padding: 20px; border: none; border-radius: 50px; font-weight: 900; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; margin-top: 10px; transition: 0.3s; }
        .btn-black:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="dev-container">
        <h2>Trocas e Devoluções</h2>
        <p>Pedido ID: #<?= htmlspecialchars($pedido_id) ?></p>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Qual o motivo?</label>
                <select name="motivo" required>
                    <option value="Tamanho incorreto">Tamanho incorreto</option>
                    <option value="Defeito de fabricação">Defeito de fabricação</option>
                    <option value="Produto diferente do anúncio">Produto diferente do anúncio</option>
                    <option value="Arrependimento">Arrependimento (7 dias)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Foto do Produto/Defeito</label>
                <input type="file" name="foto" accept="image/*" required>
            </div>

            <div class="form-group">
                <label>Explique o que aconteceu</label>
                <textarea name="detalhes" rows="4" placeholder="Ex: A costura veio solta na manga direita..."></textarea>
            </div>

            <button type="submit" class="btn-black" style="width: 100%;">Enviar para Análise</button>
        </form>
    </div>
</body>
</html>