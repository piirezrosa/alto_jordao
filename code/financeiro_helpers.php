<?php
/**
 * Financeiro — Alto Jordão
 *
 * FONTE ÚNICA dos números de resultado (dashboard, relatórios, DRE). Estrutura:
 *
 *   Receita (o que o cliente pagou: produtos − descontos + frete cobrado)
 *   − Devoluções aprovadas
 *   − CMV (custo dos produtos vendidos, gravado no item na hora da venda)
 *   − Taxa do gateway     (por gateway × forma de pagamento)
 *   − Impostos sobre a venda
 *   − Frete pago à transportadora (real, ou estimado enquanto não informado)
 *   − Embalagem
 *   = MARGEM DE CONTRIBUIÇÃO
 *   − Despesas fixas do período (salários, infraestrutura, marketing...) rateadas por dia
 *   = LUCRO LÍQUIDO
 *
 * Todos os percentuais/valores são configuráveis em Configurações > Financeiro (superadmin).
 * Os valores padrão abaixo são EXEMPLOS e devem ser ajustados ao contrato/regime da loja.
 */

require_once __DIR__ . '/loja_helpers.php';

/** Pedidos que contam como faturamento (já pagos, inclusive em separação). */
const FIN_STATUS_FATURADO = ['pago', 'em_separacao', 'enviado', 'entregue'];

/** Valores de exemplo — editáveis no admin. Chave final no banco: "financeiro_" + nome. */
const FIN_PADROES = [
    'imposto_nome'       => 'Simples Nacional (exemplo)',
    'imposto_percentual' => '6.00',

    // Taxas por gateway (mp = Mercado Pago, ps = PagBank/PagSeguro) × forma de pagamento
    'taxa_mp_pix_pct'    => '0.99', 'taxa_mp_pix_fixa'    => '0.00',
    'taxa_mp_cartao_pct' => '4.99', 'taxa_mp_cartao_fixa' => '0.00',
    'taxa_mp_boleto_pct' => '0.00', 'taxa_mp_boleto_fixa' => '3.49',
    'taxa_ps_pix_pct'    => '0.99', 'taxa_ps_pix_fixa'    => '0.00',
    'taxa_ps_cartao_pct' => '4.99', 'taxa_ps_cartao_fixa' => '0.00',
    'taxa_ps_boleto_pct' => '0.00', 'taxa_ps_boleto_fixa' => '3.49',

    'embalagem_pedido'         => '3.00',   // custo de embalagem por pedido
    'frete_custo_medio'        => '18.00',  // estimativa enquanto o custo real não é informado (0 = usar o frete cobrado)
    'devolucao_recupera_custo' => '0',      // 1 = produto devolvido volta ao estoque (recupera o CMV)
];

const FIN_CATEGORIAS = [
    'pessoal'        => 'Pessoal (salários e encargos)',
    'infraestrutura' => 'Infraestrutura (hospedagem, domínio, servidor, internet)',
    'marketing'      => 'Marketing e anúncios',
    'administrativo' => 'Administrativo (contador, aluguel, escritório)',
    'ferramentas'    => 'Ferramentas e assinaturas',
    'outros'         => 'Outros',
];

const FIN_RECORRENCIAS = ['mensal' => 'Mensal', 'anual' => 'Anual', 'unica' => 'Avulsa (única)'];

/* ───────────────────────── configuração ───────────────────────── */

function fin_cfg(PDO $pdo, string $campo): string
{
    return (string)loja_cfg($pdo, 'financeiro_' . $campo, FIN_PADROES[$campo] ?? '');
}

/**
 * Valida e grava a seção Financeiro (usada por admin_configuracoes.php, só superadmin).
 * @return string|null  mensagem de erro, ou null se salvou
 */
