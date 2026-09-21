<?php
define('PS_TOKEN', '7312ac40-5b19-4a72-bcb3-4f63523d9c89f542729a4711be1a5295634ea30cba24defd-2a78-4ba7-abd0-793569c88edb');
define('PS_SANDBOX', true);

define('PS_API_URL', PS_SANDBOX ? 'https://sandbox.api.pagseguro.com' : 'https://api.pagseguro.com');

class PagSeguro {
    private static function request(string $method, string $endpoint, array $body = []): array {
        $url = PS_API_URL . $endpoint;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . PS_TOKEN,
                'Content-Type: application/json',
                'x-idempotency-key: ' . uniqid('altojordao_ps_', true),
            ],
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        } elseif ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => true, 'message' => 'Erro de conexão PagSeguro: ' . $error];
        }

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            $msg = $data['error_messages'][0]['description'] ?? $data['message'] ?? 'Erro desconhecido PagSeguro';
            return ['error' => true, 'message' => $msg, 'http_code' => $httpCode, 'raw' => $data];
        }
        return $data ?? [];
    }

    private static function montarCustomer(array $usuario, array $end = []): array {
        $nomes = explode(' ', $usuario['nome'], 2);
        $customer  = [
            'name'   => $usuario['nome'],
            'email'  => $usuario['email'],
            'tax_id' => preg_replace('/\D/', '', $usuario['cpf'] ?? '00000000000'),
            'phones' => [[
                'country' => '55',
                'area'    => substr(preg_replace('/\D/', '', $usuario['telefone'] ?? '11'), 0, 2),
                'number'  => substr(preg_replace('/\D/', '', $usuario['telefone'] ?? '999999999'), 2, 9),
                'type'    => 'MOBILE',
            ]],
        ];

        if (!empty($end['rua'])) {
            $customer['address'] = [
                'street'      => $end['rua'],
                'number'      => $end['numero']  ?? 'S/N',
                'locality'    => $end['bairro']  ?? '',
                'city'        => $end['cidade']  ?? '',
                'region_code' => $end['estado']  ?? 'SP',
                'country'     => 'BRA',
                'postal_code' => preg_replace('/\D/', '', $end['cep'] ?? ''),
            ];
        }

        return $customer;
    }
    // MONTAR ITEMS (produtos do pedido)
    private static function montarItems(array $carrinho): array
    {
        return array_map(fn($item) => [
            'name'        => mb_substr($item['nome'] ?? 'Produto', 0, 63),
            'quantity'    => (int)($item['qtd'] ?? 1),
            'unit_amount' => (int)(round((float)$item['preco'], 2) * 100), // em centavos
        ], $carrinho);
    }
    // PIX
    public static function gerarPix(array $pedido, array $usuario, array $end, array $carrinho): array
    {
        $total_centavos = (int)(round((float)$pedido['total'], 2) * 100);

        $body = [
            'reference_id'        => 'pedido-' . $pedido['id'],
            'customer'            => self::montarCustomer($usuario, $end),
            'items'               => self::montarItems($carrinho),
            'qr_codes'            => [[
                'amount'          => ['value' => $total_centavos],
                'expiration_date' => date('Y-m-d\TH:i:s-03:00', strtotime('+30 minutes')),
            ]],
            'notification_urls'   => [SITE_URL . '/webhook_ps.php'],
        ];

        $res = self::request('POST', '/orders', $body);

        if (!empty($res['error'])) return $res;

        $qr = $res['qr_codes'][0] ?? null;
        if (!$qr) {
            return ['error' => true, 'message' => 'QR Code PIX não gerado pelo PagSeguro.'];
        }

        // Busca imagem do QR em links
        $qr_img_url = null;
        foreach ($qr['links'] ?? [] as $link) {
            if (($link['media'] ?? '') === 'image/png') {
                $qr_img_url = $link['href'];
                break;
            }
        }

        return [
            'order_id'   => $res['id'],
            'payment_id' => $res['id'],
            'status'     => $res['status'],
            'qr_code'    => $qr['text']       ?? null, // código copia-e-cola
            'qr_img_url' => $qr_img_url,               // URL da imagem PNG do QR
            'expiracao'  => date('H:i', strtotime('+30 minutes')),
        ];
    }
    // BOLETO
    public static function gerarBoleto(array $pedido, array $usuario, array $end, array $carrinho): array
    {
        $total_centavos = (int)(round((float)$pedido['total'], 2) * 100);

        $body = [
            'reference_id'      => 'pedido-' . $pedido['id'],
            'customer'          => self::montarCustomer($usuario, $end),
            'items'             => self::montarItems($carrinho),
            'charges'           => [[
                'reference_id'  => 'pedido-' . $pedido['id'],
                'description'   => 'Pedido Alto Jordão #' . $pedido['id'],
                'amount'        => [
                    'value'    => $total_centavos,
                    'currency' => 'BRL',
                ],
                'payment_method' => [
                    'type'         => 'BOLETO',
                    'boleto'       => [
                        'due_date'  => date('Y-m-d', strtotime('+3 days')),
                        'instruction_lines' => [
                            'line_1' => 'Pedido Alto Jordão #' . $pedido['id'],
                            'line_2' => 'Não receber após o vencimento.',
                        ],
                        'holder' => [
                            'name'    => $usuario['nome'],
                            'tax_id'  => preg_replace('/\D/', '', $usuario['cpf'] ?? '00000000000'),
                            'email'   => $usuario['email'],
                            'address' => [
                                'street'      => $end['rua']    ?? '',
                                'number'      => $end['numero'] ?? 'S/N',
                                'locality'    => $end['bairro'] ?? '',
                                'city'        => $end['cidade'] ?? '',
                                'region_code' => $end['estado'] ?? 'SP',
                                'country'     => 'BRA',
                                'postal_code' => preg_replace('/\D/', '', $end['cep'] ?? ''),
                            ],
                        ],
                    ],
                ],
            ]],
            'notification_urls' => [SITE_URL . '/webhook_ps.php'],
        ];

        $res = self::request('POST', '/orders', $body);

        if (!empty($res['error'])) return $res;

        $charge = $res['charges'][0] ?? null;
        if (!$charge) {
            return ['error' => true, 'message' => 'Boleto não gerado pelo PagSeguro.'];
        }

        $boleto_url = null;
        foreach ($charge['links'] ?? [] as $link) {
            if (str_contains($link['href'] ?? '', 'boleto')) {
                $boleto_url = $link['href'];
                break;
            }
        }
        // Fallback: primeiro link disponível
        if (!$boleto_url && !empty($charge['links'][0]['href'])) {
            $boleto_url = $charge['links'][0]['href'];
        }

        return [
            'order_id'    => $res['id'],
            'payment_id'  => $res['id'],
            'charge_id'   => $charge['id'],
            'status'      => $charge['status'],
            'boleto_url'  => $boleto_url,
            'barcode'     => $charge['payment_method']['boleto']['barcode'] ?? null,
            'vencimento'  => date('d/m/Y', strtotime('+3 days')),
        ];
    }
    // CARTÃO DE CRÉDITO
    public static function processarCartao(
        array  $pedido,
        array  $usuario,
        array  $end,
        array  $carrinho,
        string $token_cartao,
        int    $parcelas
    ): array {
        $total_centavos = (int)(round((float)$pedido['total'], 2) * 100);

        $body = [
            'reference_id'      => 'pedido-' . $pedido['id'],
            'customer'          => self::montarCustomer($usuario, $end),
            'items'             => self::montarItems($carrinho),
            'charges'           => [[
                'reference_id'   => 'pedido-' . $pedido['id'],
                'description'    => 'Pedido Alto Jordão #' . $pedido['id'],
                'amount'         => [
                    'value'    => $total_centavos,
                    'currency' => 'BRL',
                ],
                'payment_method' => [
                    'type'         => 'CREDIT_CARD',
                    'installments' => $parcelas,
                    'capture'      => true,
                    'card'         => [
                        'encrypted'        => $token_cartao, // token gerado pelo SDK JS do PS
                        'security_code'    => null,
                        'store'            => false,
                        'holder'           => [
                            'name'   => $usuario['nome'],
                            'tax_id' => preg_replace('/\D/', '', $usuario['cpf'] ?? '00000000000'),
                        ],
                    ],
                ],
            ]],
            'notification_urls' => [SITE_URL . '/webhook_ps.php'],
        ];

        $res = self::request('POST', '/orders', $body);

        if (!empty($res['error'])) return $res;

        $charge = $res['charges'][0] ?? null;
        $status = $charge['status'] ?? 'DECLINED';

        return [
            'order_id'      => $res['id'],
            'payment_id'    => $res['id'],
            'charge_id'     => $charge['id'] ?? null,
            'status'        => $status,
            'approved'      => ($status === 'PAID'),
            'status_detail' => $charge['payment_response']['message'] ?? '',
        ];
    }
    // CONSULTAR PEDIDO
    public static function consultarPedido(string $order_id): array
    {
        return self::request('GET', '/orders/' . $order_id);
    }
    // TRADUZIR ERROS DO CARTÃO
    public static function traduzirErroCartao(string $status_detail): string
    {
        $erros = [
            'DECLÍNIO GENÉRICO'          => 'Pagamento recusado pelo banco. Tente outro cartão.',
            'SALDO INSUFICIENTE'         => 'Saldo insuficiente no cartão.',
            'CARTÃO INVÁLIDO'            => 'Número do cartão inválido.',
            'CARTÃO EXPIRADO'            => 'Cartão vencido. Use outro cartão.',
            'CÓDIGO DE SEGURANÇA'        => 'CVV inválido.',
            'TRANSAÇÃO NÃO PERMITIDA'    => 'Transação não permitida pelo banco.',
        ];

        foreach ($erros as $chave => $msg) {
            if (str_contains(strtoupper($status_detail), $chave)) return $msg;
        }

        return 'Pagamento recusado: ' . ($status_detail ?: 'tente novamente.');
    }
}