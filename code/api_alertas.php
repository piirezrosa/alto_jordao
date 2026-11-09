<?php
// Endpoint AJAX — chamado a cada 60s pelo painel admin
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'alertas_helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) ||
    !in_array($_SESSION['usuario_nivel'] ?? '', ['admin','superadmin'])) {
    echo json_encode(['nao_lidos' => 0, 'alertas' => []]);
    exit;
}

$acao = $_GET['acao'] ?? 'contar';
alertas_garantir_schema($pdo);
$minha = alertas_visiveis_sql((int)$_SESSION['usuario_id']);   // só os alertas deste perfil

// Marcar alerta como lido
if ($acao === 'marcar_lido' && isset($_GET['id'])) {
    $pdo->prepare("
        UPDATE alertas SET lido=1, lido_por=?, lido_em=NOW() WHERE id=? AND $minha
    ")->execute([$_SESSION['usuario_id'], (int)$_GET['id']]);
    echo json_encode(['ok' => true]);
    exit;
}

// Marcar todos como lido
if ($acao === 'marcar_todos_lidos') {
    $pdo->prepare("
        UPDATE alertas SET lido=1, lido_por=?, lido_em=NOW() WHERE lido=0 AND $minha
    ")->execute([$_SESSION['usuario_id']]);
    echo json_encode(['ok' => true]);
    exit;
}

// Mantém os alertas atualizados enquanto houver um admin com o painel aberto (no máx. 1x a cada 10 min)
alertas_analise_se_necessario($pdo, 10);

// Preferência do pop-up (por admin; padrão: ligado)
$popup = true;
try {
    $q = $pdo->prepare("SELECT notificar_popup FROM alerta_configuracoes WHERE usuario_id = ?");
    $q->execute([(int)$_SESSION['usuario_id']]);
    $v = $q->fetchColumn();
    if ($v !== false && $v !== null) $popup = (int)$v === 1;
} catch (Throwable $e) {}

// Contar alertas não lidos e buscar os últimos 5
$nao_lidos = $pdo->query("SELECT COUNT(*) FROM alertas WHERE lido=0 AND $minha")->fetchColumn();

$ultimos = $pdo->query("
    SELECT id, tipo, titulo, nivel, referencia_tipo, referencia_id,
           data_criacao, lido
    FROM alertas
    WHERE lido = 0 AND $minha
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

// Para o pop-up: alertas não lidos mais novos que o último já avisado (?desde=ID; sem o parâmetro = todos)
$desde  = max(0, (int)($_GET['desde'] ?? 0));
$max_id = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM alertas WHERE lido=0 AND $minha")->fetchColumn();
$novos = 0; $novos_lista = [];
if ($popup) {
    $novos = (int)$pdo->query("SELECT COUNT(*) FROM alertas WHERE lido=0 AND id > $desde AND $minha")->fetchColumn();
    if ($novos === 1) {
        $n = $pdo->query("SELECT id, tipo, titulo, descricao, nivel FROM alertas WHERE lido=0 AND id > $desde AND $minha LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($n) {
            $n['icone'] = $meta[$n['tipo']]['icone'] ?? '🔔';
            $n['descricao'] = mb_strimwidth((string)$n['descricao'], 0, 140, '…', 'UTF-8');
            $novos_lista[] = $n;
        }
    }
}

echo json_encode(['nao_lidos' => (int)$nao_lidos, 'alertas' => $ultimos, 'popup' => $popup,
                  'max_id' => $max_id, 'novos' => $novos, 'novo' => $novos_lista[0] ?? null]);

function tempoRelativo(string $data): string {
    $diff = time() - strtotime($data);
    if ($diff < 60)     return 'agora';
    if ($diff < 3600)   return floor($diff/60) . ' min atrás';
    if ($diff < 86400)  return floor($diff/3600) . 'h atrás';
    return floor($diff/86400) . 'd atrás';
}