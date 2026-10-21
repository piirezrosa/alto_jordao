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
 * Com o Melhor Envio ativado (Configurações → Frete + token no .env) a cotação vem da API, com peso e
 * medidas reais dos produtos. Se a API falhar ou não devolver nenhum serviço, cai nesta tabela — o
 * checkout nunca fica sem frete.
 */

require_once __DIR__ . '/melhorenvio.php';

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

/** Medidas (cm) usadas quando o produto não tem as dele cadastradas — caixa pequena de roupa dobrada. */
const FRETE_DIM_PADRAO = ['largura_cm' => 20.0, 'altura_cm' => 5.0, 'comprimento_cm' => 25.0];
/** Mínimos aceitos pelos Correios/Melhor Envio. */
const FRETE_DIM_MINIMA = ['largura_cm' => 11.0, 'altura_cm' => 2.0, 'comprimento_cm' => 16.0];

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

/** Colunas de peso/medidas em `produtos` (cria o que faltar; chamar fora de transação). */
function frete_garantir_schema(PDO $pdo): void
{
    static $feito = false;
    if ($feito) return;
    $feito = true;
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM produtos")->fetchAll(PDO::FETCH_COLUMN);
        foreach (['peso_kg' => 'DECIMAL(6,3)', 'largura_cm' => 'DECIMAL(6,1)', 'altura_cm' => 'DECIMAL(6,1)', 'comprimento_cm' => 'DECIMAL(6,1)'] as $c => $t) {
            if (!in_array($c, $cols, true)) $pdo->exec("ALTER TABLE produtos ADD COLUMN $c $t NULL");
        }
    } catch (Throwable $e) {
        error_log('[FRETE SCHEMA] ' . $e->getMessage() . ' — rode code/migrations/2026_10_20_frete_melhorenvio.sql');
    }
}

/**
 * Completa peso/medidas dos itens (produto sem cadastro usa o padrão das configurações) e aplica os mínimos.
 * @param array $itens  [{id, qtd, preco, peso_kg?, largura_cm?, altura_cm?, comprimento_cm?}]
 * @return array  [{id, qtd, valor, peso_kg, largura_cm, altura_cm, comprimento_cm}]
 */
function frete_normalizar_itens(PDO $pdo, array $itens): array
{
    $peso_pad = max(0.05, frete_num($pdo, 'frete_peso_item_kg', FRETE_PESO_PADRAO_KG));
    $pad = [
        'largura_cm'     => frete_num($pdo, 'frete_dim_largura_cm',     FRETE_DIM_PADRAO['largura_cm']),
        'altura_cm'      => frete_num($pdo, 'frete_dim_altura_cm',      FRETE_DIM_PADRAO['altura_cm']),
        'comprimento_cm' => frete_num($pdo, 'frete_dim_comprimento_cm', FRETE_DIM_PADRAO['comprimento_cm']),
    ];
    $out = [];
    foreach ($itens as $it) {
        $linha = ['id' => $it['id'], 'qtd' => max(1, (int)$it['qtd']), 'valor' => round((float)($it['preco'] ?? 0), 2)];
        $peso = isset($it['peso_kg']) && is_numeric($it['peso_kg']) && (float)$it['peso_kg'] > 0 ? (float)$it['peso_kg'] : $peso_pad;
        $linha['peso_kg'] = round($peso, 3);
        foreach ($pad as $k => $padrao) {
            $v = isset($it[$k]) && is_numeric($it[$k]) && (float)$it[$k] > 0 ? (float)$it[$k] : $padrao;
            $linha[$k] = max(FRETE_DIM_MINIMA[$k], $v);
        }
        $out[] = $linha;
    }
    return $out;
}

/** Cotação do Melhor Envio com cache curto na sessão (o resumo recalcula a cada clique; a cobrança reusa o mesmo preço). */
function frete_cotar_melhorenvio(PDO $pdo, string $cep, array $itens_norm): array
{
    $origem = preg_replace('/\D/', '', (string)loja_cfg($pdo, 'frete_cep_origem', ''));
    if (strlen($origem) !== 8) return ['ok' => false, 'erro' => 'CEP de origem não configurado.', 'servicos' => []];

    $chave = md5(json_encode([$origem, $cep, $itens_norm, MelhorEnvio::sandbox()]));
    $agora = time();
    if (isset($_SESSION['frete_cache'][$chave]) && ($agora - $_SESSION['frete_cache'][$chave]['t']) < 900) {
        return ['ok' => true, 'erro' => '', 'servicos' => $_SESSION['frete_cache'][$chave]['servicos']];
    }
    $r = MelhorEnvio::cotar($origem, $cep, $itens_norm);
    if ($r['ok'] && $r['servicos'] && isset($_SESSION)) {
        $_SESSION['frete_cache'] = array_slice(($_SESSION['frete_cache'] ?? []) + [$chave => ['t' => $agora, 'servicos' => $r['servicos']]], -20, null, true);
        $_SESSION['frete_cache'][$chave] = ['t' => $agora, 'servicos' => $r['servicos']];
    }
    return $r;
}

