<?php
/**
 * Cálculo de frete NO SERVIDOR — Alto Jordão
 *
 * Como funciona (sem depender de contrato/API de transportadora):
 *   1. O CEP de destino define o estado (faixas oficiais de CEP) — o UF digitado pelo cliente NÃO é usado.
 *   2. Compara com o estado do CEP de origem da loja e define a ZONA:
 *        1 = mesmo estado | 2 = mesma região do país | 3 = demais regiões
 *   3. Para cada serviço ativo (Correios PAC, Correios SEDEX, Jadlog, frete fixo) calcula
 *        preço = valor base da zona + adicional por kg extra × (kg arredondado para cima − 1)
 *      usando a TABELA editável em Admin → Configurações → Frete.
 *   4. Frete grátis (se ativo e o pedido atingiu o mínimo) zera a opção MAIS BARATA.
 *
 * Os valores da tabela são estimativas editáveis; para preço oficial é preciso contrato com a
 * transportadora (API) — esta camada é o ponto onde isso entraria.
 */

const FRETE_SERVICOS = [
    'pac'    => ['transportadora' => 'Correios', 'nome' => 'Correios PAC',    'toggle' => 'frete_correios_ativo'],
    'sedex'  => ['transportadora' => 'Correios', 'nome' => 'Correios SEDEX',  'toggle' => 'frete_correios_ativo'],
    'jadlog' => ['transportadora' => 'Jadlog',   'nome' => 'Jadlog',          'toggle' => 'frete_jadlog_ativo'],
];

/** Valores de exemplo (base R$ até 1 kg, adicional R$ por kg extra, prazo em dias úteis) por zona. */
const FRETE_TABELA_PADRAO = [
    'pac'    => [1 => [14.90, 3.00, 3], 2 => [19.90, 4.00, 5], 3 => [29.90, 6.00, 9]],
    'sedex'  => [1 => [22.90, 5.00, 1], 2 => [32.90, 7.00, 2], 3 => [49.90, 10.00, 4]],
    'jadlog' => [1 => [16.90, 3.50, 2], 2 => [21.90, 4.50, 4], 3 => [31.90, 6.50, 7]],
];

const FRETE_PESO_PADRAO_KG = 0.4;   // peso médio de uma peça de roupa (editável)

const FRETE_REGIOES = [
    'N'  => ['AC','AP','AM','PA','RO','RR','TO'],
    'NE' => ['AL','BA','CE','MA','PB','PE','PI','RN','SE'],
    'CO' => ['DF','GO','MT','MS'],
    'SE' => ['ES','MG','RJ','SP'],
    'S'  => ['PR','RS','SC'],
];

/** Estado (UF) a partir das faixas oficiais de CEP. Retorna null se o CEP for inválido. */
function frete_uf_do_cep(string $cep): ?string
{
    $d = preg_replace('/\D/', '', $cep);
    if (strlen($d) !== 8) return null;
    $n = (int)substr($d, 0, 5);
    static $faixas = [
        [1000,19999,'SP'],[20000,28999,'RJ'],[29000,29999,'ES'],[30000,39999,'MG'],[40000,48999,'BA'],
        [49000,49999,'SE'],[50000,56999,'PE'],[57000,57999,'AL'],[58000,58999,'PB'],[59000,59999,'RN'],
        [60000,63999,'CE'],[64000,64999,'PI'],[65000,65999,'MA'],[66000,68899,'PA'],[68900,68999,'AP'],
        [69000,69299,'AM'],[69300,69399,'RR'],[69400,69899,'AM'],[69900,69999,'AC'],[70000,72799,'DF'],
        [72800,72999,'GO'],[73000,73699,'DF'],[73700,76799,'GO'],[76800,76999,'RO'],[77000,77999,'TO'],
        [78000,78899,'MT'],[78900,78999,'RO'],[79000,79999,'MS'],[80000,87999,'PR'],[88000,89999,'SC'],
        [90000,99999,'RS'],
    ];
    foreach ($faixas as [$a, $b, $uf]) if ($n >= $a && $n <= $b) return $uf;
    return null;
}

function frete_regiao(?string $uf): ?string
{
    foreach (FRETE_REGIOES as $r => $ufs) if (in_array($uf, $ufs, true)) return $r;
    return null;
}

/** 1 = mesmo estado, 2 = mesma região, 3 = demais. Sem origem válida, assume 3 (a mais cara, nunca subestima). */
function frete_zona(?string $uf_origem, ?string $uf_destino): int
{
    if (!$uf_origem || !$uf_destino) return 3;
    if ($uf_origem === $uf_destino) return 1;
    return frete_regiao($uf_origem) === frete_regiao($uf_destino) ? 2 : 3;
}

