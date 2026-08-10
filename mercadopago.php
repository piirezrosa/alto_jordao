<?php
// CREDENCIAIS MERCADO PAGO
define('MP_ACCESS_TOKEN', 'DEPOIS_COLOCAR_ACCESS_TOKEN_AQUI');
define('MP_PUBLIC_KEY', 'DEPOIS_COLOCAR_PUBLIC_KEY_AQUI');
define('MP_SANDBOX', true); // true = testes | false = produção

// URL base da API (muda automaticamente dependendo do ambiente)
define('MP_API_URL', 'https://api.mercadopago.com');

// URL do meu site - usada para redirect e webhook
define('SITE_URL', 'http://localhost/alto_jordao');

class MercadoPago {
    /**
     * Faz uma requisição autenticada à API do mercado pago
     */
    private static function request(string $method, string $endpoint, array $body = []): array {
        $url = MP_API_URL . $endpoint;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . MP_ACCESS_TOKEN,
                'Content-Type: application/json',
                'X-Idempotency-Key: ' . uniqid('altojordao_', true) // Garante que a requisição seja idempotente
            ],
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => true, 'message' => 'Erro de conexão: ' . $error];
        }

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            $msg = $data['message'] ?? $data['error'] ?? 'Erro desconhecido na API';
            return ['error' => true, 'message' => $msg, 'http_code' => $httpCode, 'raw' => $data];
        }

        return $data;
    }

    // PIX
    /**
     * Gera um pagamento via PIX.
     * Retorna o QR Code em base64 e o código do pagamento.
     */
    public static function gerarPix(array $pedido, array $usuario): array {
        $body = [
            'transaction_amount' => (float) $pedido['total'],
            'description' => 'Pedido Alto Jordão #' . $pedido['id'],
            'payment_method_id' => 'pix',
            'payer' => [
                'email' => $usuario['email'],
                'first_name' => explode(' ', $usuario['nome'])[0],
                'last_name' => explode(' ', $usuario['nome'], 2)[1] ?? 'Cliente',
                'identification' => [
                    'type' => 'CPF',
                    'number' => preg_replace('/\D/', '', $usuario['cpf'] ?? '00000000000'),
                    ],
                ],
            'date_of_expiration' => date('c', strtotime('+30 minutes')), // Expira em 30 minutos
            'external_reference' => (string) $pedido['id'],
            'notification_url' => SITE_URL . '/webhook_mp.php'
            ];

            $res = self::request('POST', '/v1/payments', $body);

            if (isset($res['error'])) return $res; // Retorna o erro

            return [
                'payment_id' => $res['id'],
                'status' => $res['status'],
                'qr_code' => $res['point_of_interaction']['transaction_data']['qr_code'] ?? null,
                'qr_base64' => $res['point_of_interaction']['transaction_data']['qr_code_base64'] ?? null,
                'expires_at' => date('H:i', strtotime('+30 minutes')),
            ];
    }

    // BOLETO
    /**
     * Gera um boleto bancário com vencimento em 3 dias úteis.
     */
    public static function gerarBoleto(array $pedido, array $usuario, array $endereco): array {
        $body = [
            'transaction_amount' => (float) $pedido['total'],
            'description' => 'Pedido Alto Jordão #' . $pedido['id'],
            'payment_method_id' => 'bolbradesco',
            'payer' => [
                'email' => $usuario['email'],
                'first_name' => explode(' ', $usuario['nome'])[0],
                'last_name' => explode(' ', $usuario['nome'], 2)[1] ?? 'Cliente',
                'identification' => [
                    'type' => 'CPF',
                    'number' => preg_replace('/\D/', '', $usuario['cpf'] ?? '00000000000'),
                ],
                'address' => [
                    'zip_code' => preg_replace('/\D/', '', $endereco['cep'] ?? ''),
                    'street_name' => $endereco['rua'] ?? '',
                    'street_number' => $endereco['numero'] ?? '',
                    'neighborhood' => $endereco['bairro'] ?? '',
                    'city' => $endereco['cidade'] ?? '',
                    'federal_unit' => $endereco['estado'] ?? ''
                ],
            ],
            'date_of_expiration' => date('Y-m-d\T23:59:59.000-03:00', strtotime('+3 days')), // Vence em 3 dias
            'external_reference' => (string) $pedido['id'],
            'notification_url' => SITE_URL . '/webhook_mp.php'
        ];

        $res = self::request('POST', '/v1/payments', $body);

        if (isset($res['error'])) return $res; // Retorna o erro

        return [
            'payment_id' => $res['id'],
            'status' => $res['status'],
            'boleto_url' => $res['transaction_details']['external_resource_url'] ?? null,
            'barcode' => $res['barcode']['content'] ?? null,
            'expires_at' => date('d/m/Y', strtotime('+3 days')),
        ];
    }

    // CARTÃO DE CRÉDITO (via Checkout Transparente)
    /**
     * Processa um pagamento com cartão de crédito usando o token gerado SDK JS do MP.
     * O token é criado no front-end e nunca expõe os dados do cartão no back-end.
     */
    public static function processarCartao(
        
    )
}