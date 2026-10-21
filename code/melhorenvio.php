<?php
/**
 * Cliente da API do Melhor Envio (cotação de frete) — Alto Jordão
 *
 * Credenciais no .env (nunca no código):
 *   MELHORENVIO_TOKEN    token de acesso (da sandbox ou da produção — são contas diferentes)
 *   MELHORENVIO_SANDBOX  true = https://sandbox.melhorenvio.com.br | false = https://melhorenvio.com.br
 *   MELHORENVIO_EMAIL    e-mail técnico de contato (o Melhor Envio exige no User-Agent)
 *
 * Só COTA o frete. Compra de etiqueta/rastreio não está implementada.
 */
require_once __DIR__ . '/env.php';

class MelhorEnvio
{
    public static function configurado(): bool
    {
        return (string)env('MELHORENVIO_TOKEN', '') !== '';
    }

    public static function sandbox(): bool
    {
        return env_bool('MELHORENVIO_SANDBOX', true);
    }

    private static function baseUrl(): string
    {
        $forcada = (string)env('MELHORENVIO_URL', '');   // só para testes locais (servidor falso); deixe vazio
        if ($forcada !== '') return rtrim($forcada, '/');
        return self::sandbox() ? 'https://sandbox.melhorenvio.com.br' : 'https://melhorenvio.com.br';
    }

    /**
     * Cota o frete.
     * @param array $produtos  [{id, largura_cm, altura_cm, comprimento_cm, peso_kg, valor, qtd}]
     * @return array{ok:bool, erro:string, servicos:array}
     *   servicos[]: id, nome, empresa, preco, prazo   (só serviços disponíveis, com preço)
     */
    public static function cotar(string $cep_origem, string $cep_destino, array $produtos): array
    {
        $falha = fn(string $m) => ['ok' => false, 'erro' => $m, 'servicos' => []];
        if (!self::configurado()) return $falha('Token do Melhor Envio não configurado (.env).');

        $body = [
            'from'    => ['postal_code' => preg_replace('/\D/', '', $cep_origem)],
            'to'      => ['postal_code' => preg_replace('/\D/', '', $cep_destino)],
            'products' => array_map(fn($p) => [
                'id'              => (string)$p['id'],
                'width'           => round((float)$p['largura_cm'], 1),
                'height'          => round((float)$p['altura_cm'], 1),
                'length'          => round((float)$p['comprimento_cm'], 1),
                'weight'          => round((float)$p['peso_kg'], 3),
                'insurance_value' => round((float)$p['valor'], 2),
                'quantity'        => (int)$p['qtd'],
            ], $produtos),
            'options' => ['receipt' => false, 'own_hand' => false],
        ];

        $contato = (string)env('MELHORENVIO_EMAIL', '');
        $ch = curl_init(self::baseUrl() . '/api/v2/me/shipment/calculate');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => 8,     // o checkout não pode ficar preso à API
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . env('MELHORENVIO_TOKEN'),
                'User-Agent: AltoJordao' . ($contato !== '' ? " ($contato)" : ''),
            ],
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($resp === false) return $falha('Sem resposta do Melhor Envio: ' . $cerr);
        return self::interpretar($http, (string)$resp);
    }

    /** Converte a resposta da API (separado para ser testável sem rede). */
    public static function interpretar(int $http, string $resp): array
    {
        $falha = fn(string $m) => ['ok' => false, 'erro' => $m, 'servicos' => []];
        $d = json_decode($resp, true);
        if ($http === 401 || $http === 403) return $falha("Melhor Envio recusou o token (HTTP $http).");
        if ($http < 200 || $http >= 300 || !is_array($d)) {
            $msg = is_array($d) ? ($d['message'] ?? '') : '';
            return $falha("Melhor Envio HTTP $http" . ($msg !== '' ? ": $msg" : ''));
        }

        $servicos = [];
        $descartados = [];
        foreach ($d as $s) {
            if (!is_array($s)) continue;
            if (!empty($s['error'])) {                                        // serviço indisponível p/ esta rota
                $descartados[] = trim(($s['name'] ?? ('#' . ($s['id'] ?? '?'))) . ': ' . (is_string($s['error']) ? $s['error'] : json_encode($s['error'], JSON_UNESCAPED_UNICODE)));
                continue;
            }
            $preco = $s['custom_price'] ?? $s['price'] ?? null;               // custom_* = com as regras da conta
            $prazo = $s['custom_delivery_time'] ?? $s['delivery_time'] ?? null;
            if (!isset($s['id']) || $preco === null || !is_numeric($preco) || (float)$preco < 0) continue;
            $empresa = (string)($s['company']['name'] ?? '');
            $nome    = (string)($s['name'] ?? ('Serviço ' . $s['id']));
            $servicos[] = [
                'id'      => (int)$s['id'],
                'nome'    => ($empresa !== '' && stripos($nome, $empresa) === false) ? "$empresa $nome" : $nome,
                'empresa' => $empresa,
                'preco'   => round((float)$preco, 2),
                'prazo'   => max(1, (int)$prazo),
            ];
        }
        $extra = $descartados ? implode(' | ', array_slice($descartados, 0, 4)) : '';
        if (!$servicos && !$descartados) $extra = 'Resposta sem serviços: ' . mb_substr($resp, 0, 200);
        return ['ok' => true, 'erro' => '', 'servicos' => $servicos, 'descartados' => $extra];
    }
}
