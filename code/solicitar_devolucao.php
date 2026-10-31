<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit; }

$pedido_id = (int)($_GET['pedido_id'] ?? 0);
$erro = '';

// O pedido precisa ser do próprio cliente
$stmt = $pdo->prepare("SELECT id FROM pedidos WHERE id = ? AND usuario_id = ?");
$stmt->execute([$pedido_id, $_SESSION['usuario_id']]);
if (!$stmt->fetchColumn()) { header("Location: meus_pedidos.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motivo   = trim((string)($_POST['motivo'] ?? ''));
    $detalhes = trim((string)($_POST['detalhes'] ?? ''));
    $foto_nome = null;

    if (!empty($_FILES['foto']['name'])) {
        // Só imagens: a extensão vem do arquivo enviado, então é validada
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $info = @getimagesize($_FILES['foto']['tmp_name']);
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) || $info === false) {
            $erro = 'Envie uma foto nos formatos JPG, PNG, WEBP ou GIF.';
        } else {
            $foto_nome = "dev_" . md5(time() . rand()) . "." . $ext;
            if (!is_dir("img/devolucoes/")) { mkdir("img/devolucoes/", 0755, true); }
            move_uploaded_file($_FILES['foto']['tmp_name'], "img/devolucoes/" . $foto_nome);
        }
    }

    if ($motivo === '') { $erro = 'Escolha o motivo.'; }

    if ($erro === '') {
        $stmt = $pdo->prepare("INSERT INTO devolucoes (pedido_id, usuario_id, motivo, detalhes, foto_defeito) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$pedido_id, $_SESSION['usuario_id'], $motivo, $detalhes, $foto_nome]);
        header("Location: meus_pedidos.php?msg=devolucao_solicitada");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Troca ou devolução | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="aj-page">

<?php include 'header.php'; ?>

<main class="aj-auth">
    <div class="aj-auth-card" style="max-width:560px">
        <h1>Troca ou devolução</h1>
        <p>Pedido #<?= str_pad($pedido_id, 5, '0', STR_PAD_LEFT) ?>. Conte o que aconteceu e envie uma foto da peça.</p>

        <?php if ($erro): ?><div class="aj-alert err" role="alert"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="aj-form">
            <div class="aj-field">
                <label for="motivo">Qual o motivo?</label>
                <select id="motivo" name="motivo" required>
                    <option value="Tamanho incorreto">Tamanho incorreto</option>
                    <option value="Defeito de fabricação">Defeito de fabricação</option>
                    <option value="Produto diferente do anúncio">Produto diferente do anúncio</option>
                    <option value="Arrependimento">Arrependimento (até 7 dias)</option>
                </select>
            </div>
            <div class="aj-field">
                <label for="foto">Foto da peça ou do defeito</label>
                <input id="foto" type="file" name="foto" accept="image/*" required>
            </div>
            <div class="aj-field">
                <label for="detalhes">Explique o que aconteceu</label>
                <textarea id="detalhes" name="detalhes" rows="4" placeholder="Por exemplo: a costura da manga direita abriu."></textarea>
            </div>
            <button type="submit" class="aj-btn aj-btn--block">Enviar para análise</button>
        </form>
        <p class="aj-auth-alt"><a href="meus_pedidos.php">Voltar para meus pedidos</a></p>
    </div>
</main>

<?php include 'footer.php'; ?>
<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>
