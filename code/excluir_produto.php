<?php
/**
 * Excluir produto.
 *  - Produto que NUNCA foi vendido: é apagado de verdade (com movimentações, avaliações etc.).
 *  - Produto que já tem pedidos: é ARQUIVADO (ativo = 0) e some da loja, mas o histórico de pedidos,
 *    relatórios e financeiro continuam corretos. Apagar quebraria esses registros.
 */
require_once 'config.php';
require_once 'csrf_helpers.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['usuario_nivel']) || !in_array($_SESSION['usuario_nivel'], ['admin', 'superadmin', 'gerente'], true)) {
    header("Location: login.php"); exit();
}

$id = (int)($_GET['id'] ?? 0);
$volta = 'admin_produtos.php';

if ($id <= 0 || !csrf_ok($_GET['_csrf'] ?? null)) {
    header("Location: $volta?msg=sessao"); exit();   // link inválido ou expirado
}

try {
    $q = $pdo->prepare("SELECT id, nome FROM produtos WHERE id = ?");
    $q->execute([$id]);
    $prod = $q->fetch(PDO::FETCH_ASSOC);
    if (!$prod) { header("Location: $volta?msg=inexistente"); exit(); }

    $q = $pdo->prepare("SELECT COUNT(*) FROM itens_pedido WHERE produto_id = ?");
    $q->execute([$id]);
    $vendido = (int)$q->fetchColumn() > 0;

    if ($vendido) {
        $pdo->prepare("UPDATE produtos SET ativo = 0, destaque = 0 WHERE id = ?")->execute([$id]);
        $acao = 'produto_arquivado'; $msg = 'arquivado';
    } else {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM estoque_movimentacoes WHERE produto_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM produtos WHERE id = ?")->execute([$id]);   // imagens, variações, avaliações e campanhas saem em cascata
        $pdo->commit();
        $acao = 'produto_excluido'; $msg = 'excluido';
    }

    try {
        $pdo->prepare("INSERT INTO logs_sistema (usuario_id, acao, tabela, registro_id, detalhes, ip) VALUES (?,?,?,?,?,?)")
            ->execute([$_SESSION['usuario_id'] ?? null, $acao, 'produtos', $id, $prod['nome'], $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Throwable $e) {}
    header("Location: $volta?msg=$msg"); exit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[EXCLUIR PRODUTO] ' . $e->getMessage());
    header("Location: $volta?msg=erro_exclusao"); exit();
}
