<?php
/**
 * ============================================================
 *  ALTO JORDÃO — Motor de Análise de Produtos
 *  Roda via cron 1x por dia (sugerido: 03:00 da manhã)
 *  Cron: 0 3 * * * php /caminho/do/projeto/analisar_produtos.php
 *
 *  Também pode ser disparado manualmente pelo admin via
 *  admin_alertas.php com chave secreta.
 * ============================================================
 */

require_once 'config.php';

$chave_secreta = 'altojordao_analise_2026'; // Troque em produção
$via_cli = (php_sapi_name() === 'cli');
if (!$via_cli && ($_GET['chave'] ?? '') !== $chave_secreta) {
    http_response_code(403); die('Acesso negado.');
}

function log_analise(string $msg): void {
    echo "[" . date('Y-m-d H:i:s') . "] $msg\n";
}

log_analise("Iniciando análise de produtos...");

// ── BUSCA CONFIGURAÇÕES DE TODOS OS ADMINS ────────────────
$admins = $pdo->query("
    SELECT u.id, u.nome, u.email, ac.*
    FROM usuarios u
    JOIN alerta_configuracoes ac ON ac.usuario_id = u.id
    WHERE u.nivel IN ('admin','superadmin') AND u.status = 'ativo'
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($admins)) {
    log_analise("Nenhum admin com configuração de alertas. Encerrando.");
    exit;
}