function fin_salvar_configuracoes(PDO $pdo, array $post): ?string
{
    $novos = [];
    foreach (FIN_PADROES as $campo => $_) {
        if ($campo === 'imposto_nome') {
            $v = trim((string)($post[$campo] ?? ''));
            if ($v === '' || mb_strlen($v) > 60) return 'Informe o nome do regime tributário (até 60 caracteres).';
            $novos[$campo] = $v;
        } elseif ($campo === 'devolucao_recupera_custo') {
            $novos[$campo] = ((string)($post[$campo] ?? '0') === '1') ? '1' : '0';
        } else {
            $raw = str_replace(',', '.', trim((string)($post[$campo] ?? '')));
            if ($raw === '' || !is_numeric($raw)) return "Valor inválido em \"$campo\".";
            $n = (float)$raw;
            $e_pct = (substr($campo, -4) === '_pct') || $campo === 'imposto_percentual';
            if ($n < 0 || ($e_pct && $n > 100) || (!$e_pct && $n > 100000)) {
                return $e_pct ? 'Percentuais devem ficar entre 0 e 100.' : 'Valores em R$ devem ficar entre 0 e 100.000.';
            }
            $novos[$campo] = number_format($n, 2, '.', '');
        }
    }

    $mudou = [];
    $up = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
    foreach ($novos as $campo => $valor) {
        if (fin_cfg($pdo, $campo) !== (string)$valor) $mudou[] = $campo;
        $up->execute(['financeiro_' . $campo, $valor]);
    }

    try {   // auditoria: quais campos mudaram (sem expor nada sensível)
        $pdo->prepare("INSERT INTO logs_sistema (usuario_id, acao, tabela, detalhes, ip) VALUES (?,?,?,?,?)")
            ->execute([$_SESSION['usuario_id'] ?? null, 'configuracao_financeira_salva', 'configuracoes',
                       $mudou ? 'Alterados: ' . implode(', ', $mudou) : 'Sem alterações', $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Throwable $e) {}
    return null;
}

/* ───────────────────────── schema ───────────────────────── */

/** Colunas de uma tabela (cache por requisição). */
function fin_colunas(PDO $pdo, string $tabela, bool $atualizar = false): array
{
    static $cache = [];
    if ($atualizar || !isset($cache[$tabela])) {
        try { $cache[$tabela] = $pdo->query("SHOW COLUMNS FROM `$tabela`")->fetchAll(PDO::FETCH_COLUMN); }
        catch (Throwable $e) { $cache[$tabela] = []; }
    }
    return $cache[$tabela];
}

/**
 * Cria o que faltar para o financeiro. ALTER/CREATE fazem commit implícito: chamar fora de transação.
 */
function fin_garantir_schema(PDO $pdo): void
{
    static $feito = false;
    if ($feito) return;
    $feito = true;
    loja_garantir_schema($pdo);
    try {
        $ped = fin_colunas($pdo, 'pedidos');
        foreach ([
            'gateway'        => "VARCHAR(10) NULL",
            'taxa_gateway'   => "DECIMAL(10,2) NULL",
            'imposto_valor'  => "DECIMAL(10,2) NULL",
            'frete_custo'    => "DECIMAL(10,2) NULL",
        ] as $col => $def) {
            if (!in_array($col, $ped, true)) $pdo->exec("ALTER TABLE pedidos ADD COLUMN $col $def");
        }
        if (!in_array('custo_unitario', fin_colunas($pdo, 'itens_pedido'), true)) {
            $pdo->exec("ALTER TABLE itens_pedido ADD COLUMN custo_unitario DECIMAL(10,2) NULL");
        }
        fin_colunas($pdo, 'pedidos', true);          // relê o cache depois dos ALTER
        fin_colunas($pdo, 'itens_pedido', true);
        $pdo->exec("CREATE TABLE IF NOT EXISTS despesas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            descricao VARCHAR(120) NOT NULL,
            categoria VARCHAR(30) NOT NULL DEFAULT 'outros',
            valor DECIMAL(12,2) NOT NULL,
            recorrencia VARCHAR(10) NOT NULL DEFAULT 'mensal',
            data_inicio DATE NOT NULL,
            data_fim DATE NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            observacao VARCHAR(255) NULL,
            criado_por INT NULL,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
        error_log('[FIN SCHEMA] ' . $e->getMessage() . ' — rode code/migrations/2026_10_15_financeiro.sql manualmente');
    }
}

/* ───────────────────────── CSRF simples (telas financeiras) ───────────────────────── */

function fin_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['fin_csrf'])) $_SESSION['fin_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['fin_csrf'];
}

function fin_csrf_ok(?string $enviado): bool
{
    return !empty($_SESSION['fin_csrf']) && is_string($enviado) && hash_equals($_SESSION['fin_csrf'], $enviado);
}

/* ───────────────────────── custos variáveis ───────────────────────── */

/** Gateway do pedido: coluna gravada no checkout, ou inferido das observações (pedidos antigos). */
function fin_gateway_do_pedido(array $p): string
{
    $g = (string)($p['gateway'] ?? '');
    if ($g === 'mp' || $g === 'ps') return $g;
    $obs = (string)($p['observacoes'] ?? '');
    if (strpos($obs, 'ps_order_id:') !== false) return 'ps';
    return 'mp';
}

/** Taxa cobrada pelo gateway sobre um valor (percentual + valor fixo). */
function fin_taxa_gateway(PDO $pdo, ?string $gateway, string $metodo, float $valor): float
{
    $gw = ($gateway === 'ps') ? 'ps' : 'mp';
    $m  = in_array($metodo, ['pix', 'cartao', 'boleto'], true) ? $metodo : 'pix';
    $pct  = (float)fin_cfg($pdo, "taxa_{$gw}_{$m}_pct");
    $fixa = (float)fin_cfg($pdo, "taxa_{$gw}_{$m}_fixa");
    return round($valor * $pct / 100 + $fixa, 2);
}

/** Imposto sobre a venda (base: valor cobrado do cliente). */
function fin_imposto(PDO $pdo, float $valor): float
{
    return round($valor * (float)fin_cfg($pdo, 'imposto_percentual') / 100, 2);
}

/* ───────────────────────── despesas fixas ───────────────────────── */

/**
 * Rateia as despesas ativas no período [ini, fim] (datas Y-m-d, inclusive), proporcional aos dias.
 *  - mensal: valor cheio por mês, proporcional aos dias do mês que caem no período
 *  - anual : valor/12 por mês, idem
 *  - unica : entra inteira se a data estiver no período
 * @return array{total: float, por_categoria: array<string,float>}
 */
function fin_ratear_despesas(PDO $pdo, string $ini, string $fim): array
{
    $out = ['total' => 0.0, 'por_categoria' => []];
    try {
        $rows = $pdo->query("SELECT categoria, valor, recorrencia, data_inicio, data_fim FROM despesas WHERE ativo = 1")
                    ->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return $out;   // tabela ainda não existe
    }

    $t_ini = strtotime($ini); $t_fim = strtotime($fim);
    if ($t_ini === false || $t_fim === false || $t_fim < $t_ini) return $out;

    foreach ($rows as $d) {
        $valor = (float)$d['valor'];
        $d_ini = strtotime(substr($d['data_inicio'], 0, 10));
        $d_fim = !empty($d['data_fim']) ? strtotime(substr($d['data_fim'], 0, 10)) : PHP_INT_MAX;
        $parcial = 0.0;

        if ($d['recorrencia'] === 'unica') {
            if ($d_ini >= $t_ini && $d_ini <= $t_fim) $parcial = $valor;
        } else {
            $mensal = ($d['recorrencia'] === 'anual') ? $valor / 12 : $valor;
            // percorre cada mês tocado pelo período (limite de segurança: 10 anos)
            $cursor = strtotime(date('Y-m-01', $t_ini));
            for ($i = 0; $i < 120 && $cursor <= $t_fim; $i++) {
                $dias_mes = (int)date('t', $cursor);
                $m_ini = $cursor;
                $m_fim = strtotime(date('Y-m-t', $cursor));
                $a = max($t_ini, $m_ini, $d_ini);
                $b = min($t_fim, $m_fim, $d_fim);
                if ($b >= $a) {
                    $dias = (int)round(($b - $a) / 86400) + 1;
                    $parcial += $mensal * $dias / $dias_mes;
                }
                $cursor = strtotime('+1 month', $cursor);
            }
        }

        if ($parcial > 0) {
            $cat = $d['categoria'];
            $out['por_categoria'][$cat] = ($out['por_categoria'][$cat] ?? 0.0) + $parcial;
            $out['total'] += $parcial;
        }
    }
    $out['total'] = round($out['total'], 2);
    foreach ($out['por_categoria'] as $k => $v) $out['por_categoria'][$k] = round($v, 2);
    arsort($out['por_categoria']);
    return $out;
}

/* ───────────────────────── resultado do período ───────────────────────── */

/**
 * Calcula o resultado do período [ini, fim] (datas Y-m-d, pela data do pedido).
 */
function fin_resumo(PDO $pdo, string $ini, string $fim): array
{
    $status_in = "'" . implode("','", FIN_STATUS_FATURADO) . "'";

    $st = $pdo->prepare("SELECT * FROM pedidos WHERE status IN ($status_in) AND DATE(data_pedido) BETWEEN ? AND ?");
    $st->execute([$ini, $fim]);
    $pedidos = $st->fetchAll(PDO::FETCH_ASSOC);

    $r = [
        'periodo_ini' => $ini, 'periodo_fim' => $fim,
        'n_pedidos' => count($pedidos),
        'receita_produtos' => 0.0, 'descontos' => 0.0, 'frete_cobrado' => 0.0, 'receita' => 0.0,
        'devolucoes' => 0.0, 'cmv' => 0.0, 'cmv_recuperado' => 0.0,
        'taxas_gateway' => 0.0, 'impostos' => 0.0,
        'frete_custo' => 0.0, 'n_frete_estimado' => 0, 'embalagem' => 0.0,
        'itens_sem_custo' => 0,
    ];

    $frete_medio = (float)fin_cfg($pdo, 'frete_custo_medio');
    foreach ($pedidos as $p) {
        $total = (float)$p['total'];
        $r['receita_produtos'] += (float)($p['subtotal'] ?? $total);
        $r['descontos']        += (float)($p['desconto'] ?? 0);
        $r['frete_cobrado']    += (float)($p['frete'] ?? 0);
        $r['receita']          += $total;

        // Taxa e imposto: usa o valor gravado no checkout; pedidos antigos usam a configuração atual
        $r['taxas_gateway'] += isset($p['taxa_gateway']) && $p['taxa_gateway'] !== null
            ? (float)$p['taxa_gateway']
            : fin_taxa_gateway($pdo, fin_gateway_do_pedido($p), (string)$p['forma_pagamento'], $total);
        $r['impostos'] += isset($p['imposto_valor']) && $p['imposto_valor'] !== null
            ? (float)$p['imposto_valor']
            : fin_imposto($pdo, $total);

        // Frete pago à transportadora: real se informado, senão estimativa
        if (isset($p['frete_custo']) && $p['frete_custo'] !== null && $p['frete_custo'] !== '') {
            $r['frete_custo'] += (float)$p['frete_custo'];
        } else {
            $r['frete_custo'] += $frete_medio > 0 ? $frete_medio : (float)($p['frete'] ?? 0);
            $r['n_frete_estimado']++;
        }
    }
    $r['embalagem'] = $r['n_pedidos'] * (float)fin_cfg($pdo, 'embalagem_pedido');

    // CMV (custo gravado no item; pedidos antigos usam o custo atual do produto)
    $q = $pdo->prepare("
        SELECT COALESCE(SUM(ip.quantidade * COALESCE(ip.custo_unitario, pr.custo, 0)), 0) AS cmv,
               COALESCE(SUM(CASE WHEN COALESCE(ip.custo_unitario, pr.custo, 0) <= 0 THEN 1 ELSE 0 END), 0) AS sem_custo
          FROM itens_pedido ip
          JOIN pedidos ped ON ped.id = ip.pedido_id
          LEFT JOIN produtos pr ON pr.id = ip.produto_id
         WHERE ped.status IN ($status_in) AND DATE(ped.data_pedido) BETWEEN ? AND ?
    ");
    $q->execute([$ini, $fim]);
    $c = $q->fetch(PDO::FETCH_ASSOC);
    $r['cmv'] = (float)$c['cmv'];
    $r['itens_sem_custo'] = (int)$c['sem_custo'];

    // Devoluções aprovadas no período (valor do pedido devolvido; devolução é por pedido)
    $q = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE id IN (
            SELECT pedido_id FROM devolucoes WHERE status = 'aprovado' AND DATE(data_resolucao) BETWEEN ? AND ?)");
    try {
        $q->execute([$ini, $fim]);
        $r['devolucoes'] = (float)$q->fetchColumn();
        if (fin_cfg($pdo, 'devolucao_recupera_custo') === '1') {
            $q = $pdo->prepare("
                SELECT COALESCE(SUM(ip.quantidade * COALESCE(ip.custo_unitario, pr.custo, 0)), 0)
                  FROM itens_pedido ip LEFT JOIN produtos pr ON pr.id = ip.produto_id
                 WHERE ip.pedido_id IN (SELECT pedido_id FROM devolucoes WHERE status = 'aprovado' AND DATE(data_resolucao) BETWEEN ? AND ?)");
            $q->execute([$ini, $fim]);
            $r['cmv_recuperado'] = (float)$q->fetchColumn();
        }
    } catch (Throwable $e) {}

    foreach (['receita_produtos', 'descontos', 'frete_cobrado', 'receita', 'devolucoes', 'cmv', 'cmv_recuperado',
              'taxas_gateway', 'impostos', 'frete_custo', 'embalagem'] as $k) {
        $r[$k] = round($r[$k], 2);
    }

    $r['margem_contribuicao'] = round(
        $r['receita'] - $r['devolucoes'] - $r['cmv'] + $r['cmv_recuperado']
        - $r['taxas_gateway'] - $r['impostos'] - $r['frete_custo'] - $r['embalagem'], 2);
    $r['margem_contribuicao_pct'] = $r['receita'] > 0 ? round($r['margem_contribuicao'] / $r['receita'] * 100, 1) : 0.0;

    $desp = fin_ratear_despesas($pdo, $ini, $fim);
    $r['despesas_fixas']      = $desp['total'];
    $r['despesas_categorias'] = $desp['por_categoria'];

    $r['lucro_liquido']     = round($r['margem_contribuicao'] - $r['despesas_fixas'], 2);
    $r['lucro_liquido_pct'] = $r['receita'] > 0 ? round($r['lucro_liquido'] / $r['receita'] * 100, 1) : 0.0;
    $r['ticket_medio']      = $r['n_pedidos'] > 0 ? round($r['receita'] / $r['n_pedidos'], 2) : 0.0;

    // Ponto de equilíbrio: receita necessária para a margem de contribuição cobrir as despesas fixas
    $r['ponto_equilibrio'] = ($r['margem_contribuicao'] > 0 && $r['receita'] > 0)
        ? round($r['despesas_fixas'] / ($r['margem_contribuicao'] / $r['receita']), 2)
        : null;

    return $r;
}
