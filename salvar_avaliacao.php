<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
 
header('Content-Type: application/json');
 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido.']);
    exit;
}
 
$produto_id = (int)($_POST['produto_id'] ?? 0);
$nota       = (int)($_POST['estrelas']   ?? 0);
$comentario = trim($_POST['comentario']  ?? '');
$nome_autor = trim($_POST['nome']        ?? '');
 
if ($produto_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Produto inválido.']);
    exit;
}
if ($nota < 1 || $nota > 5) {
    echo json_encode(['status' => 'error', 'message' => 'Nota deve ser entre 1 e 5.']);
    exit;
}
if (strlen($comentario) < 5) {
    echo json_encode(['status' => 'error', 'message' => 'Comentário muito curto (mínimo 5 caracteres).']);
    exit;
}
 
$check = $pdo->prepare("SELECT id FROM produtos WHERE id = ? AND ativo = 1");
$check->execute([$produto_id]);
if (!$check->fetchColumn()) {
    echo json_encode(['status' => 'error', 'message' => 'Produto não encontrado.']);
    exit;
}
 
$usuario_id   = $_SESSION['usuario_id']   ?? null;
$usuario_nome = $_SESSION['usuario_nome'] ?? $nome_autor;
 
if ($usuario_id) {
    $jaAvaliou = $pdo->prepare(
        "SELECT id FROM avaliacoes WHERE produto_id = ? AND usuario_id = ?"
    );
    $jaAvaliou->execute([$produto_id, $usuario_id]);
    if ($jaAvaliou->fetchColumn()) {
        echo json_encode(['status' => 'error', 'message' => 'Você já avaliou este produto.']);
        exit;
    }
}
 
// Moderação
$moderar = '1';
try {
    $q = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'loja_moderar_avaliacoes'");
    $q->execute();
    $val = $q->fetchColumn();
    if ($val !== false) $moderar = $val;
} catch (Exception $e) {}
 
$status_inicial = ($moderar === '0') ? 'aprovado' : 'pendente';
 
// Garante que a tabela existe
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS avaliacoes (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            produto_id  INT             NOT NULL,
            usuario_id  INT             DEFAULT NULL,
            pedido_id   INT             DEFAULT NULL,
            nota        TINYINT         NOT NULL,
            titulo      VARCHAR(150)    DEFAULT NULL,
            comentario  TEXT            DEFAULT NULL,
            status      ENUM('pendente','aprovado','reprovado') DEFAULT 'pendente',
            data        DATETIME        DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_produto (produto_id),
            INDEX idx_status  (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {}
 
// INSERT
try {
    $stmt = $pdo->prepare("
        INSERT INTO avaliacoes
            (produto_id, usuario_id, nota, titulo, comentario, status, data)
        VALUES
            (:produto_id, :usuario_id, :nota, :titulo, :comentario, :status, NOW())
    ");
 
    $stmt->execute([
        ':produto_id' => $produto_id,
        ':usuario_id' => $usuario_id,
        ':nota'       => $nota,
        ':titulo'     => $nome_autor ?: null,
        ':comentario' => $comentario,
        ':status'     => $status_inicial,
    ]);
 
    $nova_id = $pdo->lastInsertId();
 
    if (!$nova_id) {
        throw new Exception("INSERT não retornou ID — verifique as constraints da tabela.");
    }
 
} catch (Exception $e) {
    error_log("[AVALIACAO ERROR] produto_id=$produto_id | " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar avaliação: ' . $e->getMessage()]);
    exit;
}
 
$nome_exibir = $usuario_nome ?: ($nome_autor ?: 'Anônimo');
 
if ($status_inicial === 'aprovado') {
    echo json_encode([
        'status'    => 'success',
        'message'   => 'Avaliação publicada!',
        'moderacao' => false,
        'avaliacao' => [
            'id'         => $nova_id,
            'nome'       => htmlspecialchars($nome_exibir),
            'nota'       => $nota,
            'comentario' => htmlspecialchars($comentario),
            'data'       => date('d/m/Y'),
        ],
    ]);
} else {
    echo json_encode([
        'status'    => 'success',
        'message'   => 'Avaliação enviada! Será publicada após aprovação da equipe.',
        'moderacao' => true,
    ]);
}