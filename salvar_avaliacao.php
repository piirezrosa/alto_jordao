<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

// Só aceita POST via AJAX
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido.']);
    exit;
}

$produto_id = (int)($_POST['produto_id'] ?? 0);
$nota       = (int)($_POST['estrelas']   ?? 0);
$comentario = trim($_POST['comentario']  ?? '');
$titulo     = trim($_POST['nome']        ?? ''); // campo "nome" vira título/apelido

// Validações básicas
if ($produto_id <= 0 || $nota < 1 || $nota > 5 || strlen($comentario) < 5) {
    echo json_encode(['status' => 'error', 'message' => 'Dados inválidos. Preencha todos os campos.']);
    exit;
}

// Verifica se produto existe
$check = $pdo->prepare("SELECT id FROM produtos WHERE id = ? AND ativo = 1");
$check->execute([$produto_id]);
if (!$check->fetchColumn()) {
    echo json_encode(['status' => 'error', 'message' => 'Produto não encontrado.']);
    exit;
}

// Se logado, vincula ao usuário. Se não, salva como anônimo com o nome digitado.
$usuario_id = $_SESSION['usuario_id'] ?? null;

// Verifica se este usuário já avaliou este produto (só bloqueia se estiver logado)
if ($usuario_id) {
    $jaAvaliou = $pdo->prepare("SELECT id FROM avaliacoes WHERE produto_id = ? AND usuario_id = ?");
    $jaAvaliou->execute([$produto_id, $usuario_id]);
    if ($jaAvaliou->fetchColumn()) {
        echo json_encode(['status' => 'error', 'message' => 'Você já avaliou este produto.']);
        exit;
    }
}

// Configuração: moderação ativa ou aprovação automática
try {
    $moderacao = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'loja_moderar_avaliacoes'");
    $moderacao->execute();
    $moderar = $moderacao->fetchColumn();
} catch (Exception $e) {
    $moderar = '1'; // padrão: moderar
}

$status_inicial = ($moderar === '0') ? 'aprovado' : 'pendente';

try {
    $stmt = $pdo->prepare("
        INSERT INTO avaliacoes (produto_id, usuario_id, nota, titulo, comentario, status)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$produto_id, $usuario_id, $nota, $titulo, $comentario, $status_inicial]);

    $nova_id = $pdo->lastInsertId();

    if ($status_inicial === 'aprovado') {
        echo json_encode([
            'status'     => 'success',
            'message'    => 'Avaliação publicada!',
            'moderacao'  => false,
            'avaliacao'  => [
                'nome'       => htmlspecialchars($titulo ?: ($_SESSION['usuario_nome'] ?? 'Anônimo')),
                'nota'       => $nota,
                'comentario' => htmlspecialchars($comentario),
                'data'       => date('d/m/Y'),
            ]
        ]);
    } else {
        echo json_encode([
            'status'    => 'success',
            'message'   => 'Avaliação enviada! Ela será publicada após aprovação.',
            'moderacao' => true,
        ]);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar avaliação.']);
}