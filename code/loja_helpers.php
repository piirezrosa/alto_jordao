<?php
/**
 * Regras de venda — Alto Jordão
 *
 * ÚNICA fonte de verdade para cupom, frete e desconto PIX. É usada tanto por
 * calcular_checkout.php (resumo exibido ao cliente) quanto por processar_pedido.php
 * (cobrança real), então o que o cliente vê é sempre o que é cobrado.
 *
 * Ordem do cálculo:
 *   subtotal            soma dos preços do BANCO × quantidade
 *   - cupom             percentual ou fixo (nunca maior que o subtotal)
 *   = base
 *   - desconto PIX 5%   sobre a base (não incide sobre o frete)
 *   + frete             cotado por CEP (frete_helpers.php): Correios PAC/SEDEX, Jadlog, fixo ou grátis
 *   = total
 */

require_once __DIR__ . '/frete_helpers.php';

const LOJA_DESCONTO_PIX = 0.05;

/** Lê uma configuração da tabela `configuracoes` (chaves gravadas por admin_configuracoes.php). */
function loja_cfg(PDO $pdo, string $chave, $default = '')
{
    static $cache = [];
    if (array_key_exists($chave, $cache)) return $cache[$chave];
    try {
        $s = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
        $s->execute([$chave]);
        $v = $s->fetchColumn();
        return $cache[$chave] = ($v !== false && $v !== null && $v !== '') ? $v : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

/**
 * Garante as colunas que o checkout grava em `pedidos` (cria só o que faltar).
 * ALTER TABLE faz commit implícito no MySQL: chamar SEMPRE fora de transação.
 */
function loja_garantir_schema(PDO $pdo): void
{
    static $feito = false;
    if ($feito) return;
    $feito = true;
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM pedidos")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('frete', $cols, true))          $pdo->exec("ALTER TABLE pedidos ADD COLUMN frete DECIMAL(10,2) NOT NULL DEFAULT 0");
        if (!in_array('cupom_codigo', $cols, true))   $pdo->exec("ALTER TABLE pedidos ADD COLUMN cupom_codigo VARCHAR(50) NULL");
        if (!in_array('desconto_cupom', $cols, true)) $pdo->exec("ALTER TABLE pedidos ADD COLUMN desconto_cupom DECIMAL(10,2) NOT NULL DEFAULT 0");
        if (!in_array('frete_servico', $cols, true))  $pdo->exec("ALTER TABLE pedidos ADD COLUMN frete_servico VARCHAR(30) NULL");
        if (!in_array('frete_prazo', $cols, true))    $pdo->exec("ALTER TABLE pedidos ADD COLUMN frete_prazo INT NULL");
    } catch (Throwable $e) {
        error_log('[LOJA SCHEMA] ' . $e->getMessage() . ' — rode code/migrations/2026_10_15_frete_cupom.sql manualmente');
    }
}

function loja_brl(float $v): string
{
    return 'R$ ' . number_format($v, 2, ',', '.');
}

/**
 * Valida um cupom para um subtotal.
 * @return array{ok:bool, erro:string, cupom:?array, desconto:float}
 */
function cupom_validar(PDO $pdo, string $codigo, float $subtotal): array
{
    $falha = fn(string $m) => ['ok' => false, 'erro' => $m, 'cupom' => null, 'desconto' => 0.0];

    $codigo = strtoupper(trim($codigo));
    if ($codigo === '') return $falha('Digite o código do cupom.');

    $st = $pdo->prepare("SELECT * FROM cupons WHERE codigo = ? LIMIT 1");
    $st->execute([$codigo]);
    $c = $st->fetch(PDO::FETCH_ASSOC);

    // Mensagem única para "não existe" e "desativado" (não revela quais códigos existem)
    if (!$c || !(int)$c['ativo']) return $falha('Cupom inválido ou expirado.');

    $hoje = date('Y-m-d');
    if (!empty($c['data_inicio']) && substr($c['data_inicio'], 0, 10) > $hoje) return $falha('Este cupom ainda não está disponível.');
    if (!empty($c['data_fim'])    && substr($c['data_fim'], 0, 10)    < $hoje) return $falha('Cupom inválido ou expirado.');

    $limite = (int)($c['limite_uso'] ?? 0);
    if ($limite > 0 && (int)$c['total_usado'] >= $limite) return $falha('Este cupom atingiu o limite de usos.');

    $minimo = (float)($c['valor_minimo'] ?? 0);
    if ($minimo > 0 && $subtotal < $minimo) {
        return $falha('Este cupom vale para compras a partir de ' . loja_brl($minimo) . '.');
    }

    $valor = (float)$c['valor'];
    if ($c['tipo'] === 'percentual') {
        $desconto = round($subtotal * min(max($valor, 0), 100) / 100, 2);
    } elseif ($c['tipo'] === 'fixo') {
        $desconto = round(max($valor, 0), 2);
    } else {
        return $falha('Tipo de cupom não suportado.');
    }
    $desconto = min($desconto, $subtotal);
    if ($desconto <= 0) return $falha('Este cupom não gera desconto para o seu carrinho.');

    return ['ok' => true, 'erro' => '', 'cupom' => $c, 'desconto' => $desconto];
}

/** Reserva 1 uso do cupom de forma atômica (respeita limite_uso mesmo com pedidos simultâneos). */
function cupom_consumir(PDO $pdo, int $cupom_id): bool
{
    $st = $pdo->prepare("
        UPDATE cupons SET total_usado = total_usado + 1
         WHERE id = ? AND ativo = 1 AND (limite_uso IS NULL OR limite_uso = 0 OR total_usado < limite_uso)
    ");
    $st->execute([$cupom_id]);
    return $st->rowCount() === 1;
}

/** Devolve 1 uso do cupom (pedido cancelado/recusado). */
function cupom_liberar(PDO $pdo, string $codigo): void
{
    $pdo->prepare("UPDATE cupons SET total_usado = CASE WHEN total_usado > 0 THEN total_usado - 1 ELSE 0 END WHERE codigo = ?")
        ->execute([$codigo]);
}

/**
 * Calcula todos os valores do pedido.
 * @param array|null $cupom_validado  retorno de cupom_validar() (ou null sem cupom)
 * @param string $cep        CEP de entrega (sem ele o frete fica "não definido" e o total sai sem frete)
 * @param int    $qtd_itens  total de peças (define o peso)
 * @param string $servico    id da opção escolhida (pac|sedex|jadlog|fixo|gratis). Vazio/inválido → a mais barata;
 *                           quem cobra deve conferir que 'frete_servico' do retorno é o que o cliente pediu.
 */
function loja_calcular_totais(PDO $pdo, float $subtotal, string $metodo, ?array $cupom_validado = null,
                              string $cep = '', int $qtd_itens = 0, string $servico = ''): array
{
    $subtotal = round($subtotal, 2);

    $desc_cupom = ($cupom_validado && $cupom_validado['ok']) ? (float)$cupom_validado['desconto'] : 0.0;
    $base       = round($subtotal - $desc_cupom, 2);
    $desc_pix   = ($metodo === 'pix') ? round($base * LOJA_DESCONTO_PIX, 2) : 0.0;

    // Frete cotado no servidor pelo CEP
    $cot = frete_cotar($pdo, $cep, $qtd_itens, $base);
    $sel = null;
    if ($cot['ok']) {
        foreach ($cot['opcoes'] as $o) if ($o['id'] === $servico) { $sel = $o; break; }
        if ($sel === null) $sel = $cot['opcoes'][0];   // mais barata
    }
    $frete = $sel ? (float)$sel['preco'] : 0.0;

    $gratis_ativo = loja_cfg($pdo, 'frete_frete_gratis_ativo', '1') === '1';
    $gratis_min   = (float)loja_cfg($pdo, 'frete_frete_gratis_minimo', '199');

    $total = round($base - $desc_pix + $frete, 2);

    return [
        'subtotal'       => $subtotal,
        'desconto_cupom' => $desc_cupom,
        'desconto_pix'   => $desc_pix,
        'desconto_total' => round($desc_cupom + $desc_pix, 2),
        'frete_definido' => $sel !== null,
        'frete_erro'     => $cot['ok'] ? '' : $cot['erro'],
        'frete'          => round($frete, 2),
        'frete_servico'  => $sel['id'] ?? '',
        'frete_nome'     => $sel['nome'] ?? '',
        'frete_transportadora' => $sel['transportadora'] ?? '',
        'frete_motivo'   => $sel ? ($sel['gratis'] ? 'gratis' : 'pago') : 'indefinido',
        'opcoes_frete'   => $cot['opcoes'],
        'frete_falta'    => ($sel && $gratis_ativo && !$sel['gratis'] && $base < $gratis_min) ? round($gratis_min - $base, 2) : 0.0,
        'prazo_dias'     => $sel ? (int)$sel['prazo'] : 0,
        'total'          => $total,
        'erro'           => $total > 0 ? '' : 'O cupom não pode zerar o valor do pedido.',
    ];
}

/**
 * Valida o carrinho enviado pelo navegador contra o banco, SEM travar linhas
 * (usado só para exibir o resumo; a compra real trava em processar_pedido.php).
 * Do carrinho só são aproveitados id, quantidade e variações — nunca preço.
 *
 * @return array{ok:bool, erro:string, subtotal:float, itens:array}
 */
function loja_carregar_itens(PDO $pdo, $carrinho_in): array
{
    $falha = fn(string $m) => ['ok' => false, 'erro' => $m, 'subtotal' => 0.0, 'itens' => []];
    if (!is_array($carrinho_in) || !$carrinho_in) return $falha('Seu carrinho está vazio.');

    $qtd_por_produto = [];
    foreach ($carrinho_in as $it) {
        $pid = (int)($it['id'] ?? 0);
        $qtd = (int)($it['qtd'] ?? 1);
        if ($pid <= 0 || $qtd <= 0 || $qtd > 20) return $falha('Item inválido no carrinho.');
        $qtd_por_produto[$pid] = ($qtd_por_produto[$pid] ?? 0) + $qtd;
    }

    $st = $pdo->prepare("SELECT id, nome, preco, estoque FROM produtos WHERE id = ? AND ativo = 1");
    $subtotal = 0.0; $itens = [];
    foreach ($qtd_por_produto as $pid => $qtd) {
        $st->execute([$pid]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) return $falha('Um dos produtos do carrinho não está mais disponível.');
        if ((int)$p['estoque'] < $qtd) {
            return $falha((int)$p['estoque'] > 0
                ? "Estoque insuficiente para \"{$p['nome']}\" (restam {$p['estoque']})."
                : "\"{$p['nome']}\" está sem estoque.");
        }
        $preco = round((float)$p['preco'], 2);
        $subtotal += $preco * $qtd;
        $itens[] = ['id' => $pid, 'nome' => $p['nome'], 'preco' => $preco, 'qtd' => $qtd];
    }
    return ['ok' => true, 'erro' => '', 'subtotal' => round($subtotal, 2), 'itens' => $itens];
}