/** Lê um número não negativo das configurações (valor inválido/negativo → padrão). */
function frete_num(PDO $pdo, string $chave, float $padrao): float
{
    $v = str_replace(',', '.', (string)loja_cfg($pdo, $chave, (string)$padrao));
    return (is_numeric($v) && (float)$v >= 0 && (float)$v <= 100000) ? (float)$v : $padrao;
}

/** Linha da tabela de um serviço numa zona: [base, kg_extra, prazo]. */
function frete_linha_tabela(PDO $pdo, string $servico, int $zona): array
{
    $p = FRETE_TABELA_PADRAO[$servico][$zona];
    return [
        frete_num($pdo, "frete_tab_{$servico}_z{$zona}_base",  $p[0]),
        frete_num($pdo, "frete_tab_{$servico}_z{$zona}_kg",    $p[1]),
        max(1, (int)frete_num($pdo, "frete_tab_{$servico}_z{$zona}_prazo", (float)$p[2])),
    ];
}

/**
 * Cota o frete de um pedido.
 * @param string $cep       CEP de entrega (8 dígitos)
 * @param int    $qtd_itens total de peças
 * @param float  $base      valor dos produtos já com cupom (para a regra de frete grátis)
 * @return array{ok:bool, erro:string, uf:?string, zona:int, peso_kg:float, opcoes:array}
 *   opcoes[]: id, transportadora, nome, preco, prazo, gratis   (ordenadas do mais barato ao mais caro)
 */
function frete_cotar(PDO $pdo, string $cep, int $qtd_itens, float $base): array
{
    $falha = fn(string $m) => ['ok' => false, 'erro' => $m, 'uf' => null, 'zona' => 0, 'peso_kg' => 0.0, 'opcoes' => []];

    $cep = preg_replace('/\D/', '', $cep);
    if (strlen($cep) !== 8) return $falha('Informe o CEP para calcular o frete.');
    $uf = frete_uf_do_cep($cep);
    if ($uf === null) return $falha('CEP não encontrado. Confira os números.');
    if ($qtd_itens <= 0) return $falha('Carrinho vazio.');

    $uf_origem = frete_uf_do_cep((string)loja_cfg($pdo, 'frete_cep_origem', ''));
    $zona      = frete_zona($uf_origem, $uf);
    $peso      = round(max(0.3, $qtd_itens * frete_num($pdo, 'frete_peso_item_kg', FRETE_PESO_PADRAO_KG)), 3);
    $kg_extra  = max(0, (int)ceil($peso) - 1);

    $opcoes = [];
    foreach (FRETE_SERVICOS as $id => $s) {
        if (loja_cfg($pdo, $s['toggle'], $id === 'jadlog' ? '0' : '1') !== '1') continue;
        [$base_z, $kg_z, $prazo] = frete_linha_tabela($pdo, $id, $zona);
        $opcoes[] = ['id' => $id, 'transportadora' => $s['transportadora'], 'nome' => $s['nome'],
                     'preco' => round($base_z + $kg_z * $kg_extra, 2), 'prazo' => $prazo, 'gratis' => false];
    }
    if (loja_cfg($pdo, 'frete_frete_fixo_ativo', '0') === '1') {
        $opcoes[] = ['id' => 'fixo', 'transportadora' => 'Correios', 'nome' => 'Frete fixo',
                     'preco' => round(frete_num($pdo, 'frete_frete_fixo_valor', 15.0), 2),
                     'prazo' => max(1, (int)loja_cfg($pdo, 'frete_prazo_padrao', '5')), 'gratis' => false];
    }
    if (!$opcoes) {   // nenhuma forma de entrega configurada: mantém o comportamento antigo (frete grátis)
        $opcoes[] = ['id' => 'gratis', 'transportadora' => 'Correios', 'nome' => 'Frete grátis', 'preco' => 0.0,
                     'prazo' => max(1, (int)loja_cfg($pdo, 'frete_prazo_padrao', '5')), 'gratis' => true];
    }

    usort($opcoes, fn($a, $b) => [$a['preco'], $a['prazo']] <=> [$b['preco'], $b['prazo']]);

    // Frete grátis: zera só a opção mais barata (as mais rápidas continuam pagas)
    $gratis_ativo = loja_cfg($pdo, 'frete_frete_gratis_ativo', '1') === '1';
    $gratis_min   = frete_num($pdo, 'frete_frete_gratis_minimo', 199.0);
    if ($gratis_ativo && $base >= $gratis_min && $opcoes[0]['preco'] > 0) {
        $opcoes[0]['preco']  = 0.0;
        $opcoes[0]['gratis'] = true;
    }

    return ['ok' => true, 'erro' => '', 'uf' => $uf, 'zona' => $zona, 'peso_kg' => $peso, 'opcoes' => $opcoes];
}
