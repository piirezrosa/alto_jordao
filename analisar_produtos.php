<?php
/** 
*  Motor de análise de produtos, roda via cron 1x ao dia (por padrão, 3:00 da manhã) 
*  Também pode ser disparado manualmente pelos administradores do sistema, 
*  através do arquivo admin_alertas.php com chave secreta.
*/

require_once 'config.php';

$chave_secreta = 'AlgoMuitoSecreto123!';
$via_cli = (php_sapi_name() === 'cli');
if (!$via_cli && ($_GET['chave'] ?? '') !== $chave_secreta) {
    http_response_code(403);
    die('Acesso negado.');
}

function log_analise(string $msg): void {
    echo "[" . date('Y-m-d H:i:s') . "] $msg\n";
}

log_analise("Iniciando análise de produtos...");

// Busca configurações de todos os admins
$admins = $pdo->query("
    SELECT u.id, u.nome, u.email, ac.* FROM usuarios u JOIN alerta_configuracoes ac ON ac.usuario_id = u.id
    WHERE u.nivel IN ('admin', 'superadmin') AND u.status = 'ativo'
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($admins)) {
    log_analise("Nenhum admin com configurações personalizadas de alertas. Encerrando.");
    exit;
}

// Análise de cada produto ativo do sistema
$produtos = $pdo->query("
    SELECT p.id, p.nome, p.preco, p.custo, p.estoque,
        -- Total vendido
        COALESCE(SUM(ip.quantidade), 0) AS total_vendido,
        -- Receita bruta
        COALESCE(SUM(ip.quantidade * ip.preco_unitario), 0) AS receita_bruta,
        -- Total devoluções
        COALESCE(
            (SELECT COUNT(*) FROM devolucoes d
            JOIN pedidos ped ON d.pedido_id = ped.id
            JOIN itens_pedido ip2 ON ip2.pedido_id = ped.id
            WHERE ip2.produto_id = p.id), 0) AS total_devolucoes,
        -- Nota media (conta somente avaliações aprovadas)
        COALESCE(
            (SELECT AVG(a.nota) FROM avaliacoes a
            WHERE a.produto_id = p.id AND a.status = 'aprovado'), 0) AS nota_media,
        -- Avaliações negativas recentes (últimos 30 dias, nota <= 2)
        COALESCE(
            (SELECT COUNT(*) FROM avaliacoes a
            WHERE a.produto_id = p.id 
            AND a.nota <= 2
            AND a.data >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS aval_negativas_recentes,
        -- Pedidos cancelados após envio (diagnostico de problemas de logística)
        COALESCE(
            (SELECT COUNT(*) FROM pedidos ped
            JOIN itens_pedido ip3 ON ip3.pedido_id = ped.id
            WHERE ip3.produto_id = p.id
            AND ped.status = 'cancelado'
            AND ped.data_envio IS NOT NULL), 0) AS problemas_entrega
    FROM produtos p
    LEFT JOIN itens_pedido ip ON ip.produto_id = p.id
    LEFT JOIN pedidos ped2 ON ped2.id = ip.pedido_id AND ped2.status != 'cancelado'
    WHERE p.ativo = 1
    GROUP BY p.id
")->fetchAll(PDO::FETCH_ASSOC);

// Pedidos parados (sem atualização de status por N dias)
// Processado separadamente, não é por produto, mas sim por pedido, para identificar gargalos logísticos
$pedidos_parados_query = $pdo->prepare("
    SELECT id, data_pedido, status,
        DATEDIFF(NOW(), data_pedido) AS dias_parado
    FROM pedidos
    WHERE status IN ('pendente', 'em_separacao')
        AND DATEDIFF(NOW(), data_pedido) >= ?
");

// Função para gerar alerta (evita duplicatas no mesmo dia)
function gerarAlerta(
    PDO $pdo,
    string $tipo,
    string $titulo,
    string $descricao,
    string $nivel,
    string $ref_tipo,
    int $ref_id
): bool {
    // Verifica se já existe alerta do mesmo tipo para a mesma referência no mesmo dia
    $existe = $pdo->prepare("
        SELECT id FROM alertas
        WHERE tipo = ?
            AND referencia_tipo = ?
            AND referencia_id = ?
            AND DATE(data_criacao) = CURDATE()
    ");
    $existe->execute([$tipo, $ref_tipo, $ref_id]);

    if ($existe->fetchColumn()) return false; // Alerta já existe neste dia

    $pdo->prepare("
        INSERT INTO alertas (tipo, titulo, descricao, nivel, referencia_tipo, referencia_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([$tipo, $titulo, $descricao, $nivel, $ref_tipo, $ref_id]);

    return true;
}

$total_alertas = 0;

// Loop principal (análise por produto)
foreach ($produtos as $p) {
    $nome = $p['nome'];
    $vendido = (int)$p['total_vendido'];
    $devolucoes = (int)$p['total_devolucoes'];
    $nota = (float)$p['nota_media'];
    $neg_recentes = (int)$p['aval_negativas_recentes'];
    $prob_entrega = (int)$p['problemas_entrega'];
    $estoque = (int)$p['estoque'];
    $receita = (float)$p['receita_bruta'];
    $custo = (float)($p['custo'] ?? 0);

    // Taxa de devolução em %
    $taxa_dev = $vendido > 0 ? ($devolucoes / $vendido) * 100 : 0;

    // Margem de lucro estimada em %
    $margem = ($receita > 0 && $custo > 0 && $vendido > 0) ? (($receita - ($custo * $vendido)) / $receita) * 100 : null;

    // Pontuação de problema (medida de 0 a 100, quanto maior, mais crítico)
    $score = 0;
    $score += min(40, $taxa_dev * 2); // Devoluções pesam até 40 pontos
    $score += max(0, (3 - $nota) * 10); // Nota baixa pesa até 30 pontos
    $score += min(20, $neg_recentes * 5); // Avaliações negativas recentes pesam até 20 pontos
    $score += min(10, $prob_entrega * 5); // Problemas de entrega pesam até 10 pontos

    // Verifica alertas por admin 
    foreach ($admins as $admin) {

        // 1. Produto problemático (score alto)
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

        // 2. Taxa de devolução alta
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

        // 3. Avaliação negativa recente
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

        // 4. Estoque crítico ou zerado
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

        // 5. Margem de lucro baixa
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

// Pedidos parados
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

// LOG FINAL
$pdo->prepare("
    INSERT INTO logs_sistema (acao, tabela, detalhes)
    VALUES ('analise_produtos_executada', 'alertas', ?)
")->execute(["Total de alertas gerados: $total_alertas | Produtos analisados: " . count($produtos)]);

log_analise("✅ Análise concluída. $total_alertas alertas gerados.");