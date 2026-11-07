<?php
/**
 * Catálogo de relatórios exportáveis (CSV) — Alto Jordão.
 *
 * Cada relatório define: título, grupo, descrição, filtros aceitos e uma função
 * que devolve ['colunas' => [...], 'linhas' => [[...], ...]].
 * Colunas de custo e margem só entram para o superadmin ($super = true).
 * Números chegam como int/float (o CSV formata com vírgula); textos são protegidos
 * contra injeção de fórmulas no Excel.
 */
require_once __DIR__ . '/financeiro_helpers.php';   // FIN_STATUS_FATURADO

function rel_status_in(): string { return "'" . implode("','", FIN_STATUS_FATURADO) . "'"; }
function rel_data(?string $d, bool $hora = false): string { return ($d && strtotime($d)) ? date($hora ? 'd/m/Y H:i' : 'd/m/Y', strtotime($d)) : ''; }
function rel_dias_desde(?string $d): ?int { return ($d && strtotime($d)) ? (int)floor((time() - strtotime($d)) / 86400) : null; }
function rel_data_ok(?string $d): bool { return $d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int)substr($d, 5, 2), (int)substr($d, 8, 2), (int)substr($d, 0, 4)); }

/** Lê e valida os filtros de um relatório a partir de $src (GET). */
function rel_parametros(array $filtros, array $src): array
{
    $p = [];
    if (in_array('periodo', $filtros, true)) {
        $ini = $src['data_ini'] ?? ''; $fim = $src['data_fim'] ?? '';
        $p['data_fim'] = rel_data_ok($fim) ? $fim : date('Y-m-d');
        $p['data_ini'] = rel_data_ok($ini) ? $ini : date('Y-m-d', strtotime('-30 days'));
        if ($p['data_ini'] > $p['data_fim']) { [$p['data_ini'], $p['data_fim']] = [$p['data_fim'], $p['data_ini']]; }
    }
    if (in_array('dias', $filtros, true))   { $p['dias']   = max(1, min(730, (int)($src['dias']   ?? 30))); }
    if (in_array('limite', $filtros, true)) { $p['limite'] = max(0, min(9999, (int)($src['limite'] ?? 5))); }
    return $p;
}

function rel_situacao_estoque(int $e): string
{
    if ($e <= 0)  return 'Esgotado';
    if ($e <= 3)  return 'Crítico (1 a 3)';
    if ($e <= 10) return 'Baixo (4 a 10)';
    return 'OK';
}

