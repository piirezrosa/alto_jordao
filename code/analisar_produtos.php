<?php
/**
 * ALTO JORDÃO — Análise de produtos / geração de alertas (entrada opcional via cron ou URL)
 *
 * O motor fica em alertas_helpers.php e já roda sozinho quando um admin usa o painel
 * (a cada ~10 min), então o cron NÃO é obrigatório. Use este arquivo só se quiser rodar
 * por agendamento:   php /caminho/do/projeto/code/analisar_produtos.php
 *
 * Via navegador exige ?chave=... igual a ALERTAS_CHAVE do .env (sem ela, só funciona no terminal).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/alertas_helpers.php';

$via_cli = (php_sapi_name() === 'cli');
$chave   = (string)env('ALERTAS_CHAVE', '');
if (!$via_cli && ($chave === '' || !hash_equals($chave, (string)($_GET['chave'] ?? '')))) {
    http_response_code(403); die('Acesso negado.');
}

$r = alertas_executar_analise($pdo);
foreach ($r['log'] as $l) echo "[" . date('Y-m-d H:i:s') . "] $l\n";
echo "[" . date('Y-m-d H:i:s') . "] Análise concluída: {$r['total']} alertas gerados, {$r['produtos']} produtos analisados.\n";
