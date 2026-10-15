<?php
// Endpoint AJAX — chamado a cada 60s pelo painel admin
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) ||
    !in_array($_SESSION['usuario_nivel'] ?? '', ['admin','superadmin'])) {
    echo json_encode(['nao_lidos' => 0, 'alertas' => []]);
    exit;
}

$acao = $_GET['acao'] ?? 'contar';

// Marcar alerta como lido
if ($acao === 'marcar_lido' && isset($_GET['id'])) {
    $pdo->prepare("
        UPDATE alertas SET lido=1, lido_por=?, lido_em=NOW() WHERE id=?
    ")->execute([$_SESSION['usuario_id'], (int)$_GET['id']]);
    echo json_encode(['ok' => true]);
    exit;
}

// Marcar todos como lido
if ($acao === 'marcar_todos_lidos') {
    $pdo->prepare("
        UPDATE alertas SET lido=1, lido_por=?, lido_em=NOW() WHERE lido=0
    ")->execute([$_SESSION['usuario_id']]);
    echo json_encode(['ok' => true]);
    exit;
}

// Contar alertas não lidos e buscar os últimos 5
$nao_lidos = $pdo->query("SELECT COUNT(*) FROM alertas WHERE lido=0")->fetchColumn();

$ultimos = $pdo->query("
    SELECT id, tipo, titulo, nivel, referencia_tipo, referencia_id,
           data_criacao, lido
    FROM alertas
    WHERE lido = 0
    ORDER BY
        FIELD(nivel,'critico','aviso','info'),
        data_criacao DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Ícone e cor por tipo
$meta = [
    'produto_problematico' => ['icone' => '⚠️', 'cor' => '#f57c00'],
    'estoque_critico'      => ['icone' => '📦', 'cor' => '#d32f2f'],
    'pedido_parado'        => ['icone' => '⏰', 'cor' => '#1976d2'],
    'avaliacao_negativa'   => ['icone' => '⭐', 'cor' => '#f57c00'],
    'devolucao_alta'       => ['icone' => '🔄', 'cor' => '#d32f2f'],
    'margem_baixa'         => ['icone' => '💰', 'cor' => '#7b1fa2'],
];

foreach ($ultimos as &$a) {
    $a['icone'] = $meta[$a['tipo']]['icone'] ?? '🔔';
    $a['cor']   = $meta[$a['tipo']]['cor']   ?? '#888';
    $a['tempo'] = tempoRelativo($a['data_criacao']);
}

echo json_encode(['nao_lidos' => (int)$nao_lidos, 'alertas' => $ultimos]);

function tempoRelativo(string $data): string {
    $diff = time() - strtotime($data);
    if ($diff < 60)     return 'agora';
    if ($diff < 3600)   return floor($diff/60) . ' min atrás';
    if ($diff < 86400)  return floor($diff/3600) . 'h atrás';
    return floor($diff/86400) . 'd atrás';
}