/**
 * Cota o frete de um pedido.
 * @param string $cep    CEP de entrega (8 dígitos)
 * @param array  $itens  itens do carrinho (ver frete_normalizar_itens)
 * @param float  $base   valor dos produtos já com cupom (para a regra de frete grátis)
 * @return array{ok:bool, erro:string, uf:?string, zona:int, peso_kg:float, fonte:string, opcoes:array}
 *   opcoes[]: id, transportadora, nome, preco, prazo, gratis   (ordenadas do mais barato ao mais caro)
 *   fonte: 'melhorenvio' ou 'tabela'
 */
function frete_cotar(PDO $pdo, string $cep, array $itens, float $base): array
{
    $falha = fn(string $m) => ['ok' => false, 'erro' => $m, 'uf' => null, 'zona' => 0, 'peso_kg' => 0.0, 'fonte' => '', 'opcoes' => []];

    $cep = preg_replace('/\D/', '', $cep);
    if (strlen($cep) !== 8) return $falha('Informe o CEP para calcular o frete.');
    $uf = frete_uf_do_cep($cep);
    if ($uf === null) return $falha('CEP não encontrado. Confira os números.');
    $norm = frete_normalizar_itens($pdo, $itens);
    if (!$norm) return $falha('Carrinho vazio.');

    $peso = 0.0;
    foreach ($norm as $n) $peso += $n['peso_kg'] * $n['qtd'];
    $peso = round(max(0.3, $peso), 3);

    $uf_origem = frete_uf_do_cep((string)loja_cfg($pdo, 'frete_cep_origem', ''));
    $zona      = frete_zona($uf_origem, $uf);
    $opcoes    = [];
    $fonte     = 'tabela';

    // 1) Melhor Envio: cotação real. Fica LIGADO por padrão assim que o token existe no .env
    //    (o admin pode desligar). Respeita os interruptores Correios/Jadlog/outras.
    $me_ativo = MelhorEnvio::configurado()
        && loja_cfg($pdo, 'frete_melhorenvio_ativo', '1') === '1';
    if ($me_ativo) {
        $r = frete_cotar_melhorenvio($pdo, $cep, $norm);
        $motivo_falha = '';
        if ($r['ok']) {
            $recusados = [];
            foreach ($r['servicos'] as $sv) {
                $emp = strtolower($sv['empresa']);
                if (strpos($emp, 'correios') !== false)     $ligado = loja_cfg($pdo, 'frete_correios_ativo', '1') === '1';
                elseif (strpos($emp, 'jadlog') !== false)   $ligado = loja_cfg($pdo, 'frete_jadlog_ativo', '0') === '1';
                else                                        $ligado = loja_cfg($pdo, 'frete_melhorenvio_outras', '0') === '1';
                if (!$ligado) { $recusados[] = $sv['nome']; continue; }
                $opcoes[] = ['id' => 'me' . $sv['id'], 'transportadora' => $sv['empresa'] !== '' ? $sv['empresa'] : 'Melhor Envio',
                             'nome' => $sv['nome'], 'preco' => $sv['preco'], 'prazo' => $sv['prazo'], 'gratis' => false];
            }
            if ($opcoes) {
                $fonte = 'melhorenvio';
            } elseif ($recusados) {
                $motivo_falha = 'A API devolveu serviços (' . implode(', ', $recusados) . ') mas nenhum está habilitado nos interruptores Correios / Jadlog / outras transportadoras.';
            } else {
                $motivo_falha = 'A API respondeu, mas sem nenhum serviço com preço para este CEP/medidas.';
            }
        } else {
            $motivo_falha = $r['erro'];
        }
        frete_me_registrar($pdo, $motivo_falha === '', $motivo_falha);
        if ($motivo_falha !== '') {
            error_log('[FRETE] Melhor Envio sem opções: ' . $motivo_falha);
            if (loja_cfg($pdo, 'frete_melhorenvio_fallback', '1') !== '1') {
                return $falha('Não foi possível calcular o frete agora. Tente novamente em instantes.');
            }
            $fonte = 'tabela_fallback';   // estimativa pela tabela, e o cliente é avisado
        }
    }

    // 2) Tabela própria: modo padrão sem Melhor Envio, ou plano B quando a API falhou
    if ($fonte !== 'melhorenvio') {
        $kg_extra = max(0, (int)ceil($peso) - 1);
        foreach (FRETE_SERVICOS as $id => $s) {
            if (loja_cfg($pdo, $s['toggle'], $id === 'jadlog' ? '0' : '1') !== '1') continue;
            [$base_z, $kg_z, $prazo] = frete_linha_tabela($pdo, $id, $zona);
            $opcoes[] = ['id' => $id, 'transportadora' => $s['transportadora'], 'nome' => $s['nome'],
                         'preco' => round($base_z + $kg_z * $kg_extra, 2), 'prazo' => $prazo, 'gratis' => false];
        }
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

    return ['ok' => true, 'erro' => '', 'uf' => $uf, 'zona' => $zona, 'peso_kg' => $peso, 'fonte' => $fonte, 'opcoes' => $opcoes];
}

/**
 * Lê peso/medidas enviados pelo formulário de produto. Campo vazio = NULL (usa o padrão da loja).
 * @return array{0:array, 1:string}  [valores (peso_kg, largura_cm, altura_cm, comprimento_cm), mensagem de erro ('' se ok)]
 */
function frete_ler_dimensoes_post(array $post): array
{
    $lim = ['peso_kg' => [0.01, 50], 'largura_cm' => [1, 200], 'altura_cm' => [1, 200], 'comprimento_cm' => [1, 200]];
    $out = []; 
    foreach ($lim as $campo => [$min, $max]) {
        $v = trim(str_replace(',', '.', (string)($post[$campo] ?? '')));
        if ($v === '') { $out[$campo] = null; continue; }
        if (!is_numeric($v) || (float)$v < $min || (float)$v > $max) {
            return [[], "Valor inválido em \"$campo\" (use entre $min e $max)."];
        }
        $out[$campo] = (float)$v;
    }
    return [$out, ''];
}

/** Grava peso/medidas de um produto (chamar fora de transação). */
function frete_salvar_dimensoes(PDO $pdo, int $produto_id, array $dim): void
{
    frete_garantir_schema($pdo);
    $pdo->prepare("UPDATE produtos SET peso_kg = ?, largura_cm = ?, altura_cm = ?, comprimento_cm = ? WHERE id = ?")
        ->execute([$dim['peso_kg'], $dim['largura_cm'], $dim['altura_cm'], $dim['comprimento_cm'], $produto_id]);
}

/**
 * Guarda o último resultado da API (para o admin ver por que o checkout caiu na tabela).
 * Só grava quando muda ou a cada 10 min — não escreve no banco a cada cotação.
 */
function frete_me_registrar(PDO $pdo, bool $ok, string $erro): void
{
    try {
        $atual = json_decode((string)loja_cfg($pdo, 'frete_me_status', ''), true);
        if (is_array($atual) && ($atual['ok'] ?? null) === $ok && ($atual['erro'] ?? '') === $erro && (time() - (int)($atual['t'] ?? 0)) < 600) return;
        $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('frete_me_status', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)")
            ->execute([json_encode(['t' => time(), 'ok' => $ok, 'erro' => mb_substr($erro, 0, 200)], JSON_UNESCAPED_UNICODE)]);
    } catch (Throwable $e) { /* diagnóstico nunca derruba a cotação */ }
}

/**
 * Diagnóstico: o checkout está usando a cotação do Melhor Envio? Se não, por quê (sem depender de erro de API).
 * @return array{usando:bool, problemas:string[]}
 */
function frete_me_diagnostico(PDO $pdo): array
{
    $p = [];
    $arq = env_caminho();
    if (!is_file($arq)) {
        $p[] = "O arquivo <code>.env</code> não foi encontrado em <code>" . htmlspecialchars($arq) . "</code> (confira o nome: não pode ser <code>.env.txt</code> nem <code>.env.example</code>).";
    } elseif (!is_readable($arq)) {
        $p[] = "O arquivo <code>.env</code> existe mas o servidor não consegue lê-lo (permissões).";
    }
    if (!MelhorEnvio::configurado()) {
        $p[] = "<code>MELHORENVIO_TOKEN</code> está vazio ou ausente no <code>.env</code>" . (is_file($arq) ? " (reinicie o Apache depois de editar o arquivo)." : ".");
    }
    if (loja_cfg($pdo, 'frete_melhorenvio_ativo', '1') !== '1') {
        $p[] = "O interruptor <strong>“Usar cotação real do Melhor Envio”</strong> está DESLIGADO aqui nas configurações — ligue-o e salve.";
    }
    if (strlen(preg_replace('/\D/', '', (string)loja_cfg($pdo, 'frete_cep_origem', ''))) !== 8) {
        $p[] = "O <strong>CEP de origem</strong> não está preenchido/salvo (a API precisa dele).";
    }
    return ['usando' => !$p, 'problemas' => $p];
}