// ── ANÁLISE DE CADA PRODUTO ATIVO ────────────────────────
$produtos = $pdo->query("
    SELECT
        p.id,
        p.nome,
        p.preco,
        p.custo,
        p.estoque,
        -- Total vendido
        COALESCE(SUM(ip.quantidade), 0) AS total_vendido,
        -- Receita bruta
        COALESCE(SUM(ip.quantidade * ip.preco_unitario), 0) AS receita_bruta,
        -- Total de devoluções
        COALESCE(
            (SELECT COUNT(*) FROM devolucoes d
             JOIN pedidos ped ON d.pedido_id = ped.id
             JOIN itens_pedido ip2 ON ip2.pedido_id = ped.id
             WHERE ip2.produto_id = p.id),
        0) AS total_devolucoes,
        -- Nota média (avaliações aprovadas)
        COALESCE(
            (SELECT AVG(a.nota) FROM avaliacoes a
             WHERE a.produto_id = p.id AND a.status = 'aprovado'),
        5) AS nota_media,
        -- Avaliações negativas recentes (últimos 30 dias, nota <= 2)
        COALESCE(
            (SELECT COUNT(*) FROM avaliacoes a
             WHERE a.produto_id = p.id
               AND a.nota <= 2
               AND a.data >= DATE_SUB(NOW(), INTERVAL 30 DAY)),
        0) AS aval_negativas_recentes,
        -- Pedidos cancelados após envio (problema na entrega)
        COALESCE(
            (SELECT COUNT(*) FROM pedidos ped
             JOIN itens_pedido ip3 ON ip3.pedido_id = ped.id
             WHERE ip3.produto_id = p.id
               AND ped.status = 'cancelado'
               AND ped.data_envio IS NOT NULL),
        0) AS problemas_entrega
    FROM produtos p
    LEFT JOIN itens_pedido ip ON ip.produto_id = p.id
    LEFT JOIN pedidos ped2 ON ped2.id = ip.pedido_id AND ped2.status != 'cancelado'
    WHERE p.ativo = 1
    GROUP BY p.id
")->fetchAll(PDO::FETCH_ASSOC);

// ── PEDIDOS PARADOS (sem atualização há N dias) ───────────
// (processado separadamente — não é por produto)
$pedidos_parados_query = $pdo->prepare("
    SELECT id, data_pedido, status,
           DATEDIFF(NOW(), data_pedido) AS dias_parado
    FROM pedidos
    WHERE status IN ('pendente','em_separacao')
      AND DATEDIFF(NOW(), data_pedido) >= ?
");

// ── FUNÇÃO: GERAR ALERTA (evita duplicatas no mesmo dia) ──
function gerarAlerta(
    PDO    $pdo,
    string $tipo,
    string $titulo,
    string $descricao,
    string $nivel,
    string $ref_tipo,
    int    $ref_id
): bool {
    // Verifica se já existe alerta do mesmo tipo para o mesmo item hoje
    $existe = $pdo->prepare("
        SELECT id FROM alertas
        WHERE tipo = ?
          AND referencia_tipo = ?
          AND referencia_id = ?
          AND DATE(data_criacao) = CURDATE()
    ");
    $existe->execute([$tipo, $ref_tipo, $ref_id]);

    if ($existe->fetchColumn()) return false; // já gerado hoje

    $pdo->prepare("
        INSERT INTO alertas (tipo, titulo, descricao, nivel, referencia_tipo, referencia_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([$tipo, $titulo, $descricao, $nivel, $ref_tipo, $ref_id]);

    return true;
}

$total_alertas = 0;

// ============================================================
// LOOP PRINCIPAL — ANÁLISE POR PRODUTO
// ============================================================
foreach ($produtos as $p) {

    $nome          = $p['nome'];
    $vendido       = (int)$p['total_vendido'];
    $devolucoes    = (int)$p['total_devolucoes'];
    $nota          = (float)$p['nota_media'];
    $neg_recentes  = (int)$p['aval_negativas_recentes'];
    $prob_entrega  = (int)$p['problemas_entrega'];
    $estoque       = (int)$p['estoque'];
    $receita       = (float)$p['receita_bruta'];
    $custo         = (float)($p['custo'] ?? 0);

    // Taxa de devolução (%)
    $taxa_dev = $vendido > 0 ? ($devolucoes / $vendido) * 100 : 0;

    // Margem estimada (%)
    $margem = ($receita > 0 && $custo > 0 && $vendido > 0)
        ? (($receita - ($custo * $vendido)) / $receita) * 100
        : null;

    // ── SCORE DE PROBLEMA (0 a 100) ───────────────────────
    // Quanto maior, pior o desempenho do produto
    $score = 0;
    $score += min(40, $taxa_dev * 2);          // até 40 pts (taxa dev > 20% = max)
    $score += max(0, (3 - $nota) * 10);        // até 30 pts (nota 0 = 30pts, nota 3 = 0)
    $score += min(20, $neg_recentes * 5);      // até 20 pts (4+ negativas recentes)
    $score += min(10, $prob_entrega * 5);      // até 10 pts

    // ── VERIFICA ALERTAS POR ADMIN ────────────────────────
    foreach ($admins as $admin) {

        // 1. PRODUTO PROBLEMÁTICO (score alto)
        if ($admin['alerta_produto_problematico']) {
            $limiar_score = 30; // score >= 30 já é sinal de atenção
            if ($score >= $limiar_score) {
                $nivel = $score >= 60 ? 'critico' : 'aviso';
                $gerado = gerarAlerta(
                    $pdo,
                    'produto_problematico',
                    "Produto com desempenho ruim: $nome",
                    "Score de problema: " . round($score) . "/100. " .
                    "Taxa de devolução: " . round($taxa_dev, 1) . "%. " .
                    "Nota média: " . round($nota, 1) . "/5. " .
                    "Avaliações negativas recentes: $neg_recentes. " .
                    "Problemas na entrega: $prob_entrega.",
                    $nivel,
                    'produto',
                    $p['id']
                );
                if ($gerado) {
                    $total_alertas++;
                    log_analise("⚠ Produto problemático: $nome (score: " . round($score) . ")");
                }
            }
        }

        // 2. TAXA DE DEVOLUÇÃO ALTA
        if ($admin['alerta_devolucao_alta'] && $vendido >= 5) {
            if ($taxa_dev >= $admin['limiar_taxa_devolucao']) {
                $gerado = gerarAlerta(
                    $pdo,
                    'devolucao_alta',
                    "Taxa de devolução elevada: $nome",
                    "Taxa de devolução: " . round($taxa_dev, 1) . "% " .
                    "($devolucoes devoluções em $vendido vendas). " .
                    "Limiar configurado: " . $admin['limiar_taxa_devolucao'] . "%.",
                    $taxa_dev >= 20 ? 'critico' : 'aviso',
                    'produto',
                    $p['id']
                );
                if ($gerado) $total_alertas++;
            }
        }

        // 3. AVALIAÇÃO NEGATIVA RECENTE
        if ($admin['alerta_avaliacao_negativa']) {
            if ($nota <= $admin['limiar_nota_media'] && $nota < 5) {
                $gerado = gerarAlerta(
                    $pdo,
                    'avaliacao_negativa',
                    "Avaliação baixa: $nome",
                    "Nota média atual: " . round($nota, 1) . "/5. " .
                    "$neg_recentes avaliações negativas nos últimos 30 dias. " .
                    "Limiar configurado: " . $admin['limiar_nota_media'] . ".",
                    $nota <= 2 ? 'critico' : 'aviso',
                    'produto',
                    $p['id']
                );
                if ($gerado) $total_alertas++;
            }
        }

        // 4. ESTOQUE CRÍTICO
        if ($admin['alerta_estoque_critico']) {
            if ($estoque <= $admin['limiar_estoque_critico'] && $estoque > 0) {
                $gerado = gerarAlerta(
                    $pdo,
                    'estoque_critico',
                    "Estoque crítico: $nome",
                    "Apenas $estoque unidade(s) em estoque. " .
                    "Limiar configurado: " . $admin['limiar_estoque_critico'] . " unidades.",
                    $estoque === 1 ? 'critico' : 'aviso',
                    'produto',
                    $p['id']
                );
                if ($gerado) $total_alertas++;
            } elseif ($estoque === 0) {
                $gerado = gerarAlerta(
                    $pdo,
                    'estoque_critico',
                    "Produto sem estoque: $nome",
                    "Produto está indisponível para venda — estoque zerado.",
                    'critico',
                    'produto',
                    $p['id']
                );
                if ($gerado) $total_alertas++;
            }
        }

        // 5. MARGEM BAIXA
        if ($admin['alerta_margem_baixa'] && $margem !== null) {
            if ($margem < $admin['limiar_margem_minima']) {
                $gerado = gerarAlerta(
                    $pdo,
                    'margem_baixa',
                    "Margem baixa: $nome",
                    "Margem estimada: " . round($margem, 1) . "%. " .
                    "Limiar configurado: " . $admin['limiar_margem_minima'] . "%. " .
                    "Receita total: R$ " . number_format($receita, 2, ',', '.') . ".",
                    'aviso',
                    'produto',
                    $p['id']
                );
                if ($gerado) $total_alertas++;
            }
        }
    }
}

// ============================================================
// PEDIDOS PARADOS
// ============================================================
foreach ($admins as $admin) {
    if (!$admin['alerta_pedido_parado']) continue;

    $pedidos_parados_query->execute([$admin['limiar_dias_pedido_parado']]);
    $parados = $pedidos_parados_query->fetchAll(PDO::FETCH_ASSOC);

    foreach ($parados as $ped) {
        $gerado = gerarAlerta(
            $pdo,
            'pedido_parado',
            "Pedido #{$ped['id']} parado há {$ped['dias_parado']} dias",
            "Pedido com status '{$ped['status']}' sem atualização há {$ped['dias_parado']} dias. " .
            "Data do pedido: " . date('d/m/Y', strtotime($ped['data_pedido'])) . ".",
            $ped['dias_parado'] >= 14 ? 'critico' : 'aviso',
            'pedido',
            $ped['id']
        );
        if ($gerado) $total_alertas++;
    }
}

// ── LOG FINAL ─────────────────────────────────────────────
$pdo->prepare("
    INSERT INTO logs_sistema (acao, tabela, detalhes)
    VALUES ('analise_produtos_executada', 'alertas', ?)
")->execute(["Total de alertas gerados: $total_alertas | Produtos analisados: " . count($produtos)]);

log_analise("✅ Análise concluída. $total_alertas alertas gerados.");