function rel_catalogo(): array
{
    return [
    // ═════════════ ESTOQUE E PRODUTOS ═════════════
    'estoque_atual' => [
        'grupo' => 'Estoque e produtos', 'titulo' => 'Estoque atual',
        'desc' => 'Todos os produtos com quantidade, situação, preço e valor parado em estoque.', 'filtros' => [],
        'gerar' => function (PDO $pdo, array $p, bool $super): array {
            $rows = $pdo->query("SELECT id, nome, categoria, genero, estoque, preco, custo, ativo FROM produtos ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
            $col = ['ID', 'Produto', 'Categoria', 'Gênero', 'Estoque', 'Situação', 'Preço de venda (R$)', 'Valor em estoque a preço (R$)'];
            if ($super) { array_push($col, 'Custo unitário (R$)', 'Valor em estoque a custo (R$)', 'Margem (%)'); }
            $col[] = 'Ativo';
            $out = [];
            foreach ($rows as $r) {
                $e = (int)$r['estoque']; $preco = (float)$r['preco'];
                $l = [(int)$r['id'], $r['nome'], $r['categoria'], $r['genero'], $e, rel_situacao_estoque($e), $preco, round($e * $preco, 2)];
                if ($super) {
                    $c = ($r['custo'] !== null && $r['custo'] !== '') ? (float)$r['custo'] : null;
                    $l[] = $c; $l[] = $c !== null ? round($e * $c, 2) : null;
                    $l[] = ($c !== null && $preco > 0) ? round(($preco - $c) / $preco * 100, 2) : null;
                }
                $l[] = $r['ativo'] ? 'Sim' : 'Não';
                $out[] = $l;
            }
            return ['colunas' => $col, 'linhas' => $out];
        },
    ],
    'reposicao' => [
        'grupo' => 'Estoque e produtos', 'titulo' => 'Reposição sugerida',
        'desc' => 'Produtos ativos com estoque baixo, ritmo de venda e quantas unidades repor para cobrir 30 dias.', 'filtros' => ['dias', 'limite'],
        'gerar' => function (PDO $pdo, array $p, bool $super): array {
            $ini = date('Y-m-d', strtotime('-' . $p['dias'] . ' days'));
            $st = $pdo->prepare("
                SELECT pr.id, pr.nome, pr.categoria, pr.estoque, COALESCE(SUM(v.quantidade), 0) AS vendidas
                FROM produtos pr
                LEFT JOIN (SELECT ip.produto_id, ip.quantidade FROM itens_pedido ip
                           JOIN pedidos pe ON pe.id = ip.pedido_id
                           WHERE pe.status IN (" . rel_status_in() . ") AND DATE(pe.data_pedido) >= ?) v ON v.produto_id = pr.id
                WHERE pr.ativo = 1 AND pr.estoque <= ?
                GROUP BY pr.id, pr.nome, pr.categoria, pr.estoque");
            $st->execute([$ini, $p['limite']]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $e = (int)$r['estoque']; $v = (int)$r['vendidas']; $media = $v / $p['dias'];
                $cob = $media > 0 ? round($e / $media, 1) : null;
                $sug = max(0, (int)ceil($media * 30) - $e);
                $out[] = [(int)$r['id'], $r['nome'], $r['categoria'], $e, $v, round($media, 2), $cob, $sug];
            }
            usort($out, fn($a, $b) => ($a[6] ?? 99999) <=> ($b[6] ?? 99999));
            return ['colunas' => ['ID', 'Produto', 'Categoria', 'Estoque', 'Vendidas no período', 'Média por dia', 'Cobertura (dias)', 'Sugestão de reposição (un.)'], 'linhas' => $out];
        },
    ],
    'produtos_parados' => [
        'grupo' => 'Estoque e produtos', 'titulo' => 'Produtos parados',
        'desc' => 'Produtos ativos com estoque que não venderam nos últimos dias, e o dinheiro parado neles.', 'filtros' => ['dias'],
        'gerar' => function (PDO $pdo, array $p, bool $super): array {
            $corte = date('Y-m-d', strtotime('-' . $p['dias'] . ' days'));
            $rows = $pdo->query("
                SELECT pr.id, pr.nome, pr.categoria, pr.estoque, pr.preco, pr.custo, MAX(pe.data_pedido) AS ultima
                FROM produtos pr
                LEFT JOIN itens_pedido ip ON ip.produto_id = pr.id
                LEFT JOIN pedidos pe ON pe.id = ip.pedido_id AND pe.status IN (" . rel_status_in() . ")
                WHERE pr.ativo = 1 AND pr.estoque > 0
                GROUP BY pr.id, pr.nome, pr.categoria, pr.estoque, pr.preco, pr.custo")->fetchAll(PDO::FETCH_ASSOC);
            $col = ['ID', 'Produto', 'Categoria', 'Estoque', 'Preço (R$)', 'Valor parado a preço (R$)'];
            if ($super) $col[] = 'Valor parado a custo (R$)';
            array_push($col, 'Última venda', 'Dias sem vender');
            $out = [];
            foreach ($rows as $r) {
                if ($r['ultima'] && substr($r['ultima'], 0, 10) >= $corte) continue;   // vendeu dentro da janela
                $e = (int)$r['estoque']; $preco = (float)$r['preco'];
                $l = [(int)$r['id'], $r['nome'], $r['categoria'], $e, $preco, round($e * $preco, 2)];
                if ($super) $l[] = ($r['custo'] !== null && $r['custo'] !== '') ? round($e * (float)$r['custo'], 2) : null;
                $l[] = $r['ultima'] ? rel_data($r['ultima']) : 'Nunca vendeu';
                $l[] = rel_dias_desde($r['ultima']);
                $out[] = $l;
            }
            usort($out, fn($a, $b) => $b[5] <=> $a[5]);   // mais dinheiro parado primeiro
            return ['colunas' => $col, 'linhas' => $out];
        },
    ],
    'ranking_produtos' => [
        'grupo' => 'Estoque e produtos', 'titulo' => 'Ranking de produtos vendidos',
        'desc' => 'Quantidade, pedidos e receita de cada produto no período, do mais ao menos vendido (inclui quem não vendeu).', 'filtros' => ['periodo'],
        'gerar' => function (PDO $pdo, array $p, bool $super): array {
            $st = $pdo->prepare("
                SELECT pr.id, pr.nome, pr.categoria, pr.estoque,
                       COALESCE(SUM(v.quantidade), 0) AS qtd, COUNT(DISTINCT v.pedido_id) AS pedidos,
                       COALESCE(SUM(v.quantidade * v.preco_unitario), 0) AS receita,
                       COALESCE(SUM(v.quantidade * v.custo), 0) AS cmv
                FROM produtos pr
                LEFT JOIN (SELECT ip.produto_id, ip.pedido_id, ip.quantidade, ip.preco_unitario,
                                  COALESCE(ip.custo_unitario, 0) AS custo
                           FROM itens_pedido ip JOIN pedidos pe ON pe.id = ip.pedido_id
                           WHERE pe.status IN (" . rel_status_in() . ") AND DATE(pe.data_pedido) BETWEEN ? AND ?) v ON v.produto_id = pr.id
                WHERE pr.ativo = 1
                GROUP BY pr.id, pr.nome, pr.categoria, pr.estoque
                ORDER BY qtd DESC, receita DESC, pr.nome");
            $st->execute([$p['data_ini'], $p['data_fim']]);
            $col = ['Posição', 'ID', 'Produto', 'Categoria', 'Quantidade vendida', 'Pedidos', 'Receita (R$)'];
            if ($super) array_push($col, 'Custo dos produtos vendidos (R$)', 'Lucro bruto (R$)', 'Margem bruta (%)');
            $col[] = 'Estoque atual';
            $out = []; $i = 0;
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $rec = round((float)$r['receita'], 2); $cmv = round((float)$r['cmv'], 2);
                $l = [++$i, (int)$r['id'], $r['nome'], $r['categoria'], (int)$r['qtd'], (int)$r['pedidos'], $rec];
                if ($super) { $l[] = $cmv; $l[] = round($rec - $cmv, 2); $l[] = $rec > 0 ? round(($rec - $cmv) / $rec * 100, 2) : null; }
                $l[] = (int)$r['estoque'];
                $out[] = $l;
            }
            return ['colunas' => $col, 'linhas' => $out];
        },
    ],

    // ═════════════ CLIENTES, CUPONS E DEVOLUÇÕES ═════════════
    'melhores_clientes' => [
        'grupo' => 'Clientes, cupons e devoluções', 'titulo' => 'Melhores clientes',
        'desc' => 'Quem mais comprou no período: pedidos, total gasto, ticket médio e há quantos dias não compra.', 'filtros' => ['periodo'],
        'gerar' => function (PDO $pdo, array $p, bool $super): array {
            $st = $pdo->prepare("
                SELECT u.id, u.nome, u.email, u.telefone, COUNT(pe.id) AS pedidos, SUM(pe.total) AS gasto,
                       MIN(pe.data_pedido) AS primeira, MAX(pe.data_pedido) AS ultima
                FROM pedidos pe JOIN usuarios u ON u.id = pe.usuario_id
                WHERE pe.status IN (" . rel_status_in() . ") AND DATE(pe.data_pedido) BETWEEN ? AND ?
                GROUP BY u.id, u.nome, u.email, u.telefone
                ORDER BY gasto DESC");
            $st->execute([$p['data_ini'], $p['data_fim']]);
            $out = []; $i = 0;
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $n = (int)$r['pedidos']; $g = round((float)$r['gasto'], 2);
                $out[] = [++$i, (int)$r['id'], $r['nome'], $r['email'], $r['telefone'], $n, $g, $n ? round($g / $n, 2) : null,
                          rel_data($r['primeira']), rel_data($r['ultima']), rel_dias_desde($r['ultima'])];
            }
            return ['colunas' => ['Posição', 'ID', 'Cliente', 'E-mail', 'Telefone', 'Pedidos', 'Total gasto (R$)', 'Ticket médio (R$)', 'Primeira compra no período', 'Última compra', 'Dias desde a última compra'], 'linhas' => $out];
        },
    ],
    'novos_cadastros' => [
        'grupo' => 'Clientes, cupons e devoluções', 'titulo' => 'Novos cadastros',
        'desc' => 'Clientes que se cadastraram no período e se já fizeram alguma compra paga.', 'filtros' => ['periodo'],
        'gerar' => function (PDO $pdo, array $p, bool $super): array {
            $st = $pdo->prepare("
                SELECT u.id, u.nome, u.email, u.telefone, u.data_cadastro,
                       (SELECT COUNT(*) FROM pedidos pe WHERE pe.usuario_id = u.id AND pe.status IN (" . rel_status_in() . ")) AS compras
                FROM usuarios u
                WHERE u.nivel NOT IN ('admin','superadmin','operador','gerente') AND DATE(u.data_cadastro) BETWEEN ? AND ?
                ORDER BY u.data_cadastro DESC");
            $st->execute([$p['data_ini'], $p['data_fim']]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[] = [(int)$r['id'], $r['nome'], $r['email'], $r['telefone'], rel_data($r['data_cadastro'], true), (int)$r['compras'], (int)$r['compras'] > 0 ? 'Sim' : 'Não'];
            }
            return ['colunas' => ['ID', 'Cliente', 'E-mail', 'Telefone', 'Cadastrado em', 'Compras pagas', 'Já comprou'], 'linhas' => $out];
        },
    ],
    'cupons' => [
        'grupo' => 'Clientes, cupons e devoluções', 'titulo' => 'Desempenho de cupons',
        'desc' => 'Cada cupom com validade, limite, usos e, no período, pedidos, desconto concedido e receita gerada.', 'filtros' => ['periodo'],
        'gerar' => function (PDO $pdo, array $p, bool $super): array {
            $cupons = $pdo->query("SELECT codigo, tipo, valor, valor_minimo, limite_uso, total_usado, data_inicio, data_fim, ativo FROM cupons ORDER BY codigo")->fetchAll(PDO::FETCH_ASSOC);
            $st = $pdo->prepare("
                SELECT cupom_codigo, COUNT(*) AS pedidos, COALESCE(SUM(desconto_cupom), 0) AS desconto, COALESCE(SUM(total), 0) AS receita
                FROM pedidos
                WHERE cupom_codigo IS NOT NULL AND cupom_codigo <> '' AND status IN (" . rel_status_in() . ") AND DATE(data_pedido) BETWEEN ? AND ?
                GROUP BY cupom_codigo");
            $st->execute([$p['data_ini'], $p['data_fim']]);
            $uso = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $uso[strtoupper($r['cupom_codigo'])] = $r;
            $out = [];
            foreach ($cupons as $c) {
                $u = $uso[strtoupper($c['codigo'])] ?? ['pedidos' => 0, 'desconto' => 0, 'receita' => 0];
                $valor = $c['tipo'] === 'percentual' ? number_format((float)$c['valor'], 0, ',', '') . '%' : 'R$ ' . number_format((float)$c['valor'], 2, ',', '.');
                $out[] = [$c['codigo'], $c['tipo'] === 'percentual' ? 'Percentual' : 'Valor fixo', $valor, (float)$c['valor_minimo'],
                          rel_data($c['data_inicio']) . ' a ' . ($c['data_fim'] ? rel_data($c['data_fim']) : 'sem fim'),
                          $c['limite_uso'] ? (int)$c['limite_uso'] : 'Sem limite', (int)$c['total_usado'], $c['ativo'] ? 'Sim' : 'Não',
                          (int)$u['pedidos'], round((float)$u['desconto'], 2), round((float)$u['receita'], 2)];
            }
            usort($out, fn($a, $b) => $b[8] <=> $a[8]);
            return ['colunas' => ['Código', 'Tipo', 'Valor', 'Pedido mínimo (R$)', 'Validade', 'Limite de usos', 'Usos registrados', 'Ativo', 'Pedidos no período', 'Desconto concedido (R$)', 'Receita dos pedidos (R$)'], 'linhas' => $out];
        },
    ],
    'devolucoes' => [
        'grupo' => 'Clientes, cupons e devoluções', 'titulo' => 'Devoluções e trocas',
        'desc' => 'Solicitações do período com cliente, motivo, situação e tempo até a resolução.', 'filtros' => ['periodo'],
        'gerar' => function (PDO $pdo, array $p, bool $super): array {
            $st = $pdo->prepare("
                SELECT d.id, d.pedido_id, u.nome AS cliente, pe.total, d.motivo, d.status, d.data_solicitacao, d.data_resolucao, d.detalhes
                FROM devolucoes d
                JOIN usuarios u ON u.id = d.usuario_id
                LEFT JOIN pedidos pe ON pe.id = d.pedido_id
                WHERE DATE(d.data_solicitacao) BETWEEN ? AND ?
                ORDER BY d.data_solicitacao DESC");
            $st->execute([$p['data_ini'], $p['data_fim']]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $dias = ($r['data_resolucao'] && $r['data_solicitacao']) ? (int)floor((strtotime($r['data_resolucao']) - strtotime($r['data_solicitacao'])) / 86400) : null;
                $out[] = [(int)$r['id'], (int)$r['pedido_id'], $r['cliente'], $r['total'] !== null ? (float)$r['total'] : null, $r['motivo'], ucfirst((string)$r['status']),
                          rel_data($r['data_solicitacao'], true), rel_data($r['data_resolucao'], true), $dias, $r['detalhes']];
            }
            return ['colunas' => ['ID', 'Pedido', 'Cliente', 'Valor do pedido (R$)', 'Motivo', 'Situação', 'Solicitada em', 'Resolvida em', 'Dias para resolver', 'Detalhes do cliente'], 'linhas' => $out];
        },
    ],
    'devolucoes_motivo' => [
        'grupo' => 'Clientes, cupons e devoluções', 'titulo' => 'Devoluções por motivo',
        'desc' => 'Resumo dos motivos de devolução no período, com quantas foram aprovadas, recusadas ou estão pendentes.', 'filtros' => ['periodo'],
        'gerar' => function (PDO $pdo, array $p, bool $super): array {
            $st = $pdo->prepare("SELECT motivo, status, COUNT(*) AS n FROM devolucoes WHERE DATE(data_solicitacao) BETWEEN ? AND ? GROUP BY motivo, status");
            $st->execute([$p['data_ini'], $p['data_fim']]);
            $m = []; $total = 0;
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $k = $r['motivo'] ?: '(sem motivo)';
                $m[$k] = $m[$k] ?? ['t' => 0, 'aprovado' => 0, 'recusado' => 0, 'pendente' => 0];
                $m[$k]['t'] += (int)$r['n']; $total += (int)$r['n'];
                $s = strtolower((string)$r['status']);
                if (isset($m[$k][$s])) $m[$k][$s] += (int)$r['n'];
            }
            uasort($m, fn($a, $b) => $b['t'] <=> $a['t']);
            $out = [];
            foreach ($m as $mot => $v) $out[] = [$mot, $v['t'], $v['aprovado'], $v['recusado'], $v['pendente'], $total ? round($v['t'] / $total * 100, 2) : null];
            return ['colunas' => ['Motivo', 'Solicitações', 'Aprovadas', 'Recusadas', 'Pendentes', '% do total'], 'linhas' => $out];
        },
    ],
    // ═════════════ PEDIDOS E FINANCEIRO ═════════════
    'pedidos' => [
        'grupo' => 'Pedidos e financeiro', 'titulo' => 'Pedidos do período',
        'desc' => 'Todos os pedidos (qualquer status) com cliente, pagamento, valores, frete, rastreio e cupom.', 'filtros' => ['periodo'],
        'gerar' => function (PDO $pdo, array $p, bool $super): array {
            $st = $pdo->prepare("
                SELECT pe.*, u.nome AS cliente_nome, u.email AS cliente_email
                FROM pedidos pe LEFT JOIN usuarios u ON u.id = pe.usuario_id
                WHERE DATE(pe.data_pedido) BETWEEN ? AND ?
                ORDER BY pe.data_pedido DESC");
            $st->execute([$p['data_ini'], $p['data_fim']]);
            $col = ['Pedido', 'Data', 'Cliente', 'E-mail', 'Status', 'Pagamento', 'Subtotal (R$)', 'Desconto (R$)', 'Cupom', 'Frete cobrado (R$)', 'Total (R$)', 'Transportadora', 'Código de rastreio'];
            if ($super) array_push($col, 'Frete pago à transportadora (R$)', 'Taxa do gateway (R$)');
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $n = fn($k) => (isset($r[$k]) && $r[$k] !== '') ? round((float)$r[$k], 2) : null;
                $l = [(int)$r['id'], rel_data($r['data_pedido'], true), $r['cliente_nome'], $r['cliente_email'], $r['status'], $r['forma_pagamento'] ?? '',
                      $n('subtotal'), $n('desconto') ?? $n('desconto_cupom'), $r['cupom_codigo'] ?? '', $n('frete'), $n('total'),
                      $r['transportadora'] ?? '', $r['codigo_rastreio'] ?? ''];
                if ($super) { $l[] = $n('frete_custo'); $l[] = $n('taxa_gateway'); }
                $out[] = $l;
            }
            return ['colunas' => $col, 'linhas' => $out];
        },
    ],
    'financeiro_resumo' => [
        'grupo' => 'Pedidos e financeiro', 'titulo' => 'Resumo financeiro (DRE)',
        'desc' => 'Receita, custos, margem de contribuição, despesas fixas e lucro líquido do período. Somente superadmin.', 'filtros' => ['periodo'], 'somente_super' => true,
        'gerar' => function (PDO $pdo, array $p, bool $super): array {
            if (!$super) return ['colunas' => ['Aviso'], 'linhas' => [['Acesso restrito ao superadmin']]];
            $r = fin_resumo($pdo, $p['data_ini'], $p['data_fim']);
            $f = fn($k) => round((float)$r[$k], 2);
            $L = [
                ['Pedidos faturados', (int)$r['n_pedidos'], ''],
                ['Receita de produtos', $f('receita_produtos'), ''],
                ['(-) Descontos', $f('descontos'), ''],
                ['(+) Frete cobrado', $f('frete_cobrado'), ''],
                ['= Receita total', $f('receita'), ''],
                ['(-) Devoluções aprovadas', $f('devolucoes'), ''],
                ['(-) Custo dos produtos vendidos', $f('cmv'), $r['itens_sem_custo'] ? $r['itens_sem_custo'] . ' item(ns) sem custo cadastrado' : ''],
                ['(+) Custo recuperado em devoluções', $f('cmv_recuperado'), ''],
                ['(-) Taxas do gateway', $f('taxas_gateway'), ''],
                ['(-) Impostos', $f('impostos'), ''],
                ['(-) Frete pago à transportadora', $f('frete_custo'), $r['n_frete_estimado'] ? $r['n_frete_estimado'] . ' pedido(s) com frete estimado' : ''],
                ['(-) Embalagem', $f('embalagem'), ''],
                ['= Margem de contribuição', $f('margem_contribuicao'), $r['margem_contribuicao_pct'] . '% da receita'],
                ['(-) Despesas fixas (rateadas)', $f('despesas_fixas'), ''],
                ['= Lucro líquido', $f('lucro_liquido'), $r['lucro_liquido_pct'] . '% da receita'],
                ['Ticket médio', $f('ticket_medio'), ''],
                ['Ponto de equilíbrio (receita)', $r['ponto_equilibrio'] !== null ? round((float)$r['ponto_equilibrio'], 2) : null, ''],
            ];
            foreach ((array)($r['despesas_categorias'] ?? []) as $cat => $v) $L[] = ['   Despesa: ' . $cat, round((float)$v, 2), ''];
            // linhas que começam com "=" ou "(" são rótulos: o guard de injeção só age em = + - @, então prefixamos
            foreach ($L as &$x) { if ($x[0][0] === '=') $x[0] = 'Total — ' . ltrim(substr($x[0], 1)); } unset($x);
            return ['colunas' => ['Item', 'Valor (R$ ou quantidade)', 'Observação'], 'linhas' => $L];
        },
    ],
    ];
}

/** Formata uma célula para CSV/tela: números em pt-BR e proteção contra fórmulas do Excel. */
function rel_celula($v): string
{
    if ($v === null) return '';
    if (is_int($v)) return (string)$v;
    if (is_float($v)) return number_format($v, 2, ',', '');
    $s = (string)$v;
    if ($s !== '' && strpos("=+-@\t\r", $s[0]) !== false) $s = "'" . $s;   // evita =HYPERLINK(...) etc.
    return $s;
}
